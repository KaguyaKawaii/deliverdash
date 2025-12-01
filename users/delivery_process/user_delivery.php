<?php
session_start();
session_regenerate_id(true);

include '../../connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'];

$sql = "SELECT contact FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($contact);
$stmt->fetch();
$stmt->close();

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dropoff_name = trim($_POST['dropoff_name']);
    $dropoff_contact = trim($_POST['dropoff_contact']);
    $pickup_address = trim($_POST['pickup_address']);
    $dropoff_address = trim($_POST['dropoff_address']);

    if (!empty($dropoff_name) && !empty($dropoff_contact) && !empty($pickup_address) && !empty($dropoff_address)) {
        $sql = "INSERT INTO deliveries (user_id, dropoff_name, dropoff_contact, pickup_address, dropoff_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("issss", $user_id, $dropoff_name, $dropoff_contact, $pickup_address, $dropoff_address);
            if ($stmt->execute()) {
                header("Location: delivery_details.php?delivery_id=" . $stmt->insert_id);
                exit();
            } else {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error preparing statement: " . htmlspecialchars($conn->error) . "</div>";
        }
    } else {
        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>All fields are required.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a Delivery | DeliverDash</title>
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
            border-color: #333 !important;
            color: #f3f4f6 !important;
        }
        input:read-only, textarea:read-only {
            background-color: #1f1f1f !important;
            color: #9ca3af !important;
        }
        .nav-link:hover {
            background-color: #1f1f1f;
        }
        .nav-link.active {
            background-color: #4f46e5;
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
                <!-- <div class="hidden md:ml-6 md:flex md:items-center">
                    <div class="ml-3 relative">
                        <div>
                            <button type="button" class="bg-dark-800 flex text-sm rounded-full focus:outline-none" id="user-menu">
                                <span class="sr-only">Open user menu</span>
                                <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center text-white">
                                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                                </div>
                            </button>
                        </div>
                    </div>
                </div> -->
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto px-4 py-8"> <!-- Adjusted width to prevent scrolling -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-100 mb-2">Request a Delivery</h1>
            <p class="text-gray-400">Complete the form to schedule your package delivery</p>
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <form action="" method="POST" class="space-y-6 bg-dark-800 rounded-xl shadow-xl p-6 border border-gray-800">
            <!-- Sender Information -->
            <div class="form-section bg-dark-700 p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-primary-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-user text-primary-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Sender Information</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-400 mb-1">Your Name</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" 
                               class=" w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" readonly>
                    </div>
                    
                    <div>
    <label for="contact" class="block text-sm font-medium text-gray-400 mb-1">Sender Contact*</label>
    <input 
        type="text" 
        id="contact" 
        name="contact" 
        value="<?php echo htmlspecialchars($contact); ?>" 
        maxlength="10" 
        readonly
        class="w-full px-4 py-2 rounded-lg bg-gray-100 focus:ring-0 focus:border-gray-300"
        placeholder="Sender's phone number"
        required>
</div>
                    
                    <div class="md:col-span-2">
                        <label for="pickup_address" class="block text-sm font-medium text-gray-400 mb-1">Pickup Address*</label>
                        <textarea id="pickup_address" name="pickup_address" required rows="3"
                                  class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                  placeholder="Enter full pickup address including landmarks"></textarea>
                    </div>
                </div>
            </div>

            <!-- Receiver Information -->
            <div class="form-section bg-dark-700 p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-user-friends text-purple-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Receiver Information</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="dropoff_name" class="block text-sm font-medium text-gray-400 mb-1">Receiver Name*</label>
                        <input type="text" id="dropoff_name" name="dropoff_name" required
                               class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="Enter receiver's full name">
                    </div>
                    
                    <div>
    <label for="dropoff_contact" class="block text-sm font-medium text-gray-400 mb-1">Receiver Contact*</label>
    <input 
        type="text" 
        id="dropoff_contact" 
        name="dropoff_contact" 
        maxlength="10" 
        pattern="^9\d{9}$" 
        title="Enter a 10-digit phone number starting with 9"
        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
        class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        placeholder="Enter receiver's phone number"
        required>
    <p id="contact-error" class="text-red-500 text-sm mt-1 hidden">Receiver number must be different from sender number.</p>
</div>

                    
                    <div class="md:col-span-2">
                        <label for="dropoff_address" class="block text-sm font-medium text-gray-400 mb-1">Delivery Address*</label>
                        <textarea id="dropoff_address" name="dropoff_address" required rows="3"
                                  class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                  placeholder="Enter full delivery address including landmarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex space-x-4">
                <a href="../user_dashboard.php" 
                   class="flex-1 bg-gray-700 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-600 transition duration-300 shadow-lg hover:shadow-xl text-center"
                   onclick="return confirm('Are you sure you want to cancel this delivery? This action cannot be undone.');">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-6 rounded-lg font-medium hover:from-green-600 hover:to-green-700 transition duration-300 shadow-lg hover:shadow-xl">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Delivery Request
                </button>
                
            </div>
        </form>
        
        <div class="mt-6 text-center text-gray-500 text-sm">
            <p>Need assistance? <a href="#" class="text-green-500 hover:text-green-400 hover:underline">Contact our support team</a></p>
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

         const senderInput = document.getElementById('contact');
    const receiverInput = document.getElementById('dropoff_contact');
    const errorMsg = document.getElementById('contact-error');

    receiverInput.addEventListener('input', () => {
        const senderVal = senderInput.value.trim();
        const receiverVal = receiverInput.value.trim();

        if (receiverVal === senderVal && receiverVal !== '') {
            errorMsg.classList.remove('hidden');
            receiverInput.setCustomValidity("Receiver number must be different from sender number.");
        } else {
            errorMsg.classList.add('hidden');
            receiverInput.setCustomValidity("");
        }
    });
    </script>
</body>
</html>