<?php
session_start();
include '../../connection.php';

// Check if the payment_id is passed in the URL and is valid
if (!isset($_GET['payment_id']) || !is_numeric($_GET['payment_id'])) {
    echo "Payment ID is required and must be a valid number!";
    exit();
}

$payment_id = $_GET['payment_id'];

// Fetch payment details including driver_fee
$sql = "SELECT * FROM payments WHERE payment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    echo "Payment not found.";
    exit();
}

// Fetch delivery details including sender and receiver information
$query = "SELECT user_id, box_size, pickup_address, dropoff_name, dropoff_address, dropoff_contact 
          FROM deliveries WHERE delivery_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment['delivery_id']);
$stmt->execute();
$result = $stmt->get_result();
$delivery = $result->fetch_assoc();
$stmt->close();

if (!$delivery) {
    echo "Delivery details not found.";
    exit();
}

$user_id = $delivery['user_id'];
$box_size = $delivery['box_size'];
$pickup_address = $delivery['pickup_address']; // Sender's Address
$receiver_name = $delivery['dropoff_name'];
$receiver_address = $delivery['dropoff_address'];
$receiver_contact = $delivery['dropoff_contact'];

// Fetch sender details from users table
$query = "SELECT name, contact FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$sender = $result->fetch_assoc();
$stmt->close();

$sender_name = $sender ? $sender['name'] : "Unknown";
$sender_contact = $sender ? $sender['contact'] : "Unknown";

$total_amount = $payment['amount'] + $payment['driver_fee'];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top left, #0f172a 0%, #020617 100%);
        }
        .success-card {
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
        }
        .info-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        .payment-glow {
            box-shadow: 0 0 20px rgba(74, 222, 128, 0.3);
            border: 1px solid rgba(74, 222, 128, 0.2);
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
        }
        .badge-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(74, 222, 128, 0); }
            100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
        }
        .hover-grow {
            transition: all 0.2s ease;
        }
        .hover-grow:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen flex items-center justify-center p-4">
    <div class="success-card rounded-2xl overflow-hidden w-full max-w-2xl">
        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-900/30 to-emerald-900/10 p-6 border-b border-emerald-900/20">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="bg-emerald-500/20 p-3 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Payment Successful</h1>
                        <p class="text-emerald-300">Thank you for using DeliverDash!</p>
                    </div>
                </div>
                <span class="badge-pulse px-4 py-1.5 bg-emerald-900/80 text-emerald-300 rounded-full text-sm font-medium border border-emerald-800/50">
                    Completed
                </span>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="p-6 space-y-6">
            <!-- Payment Summary -->
            <div class="info-card p-6 rounded-xl payment-glow">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <div class="bg-emerald-500/20 p-2 rounded-lg mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        Payment Summary
                    </h2>
                    <span class="text-xs text-gray-400">Ref: <?php echo htmlspecialchars($payment['payment_id']); ?></span>
                </div>
                
                <div class="grid grid-cols-2 gap-5 mb-5">
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Delivery ID</p>
                        <p class="font-medium text-white">#<?php echo htmlspecialchars($payment['delivery_id']); ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Box Size</p>
                        <p class="font-medium text-white"><?php echo htmlspecialchars($box_size); ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Payment Method</p>
                        <p class="font-medium text-white"><?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?></p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider">Date</p>
                        <p class="font-medium text-white"><?php echo date('M j, Y'); ?></p>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <div class="space-y-3 pt-4">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Delivery Amount:</span>
                        <span class="font-medium">₱<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Driver Fee:</span>
                        <span class="font-medium">₱<?php echo number_format($payment['driver_fee'], 2); ?></span>
                    </div>
                    <div class="divider"></div>
                    <div class="flex justify-between pt-2">
                        <span class="text-gray-300 font-semibold">Total Paid:</span>
                        <span class="text-emerald-400 font-bold text-lg">₱<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Sender Information -->
                <div class="info-card p-6 rounded-xl hover-grow">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <div class="bg-blue-500/20 p-2 rounded-lg mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        Sender Details
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Name</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($sender_name); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Contact</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($sender_contact); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Pickup Address</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($pickup_address); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Receiver Information -->
                <div class="info-card p-6 rounded-xl hover-grow">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center">
                        <div class="bg-purple-500/20 p-2 rounded-lg mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6z" />
                            </svg>
                        </div>
                        Receiver Details
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Name</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($receiver_name); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Contact</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($receiver_contact); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Delivery Address</p>
                            <p class="font-medium text-white"><?php echo htmlspecialchars($receiver_address); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 pt-4">
                <a href="../user_dashboard.php" class="flex-1 px-6 py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-medium rounded-lg transition-all duration-300 flex items-center justify-center hover-grow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Return to Dashboard
                </a>
                    <button onclick="generatePlainTextReceipt()" class="flex-1 px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all duration-300 flex items-center justify-center hover-grow">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M5 4v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v1h6V4zm0 5H7v1h6V9zm-6 4h6v1H7v-1z" clip-rule="evenodd" />
    </svg>
    Print Receipt
