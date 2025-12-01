<?php
// Include database connection
include('../connection.php'); 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle support message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['support_message'])) {
    $message = trim($_POST['support_message']);
    
    if (!empty($message)) {
        $insert_stmt = $conn->prepare("INSERT INTO support_messages (user_id, message_from, message, status) VALUES (?, 'user', ?, 'open')");
        $insert_stmt->bind_param("is", $user_id, $message);
        $insert_stmt->execute();
        
        // Set success message
        $_SESSION['support_message_sent'] = true;
        header("Location: user_dashboard.php");
        exit();
    }
}

// Prepare and execute user query
$user_stmt = $conn->prepare("SELECT name, address, profile_photo FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header("Location: user_login.php");
    exit();
}

$user_name = htmlspecialchars($user['name']);
$address = htmlspecialchars($user['address']);

// Fetch unread support messages count
$unread_count = 0;
$unread_stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM support_messages WHERE user_id = ? AND message_from = 'support' AND status = 'open'");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result()->fetch_assoc();
$unread_count = $unread_result['unread_count'] ?? 0;

// Fetch delivery counts
$count_query = "
    SELECT 
        SUM(CASE WHEN d.status != 'Completed' AND d.status != 'Cancelled' THEN 1 ELSE 0 END) AS active_deliveries,
        SUM(CASE WHEN p.status = 'Pending' THEN 1 ELSE 0 END) AS pending_payments,
        SUM(CASE WHEN d.status = 'Completed' THEN 1 ELSE 0 END) AS completed_deliveries
    FROM deliveries d
    LEFT JOIN payments p ON d.delivery_id = p.delivery_id
    WHERE d.user_id = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();

$active_deliveries = $count_result['active_deliveries'] ?? 0;
$pending_payments = $count_result['pending_payments'] ?? 0;
$completed_deliveries = $count_result['completed_deliveries'] ?? 0;

// Fetch latest delivery with complete payment and driver info
$latest_query = "
    SELECT 
        d.delivery_id, d.dropoff_name, d.dropoff_address, d.status, 
        d.product_name, d.delivery_option, d.created_at, d.driver_id,
        COALESCE(p.amount, 0) AS amount, 
        COALESCE(p.driver_fee, 0) AS driver_fee, 
        (COALESCE(p.amount, 0) + COALESCE(p.driver_fee, 0)) AS total_cost,
        p.payment_method,
        p.status AS payment_status,
        CASE 
            WHEN d.driver_id IS NULL THEN 'Not Assigned'
            WHEN dr.name IS NULL THEN CONCAT('Driver (ID: ', d.driver_id, ')')
            ELSE dr.name
        END AS driver_name,
        COALESCE(dr.contact, 'N/A') AS driver_contact,
        COALESCE(dr.vehicle, 'N/A') AS driver_vehicle,
        COALESCE(dr.license_no, 'N/A') AS driver_license_plate
    FROM deliveries d
    LEFT JOIN payments p ON d.delivery_id = p.delivery_id
    LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
    LIMIT 1";
$latest_stmt = $conn->prepare($latest_query);
$latest_stmt->bind_param("i", $user_id);
$latest_stmt->execute();
$latest_result = $latest_stmt->get_result();
$latest_delivery = $latest_result->fetch_assoc();
$latest_delivery_id = $latest_delivery['delivery_id'] ?? 0;

