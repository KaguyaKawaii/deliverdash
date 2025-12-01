<?php
include '../connection.php';

header("Content-Type: text/html; charset=UTF-8");

// Handle form submission for adding or editing support account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        // Handle deletion with prepared statement
        if (isset($_POST['support_id'])) {
            $support_id = (int)$_POST['support_id'];
            $stmt = $conn->prepare("DELETE FROM support WHERE support_id = ?");
            $stmt->bind_param("i", $support_id);
            
            if ($stmt->execute()) {
                $success_message = "Support account deleted successfully!";
            } else {
                $error_message = "Error deleting support account: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['name'], $_POST['email'])) {
        // Validate inputs
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $support_id = isset($_POST['support_id']) ? (int)$_POST['support_id'] : 0;
        
        if (empty($name) || empty($email)) {
            $error_message = "Name and email are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Add or Edit
            $password = null;
            if (isset($_POST['password']) && !empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            if ($support_id > 0) {
                // Edit existing support account
                if ($password) {
                    $stmt = $conn->prepare("UPDATE support SET name=?, email=?, password=? WHERE support_id=?");
                    $stmt->bind_param("sssi", $name, $email, $password, $support_id);
                } else {
                    $stmt = $conn->prepare("UPDATE support SET name=?, email=? WHERE support_id=?");
                    $stmt->bind_param("ssi", $name, $email, $support_id);
                }
                
                if ($stmt->execute()) {
                    $success_message = "Support account updated successfully!";
                } else {
                    $error_message = "Error updating support account: " . $stmt->error;
                }
                $stmt->close();
            } else {
                // Create new support account
                if (empty($_POST['password'])) {
                    $error_message = "Password is required for new accounts.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO support (name, email, password) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $name, $email, $password);
                    
                    if ($stmt->execute()) {
                        $success_message = "Support account created successfully!";
                    } else {
                        $error_message = "Error creating support account: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch all support accounts
$support_accounts = [];
$sql = "SELECT * FROM support ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $support_accounts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Accounts | DeliverDash</title>
    
    <!-- CSS Links -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        .sidebar-link.active {
            background-color: #4B5563;
            color: white;
        }
        .status-badge {
            @apply px-2 py-1 rounded-full text-xs;
        }
        .status-active {
            @apply bg-green-600;
        }
        .status-inactive {
            @apply bg-gray-600;
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-montserrat">
    <!-- Main Layout -->
    <main class="min-h-screen flex flex-col">
        <!-- Navigation Bar -->
       <nav class="bg-gray-800 shadow-md py-3 px-6 flex justify-between items-center">
            <h1 class="text-xl font-semibold text-white">DeliverDash Administration</h1>
            
        </nav>

        <div class="flex flex-1">
            <!-- Sidebar -->
            <aside class="bg-gray-800 w-64 p-6 shadow-xl flex flex-col ml-5 mb-5 mt-5 rounded-lg sticky top-5">
                <div class="flex items-center justify-center mb-6">
                
                    <h2 class="text-xl font-bold text-gray-100">Admin Panel</h2>
                </div>
                <hr class="border-gray-700 mb-4">
                
                <nav class="flex-1">
                    <ul class="space-y-2">
                        <li>
                            <a href="../dashboard/admin_dashboard.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-user w-5 text-center mr-3 text-gray-300 group-hover:text-white"></i>
                                <span>Users</span>
                                
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/accounts.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-user-circle w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Accounts</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/deliveries.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-truck w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Deliveries</span>
                                
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/driver.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-id-card w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Drivers</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/payments.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-credit-card w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Payments</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/support.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
                                <i class="fas fa-headset w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Support</span>
                                
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="pt-4 mt-auto border-t border-gray-700">
                    
                    <a href="admin_logout.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-red-400 hover:bg-red-600 hover:text-white group">
                        <i class="fas fa-sign-out-alt w-5 text-center mr-3 group-hover:text-white"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 p-8">
                <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">Support Accounts</h2>
                        <button onclick="openSupportModal()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Support Account
                        </button>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($success_message)): ?>
                        <div class="bg-green-600 text-white p-3 rounded mb-4">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-600 text-white p-3 rounded mb-4">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Support Accounts Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-gray-700 rounded-lg overflow-hidden">
                            <thead class="bg-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">ID</th>
                                    <th class="px-4 py-3 text-left">Name</th>
                                    <th class="px-4 py-3 text-left">Email</th>
                                    <th class="px-4 py-3 text-left">Created At</th>
                                    <th class="px-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-600">
                                <?php if (!empty($support_accounts)): ?>
                                    <?php foreach ($support_accounts as $account): ?>
                                        <tr class="hover:bg-gray-600 transition-colors">
                                            <td class="px-4 py-3"><?= htmlspecialchars($account['support_id']) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($account['name']) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars($account['email']) ?></td>
                                            <td class="px-4 py-3"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($account['created_at']))) ?></td>
                                            <td class="px-4 py-3">
                                                <button class="text-blue-400 hover:text-blue-300 mr-2" 
                                                    onclick="editSupportAccount(<?= $account['support_id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($account['email'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-400 hover:text-red-300" onclick="confirmDelete(<?= $account['support_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-center">No support accounts found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Account Modal -->
        <div id="supportModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 id="modalTitle" class="text-xl font-semibold">Add Support Account</h2>
                    <button onclick="closeSupportModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="supportForm" action="" method="POST" class="space-y-4">
                    <input type="hidden" id="support_id" name="support_id" value="">
                    <div>
                        <label class="block mb-1 text-sm">Name</label>
                        <input type="text" name="name" id="name" placeholder="Full Name" required 
                               class="w-full p-2 bg-gray-700 rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block mb-1 text-sm">Email</label>
                        <input type="email" name="email" id="email" placeholder="email@example.com" required 
                               class="w-full p-2 bg-gray-700 rounded focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block mb-1 text-sm">Password</label>
                        <input type="password" name="password" id="password" placeholder="••••••••" 
                               class="w-full p-2 bg-gray-700 rounded focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-400 mt-1">Leave blank to keep current password when editing</p>
                    </div>
                    
                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" onclick="closeSupportModal()" 
                                class="px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition-colors">
                            Save Account
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center hidden z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Confirm Deletion</h2>
                    <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <p class="mb-6">Are you sure you want to delete this support account? This action cannot be undone.</p>
                
                <form id="deleteForm" method="POST" class="space-y-4">
                    <input type="hidden" id="delete_id" name="support_id" value="">
                    <input type="hidden" name="delete" value="1">
                    
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded hover:bg-gray-600 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded transition-colors">
                            Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Modal Functions
        function openSupportModal() {
            const modal = document.getElementById('supportModal');
            const form = document.getElementById('supportForm');
            const passwordField = document.getElementById('password');
            
            modal.classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Add Support Account';
            form.reset();
            form.action = '';
            document.getElementById('support_id').value = '';
            passwordField.required = true;
            passwordField.placeholder = '•••••••• (required)';
        }
        
        function closeSupportModal() {
            document.getElementById('supportModal').classList.add('hidden');
        }

        function confirmDelete(supportId) {
            document.getElementById('delete_id').value = supportId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function editSupportAccount(supportId, name, email) {
            const modal = document.getElementById('supportModal');
            const form = document.getElementById('supportForm');
            const passwordField = document.getElementById('password');
            
            document.getElementById('modalTitle').textContent = 'Edit Support Account';
            document.getElementById('support_id').value = supportId;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            passwordField.required = false;
            passwordField.placeholder = '•••••••• (leave blank to keep current)';
            
            modal.classList.remove('hidden');
        }

        // Highlight active sidebar link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.sidebar-link');
            
            links.forEach(link => {
                if (link.getAttribute('href').includes(currentPage)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>