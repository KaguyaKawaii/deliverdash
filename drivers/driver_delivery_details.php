<?php
session_start();
require '../connection.php';

// Validate session and connection
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Validate delivery_id
if (!isset($_GET['delivery_id']) || !ctype_digit($_GET['delivery_id'])) {
    die("Invalid delivery ID.");
}

$delivery_id = intval($_GET['delivery_id']);
$driver_id = $_SESSION['driver_id'];

// Fetch delivery details with secure prepared statement
$query = "SELECT 
            d.*, 
            u.name AS user_name, 
            u.contact AS user_contact,
            dr.name AS driver_name,  
            dr.contact AS driver_contact,
            dr.vehicle AS driver_vehicle,
            p.amount,
            p.driver_fee,
            p.payment_method,
            p.status AS payment_status
          FROM deliveries d 
          JOIN users u ON d.user_id = u.user_id
          LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
          LEFT JOIN payments p ON d.delivery_id = p.delivery_id
          WHERE d.delivery_id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt || !mysqli_stmt_bind_param($stmt, "i", $delivery_id) || !mysqli_stmt_execute($stmt)) {
    die("Database error: " . mysqli_error($conn));
}

$result = mysqli_stmt_get_result($stmt);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    die("Delivery not found.");
}

// Handle delivery actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept delivery
    if (isset($_POST['accept'])) {
        mysqli_begin_transaction($conn);
        
        try {
            // Verify delivery is still available
            $check_query = "SELECT status FROM deliveries WHERE delivery_id = ? AND (driver_id IS NULL OR driver_id = ?) FOR UPDATE";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "ii", $delivery_id, $driver_id);
            mysqli_stmt_execute($check_stmt);
            $status = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))['status'];
            
            if (!in_array($status, ['Pending', 'Pending Driver Acceptance'])) {
                throw new Exception("Delivery no longer available");
            }
            
            // Assign delivery
            $update_query = "UPDATE deliveries SET 
                            driver_id = ?, 
                            status = 'Accepted',
                            updated_at = NOW()
                            WHERE delivery_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $driver_id, $delivery_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to accept delivery");
            }
            
            // Update driver status
            $driver_query = "UPDATE drivers SET status = 'on_delivery' WHERE driver_id = ?";
            $driver_stmt = mysqli_prepare($conn, $driver_query);
            mysqli_stmt_bind_param($driver_stmt, "i", $driver_id);
            
            if (!mysqli_stmt_execute($driver_stmt)) {
                throw new Exception("Failed to update driver status");
            }
            
            mysqli_commit($conn);
            header("Location: driver_delivery_details.php?delivery_id=$delivery_id");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
    
    // Complete delivery
    if (isset($_POST['complete'])) {
        mysqli_begin_transaction($conn);
        
        try {
            // Verify assignment
            $verify_query = "SELECT driver_id, status FROM deliveries WHERE delivery_id = ? FOR UPDATE";
            $verify_stmt = mysqli_prepare($conn, $verify_query);
            mysqli_stmt_bind_param($verify_stmt, "i", $delivery_id);
            mysqli_stmt_execute($verify_stmt);
            $delivery_data = mysqli_fetch_assoc(mysqli_stmt_get_result($verify_stmt));
            
            if ($delivery_data['driver_id'] != $driver_id) {
                throw new Exception("You are not assigned to this delivery");
            }
            
            if ($delivery_data['status'] !== 'Accepted') {
                throw new Exception("Cannot complete delivery in current status");
            }
            
            // Update delivery
            $update_delivery = "UPDATE deliveries SET 
                              status = 'Completed',
                              updated_at = NOW()
                              WHERE delivery_id = ?";
            $delivery_stmt = mysqli_prepare($conn, $update_delivery);
            mysqli_stmt_bind_param($delivery_stmt, "i", $delivery_id);
            
            if (!mysqli_stmt_execute($delivery_stmt)) {
                throw new Exception("Failed to complete delivery");
            }
            
            // Handle COD payment
            if ($delivery['payment_method'] == 'cash_on_delivery' && $delivery['payment_status'] != 'Completed') {
                $update_payment = "UPDATE payments SET 
                                 status = 'Completed',
                                 transaction_date = NOW()
                                 WHERE delivery_id = ?";
                $payment_stmt = mysqli_prepare($conn, $update_payment);
                mysqli_stmt_bind_param($payment_stmt, "i", $delivery_id);
                
                if (!mysqli_stmt_execute($payment_stmt)) {
                    throw new Exception("Failed to update payment");
                }
            }
            
            // Free up driver
            $update_driver = "UPDATE drivers SET status = 'available' WHERE driver_id = ?";
            $driver_stmt = mysqli_prepare($conn, $update_driver);
            mysqli_stmt_bind_param($driver_stmt, "i", $driver_id);
            
            if (!mysqli_stmt_execute($driver_stmt)) {
                throw new Exception("Failed to update driver status");
            }
            
            mysqli_commit($conn);
            header("Location: driver_dashboard.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: #1e293b; /* Default light mode text color */
        }
        .dark body {
            background-color: #0f172a;
            color: #f8fafc; /* Default dark mode text color */
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
        .payment-badge {
            padding: 0.35rem 0.7rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.8rem;
        }
        .payment-pending { 
            background-color: #ffedd5; 
            color: #9a3412;
        }
        .dark .payment-pending {
            background-color: #431407;
            color: #fdba74;
        }
        .payment-completed { 
            background-color: #dcfce7; 
            color: #166534;
        }
        .dark .payment-completed {
            background-color: #14532d;
            color: #86efac;
        }
        .card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            color: #1e293b; /* Light mode text color */
        }
        .dark .card {
            background-color: #1e293b;
            border-color: #334155;
            color: #f8fafc; /* Dark mode text color */
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .dark .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        .section-title {
            position: relative;
            padding-left: 1rem;
            color: #1e293b; /* Light mode title color */
        }
        .dark .section-title {
            color: #f8fafc; /* Dark mode title color */
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
        .info-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .info-item {
            border-bottom-color: #334155;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
            width: 120px;
            flex-shrink: 0;
        }
        .dark .info-label {
            color: #94a3b8;
        }
        .info-value {
            color: #1e293b;
            flex-grow: 1;
        }
        .dark .info-value {
            color: #f8fafc;
        }
        .btn-primary {
            background-color: #4f46e5;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary:hover {
            background-color: #4338ca;
        }
        .btn-success {
            background-color: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-success:hover {
            background-color: #0d9f6e;
        }
        .icon-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 0.75rem;
        }
        .icon-blue {
            background-color: #dbeafe;
            color: #2563eb;
        }
        .dark .icon-blue {
            background-color: #1e3a8a;
            color: #93c5fd;
        }
        .icon-green {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .dark .icon-green {
            background-color: #14532d;
            color: #86efac;
        }
        .icon-amber {
            background-color: #fef3c7;
            color: #d97706;
        }
        .dark .icon-amber {
            background-color: #5a3806;
            color: #fbbf24;
        }
        /* Additional text color classes */
        .text-dark {
            color: #1e293b;
        }
        .dark .text-dark {
            color: #f8fafc;
        }
        .text-muted {
            color: #64748b;
        }
        .dark .text-muted {
            color: #94a3b8;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <nav class="bg-gray-800 dark:bg-gray-800 p-4 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="driver_dashboard.php" class="text-gray-300 hover:text-white">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <h1 class="text-xl font-bold">Delivery Details</h1>
            </div>
            <span class="bg-gray-700 dark:bg-gray-600 px-3 py-1 rounded-full text-sm">
                ID: #<?= htmlspecialchars($delivery['delivery_id']) ?>
            </span>
        </div>
    </nav>

    <main class="container mx-auto p-4">
        <?php if (isset($error)): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-6 flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Delivery Summary -->
        <div class="card p-6 mb-6">
            <div class="flex items-center mb-4">
                <div class="icon-container icon-blue">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h2 class="section-title text-xl font-bold text-dark-500">Delivery Summary</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Delivery Status -->
                <div class="card p-4">
                    <h3 class="font-semibold text-dark-700 mb-3 flex items-center">
                        <i class="fas fa-truck text-blue-500 mr-2"></i>
                        Delivery Status
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="status-badge status-<?= strtolower($delivery['status']) ?>">
                                <?php if ($delivery['status'] === 'Pending'): ?>
                                    <i class="fas fa-clock"></i>
                                <?php elseif ($delivery['status'] === 'Accepted'): ?>
                                    <i class="fas fa-truck-moving"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($delivery['status']) ?>
                            </span>
                        </div>
                        
                        <?php if ($delivery['driver_id']): ?>
                            <div class="info-item">
                                <span class="info-label">Driver</span>
                                <span class="info-value"><?= htmlspecialchars($delivery['driver_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Vehicle</span>
                                <span class="info-value"><?= htmlspecialchars($delivery['driver_vehicle'] ?? 'N/A') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="card p-4">
                    <h3 class="font-semibold text-gray-700 dark:text-dark-300 mb-3 flex items-center">
                        <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                        Payment Information
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="payment-badge payment-<?= strtolower($delivery['payment_status']) ?>">
                                <?= htmlspecialchars($delivery['payment_status']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Method</span>
                            <span class="info-value"><?= htmlspecialchars(str_replace('_', ' ', $delivery['payment_method'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total</span>
                            <span class="info-value font-semibold text-blue-600 dark:text-blue-400">
                                ₱<?= number_format($delivery['amount'] + $delivery['driver_fee'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Delivery Details -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Pickup Information -->
            <div class="card p-6">
                <div class="flex items-center mb-4">
                    <div class="icon-container icon-blue">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h2 class="section-title text-xl font-bold text-dark-800 dark:text-dark">Pickup Information</h2>
                </div>
                
                <div class="space-y-3">
                    <div class="info-item">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['user_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['user_contact']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['pickup_address']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Dropoff Information -->
            <div class="card p-6">
                <div class="flex items-center mb-4">
                    <div class="icon-container icon-green">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <h2 class="section-title text-xl font-bold text-gray-800 dark:text-dark">Dropoff Information</h2>
                </div>
                
                <div class="space-y-3">
                    <div class="info-item">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['dropoff_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['dropoff_contact']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($delivery['dropoff_address']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Details -->
        <div class="card p-6 mb-6">
            <div class="flex items-center mb-4">
                <div class="icon-container icon-amber">
                    <i class="fas fa-box-open"></i>
                </div>
                <h2 class="section-title text-xl font-bold text-gray-800 dark:text-dark">Product Details</h2>
            </div>
            
            <div class="space-y-3">
                <div class="info-item">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?= htmlspecialchars($delivery['product_name']) ?></span>
                </div>
                
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-6">
            <?php if ($delivery['status'] === 'Pending' || $delivery['status'] === 'Pending Driver Acceptance'): ?>
                <form method="POST" onsubmit="return confirm('Accept this delivery?');" class="w-full">
                    <button type="submit" name="accept" class="btn-primary w-full">
                        <i class="fas fa-check-circle mr-2"></i> Accept Delivery
                    </button>
                </form>
            
            <?php elseif ($delivery['status'] === 'Accepted' && $delivery['driver_id'] == $driver_id): ?>
                <form method="POST" onsubmit="return confirm('<?= ($delivery['payment_method'] == 'cash_on_delivery' ? 'Confirm payment received and complete delivery?' : 'Mark delivery as completed?') ?>');" class="w-full">
                    <button type="submit" name="complete" class="btn-success w-full">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        <?= ($delivery['payment_method'] == 'cash_on_delivery' ? 'Confirm Payment & Complete' : 'Complete Delivery') ?>
                    </button>
                </form>
            
            <?php elseif ($delivery['status'] === 'Completed'): ?>
                <div class="card p-4 text-center bg-green-50 dark:bg-green-900/30 border border-green-100 dark:border-green-800">
                    <div class="flex items-center justify-center space-x-2">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                        <h3 class="font-medium text-green-800 dark:text-green-200">Delivery Completed</h3>
                    </div>
                    <p class="text-green-600 dark:text-green-300 mt-1">
                        <?= date('M j, Y g:i A', strtotime($delivery['updated_at'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Theme detection (matches dashboard functionality)
        if (localStorage.getItem('color-theme') === 'dark' || (!localStorage.getItem('color-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</body>
</html>