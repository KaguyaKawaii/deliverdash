<?php
session_start();
include '../../connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'];



// Handle cancel action - moved to the top for better flow
if (isset($_GET['cancel']) && $_GET['cancel'] === 'true') {
    if (!isset($_GET['delivery_id'])) {
        die("Delivery ID is required to cancel.");
    }

    $delivery_id = intval($_GET['delivery_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // First check if the delivery belongs to the user
        $check_query = "SELECT status FROM deliveries WHERE delivery_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $delivery_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Delivery not found or you don't have permission to cancel it.");
        }

        // Delete the delivery record
        $sql = "DELETE FROM deliveries WHERE delivery_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("ii", $delivery_id, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to cancel the delivery.");
        }

        $conn->commit();
        header("Location: user_delivery.php?message=Delivery+canceled+successfully");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: " . $e->getMessage() . "</div>";
    }
}

// Check for delivery_id and amount only if not canceling
if (!isset($_GET['delivery_id']) || !isset($_GET['amount'])) {
    die("Missing delivery information.");
}

$delivery_id = intval($_GET['delivery_id']);
$amount = floatval($_GET['amount']);
$driver_fee = 300.00;
$total_amount = $amount + $driver_fee;

// Fetch box_size from the deliveries table
$query = "SELECT box_size FROM deliveries WHERE delivery_id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("ii", $delivery_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid delivery ID or you don't have permission to access this delivery.");
}
$row = $result->fetch_assoc();
$box_size = $row['box_size'];