// Fetch delivery history with complete info
$history_query = "
    SELECT 
        d.delivery_id, d.dropoff_name, d.dropoff_address, d.status, 
        d.product_name, d.delivery_option, d.created_at, d.driver_id,
        COALESCE(p.amount, 0) AS amount, 
        COALESCE(p.driver_fee, 0) AS driver_fee, 
        (COALESCE(p.amount, 0) + COALESCE(p.driver_fee, 0)) AS total_cost,
        p.payment_method,
        p.status AS payment_status,
        CASE 
            WHEN d.driver_id IS NULL THEN 'Not Assigned'
            WHEN dr.name IS NULL THEN CONCAT('Driver (ID: ', d.driver_id, ')')
            ELSE dr.name
        END AS driver_name,
        COALESCE(dr.contact, 'N/A') AS driver_contact,
        COALESCE(dr.vehicle, 'N/A') AS driver_vehicle,
        COALESCE(dr.license_no, 'N/A') AS driver_license_plate
    FROM deliveries d
    LEFT JOIN payments p ON d.delivery_id = p.delivery_id
    LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
    WHERE d.user_id = ? AND (d.delivery_id != ? OR ? = 0)
    ORDER BY d.created_at DESC";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("iii", $user_id, $latest_delivery_id, $latest_delivery_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// In the user_dashboard.php file, update the support message query:
$support_query = "SELECT m.*, s.name as support_name 
                 FROM support_messages m
                 LEFT JOIN support s ON m.support_id = s.support_id
                 WHERE m.user_id = ? 
                 ORDER BY m.created_at DESC";
