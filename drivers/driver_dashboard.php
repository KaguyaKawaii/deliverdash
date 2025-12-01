<?php
session_start();
require '../connection.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$driver_name = $_SESSION['driver_name'] ?? 'Driver';

// Handle delivery actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ACCEPT DELIVERY
    if (isset($_POST['accept'])) {
        $delivery_id = intval($_POST['delivery_id']);
        mysqli_begin_transaction($conn);

        try {
            $check_query = "SELECT d.status FROM deliveries d
                            JOIN payments p ON d.delivery_id = p.delivery_id
                            WHERE d.delivery_id = ?
                            AND (d.status = 'Pending' OR d.status = 'Pending Driver Acceptance')
                            FOR UPDATE";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "i", $delivery_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $delivery = mysqli_fetch_assoc($result);

            if (!$delivery) throw new Exception("Delivery is no longer available.");

            $update_query = "UPDATE deliveries SET status = 'Accepted', driver_id = ? WHERE delivery_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ii", $driver_id, $delivery_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Failed to accept delivery.");

            $update_driver = "UPDATE drivers SET status = 'on_delivery' WHERE driver_id = ?";
            $stmt = mysqli_prepare($conn, $update_driver);
            mysqli_stmt_bind_param($stmt, "i", $driver_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Failed to update driver status.");

            mysqli_commit($conn);
            header("Location: driver_delivery_details.php?delivery_id=" . $delivery_id);
            exit();

            

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }

    // COMPLETE DELIVERY
    if (isset($_POST['complete'])) {
        $delivery_id = intval($_POST['delivery_id']);
        mysqli_begin_transaction($conn);

        try {
            $check_query = "SELECT status FROM deliveries
                            WHERE delivery_id = ? AND driver_id = ? AND status = 'Accepted'
                            FOR UPDATE";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "ii", $delivery_id, $driver_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $delivery = mysqli_fetch_assoc($result);

            if (!$delivery) throw new Exception("Delivery cannot be completed or is not assigned to you.");

            // Update to completed and set current timestamp
$update_query = "UPDATE deliveries 
                 SET status = 'Completed', 
                     completed_at = NOW(),
                     updated_at = NOW() 
                 WHERE delivery_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "i", $delivery_id);

            $update_driver = "UPDATE drivers SET status = 'available' WHERE driver_id = ?";
            $stmt = mysqli_prepare($conn, $update_driver);
            mysqli_stmt_bind_param($stmt, "i", $driver_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Failed to update driver status.");

            $update_query = "UPDATE deliveries 
                 SET status = 'Completed', completed_at = NOW() 
                 WHERE delivery_id = ?";

            mysqli_commit($conn);
            header("Location: driver_dashboard.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch available deliveries
$query_available = "SELECT d.delivery_id, d.pickup_address, d.dropoff_address, 
                   d.status AS delivery_status, d.product_name, d.created_at,
                   (p.amount + 300) AS amount, p.payment_method, p.status AS payment_status,
                   u.name AS customer_name, u.contact AS customer_contact
                   FROM deliveries d
                   JOIN payments p ON d.delivery_id = p.delivery_id
                   JOIN users u ON d.user_id = u.user_id
                   WHERE d.status IN ('Pending', 'Pending Driver Acceptance')
                   ORDER BY d.created_at DESC";
$result_available = mysqli_query($conn, $query_available);

// Fetch active deliveries
$query_active = "SELECT d.delivery_id, d.pickup_address, d.dropoff_address, 
                d.status AS delivery_status, d.product_name, d.created_at,
                (p.amount + 300) AS amount, p.payment_method, p.status AS payment_status,
                u.name AS customer_name, u.contact AS customer_contact
                FROM deliveries d
                JOIN payments p ON d.delivery_id = p.delivery_id
                JOIN users u ON d.user_id = u.user_id
                WHERE d.driver_id = ? AND d.status = 'Accepted'
                ORDER BY d.updated_at DESC";
$stmt_active = mysqli_prepare($conn, $query_active);
mysqli_stmt_bind_param($stmt_active, "i", $driver_id);
mysqli_stmt_execute($stmt_active);
$result_active = mysqli_stmt_get_result($stmt_active);

// Fetch completed deliveries
$query_completed = "SELECT d.delivery_id, d.pickup_address, d.dropoff_address, 
                   d.status AS delivery_status, d.product_name, d.created_at, d.completed_at,
                   p.amount, p.payment_method, p.status AS payment_status,
                   u.name AS customer_name, u.contact AS customer_contact
                   FROM deliveries d
                   JOIN payments p ON d.delivery_id = p.delivery_id
                   JOIN users u ON d.user_id = u.user_id
                   WHERE d.driver_id = ? AND d.status = 'Completed'
                   ORDER BY d.completed_at DESC
                   LIMIT 20";
$stmt_completed = mysqli_prepare($conn, $query_completed);
mysqli_stmt_bind_param($stmt_completed, "i", $driver_id);
mysqli_stmt_execute($stmt_completed);
$result_completed = mysqli_stmt_get_result($stmt_completed);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }
        .dark body {
            background-color: #0f172a;
        }
        .status-badge {
            padding: 0.35rem 0.7rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .status-pending { 
            background-color: #ffedd5; 
            color: #9a3412;
        }
        .dark .status-pending {
            background-color: #431407;
            color: #fdba74;
        }
        .status-accepted { 
            background-color: #dbeafe; 
            color: #1e40af;
        }
        .dark .status-accepted {
            background-color: #1e3a8a;
            color: #93c5fd;
        }
        .status-completed { 
            background-color: #dcfce7; 
            color: #166534;
        }
        .dark .status-completed {
            background-color: #14532d;
            color: #86efac;
        }
        .delivery-card {
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .delivery-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .dark .delivery-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        .section-title {
            position: relative;
            padding-left: 1rem;
        }
        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(to bottom, #4f46e5, #7c3aed);
            border-radius: 4px;
        }
        .delivery-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .delivery-details-content {
            background-color: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .dark .delivery-details-content {
            background-color: #1e293b;
        }
        .map-placeholder {
            height: 250px;
            background-color: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .dark .map-placeholder {
            background-color: #334155;
        }
        .map-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.3));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1rem;
            color: white;
        }
        .route-steps {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .route-step {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .step-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
        }
        .step-start {
            background-color: #3b82f6;
            color: white;
        }
        .step-end {
            background-color: #10b981;
            color: white;
        }
        .step-line {
            width: 2px;
            height: 20px;
            background-color: #e2e8f0;
            margin-left: 11px;
        }
        .dark .step-line {
            background-color: #475569;
        }
        .delivery-timeline {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .timeline-item {
            display: flex;
            gap: 1rem;
        }
        .timeline-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e2e8f0;
        }
        .dark .timeline-icon {
            background-color: #334155;
        }
        .timeline-content {
            flex-grow: 1;
            padding-bottom: 1.5rem;
            border-bottom: 1px dashed #e2e8f0;
        }
        .dark .timeline-content {
            border-bottom-color: #334155;
        }
        .timeline-item:last-child .timeline-content {
            border-bottom: none;
            padding-bottom: 0;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <nav class="bg-gray-800 dark:bg-gray-800 p-4 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="../picture/icon/logo.png" alt="Logo" class="h-10 w-25 rounded-full">
                <h1 class="text-xl font-bold">DeliverDash | Driver Dashboard</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button id="themeToggle" class="p-2 rounded-full bg-gray-700 dark:bg-gray-600 text-gray-200 hover:bg-gray-600 dark:hover:bg-gray-500 transition">
                    <svg id="sunIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                    <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                </button>
                <a href="driver_profile.php" class="bg-gray-700 dark:bg-gray-600 hover:bg-gray-600 dark:hover:bg-gray-500 text-white px-3 py-1 rounded-full transition">
                <span class="bg-gray-700 dark:bg-gray-600 px-3 py-1 rounded-full flex items-center">
                    <i class="fas fa-user-circle mr-2"></i>
                    <p>Profile</p>
                    
                </a>
                </span>
                <a href="driver_logout.php" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded-full transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6 flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Available Deliveries</p>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
                            <?= mysqli_num_rows($result_available) ?>
                        </h3>
                    </div>
                    <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-full">
                        <i class="fas fa-box-open text-blue-500 dark:text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border-l-4 border-yellow-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Active Deliveries</p>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
                            <?= mysqli_num_rows($result_active) ?>
                        </h3>
                    </div>
                    <div class="bg-yellow-100 dark:bg-yellow-900/50 p-3 rounded-full">
                        <i class="fas fa-truck-moving text-yellow-500 dark:text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">Completed (Recent)</p>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
                            <?= mysqli_num_rows($result_completed) ?>
                        </h3>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/50 p-3 rounded-full">
                        <i class="fas fa-check-circle text-green-500 dark:text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Deliveries -->
        <div class="mb-10">
            <div class="flex justify-between items-center mb-6">
                <h2 class="section-title text-2xl font-bold text-gray-800 dark:text-white">Available Deliveries</h2>
                <button id="toggleAvailable" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                    <i class="fas fa-chevron-down mr-1"></i> Collapse
                </button>
            </div>
            <div id="availableDeliveriesSection">
                <?php if (mysqli_num_rows($result_available) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($row = mysqli_fetch_assoc($result_available)): ?>
                            <div class="delivery-card bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Delivery #<?= $row['delivery_id'] ?></h3>
                                    <div class="flex space-x-2">
                                        <span class="status-badge status-<?= strtolower($row['delivery_status']) ?>">
                                            <?php if ($row['delivery_status'] === 'Pending'): ?>
                                                <i class="fas fa-clock"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user-clock"></i>
                                            <?php endif; ?>
                                            <?= $row['delivery_status'] ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-user mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">Customer:</span> <?= htmlspecialchars($row['customer_name']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-map-marker-alt mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">From:</span> <?= htmlspecialchars($row['pickup_address']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-flag-checkered mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">To:</span> <?= htmlspecialchars($row['dropoff_address']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        ₱<?= number_format($row['amount'], 2) ?>
                                    </span>
                                    <span class="text-sm font-medium <?= $row['payment_status'] === 'Completed' ? 'text-green-500 dark:text-green-400' : 'text-yellow-500 dark:text-yellow-400' ?>">
                                        <?= $row['payment_status'] ?>
                                    </span>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <button onclick="showDeliveryDetails(<?= $row['delivery_id'] ?>)" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition flex items-center justify-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        View
                                    </button>
                                    <?php if ($row['delivery_status'] === 'Pending' || $row['delivery_status'] === 'Pending Driver Acceptance'): ?>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="delivery_id" value="<?= $row['delivery_id'] ?>">
                                            <button type="submit" name="accept" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg transition flex items-center justify-center">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                Accept
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-md text-center border border-gray-200 dark:border-gray-700">
                        <i class="fas fa-box-open text-5xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mt-4">No available deliveries</h3>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Check back later for new delivery requests</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Deliveries -->
        <div class="mb-10">
            <div class="flex justify-between items-center mb-6">
                <h2 class="section-title text-2xl font-bold text-gray-800 dark:text-white">Your Active Deliveries</h2>
                <button id="toggleActive" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                    <i class="fas fa-chevron-down mr-1"></i> Collapse
                </button>
            </div>
            <div id="activeDeliveriesSection">
                <?php if (mysqli_num_rows($result_active) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($row = mysqli_fetch_assoc($result_active)): ?>
                            <div class="delivery-card bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-blue-200 dark:border-blue-700">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Delivery #<?= $row['delivery_id'] ?></h3>
                                    <span class="status-badge status-accepted">
                                        <i class="fas fa-truck-moving animate-pulse mr-1"></i>
                                        In Progress
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-user mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">Customer:</span> <?= htmlspecialchars($row['customer_name']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-map-marker-alt mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">From:</span> <?= htmlspecialchars($row['pickup_address']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-flag-checkered mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">To:</span> <?= htmlspecialchars($row['dropoff_address']) ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($row['payment_method'] === 'cash_on_delivery'): ?>
                                    <div class="bg-green-50 dark:bg-green-900/30 border border-green-100 dark:border-green-800 rounded-lg p-3 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-money-bill-wave text-green-500 mr-2 text-xl"></i>
                                            <div>
                                                <p class="font-medium text-green-700 dark:text-green-300">Collect Payment</p>
                                                <p class="text-green-600 dark:text-green-200 font-bold">₱<?= number_format($row['amount'], 2) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-800 rounded-lg p-3 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-credit-card text-blue-500 mr-2 text-xl"></i>
                                            <div>
                                                <p class="font-medium text-blue-700 dark:text-blue-300">Paid by Card</p>
                                                <p class="text-blue-600 dark:text-blue-200">No cash collection needed</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex space-x-2">
                                    <a href="driver_delivery_details.php?delivery_id=<?= $row['delivery_id'] ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition text-center flex items-center justify-center">
                                        <i class="fas fa-truck mr-2"></i>
                                        Manage
                                    </a>
                                    <button onclick="showDeliveryDetails(<?= $row['delivery_id'] ?>)" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded-lg transition text-center flex items-center justify-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        View
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-md text-center border border-gray-200 dark:border-gray-700">
                        <i class="fas fa-truck text-5xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mt-4">No active deliveries</h3>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Accept deliveries from the available section</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Deliveries -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="section-title text-2xl font-bold text-gray-800 dark:text-white">Recently Completed</h2>
                <button id="toggleCompleted" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center">
                    <i class="fas fa-chevron-down mr-1"></i> Collapse
                </button>
            </div>
            <div id="completedDeliveriesSection">
                <?php if (mysqli_num_rows($result_completed) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($row = mysqli_fetch_assoc($result_completed)): ?>
                            <div class="delivery-card bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-green-200 dark:border-green-700">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Delivery #<?= $row['delivery_id'] ?></h3>
                                    <span class="status-badge status-completed">
                                        <i class="fas fa-check-circle"></i>
                                        Completed
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex items-start">
                                        <i class="fas fa-user mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">Customer:</span> <?= htmlspecialchars($row['customer_name']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-map-marker-alt mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">From:</span> <?= htmlspecialchars($row['pickup_address']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-flag-checkered mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">To:</span> <?= htmlspecialchars($row['dropoff_address']) ?></p>
                                    </div>
                                    <div class="flex items-start">
                                        <i class="fas fa-calendar-alt mt-1 mr-2 text-gray-500 dark:text-gray-400"></i>
                                        <p class="text-gray-600 dark:text-gray-300"><span class="font-medium">Completed:</span> <?= date('M j, Y g:i A', strtotime($row['completed_at'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button onclick="showDeliveryDetails(<?= $row['delivery_id'] ?>)" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition text-center flex items-center justify-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 p-8 rounded-xl shadow-md text-center border border-gray-200 dark:border-gray-700">
                        <i class="fas fa-check-circle text-5xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mt-4">No completed deliveries yet</h3>
                        <p class="text-gray-500 dark:text-gray-400 mt-1">Your completed deliveries will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delivery Details Modal -->
    <div id="deliveryDetailsModal" class="delivery-details-modal">
        <div class="delivery-details-content p-6 dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Delivery Details</h3>
                <button onclick="hideDeliveryDetails()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-6">
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt text-4xl text-gray-400"></i>
                    <div class="map-overlay">
                        <h4 class="font-bold text-lg">Delivery Route</h4>
                        <div class="route-steps">
                            <div class="route-step">
                                <div class="step-icon step-start">
                                    <i class="fas fa-map-marker-alt text-sm"></i>
                                </div>
                                <div>
                                    <p id="pickupAddress" class="font-medium text-white"></p>
                                    <p class="text-gray-200 text-sm">Pickup Location</p>
                                </div>
                            </div>
                            <div class="step-line"></div>
                            <div class="route-step">
                                <div class="step-icon step-end">
                                    <i class="fas fa-flag-checkered text-sm"></i>
                                </div>
                                <div>
                                    <p id="dropoffAddress" class="font-medium text-white"></p>
                                    <p class="text-gray-200 text-sm">Dropoff Location</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <i class="fas fa-user mr-2 text-blue-500"></i>
                            Customer Information
                        </h4>
                        <div class="space-y-2">
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Name:</span> 
                                <span id="customerName"></span>
                            </p>
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Contact:</span> 
                                <span id="customerContact"></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <i class="fas fa-box-open mr-2 text-green-500"></i>
                            Delivery Information
                        </h4>
                        <div class="space-y-2">
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Product:</span> 
                                <span id="productName"></span>
                            </p>
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Amount:</span> 
                                <span id="deliveryAmount"></span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg mb-6">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                        <i class="fas fa-credit-card mr-2 text-purple-500"></i>
                        Payment Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Method:</span> 
                                <span id="paymentMethod"></span>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="font-medium">Status:</span> 
                                <span id="paymentStatus"></span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="delivery-timeline">
                    <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Delivery Timeline</h4>
                    
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas fa-calendar-plus text-gray-500 dark:text-gray-400"></i>
                        </div>
                        <div class="timeline-content">
                            <p class="font-medium text-gray-800 dark:text-white">Order Created</p>
                            <p id="createdAt" class="text-gray-500 dark:text-gray-400 text-sm"></p>
                        </div>
                    </div>
                    
                    <div id="acceptedTimelineItem" class="timeline-item hidden">
                        <div class="timeline-icon">
                            <i class="fas fa-user-check text-gray-500 dark:text-gray-400"></i>
                        </div>
                        <div class="timeline-content">
                            <p class="font-medium text-gray-800 dark:text-white">Accepted by Driver</p>
                            <p id="acceptedAt" class="text-gray-500 dark:text-gray-400 text-sm"></p>
                        </div>
                    </div>
                    
                    <div id="completedTimelineItem" class="timeline-item hidden">
                        <div class="timeline-icon">
                            <i class="fas fa-check-circle text-gray-500 dark:text-gray-400"></i>
                        </div>
                        <div class="timeline-content">
                            <p class="font-medium text-gray-800 dark:text-white">Delivery Completed</p>
                            <p id="completedAt" class="text-gray-500 dark:text-gray-400 text-sm"></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button onclick="hideDeliveryDetails()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">
                    Close
                </button>
                <a id="manageDeliveryBtn" href="#" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-truck mr-2"></i>Manage Delivery
                </a>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        
        // Check for saved user preference or use system preference
        if (localStorage.getItem('color-theme') === 'dark' || (!localStorage.getItem('color-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            sunIcon.classList.add('hidden');
            moonIcon.classList.remove('hidden');
        } else {
            document.documentElement.classList.remove('dark');
            sunIcon.classList.remove('hidden');
            moonIcon.classList.add('hidden');
        }
        
        // Toggle theme on button click
        themeToggle.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
                sunIcon.classList.remove('hidden');
                moonIcon.classList.add('hidden');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            }
        });

        // Section toggle functionality
        document.getElementById('toggleAvailable').addEventListener('click', function() {
            const section = document.getElementById('availableDeliveriesSection');
            const icon = this.querySelector('i');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> Collapse';
            } else {
                section.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> Expand';
            }
        });

        document.getElementById('toggleActive').addEventListener('click', function() {
            const section = document.getElementById('activeDeliveriesSection');
            const icon = this.querySelector('i');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> Collapse';
            } else {
                section.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> Expand';
            }
        });

        document.getElementById('toggleCompleted').addEventListener('click', function() {
            const section = document.getElementById('completedDeliveriesSection');
            const icon = this.querySelector('i');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.innerHTML = '<i class="fas fa-chevron-down mr-1"></i> Collapse';
            } else {
                section.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.innerHTML = '<i class="fas fa-chevron-up mr-1"></i> Expand';
            }
        });

        // Delivery details modal functions
        function showDeliveryDetails(deliveryId) {
            // In a real application, you would fetch these details from the server via AJAX
            // For this example, we'll simulate it with the data we have
            
            // Find the delivery in the available, active, or completed sections
            let delivery = null;
            
            // Check available deliveries
            <?php 
            mysqli_data_seek($result_available, 0);
            while ($row = mysqli_fetch_assoc($result_available)): ?>
                if (<?= $row['delivery_id'] ?> === deliveryId) {
                    delivery = {
                        id: <?= $row['delivery_id'] ?>,
                        customerName: '<?= addslashes($row['customer_name']) ?>',
                        customerContact: '<?= addslashes($row['customer_contact']) ?>',
                        pickupAddress: '<?= addslashes($row['pickup_address']) ?>',
                        dropoffAddress: '<?= addslashes($row['dropoff_address']) ?>',
                        productName: '<?= addslashes($row['product_name']) ?>',
                        amount: '₱<?= number_format($row['amount'], 2) ?>',
                        paymentMethod: '<?= str_replace('_', ' ', $row['payment_method']) ?>',
                        paymentStatus: '<?= $row['payment_status'] ?>',
                        createdAt: '<?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>',
                        acceptedAt: null,
                        completedAt: null,
                        isActive: false
                    };
                }
            <?php endwhile; ?>
            
            // Check active deliveries
            <?php 
            mysqli_data_seek($result_active, 0);
            while ($row = mysqli_fetch_assoc($result_active)): ?>
                if (<?= $row['delivery_id'] ?> === deliveryId) {
                    delivery = {
                        id: <?= $row['delivery_id'] ?>,
                        customerName: '<?= addslashes($row['customer_name']) ?>',
                        customerContact: '<?= addslashes($row['customer_contact']) ?>',
                        pickupAddress: '<?= addslashes($row['pickup_address']) ?>',
                        dropoffAddress: '<?= addslashes($row['dropoff_address']) ?>',
                        productName: '<?= addslashes($row['product_name']) ?>',
                        amount: '₱<?= number_format($row['amount'], 2) ?>',
                        paymentMethod: '<?= str_replace('_', ' ', $row['payment_method']) ?>',
                        paymentStatus: '<?= $row['payment_status'] ?>',
                        createdAt: '<?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>',
                        acceptedAt: '<?= date('M j, Y g:i A', strtotime($row['created_at'] . ' + 15 minutes')) ?>',
                        completedAt: null,
                        isActive: true
                    };
                }
            <?php endwhile; ?>
            
            // Check completed deliveries
            <?php 
            mysqli_data_seek($result_completed, 0);
            while ($row = mysqli_fetch_assoc($result_completed)): ?>
                if (<?= $row['delivery_id'] ?> === deliveryId) {
                    delivery = {
                        id: <?= $row['delivery_id'] ?>,
                        customerName: '<?= addslashes($row['customer_name']) ?>',
                        customerContact: '<?= addslashes($row['customer_contact']) ?>',
                        pickupAddress: '<?= addslashes($row['pickup_address']) ?>',
                        dropoffAddress: '<?= addslashes($row['dropoff_address']) ?>',
                        productName: '<?= addslashes($row['product_name']) ?>',
                        amount: '₱<?= number_format($row['amount'], 2) ?>',
                        paymentMethod: '<?= str_replace('_', ' ', $row['payment_method']) ?>',
                        paymentStatus: '<?= $row['payment_status'] ?>',
                        createdAt: '<?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>',
                        acceptedAt: '<?= date('M j, Y g:i A', strtotime($row['created_at'] . ' + 15 minutes')) ?>',
                        completedAt: '<?= date('M j, Y g:i A', strtotime($row['completed_at'])) ?>',
                        isActive: false
                    };
                }
            <?php endwhile; ?>
            
            if (delivery) {
                // Populate the modal with delivery details
                document.getElementById('pickupAddress').textContent = delivery.pickupAddress;
                document.getElementById('dropoffAddress').textContent = delivery.dropoffAddress;
                document.getElementById('customerName').textContent = delivery.customerName;
                document.getElementById('customerContact').textContent = delivery.customerContact;
                document.getElementById('productName').textContent = delivery.productName;
                document.getElementById('deliveryAmount').textContent = delivery.amount;
                document.getElementById('paymentMethod').textContent = delivery.paymentMethod;
                document.getElementById('paymentStatus').textContent = delivery.paymentStatus;
                document.getElementById('createdAt').textContent = delivery.createdAt;
                
                // Show/hide timeline items
                const acceptedItem = document.getElementById('acceptedTimelineItem');
                const completedItem = document.getElementById('completedTimelineItem');
                
                if (delivery.acceptedAt) {
                    document.getElementById('acceptedAt').textContent = delivery.acceptedAt;
                    acceptedItem.classList.remove('hidden');
                } else {
                    acceptedItem.classList.add('hidden');
                }
                
                if (delivery.completedAt) {
                    document.getElementById('completedAt').textContent = delivery.completedAt;
                    completedItem.classList.remove('hidden');
                } else {
                    completedItem.classList.add('hidden');
                }
                
                // Update the manage delivery button link
                const manageBtn = document.getElementById('manageDeliveryBtn');
                manageBtn.href = `driver_delivery_details.php?delivery_id=${delivery.id}`;
                
                // Hide manage button for completed deliveries
                if (delivery.completedAt) {
                    manageBtn.classList.add('hidden');
                } else {
                    manageBtn.classList.remove('hidden');
                }
                
                // Show the modal
                document.getElementById('deliveryDetailsModal').style.display = 'flex';
            }
        }

        function hideDeliveryDetails() {
            document.getElementById('deliveryDetailsModal').style.display = 'none';
        }
    </script>
</body>
</html>