</button>

<!-- Add this script before the closing body tag -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    // Initialize jsPDF
    const { jsPDF } = window.jspdf;

    function generatePlainTextReceipt() {
        // Create a new PDF
        const pdf = new jsPDF();
        
        // Set font and size
        pdf.setFont("helvetica");
        pdf.setFontSize(12);
        
        // Add logo or title
        pdf.setFontSize(20);
        pdf.setTextColor(0, 0, 0);
        pdf.text("DeliverDash Receipt", 105, 20, { align: 'center' });
        
        // Add divider line
        pdf.setDrawColor(0, 0, 0);
        pdf.line(20, 25, 190, 25);
        
        // Reset font size for content
        pdf.setFontSize(12);
        
        // Payment details
        pdf.text(`Payment ID: ${<?php echo $payment_id; ?>}`, 20, 35);
        pdf.text(`Date: ${new Date().toLocaleDateString()}`, 20, 45);
        pdf.text(`Delivery ID: ${<?php echo $payment['delivery_id']; ?>}`, 20, 55);
        pdf.text(`Box Size: ${<?php echo json_encode($box_size); ?>}`, 20, 65);
        pdf.text(`Payment Method: ${<?php echo json_encode(ucfirst($payment['payment_method'])); ?>}`, 20, 75);
        
        // Add divider
        pdf.line(20, 85, 190, 85);
        
        // Amount details
        pdf.text("Delivery Amount:", 20, 95);
        pdf.text(`${<?php echo number_format($payment['amount'], 2); ?>} Pesos`, 180, 95, { align: 'right' });
        
        pdf.text("Driver Fee:", 20, 105);
        pdf.text(`${<?php echo number_format($payment['driver_fee'], 2); ?>} Pesos`, 180, 105, { align: 'right' });
        
        pdf.setFont("helvetica", "bold");
        pdf.text("Total Paid:", 20, 115);
        pdf.text(`${<?php echo number_format($total_amount, 2); ?>} Pesos`, 180, 115, { align: 'right' });
        
        // Add divider
        pdf.line(20, 125, 190, 125);
        
        // Sender information
        pdf.setFont("helvetica", "bold");
        pdf.text("Sender Details", 20, 135);
        pdf.setFont("helvetica", "normal");
        pdf.text(`Name: ${<?php echo json_encode($sender_name); ?>}`, 20, 145);
        pdf.text(`Contact: ${<?php echo json_encode($sender_contact); ?>}`, 20, 155);
        pdf.text(`Pickup Address: ${<?php echo json_encode($pickup_address); ?>}`, 20, 165);
        
        // Receiver information
        pdf.setFont("helvetica", "bold");
        pdf.text("Receiver Details", 20, 175);
        pdf.setFont("helvetica", "normal");
        pdf.text(`Name: ${<?php echo json_encode($receiver_name); ?>}`, 20, 185);
        pdf.text(`Contact: ${<?php echo json_encode($receiver_contact); ?>}`, 20, 195);
        pdf.text(`Delivery Address: ${<?php echo json_encode($receiver_address); ?>}`, 20, 205);
        
        // Footer
        pdf.setFontSize(10);
        pdf.setTextColor(100, 100, 100);
        pdf.text("Thank you for using DeliverDash!", 105, 280, { align: 'center' });
        
        // Download the PDF
        pdf.save(`DeliverDash_Receipt_${<?php echo $payment_id; ?>}.pdf`);
    }
</script>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="bg-gray-900/50 p-4 text-center text-gray-500 text-sm border-t border-gray-800">
            <p class="flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                Payment processed on <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
            <p class="mt-1">Need assistance? Contact our <a href="#" class="text-emerald-400 hover:underline">support team</a>.</p>
        </div>
    </div>

    <!-- Confetti Effect (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.4.0/dist/confetti.browser.min.js"></script>
    <script>
        // Trigger confetti on page load
        document.addEventListener('DOMContentLoaded', function() {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 }
            });
        });
    </script>
</body>
</html>