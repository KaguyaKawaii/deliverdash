<?php
session_start();
include '../../connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];  // Get the user_id from session
$username = $_SESSION['user_name'];

// Ensure delivery_id is present
if (!isset($_GET['delivery_id'])) {
    header("Location: user_delivery.php");
    exit();
}

$delivery_id = $_GET['delivery_id'];

// Set initial amount to 0
$amount = 0;

// Box size prices
$box_prices = [
    "small" => 50.00,
    "medium" => 100.00,
    "large" => 150.00
];
// Handle cancel action
if (isset($_GET['cancel'])) {
    $sql = "DELETE FROM deliveries WHERE delivery_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $delivery_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: ../user_dashboard.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $delivery_option = $_POST['delivery_option'];
    $product_name = $_POST['product_name'];
    $weight = $_POST['weight'];
    $box_size = $_POST['box_size'];
    $category = $_POST['category'];

    // Set amount based on the selected box size
    if (isset($box_prices[$box_size])) {
        $amount = $box_prices[$box_size];
    }

    if (!empty($delivery_option) && !empty($product_name) && !empty($weight) && !empty($box_size) && !empty($category)) {
        // Prepare the query to update the deliveries table
        $sql = "UPDATE deliveries SET delivery_option = ?, product_name = ?, weight = ?, box_size = ?, category = ?, amount = ? WHERE delivery_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssdsdsi", $delivery_option, $product_name, $weight, $box_size, $category, $amount, $delivery_id);
            if ($stmt->execute()) {
                // Redirect to payment processing page
                header("Location: payment_processing.php?delivery_id=" . $delivery_id . "&amount=" . $amount);
                exit();
            } else {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
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
    <title>Delivery Details | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
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
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.1);
        }
        .input-group {
            position: relative;
        }
        .input-icon {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }
        .input-field {
            padding-left: 40px !important;
        
        }
        input, textarea, select {
            background-color: #1f1f1f !important;
            border-color: #d1d5db !important;
            color: #d1d5db !important;
            transition: all 0.2s ease;
        }
        input:focus, select:focus {
            border-color: #2E7D32 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

      
        .input-error {
            border-color: #ef4444 !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: none;
        }
        .nav-link:hover {
            background-color: #1f1f1f;
        }
        .nav-link.active {
            background-color: #2E7D32;
        }
        .radio-card {
            border: 1px solid #333;
            transition: all 0.2s ease;
        }
        .radio-card:hover {
            border-color: #2E7D32;
        }
        .radio-card.selected {
            border-color: #4CAF50;
            background-color: #272727;
        }
        .box-size-card {
            border: 1px solid #333;
            transition: all 0.2s ease;
            cursor: pointer;
            
        }
        .box-size-card:hover {
            transform: translateY(-2px);
            border-color: #2E7D32;
        }
        .box-size-card.selected {
            border-color: #4CAF50;
            background-color: #272727;
            transform: translateY(-2px);
            
        }
        .box-size-card.selected span {
            color: #81C784; /* or use Tailwind's green-400 hex */
            font-weight: bold;
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
            <h1 class="text-3xl font-bold text-gray-100 mb-2">Delivery Details</h1>
            <p class="text-gray-400">Complete your package information</p>
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <form action="" method="POST" class="space-y-6 bg-dark-800 rounded-xl shadow-xl p-6 border border-gray-800" id="deliveryForm">
            <!-- Delivery Option -->
            <div class="form-section bg-dark-700 p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-primary-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-truck text-primary-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Delivery Information</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Delivery Option Radio Cards -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Delivery Option</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="radio-card rounded-lg p-3 flex items-center justify-center" onclick="selectRadio('express')">
                                <input type="radio" name="delivery_option" value="express" class="hidden" checked>
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-bolt text-yellow-500 mb-1"></i>
                                    <span class="text-sm font-medium">Express</span>
                                    <span class="text-xs text-gray-400">Faster delivery</span>
                                </div>
                            </label>
                            <label class="radio-card rounded-lg p-3 flex items-center justify-center" onclick="selectRadio('normal')">
                                <input type="radio" name="delivery_option" value="normal" class="hidden">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-truck text-blue-500 mb-1"></i>
                                    <span class="text-sm font-medium">Normal</span>
                                    <span class="text-xs text-gray-400">Standard delivery</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Category Select -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-400 mb-1">Category</label>
                        <div class="input-group">
                            <i class="fas fa-tags input-icon"></i>
                            <select name="category" id="category" class="w-full px-4 py-2 text-gray-500 rounded-lg focus:ring-2 focus:ring-[#4CAF50] focus:border-[#4CAF50] bg-white text-gray-500 input-field">
                                <option value="documents">Documents</option>
                                <option value="electronics">Electronics</option>
                                <option value="foods">Foods</option>
                                <option value="product">Product</option>
                                <option value="glass">Glass</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Information -->
            <div class="form-section bg-dark-700 p-6 rounded-lg">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-500/20 p-2 rounded-full mr-3">
                        <i class="fas fa-box text-purple-500"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">Product Information</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Product Name -->
                    <div>
                        <label for="product_name" class="block text-sm font-medium text-gray-400 mb-1">Product Name*</label>
                        <div class="input-group">
                            <i class="fas fa-box-open input-icon"></i>
                            <input type="text" name="product_name" id="product_name" required
                                   class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-600 bg-white text-gray-900 input-field"
                                   placeholder="Enter product name"
                                   oninput="validateProductName()">
                            <div id="product_name_error" class="error-message">Product name is required</div>
                        </div>
                    </div>
                    
                    <!-- Weight Input -->
                    <div>
                        <label for="weight" class="block text-sm font-medium text-gray-400 mb-1">Weight (kg)*</label>
                        <div class="input-group">
                            <i class="fas fa-weight-hanging input-icon"></i>
                            <input type="number" step="0.01" name="weight" id="weight" required
                                   class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white text-gray-900 input-field"
                                   placeholder="0.00"
                                   min="0.01"
                                   oninput="validateWeight()">
                            <div id="weight_error" class="error-message">Please enter a valid weight (min 0.01kg)</div>
                        </div>
                    </div>
                    
                    <!-- Box Size Cards -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Box Size*</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <label class="box-size-card rounded-lg p-4 flex flex-col items-center" onclick="selectBoxSize('small')">
                                <input type="radio" name="box_size" value="small" class="hidden" checked>
                                <div class="w-24 h-24 bg-blue-100 rounded-lg mb-2 flex items-center justify-center">
                                    <i class="fas fa-box text-purple-500 text-3xl"></i>
                                </div>
                                <p class="text-sm font-medium">Small</p>
                                <p class="text-xs text-gray-400">25 x 15 x 10 cm</p>
                                <span class="text-s text-gray-300 font-medium">₱50.00</span>
                            </label>
                            <label class="box-size-card rounded-lg p-4 flex flex-col items-center" onclick="selectBoxSize('medium')">
                                <input type="radio" name="box_size" value="medium" class="hidden">
                                <div class="w-24 h-24 bg-purple-100 rounded-lg mb-2 flex items-center justify-center">
                                    <i class="fas fa-box text-purple-500 text-3xl"></i>
                                </div>
                                <p class="text-sm font-medium">Medium</p>
                                <p class="text-xs text-gray-400">35 x 25 x 20 cm</p>
                                <span class="text-s text-gray-300 font-medium">₱100.00</span>
                            </label>
                            <label class="box-size-card rounded-lg p-4 flex flex-col items-center" onclick="selectBoxSize('large')">
                                <input type="radio" name="box_size" value="large" class="hidden">
                                <div class="w-24 h-24 bg-green-100 rounded-lg mb-2 flex items-center justify-center">
                                    <i class="fas fa-box text-green-500 text-4xl"></i>
                                </div>
                                <p class="text-sm font-medium">Large</p>
                                <p class="text-xs text-gray-400">45 x 35 x 30 cm</p>
                                <span class="text-s text-gray-300 font-medium">₱150.00</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Amount Display -->
            <div class="flex justify-between items-center bg-dark-700 p-4 rounded-lg border border-gray-800">
                <div class="flex items-center">
                    <i class="fas fa-receipt text-primary-500 mr-2"></i>
                    <span class="text-gray-300">Estimated Amount:</span>
                </div>
                <div id="amount" class="text-2xl font-bold text-green-500">₱50.00</div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex gap-4">
                <a href="?cancel=1&delivery_id=<?php echo $delivery_id; ?>" 
                   class="w-1/3 bg-gray-700 text-white py-3 px-6 rounded-lg font-medium hover:bg-gray-600 transition duration-300 shadow hover:shadow-lg flex items-center justify-center"
                   onclick="return confirm('Are you sure you want to cancel this delivery? All information will be lost.');">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" id="submitBtn"
                        class="w-2/3 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-6 rounded-lg font-medium hover:from-green-600 hover:to-green-700 transition duration-300 shadow-lg hover:shadow-xl flex items-center justify-center">
                    <i class="fas fa-credit-card mr-2"></i> Proceed to Payment
                </button>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script>
        // Box size prices
        const boxPrices = {
            "small": 50.00,
            "medium": 100.00,
            "large": 150.00
        };
        
        // Update amount display
        function updateAmount() {
            const selectedBoxSize = document.querySelector('input[name="box_size"]:checked').value;
            const amount = boxPrices[selectedBoxSize];
            document.getElementById("amount").innerText = "₱" + amount.toFixed(2);
        }
        
        // Select box size
        function selectBoxSize(size) {
            // Remove selected class from all cards
            document.querySelectorAll('.box-size-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update the radio button
            document.querySelector(`input[name="box_size"][value="${size}"]`).checked = true;
            
            // Update amount display
            updateAmount();
        }
        
        // Select delivery option
        function selectRadio(option) {
            // Remove selected class from all cards
            document.querySelectorAll('.radio-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update the radio button
            document.querySelector(`input[name="delivery_option"][value="${option}"]`).checked = true;
        }
        
        // Initialize selected states
        document.addEventListener('DOMContentLoaded', function() {
            // Set first delivery option as selected
            document.querySelector('.radio-card').classList.add('selected');
            
            // Set first box size as selected
            document.querySelector('.box-size-card').classList.add('selected');
        });
        
        // Form validation
        function validateProductName() {
            const input = document.getElementById('product_name');
            const error = document.getElementById('product_name_error');
            
            if (input.value.trim() === '') {
                input.classList.add('input-error');
                error.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                error.style.display = 'none';
                return true;
            }
        }
        
        function validateWeight() {
            const input = document.getElementById('weight');
            const error = document.getElementById('weight_error');
            
            if (input.value === '' || parseFloat(input.value) < 0.01) {
                input.classList.add('input-error');
                error.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                error.style.display = 'none';
                return true;
            }
        }
        
        // Form submission validation
        document.getElementById('deliveryForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            if (!validateProductName()) isValid = false;
            if (!validateWeight()) isValid = false;
            
            if (!isValid) {
                e.preventDefault();
                
                // Scroll to first error
                const firstError = document.querySelector('.input-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    </script>
</body>
</html>