<?php
session_start();

// Check if support staff is logged in
if (!isset($_SESSION['support_logged_in'])) {
    header("Location: support_login.php");
    exit();
}

include '../connection.php';

// Handle message reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = (int)$_POST['message_id'];
    $reply = trim($_POST['reply_text']);
    $user_id = (int)$_POST['user_id'];
    
    if (!empty($reply)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update the original message status to 'responded'
            $update_stmt = $conn->prepare("UPDATE support_messages SET status = 'responded' WHERE message_id = ?");
            $update_stmt->bind_param("i", $message_id);
            $update_stmt->execute();
            
            // Insert the reply with the correct support_id
            $support_id = $_SESSION['support_id'];
            $insert_stmt = $conn->prepare("
                INSERT INTO support_messages 
                (user_id, support_id, message_from, message, status, related_to) 
                VALUES (?, ?, 'support', ?, 'open', ?)
            ");
            $insert_stmt->bind_param("iisi", $user_id, $support_id, $reply, $message_id);
            $insert_stmt->execute();
            
            // Update support staff's unread count
            $conn->query("UPDATE support SET unread_notifications = GREATEST(0, unread_notifications - 1) WHERE support_id = $support_id");
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['support_message_sent'] = true;
            header("Location: support_dashboard.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            error_log("Error sending support reply: " . $e->getMessage());
            $_SESSION['error'] = "Failed to send reply. Please try again.";
            header("Location: support_dashboard.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Reply message cannot be empty";
        header("Location: support_dashboard.php");
        exit();
    }
}

// Get support staff's unread notifications count
$support_id = $_SESSION['support_id'];
$unread_count = $conn->query("SELECT unread_notifications FROM support WHERE support_id = $support_id")->fetch_assoc()['unread_notifications'];

// Get statistics for dashboard
$open_tickets = $conn->query("SELECT COUNT(*) AS count FROM support_messages WHERE message_from = 'user' AND status = 'open'")->fetch_assoc()['count'];
$resolved_today = $conn->query("SELECT COUNT(*) AS count FROM support_messages WHERE message_from = 'user' AND status = 'responded' AND DATE(updated_at) = CURDATE()")->fetch_assoc()['count'];
$pending_response = $conn->query("SELECT COUNT(*) AS count FROM support_messages WHERE message_from = 'support' AND status = 'open'")->fetch_assoc()['count'];

// Get recent tickets
$recent_tickets = $conn->query("
    SELECT m.message_id, m.user_id, u.name, m.message, m.status, m.created_at, m.updated_at 
    FROM support_messages m
    JOIN users u ON m.user_id = u.user_id
    WHERE m.message_from = 'user' AND m.related_to IS NULL
    ORDER BY m.updated_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Dashboard | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        .sidebar-link.active {
            background-color: #4B5563;
            color: white;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .status-open { background-color: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-responded { background-color: rgba(16, 185, 129, 0.2); color: #10b981; }
        .scrollable-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .scrollable-container::-webkit-scrollbar {
            width: 6px;
        }
        .scrollable-container::-webkit-scrollbar-thumb {
            background-color: rgba(74, 85, 104, 0.7);
            border-radius: 4px;
        }
        .scrollable-container::-webkit-scrollbar-track {
            background: rgba(45, 55, 72, 0.3);
        }
        .message-bubble {
            max-width: 80%;
            word-wrap: break-word;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            margin-bottom: 0.5rem;
        }
        .user-message {
            background-color: rgba(59, 130, 246, 0.2);
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        .support-message {
            background-color: rgba(30, 41, 59, 0.8);
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
        }
        
        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            background-color: #EF4444;
            color: white;
            border-radius: 9999px;
            width: 1.25rem;
            height: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        /* Modal enhancements */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-container {
            background-color: #1F2937;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            padding: 1.5rem;
            background-color: #111827;
            border-bottom: 1px solid #374151;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 200px);
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            background-color: #111827;
            border-top: 1px solid #374151;
            display: flex;
            justify-content: flex-end;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* New message indicator */
        .new-message-indicator {
            position: relative;
        }
        .new-message-indicator::after {
            content: '';
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 0.5rem;
            height: 0.5rem;
            background-color: #EF4444;
            border-radius: 50%;
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-montserrat">
    <!-- Navigation Bar -->
    <nav class="bg-gray-800 shadow-md py-3 px-6 flex justify-between items-center">
        <h1 class="text-xl font-semibold">DeliverDash Support</h1>
        <div class="flex gap-6 items-center">
            <span class="text-gray-300">Customer Service</span>
            |
            <div class="relative">
                <a href="support_tickets.php" class="hover:text-blue-400 transition-colors">
                    <i class="fas fa-bell text-xl"></i>
                </a>
                <?php if ($unread_count > 0): ?>
                <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            |
            <a href="support_logout.php" class="hover:text-red-500 transition-colors">Logout</a>
        </div>
    </nav>

    <!-- Content Area -->
    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside class="bg-gray-800 w-64 p-6 shadow-lg flex flex-col h-screen sticky top-0">
            <h2 class="text-lg font-semibold text-center mb-4">Support Menu</h2>
            <hr class="border-gray-700">
            <ul class="mt-4 space-y-2">
                <li>
                    <a href="support_dashboard.php" class="block hover:bg-gray-700 hover:text-white p-3 rounded-md transition-all duration-300 sidebar-link active">
                        <i class="fas fa-tachometer-alt pr-2"></i> Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="support_profile.php" class="block hover:bg-gray-700 hover:text-white p-3 rounded-md transition-all duration-300">
                        <i class="fas fa-user-cog pr-2"></i> Profile
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <?php if (isset($_SESSION['support_message_sent'])): ?>
                <div class="bg-green-500 text-white p-3 rounded-lg mb-4 flex justify-between items-center">
                    <span><i class="fas fa-check-circle mr-2"></i> Your reply has been sent successfully!</span>
                    <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php unset($_SESSION['support_message_sent']); ?>
            <?php endif; ?>

            <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
                <h2 class="text-xl font-semibold mb-6">Support Dashboard</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-lg font-medium mb-2">Open Tickets</h3>
                        <p class="text-3xl font-bold text-blue-400"><?php echo $open_tickets; ?></p>
                    </div>
                    <div class="bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-lg font-medium mb-2">Resolved Today</h3>
                        <p class="text-3xl font-bold text-green-400"><?php echo $resolved_today; ?></p>
                    </div>
                    <div class="bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-lg font-medium mb-2">Pending Responses</h3>
                        <p class="text-3xl font-bold text-yellow-400"><?php echo $pending_response; ?></p>
                    </div>
                </div>
                
                <div class="bg-gray-700 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium mb-4">Recent Tickets</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-gray-800 rounded-lg">
                            <thead>
                                <tr class="bg-gray-600">
                                    <th class="px-4 py-3 text-left">Ticket ID</th>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Message</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Last Updated</th>
                                    <th class="px-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                <?php while ($ticket = $recent_tickets->fetch_assoc()): ?>
                                <?php 
                                    // Check if this ticket has unread messages
                                    $has_unread = $conn->query("
                                        SELECT COUNT(*) AS unread 
                                        FROM support_messages 
                                        WHERE related_to = {$ticket['message_id']} 
                                        AND message_from = 'user' 
                                        AND status = 'open'
                                    ")->fetch_assoc()['unread'] > 0;
                                ?>
                                <tr class="hover:bg-gray-600 <?php echo $has_unread ? 'bg-gray-600/50' : ''; ?>">
                                    <td class="px-4 py-3">#<?php echo $ticket['message_id']; ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($ticket['name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars(substr($ticket['message'], 0, 50)) . (strlen($ticket['message']) > 50 ? '...' : ''); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="status-badge status-<?php echo strtolower($ticket['status']); ?>">
                                            <i class="fas fa-circle mr-1"></i><?php echo $ticket['status']; ?>
                                        </span>
                                        <?php if ($has_unread): ?>
                                        <span class="ml-2 text-red-400 text-xs">New reply!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3"><?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?></td>
                                    <td class="px-4 py-3">
                                        <button onclick="openTicketModal(<?php echo $ticket['message_id']; ?>)" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm relative">
                                            <i class="fas fa-eye mr-1"></i> View
                                            <?php if ($has_unread): ?>
                                            <span class="new-message-indicator"></span>
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Ticket Modal -->
    <div id="ticketModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Ticket Conversation</h3>
                <button onclick="closeTicketModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div id="ticketDetails" class="mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h4 class="font-medium text-lg" id="ticketCustomer"></h4>
                            <p class="text-gray-400 text-sm" id="ticketDate"></p>
                        </div>
                        <span class="status-badge" id="ticketStatus"></span>
                    </div>
                    <div class="bg-gray-700 p-4 rounded-lg mb-4">
                        <p id="ticketMessage" class="whitespace-pre-wrap"></p>
                    </div>
                </div>

                <div class="scrollable-container mb-4 p-4 bg-gray-700 rounded-lg" id="messageThread">
                    <!-- Messages will be loaded here -->
                </div>
            </div>
            
            <div class="modal-footer">
                <form id="replyForm" method="POST" class="w-full">
                    <input type="hidden" name="message_id" id="replyMessageId">
                    <input type="hidden" name="user_id" id="replyUserId">
                    <div class="flex items-start space-x-3">
                        <div class="flex-1">
                            <textarea name="reply_text" id="replyText" rows="3" 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white focus:border-blue-500 focus:ring-blue-500" 
                                placeholder="Type your reply here..." required></textarea>
                        </div>
                        <button type="submit" name="reply_message" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-lg flex items-center">
                            <span id="submitText">Send</span>
                            <span id="submitSpinner" class="ml-2 hidden">
                                <span class="loading-spinner"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to open ticket modal and load messages
        function openTicketModal(messageId) {
            const modal = document.getElementById('ticketModal');
            const messageThread = document.getElementById('messageThread');
            
            // Show loading state
            messageThread.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading conversation...</div>';
            
            // Clear previous form data
            document.getElementById('replyText').value = '';
            
            // Show modal
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Fetch ticket details and messages
            fetchTicketDetails(messageId);
            
            // Mark messages as read
            markMessagesAsRead(messageId);
        }

        function fetchTicketDetails(messageId) {
            const messageThread = document.getElementById('messageThread');
            
            fetch(`get_ticket_details.php?message_id=${messageId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        messageThread.innerHTML = `<div class="text-red-500 text-center py-4">${data.error}</div>`;
                        return;
                    }

                    // Populate ticket details
                    document.getElementById('ticketCustomer').textContent = data.ticket.name;
                    document.getElementById('ticketDate').textContent = `Created: ${new Date(data.ticket.created_at).toLocaleString()}`;
                    document.getElementById('ticketMessage').textContent = data.ticket.message;
                    
                    const statusBadge = document.getElementById('ticketStatus');
                    statusBadge.className = `status-badge status-${data.ticket.status.toLowerCase()}`;
                    statusBadge.innerHTML = `<i class="fas fa-circle mr-1"></i>${data.ticket.status}`;
                    
                    // Set form hidden fields
                    document.getElementById('replyMessageId').value = data.ticket.message_id;
                    document.getElementById('replyUserId').value = data.ticket.user_id;

                    // Populate message thread
                    if (data.messages.length > 0) {
                        let messagesHtml = '';
                        data.messages.forEach(msg => {
                            const isSupport = msg.message_from === 'support';
                            messagesHtml += `
                                <div class="message-bubble ${isSupport ? 'support-message' : 'user-message'} mb-3">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 mr-2 mt-1">
                                            <i class="fas ${isSupport ? 'fa-headset text-blue-400' : 'fa-user text-green-400'}"></i>
                                        </div>
                                        <div>
                                            <p class="whitespace-pre-wrap">${msg.message}</p>
                                            <div class="text-xs text-gray-400 mt-1">
                                                ${isSupport ? 'Support Agent' : 'Customer'} • ${new Date(msg.created_at).toLocaleString()}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        messageThread.innerHTML = messagesHtml;
                        messageThread.scrollTop = messageThread.scrollHeight;
                    } else {
                        messageThread.innerHTML = '<div class="text-center py-4 text-gray-400">No messages in this thread yet.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageThread.innerHTML = '<div class="text-red-500 text-center py-4">Failed to load conversation. Please try again.</div>';
                });
        }

        function markMessagesAsRead(messageId) {
            fetch('mark_messages_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count in UI
                    updateNotificationCount(data.unread_count);
                    
                    // Remove new message indicators for this ticket
                    const ticketRow = document.querySelector(`tr[data-ticket-id="${messageId}"]`);
                    if (ticketRow) {
                        ticketRow.classList.remove('bg-gray-600/50');
                        const newMessageIndicators = ticketRow.querySelectorAll('.new-message-indicator, .text-red-400');
                        newMessageIndicators.forEach(indicator => indicator.remove());
                    }
                }
            })
            .catch(error => console.error('Error marking messages as read:', error));
        }

        function updateNotificationCount(count) {
            const notificationBadges = document.querySelectorAll('.notification-badge');
            if (count > 0) {
                notificationBadges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                });
            } else {
                notificationBadges.forEach(badge => {
                    badge.style.display = 'none';
                });
            }
        }

        function closeTicketModal() {
            document.getElementById('ticketModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('ticketModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTicketModal();
            }
        });

        // Handle form submission
        document.getElementById('replyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const submitText = document.getElementById('submitText');
            const submitSpinner = document.getElementById('submitSpinner');
            
            // Show loading state
            submitText.textContent = 'Sending...';
            submitSpinner.classList.remove('hidden');
            submitBtn.disabled = true;
            
            // Create FormData object
            const formData = new FormData(this);
            
            // Add additional fields if needed
            formData.append('reply_message', '1');
            
            // Submit via fetch
            fetch('support_dashboard.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.text();
            })
            .then(data => {
                // If we get here, the response wasn't a redirect
                console.log(data);
                throw new Error('Unexpected response');
            })
            .catch(error => {
                console.error('Error:', error);
                submitText.textContent = 'Send';
                submitSpinner.classList.add('hidden');
                submitBtn.disabled = false;
                
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'bg-red-500 text-white p-2 rounded mb-4 text-center';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i> Failed to send reply. Please try again.';
                document.querySelector('.modal-body').insertBefore(errorDiv, document.getElementById('messageThread'));
                
                // Remove error message after 5 seconds
                setTimeout(() => {
                    errorDiv.remove();
                }, 5000);
            });
        });

        // Periodically check for new messages
        setInterval(() => {
            fetch('check_new_messages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        updateNotificationCount(data.unread_count);
                        
                        // Update any tickets with new messages
                        data.new_messages.forEach(ticketId => {
                            const ticketRow = document.querySelector(`tr[data-ticket-id="${ticketId}"]`);
                            if (ticketRow && !ticketRow.classList.contains('bg-gray-600/50')) {
                                ticketRow.classList.add('bg-gray-600/50');
                                
                                // Add new message indicator to status cell
                                const statusCell = ticketRow.querySelector('td:nth-child(4)');
                                if (statusCell && !statusCell.querySelector('.text-red-400')) {
                                    const newMessageSpan = document.createElement('span');
                                    newMessageSpan.className = 'ml-2 text-red-400 text-xs';
                                    newMessageSpan.textContent = 'New reply!';
                                    statusCell.appendChild(newMessageSpan);
                                }
                                
                                // Add indicator to view button
                                const viewButton = ticketRow.querySelector('button');
                                if (viewButton && !viewButton.querySelector('.new-message-indicator')) {
                                    const indicator = document.createElement('span');
                                    indicator.className = 'new-message-indicator';
                                    viewButton.appendChild(indicator);
                                }
                            }
                        });
                    }
                })
                .catch(error => console.error('Error checking for new messages:', error));
        }, 30000); // Check every 30 seconds
    </script>
</body>
</html>