// Process payment form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    
    // Validate payment method
    if (!in_array($payment_method, ["credit_card", "cash_on_delivery"])) {
        die("Invalid payment method.");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Set statuses based on payment method
        $payment_status = ($payment_method == "credit_card") ? "Completed" : "Pending";
        $delivery_status = ($payment_method == "credit_card") ? "Pending" : "Pending Driver Acceptance";
        
        // Insert the payment
        $sql = "INSERT INTO payments (user_id, delivery_id, amount, driver_fee, payment_method, status, transaction_date) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Payment processing error: " . $conn->error);
        }
        $stmt->bind_param("iidsss", $user_id, $delivery_id, $amount, $driver_fee, $payment_method, $payment_status);
        if (!$stmt->execute()) {
            throw new Exception("Failed to process payment.");
        }
        $payment_id = $conn->insert_id;

        // Update delivery status
        $update_delivery = "UPDATE deliveries 
                           SET payment_status = ?,
                               status = ?
                           WHERE delivery_id = ?";
        $stmt = $conn->prepare($update_delivery);
        if (!$stmt) {
            throw new Exception("Delivery update error: " . $conn->error);
        }
        $stmt->bind_param("ssi", $payment_status, $delivery_status, $delivery_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update delivery status.");
        }

        // Only assign driver immediately for credit card payments
        if ($payment_method == "credit_card") {
            $find_driver = "SELECT driver_id FROM drivers WHERE status = 'available' LIMIT 1 FOR UPDATE";
            $driver_result = $conn->query($find_driver);
            
            if ($driver_result && $driver_result->num_rows > 0) {
                $driver_row = $driver_result->fetch_assoc();
                $driver_id = $driver_row['driver_id'];
                
                // Create assignment
                $insert_assignment = "INSERT INTO driver_assignments 
                                    (driver_id, delivery_id, assigned_at, status)
                                    VALUES (?, ?, NOW(), 'pending')";
                $stmt = $conn->prepare($insert_assignment);
                if (!$stmt || !$stmt->execute([$driver_id, $delivery_id])) {
                    throw new Exception("Failed to assign driver.");
                }
                
                // Update driver status
                $update_driver = "UPDATE drivers SET status = 'on_delivery' WHERE driver_id = ?";
                $stmt = $conn->prepare($update_driver);
                if (!$stmt || !$stmt->execute([$driver_id])) {
                    throw new Exception("Failed to update driver status.");
                }
            } else {
                throw new Exception("No available drivers. Please try again later.");
            }
        }

        $conn->commit();
        header("Location: payment_success.php?payment_id=" . $payment_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            800: '#1a1a1a',
                            900: '#121212',
                        },
                        primary: {
                            500: '#6366f1',
                            600: '#4f46e5',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #121212;
            color: #e5e7eb;
        }
        .form-section {
            transition: all 0.3s ease;
            border: 1px solid #333;
        }
        .form-section:hover {
            transform: translateY(-2px);
            border-color: #2E7D32;
        }
        input, textarea, select {
            background-color: #1f1f1f !important;
            border-color: #9ca3af !important;
            color:rgb(181, 185, 192) !important;
        }
        input:read-only, textarea:read-only {
            background-color: #f3f4f6 !important;
            color:rgb(207, 212, 221) !important;
        }
        .nav-link:hover {
            background-color: #1f1f1f;
        }
        .nav-link.active {
            background-color: #2E7D32;
        }
        .payment-summary {
            background-color: #1a1a1a;
            border-color: #333;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-dark-800 border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-xl font-semibold text-white">DeliverDash</span>
                    </div>
                </div>
                
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-100 mb-2">Payment Processing</h1>
            <p class="text-gray-400">Complete your payment details</p>
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <div class="space-y-6 bg-dark-800 rounded-xl shadow-xl p-6 border border-gray-800">
            <!-- Payment Summary -->
            <div class="form-section payment-summary p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-primary-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-receipt text-primary-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Order Summary</h3>
                </div>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Box Size:</span>
                        <span class="font-medium text-gray-100"><?= htmlspecialchars(ucfirst($box_size)) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Box Price:</span>
                        <span class="font-medium text-gray-100">₱<?= number_format($amount, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Driver Fee:</span>
                        <span class="font-medium text-gray-100">₱<?= number_format($driver_fee, 2) ?></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-700 pt-3 mt-3">
                        <span class="text-lg font-semibold text-green-500">Total Amount:</span>
                        <span class="text-lg font-bold text-green-500">₱<?= number_format($total_amount, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <form method="POST" class="form-section bg-dark-700 p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-credit-card text-purple-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Payment Method</h3>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-400 mb-1">Select Payment Method*</label>
                        <select name="payment_method" id="payment_method" class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                            <option value="">Select Payment Method</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="cash_on_delivery">Cash on Delivery</option>
                        </select>
                    </div>

                    <input type="hidden" name="delivery_id" value="<?= htmlspecialchars($delivery_id) ?>">
                    <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">
                    <input type="hidden" name="box_size" value="<?= htmlspecialchars($box_size) ?>">
                </div>

               
            

                <div class="pt-6 flex gap-4">
                    
                    <!-- Cancel Button (Left) -->
                    <a href="?cancel=true&delivery_id=<?= htmlspecialchars($delivery_id) ?>&amount=<?= htmlspecialchars($amount) ?>" 
                    class="w-1/2 bg-gray-700 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-600 transition duration-300 shadow hover:shadow-lg text-center flex items-center justify-center"
                    onclick="return confirm('Are you sure you want to cancel this delivery? All information will be lost.');">
                        <i class="fas fa-times mr-2"></i> Cancel Delivery
                    </a>

                    <!-- Confirm Button (Right ja ha hindi t left) -->
                    <button type="submit" 
                        class="w-1/2 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-6 rounded-lg font-medium hover:from-green-600 hover:to-green-700 transition duration-300 shadow-lg hover:shadow-xl">
                        <i class="fas fa-lock mr-2"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple script to handle mobile menu if needed
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuButton = document.getElementById('user-menu');
            if (userMenuButton) {
                userMenuButton.addEventListener('click', function() {
                    // Add dropdown menu functionality here
                    console.log('User menu clicked');
                });
            }
        });
    </script>
</body>
</html>