$support_stmt = $conn->prepare($support_query);
$support_stmt->bind_param("i", $user_id);
$support_stmt->execute();
$support_result = $support_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --secondary: #3b82f6;
            --dark: #1e293b;
            --darker: #0f172a;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
        }
        
        .scrollable-history::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scrollable-history::-webkit-scrollbar-thumb {
            background-color: rgba(74, 85, 104, 0.7);
            border-radius: 4px;
        }
        .scrollable-history::-webkit-scrollbar-track {
            background: rgba(45, 55, 72, 0.3);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: capitalize;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
        }
        .status-badge i {
            margin-right: 0.25rem;
            font-size: 0.65rem;
        }
        .status-pending { background-color: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-pending-driver-acceptance { rgba(216, 142, 57, 0.2); color: #d97706; }
        .status-accepted { background-color: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .status-on-delivery { background-color: rgba(99, 102, 241, 0.2); color: #6366f1; }
        .status-completed { background-color: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-cancelled { background-color: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .payment-status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        .payment-status-badge i {
            margin-right: 0.25rem;
            font-size: 0.6rem;
        }
        .payment-pending { background-color: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .payment-completed { background-color: rgba(16, 185, 129, 0.2); color: #10b981; }
        .payment-failed { background-color: rgba(239, 68, 68, 0.2); color: #ef4444; }
        
        .card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        }
        
        .glow {
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, #10b981 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Support button styles */
        .support-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #10b981 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 100;
            transition: all 0.3s ease;
        }

        .support-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
        }

        .support-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
        }

        /* Support modal styles */
        .support-modal {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 350px;
            max-height: 500px;
            background: rgba(30, 41, 59, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 101;
            display: none;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .support-modal-header {
            padding: 15px;
            background: rgba(16, 185, 129, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .support-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .support-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 80%;
            word-wrap: break-word;
        }

        .user-message {
            background: rgba(59, 130, 246, 0.2);
            margin-left: auto;
            border-top-right-radius: 0;
        }

        .support-message {
            background: rgba(30, 41, 59, 0.8);
            margin-right: auto;
            border-top-left-radius: 0;
        }

        .message-time {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 5px;
            text-align: right;
        }

        .support-input-area {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.8);
        }

        .support-textarea {
            width: 100%;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            color: white;
            resize: none;
            margin-bottom: 10px;
            min-height: 80px;
        }

        .support-textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .support-submit-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #10b981 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .support-submit-btn:hover {
            opacity: 0.9;
        }

        .support-close-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 18px;
        }

        .support-close-btn:hover {
            color: white;
        }

        /* Success message */
        .support-success-message {
            position: fixed;
            bottom: 100px;
            right: 30px;
            background: rgba(16, 185, 129, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 102;
            display: none;
        }
        .avatar img, .avatar div {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .avatar:hover img, .avatar:hover div {
            transform: scale(1.05);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="min-h-screen text-gray-200">
    

<nav class="bg-[#0f172a] shadow-lg border-b border-gray-800/50 sticky top-0 z-50">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Left side: Logo -->
            <div class="flex items-center space-x-3">
                <a href="../users/user_dashboard.php" class="flex items-center space-x-3 text-white hover:text-red-400 transition-colors">
                    <img class="w-10 h-10 object-contain" src="../picture/icon/logo.png" alt="Logo">
                    <h1 class="text-xl font-bold">DeliverDash</h1>
                </a>
            </div>

            <!-- Right side: Profile + Logout -->
            <div class="hidden md:flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-700/50" alt="Profile">
                    <?php else: ?>
                        <div class="h-8 w-8 rounded-full bg-primary-500/20 flex items-center justify-center text-white ring-1 ring-gray-700/50">
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                </div>

                <!-- Logout -->
                <form action="../users/user_logout.php" method="POST">
                    <button type="submit" 
                            class="flex items-center px-4 py-2 text-sm font-medium text-red-500 hover:text-red-400 transition-colors duration-200"
                            aria-label="Logout">
                        <i class="fas fa-sign-out-alt mr-2" aria-hidden="true"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>



    
    
    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        
        <!-- Profile Card -->
        <div class="card p-6 rounded-2xl mb-8 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-800/30 to-gray-900/50"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                    <!-- Large Avatar -->
                    <div class="relative avatar inline-block">
                        <?php if (!empty($user['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     class="h-24 w-24 rounded-full object-cover border-2 border-primary-500" id="profileImage">
                            <?php else: ?>
                                <div class="h-24 w-24 rounded-full bg-primary-500/20 flex items-center justify-center text-4xl font-bold text-primary-500" id="profileInitials">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold">Hi, <?php echo $user_name; ?></h2>
                        <p class="text-sm text-gray-400 hover:text-green-400 transition cursor-pointer flex items-center">
                            <a href="../users/user_profile.php"><i class="fas fa-pencil-alt mr-1 text-xs"></i> Edit Profile</a>
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-4 bg-gray-800/50 p-3 rounded-xl border border-gray-700/50">
                    <div class="p-2 bg-blue-500/20 rounded-lg">
                        <i class="fas fa-map-marker-alt text-blue-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold">Your Location</h2>
                        <p class="text-sm text-gray-300"><?php echo $address; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card p-6 rounded-xl flex items-center space-x-4 hover:glow">
                <div class="p-3 bg-green-500/20 rounded-xl">
                    <i class="fas fa-truck text-green-400"></i>
                </div>
                <div>
                    <h3 class="text-gray-400 font-medium">Active Deliveries</h3>
                    <p class="text-white text-2xl font-bold"><?php echo $active_deliveries; ?></p>
                </div>
            </div>
            
            <div class="card p-6 rounded-xl flex items-center space-x-4 hover:glow">
                <div class="p-3 bg-amber-500/20 rounded-xl">
                    <i class="fas fa-clock text-amber-400"></i>
                </div>
                <div>
                    <h3 class="text-gray-400 font-medium">Pending Payments</h3>
                    <p class="text-white text-2xl font-bold"><?php echo $pending_payments; ?></p>
                </div>
            </div>
            
            <div class="card p-6 rounded-xl flex items-center space-x-4 hover:glow">
                <div class="p-3 bg-blue-500/20 rounded-xl">
                    <i class="fas fa-check-circle text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-gray-400 font-medium">Completed Deliveries</h3>
                    <p class="text-white text-2xl font-bold"><?php echo $completed_deliveries; ?></p>
                </div>
            </div>
        </div>

        <!-- Delivery History Section -->
        <div class="card p-6 rounded-2xl mb-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Delivery History</h3>
                <div class="relative">
                    
                    
                </div>
            </div>

            <?php if ($latest_delivery) : ?>
                <div class="card p-5 rounded-xl mb-6 border-l-4 border-green-500 hover:border-green-400 transition-all">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div class="mb-4 md:mb-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <h4 class="text-lg font-bold text-green-400 flex items-center">
                                    <i class="fas fa-bolt mr-2 text-xs"></i> Latest Delivery
                                </h4>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $latest_delivery['status'])) ?>">
                                    <i class="fas fa-circle"></i> <?= htmlspecialchars($latest_delivery['status']) ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-xs text-gray-400">Recipient</p>
                                    <p class="font-medium"><?= htmlspecialchars($latest_delivery['dropoff_name']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Payment</p>
                                    <p class="font-medium">
                                        <?= htmlspecialchars($latest_delivery['payment_method']) ?> 
                                        <span class="payment-status-badge payment-<?= strtolower($latest_delivery['payment_status']) ?>">
                                            <i class="fas fa-circle"></i> <?= htmlspecialchars($latest_delivery['payment_status']) ?>
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Driver</p>
                                    <p class="font-medium"><?= htmlspecialchars($latest_delivery['driver_name']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">Amount</p>
                                    <p class="font-bold">₱<?= number_format($latest_delivery['total_cost'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        <button onclick="openModal(<?= htmlspecialchars(json_encode($latest_delivery), ENT_QUOTES, 'UTF-8') ?>)"
                            class="flex items-center space-x-2 bg-green-500/90 hover:bg-green-600 px-4 py-2 rounded-lg transition-all">
                            <i class="fas fa-eye text-xs"></i>
                            <span>View Details</span>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="card p-8 text-center rounded-xl">
                    <i class="fas fa-box-open text-4xl text-gray-600 mb-3"></i>
                    <p class="text-gray-400">No deliveries found</p>
                </div>
            <?php endif; ?>

            <div class="space-y-4 max-h-96 overflow-y-auto scrollable-history pr-2">
                <?php if ($history_result->num_rows > 0) : ?>
                    <?php while ($delivery = $history_result->fetch_assoc()) : ?>
                        <div class="card p-4 rounded-lg hover:bg-gray-800/50 transition-all">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                                <div class="mb-3 md:mb-0">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <p class="font-medium">
                                            To: <?= htmlspecialchars($delivery['dropoff_name']) ?>
                                        </p>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $delivery['status'])) ?>">
                                            <i class="fas fa-circle"></i> <?= htmlspecialchars($delivery['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-xs text-gray-400">Payment</p>
                                            <p class="text-sm">
                                                <?= htmlspecialchars($delivery['payment_method']) ?> 
                                                <span class="payment-status-badge payment-<?= strtolower($delivery['payment_status']) ?>">
                                                    <i class="fas fa-circle"></i> <?= htmlspecialchars($delivery['payment_status']) ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Driver</p>
                                            <p class="text-sm"><?= htmlspecialchars($delivery['driver_name']) ?></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">Amount</p>
                                            <p class="text-sm font-bold">₱<?= number_format($delivery['total_cost'], 2) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="openModal(<?= htmlspecialchars(json_encode($delivery), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="flex items-center space-x-2 bg-gray-700 hover:bg-gray-600 px-3 py-1.5 rounded-lg text-sm transition-all">
                                    <i class="fas fa-eye text-xs"></i>
                                    <span>Details</span>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card p-8 text-center rounded-xl">
                        <i class="fas fa-history text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No previous deliveries found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="relative rounded-2xl overflow-hidden p-8 md:p-12 text-center mb-8" style="background: linear-gradient(135deg, #f97316 0%, #ef4444 50%, #f97316 100%); background-size: 200% 200%; animation: gradient 8s ease infinite;">
            <div class="absolute inset-0 bg-black/30"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-4xl font-bold mb-3 drop-shadow-lg">Ready to Book Now?</h2>
                <p class="text-lg mb-6 text-gray-100 max-w-2xl mx-auto">Experience lightning-fast deliveries with our premium service</p>
                <a href="../users/delivery_process/delivery_details.php" 
                    class="inline-flex items-center space-x-2 bg-white text-gray-900 px-8 py-3 rounded-full font-semibold 
                        hover:bg-gray-100 transition duration-300 shadow-lg transform hover:scale-105">
                    <i class="fas fa-bolt"></i>
                    <span>Book Now</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900/80 border-t border-gray-800 py-6 mt-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-3 mb-4 md:mb-0">
                    <img class="w-8 h-8 object-contain" src="../picture/icon/logo.png" alt="Logo">
                    <h1 class="text-lg font-bold text-white">DeliverDash</h1>
                </div>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="mt-6 text-center md:text-left">
                <p class="text-xs text-gray-500">© <?php echo date("Y"); ?> DeliverDash. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Support Button -->
    <div class="support-btn" onclick="toggleSupportModal()">
        <i class="fas fa-headset text-xl"></i>
        <?php if ($unread_count > 0): ?>
            <div class="support-badge"><?php echo $unread_count; ?></div>
        <?php endif; ?>
    </div>

    <!-- Support Modal -->
<div class="fixed inset-0 z-50 hidden " id="supportModal">
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="toggleSupportModal()"></div>
    <div class="absolute bottom-0 right-[120px] w-full max-w-md h-[80vh] max-h-[600px] bg-gray-800 rounded-t-lg shadow-xl flex flex-col transform transition-transform duration-300 ease-in-out">
        <!-- Header -->
        <div class="flex justify-between items-center p-4 bg-gray-700 rounded-t-lg border-b border-gray-600">
            <div class="flex items-center space-x-2">
                <i class="fas fa-headset text-blue-400"></i>
                <h3 class="text-lg font-bold">Support Center</h3>
                <?php if ($unread_count > 0): ?>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?php echo $unread_count; ?> new
                    </span>
                <?php endif; ?>
            </div>
            <button class="text-gray-300 hover:text-white" onclick="toggleSupportModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Messages Container - Reverse Column Layout -->
        <div class="flex-1 overflow-y-auto p-4 flex flex-col-reverse" id="supportMessages">
            <div class="space-y-3">
                <?php if ($support_result->num_rows > 0): ?>
                    <?php 
                    // Store messages in array to reverse them
                    $messages = [];
                    while ($message = $support_result->fetch_assoc()) {
                        $messages[] = $message;
                    }
                    // Display messages in reverse order (newest at bottom)
                    foreach (array_reverse($messages) as $message): 
                    ?>
                        <div class="flex <?php echo $message['message_from'] === 'user' ? 'justify-end' : 'justify-start'; ?>">
                            <div class="max-w-[80%] rounded-lg p-3 <?php echo $message['message_from'] === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-700'; ?>">
                                <p class="whitespace-pre-wrap"><?php echo htmlspecialchars($message['message']); ?></p>
                                <div class="text-xs mt-1 <?php echo $message['message_from'] === 'user' ? 'text-blue-200' : 'text-gray-400'; ?>">
                              
                                    <?php 
                                        echo 'Customer Service • ' . date('M j, Y g:i A', strtotime($message['created_at'])); 
                                        ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center text-gray-400">
                        <i class="fas fa-comments text-4xl mb-2"></i>
                        <p>No messages yet</p>
                        <p class="text-sm">Start a conversation with our support team!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Input Area -->
        <form method="POST" class="p-4 border-t border-gray-700 bg-gray-750">
            <div class="flex space-x-2">
                <textarea 
                    name="support_message" 
                    class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none" 
                    placeholder="Type your message here..." 
                    rows="2"
                    required
                ></textarea>
                <button 
                    type="submit" 
                    class="self-end bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200"
                >
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSupportModal() {
    const modal = document.getElementById('supportModal');
    if (modal.classList.contains('hidden')) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Scroll to bottom of messages
        setTimeout(() => {
            const messages = document.getElementById('supportMessages');
            messages.scrollTop = 0; // Scroll to bottom (since it's reverse column)
        }, 100);
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// Auto-scroll to bottom (which is top in reverse column) when new messages are added
const observer = new MutationObserver(function() {
    const messages = document.getElementById('supportMessages');
    messages.scrollTop = 0;
});

observer.observe(document.getElementById('supportMessages'), {
    childList: true,
    subtree: true
});

// Initialize scroll position when modal opens
document.getElementById('supportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        const messages = document.getElementById('supportMessages');
        messages.scrollTop = 0;
    }
});
</script>

    <!-- Success Message -->
    <?php if (isset($_SESSION['support_message_sent'])): ?>
        <div class="support-success-message" id="supportSuccessMessage">
            <i class="fas fa-check-circle mr-2"></i> Your message has been sent to support!
        </div>
        <?php unset($_SESSION['support_message_sent']); ?>
    <?php endif; ?>

    <!-- Delivery Modal -->
    <div id="deliveryModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex justify-center items-center z-50 p-4">
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-8 rounded-2xl shadow-2xl w-full max-w-max relative border border-gray-700 overflow-y-auto max-h-screen">
            

            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                    <div class="bg-green-500/20 p-3 rounded-xl">
                        <i class="fas fa-clipboard-check text-green-400"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold bg-gradient-to-r from-green-400 to-emerald-400 bg-clip-text text-transparent">Delivery Details</h3>
                        <p id="modalDeliveryId" class="text-gray-400 text-sm mt-1"></p>
                    </div>
                </div>
                
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Recipient Card -->
                    <div class="card p-6 rounded-xl">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="bg-blue-500/20 p-2 rounded-lg">
                                <i class="fas fa-user text-blue-400"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-300">Recipient Information</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Name</p>
                                <p id="modalDropoff" class="text-white font-medium"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Delivery Address</p>
                                <p id="modalAddress" class="text-gray-400"></p>
                            </div>
                        </div>
                    </div>

                

                

                    <!-- Product and Delivery Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Product Card -->
                        <div class="card p-6 rounded-xl">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="bg-purple-500/20 p-2 rounded-lg">
                                    <i class="fas fa-box-open text-purple-400"></i>
                                </div>
                                <h4 class="text-lg font-bold text-gray-300">Product Details</h4>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-400 mb-1">Product Name</p>
                                    <p id="modalProduct" class="text-white font-medium"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400 mb-1">Delivery Option</p>
                                    <p id="modalOption" class="text-white font-medium"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Date and Time Card -->
                        <div class="card p-6 rounded-xl">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="bg-amber-500/20 p-2 rounded-lg">
                                    <i class="fas fa-clock text-amber-400"></i>
                                </div>
                                <h4 class="text-lg font-bold text-gray-300">Timeline</h4>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-400 mb-1">Order Date & Time</p>
                                    <p id="modalDate" class="text-white font-medium"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-400 mb-1">Estimated Delivery</p>
                                    <p class="text-white font-medium">Within 24 hours</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Driver Card -->
                    <div class="card p-6 rounded-xl">

                 
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="bg-cyan-500/20 p-2 rounded-lg">
                                <i class="fas fa-truck text-cyan-400"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-300">Driver Information</h4>
                        </div>
                        <div class="space-y-4">

                        <div class="flex items-center space-x-3">
                        <p class="text-sm text-gray-400 mb-1">Status</p>
                        <div id="modalStatus" class="px-3 py-1 rounded-full text-sm font-medium"></div>
                    </div>
                            
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Driver Name</p>
                                <p id="modalDriverName" class="text-white font-medium"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Contact Number</p>
                                <p id="modalDriverContact" class="text-white font-medium"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400 mb-1">Vehicle Type</p>
                                <p id="modalDriverVehicle" class="text-white font-medium"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400 mb-1">License Plate</p>
                                <p id="modalDriverLicense" class="text-white font-medium"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Card -->
                    <div class="card p-6 rounded-xl">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="bg-emerald-500/20 p-2 rounded-lg">
                                <i class="fas fa-credit-card text-emerald-400"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-300">Payment Summary</h4>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center space-x-3">
                                <p class="text-sm text-gray-400">Payment</p>
                                <div id="modalPaymentStatus" class="px-3 py-1 rounded-full text-sm font-medium"></div>
                            </div>

                            <div>
                                <p class="text-sm text-gray-400 mb-1">Payment Method</p>
                                <p id="modalPaymentMethod" class="text-white font-medium"></p>
                            </div>

                            

                            <div class="space-y-3 pt-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-400">Delivery Fee</span>
                                    <span id="modalAmount" class="text-white font-medium"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-400">Driver Fee</span>
                                    <span id="modalDriverFee" class="text-white font-medium"></span>
                                </div>
                                <div class="border-t border-gray-700 pt-2 mt-2 flex justify-between">
                                    <span class="text-base font-bold text-gray-300">Total Amount</span>
                                    <span id="modalCost" class="text-green-400 font-bold text-lg"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4">
                <button onclick="closeModal()" class="px-6 py-2 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 transition flex items-center justify-center space-x-2">
                    <i class="fas fa-times"></i>
                    <span>Close</span>
                </button>
                
            </div>
        </div>
    </div>

    <script>
    function openModal(delivery) {
        // Format date
        const deliveryDate = new Date(delivery.created_at);
        const formattedDate = deliveryDate.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        // Set all the modal fields
        document.getElementById("modalDeliveryId").textContent = `Delivery #${delivery.delivery_id}`;
        document.getElementById("modalDropoff").textContent = delivery.dropoff_name;
        document.getElementById("modalAddress").textContent = delivery.dropoff_address;
        document.getElementById("modalProduct").textContent = delivery.product_name;
        document.getElementById("modalOption").textContent = delivery.delivery_option;
        document.getElementById("modalDate").textContent = formattedDate;
        document.getElementById("modalAmount").textContent = '₱' + parseFloat(delivery.amount).toFixed(2);
        document.getElementById("modalDriverFee").textContent = '₱' + parseFloat(delivery.driver_fee).toFixed(2);
        document.getElementById("modalCost").textContent = '₱' + parseFloat(delivery.total_cost).toFixed(2);
        
        // Set payment information
        document.getElementById("modalPaymentMethod").textContent = delivery.payment_method;
        
        // Set driver information
        document.getElementById("modalDriverName").textContent = delivery.driver_name || 'Not assigned';
        document.getElementById("modalDriverContact").textContent = delivery.driver_contact !== 'N/A' ? delivery.driver_contact : 'Not available';
        document.getElementById("modalDriverVehicle").textContent = delivery.driver_vehicle !== 'N/A' ? delivery.driver_vehicle : 'Not assigned';
        document.getElementById("modalDriverLicense").textContent = delivery.driver_license_plate !== 'N/A' ? delivery.driver_license_plate : 'Not available';
        
        // Set status with appropriate styling
        const statusElement = document.getElementById("modalStatus");
        statusElement.textContent = delivery.status;
        statusElement.className = 'px-3 py-1 rounded-full text-sm font-medium status-badge status-' + delivery.status.toLowerCase().replace(' ', '-');
        
        const paymentStatusElement = document.getElementById("modalPaymentStatus");
        paymentStatusElement.textContent = delivery.payment_status;
        paymentStatusElement.className = 'px-3 py-1 rounded-full text-sm font-medium payment-status-badge payment-' + delivery.payment_status.toLowerCase();
        
        document.getElementById("deliveryModal").classList.remove("hidden");
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById("deliveryModal").classList.add("hidden");
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('deliveryModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Support modal functions
    function toggleSupportModal() {
        const modal = document.getElementById('supportModal');
        if (modal.style.display === 'flex') {
            modal.style.display = 'none';
        } else {
            modal.style.display = 'flex';
            // Scroll to bottom of messages
            const messages = document.getElementById('supportMessages');
            messages.scrollTop = messages.scrollHeight;
        }
    }

    // Show success message if it exists
    const successMessage = document.getElementById('supportSuccessMessage');
    if (successMessage) {
        successMessage.style.display = 'flex';
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 5000);
    }
    </script>









</body>
</html>