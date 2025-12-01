<?php
session_start();
include '../connection.php';

header("Content-Type: text/html; charset=UTF-8");

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password";
    } else {
        // Check if support account exists
        $stmt = $conn->prepare("SELECT support_id, name, email, password FROM support WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $support = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $support['password'])) {
                // Login successful - set session variables
                $_SESSION['support_id'] = $support['support_id'];
                $_SESSION['support_name'] = $support['name'];
                $_SESSION['support_email'] = $support['email'];
                $_SESSION['support_logged_in'] = true;
                
                // Redirect to support dashboard
                header("Location: support_dashboard.php");
                exit();
            } else {
                $error_message = "Invalid email or password";
            }
        } else {
            $error_message = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Login | DeliverDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .font-montserrat {
            font-family: 'Montserrat', sans-serif;
        }
        .login-container {
            position: relative;
            overflow: hidden;
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, rgba(0, 0, 0, 0) 70%);
            animation: rotate 15s linear infinite;
            z-index: 0;
        }
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .form-content {
            position: relative;
            z-index: 1;
        }
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-montserrat flex items-center justify-center min-h-screen p-4">
    <div class="login-container bg-gray-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="form-content p-8">
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="bg-green-500 p-3 rounded-full">
                        <i class="fas fa-headset text-2xl"></i>
                    </div>
                </div>
                <h1 class="text-3xl font-bold tracking-tight">DeliverDash</h1>
                <h1 class="text-3xl font-bold tracking-tight">Support Portal</h1>
                <p class="text-gray-400 mt-2 text-sm">Enter your credentials to access the dashboard</p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-600/90 text-white p-3 rounded mb-4 flex items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-5">
                <div class="space-y-1">
                    <label for="email" class="block text-sm font-medium">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-500"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                               class="input-field w-full pl-10 pr-3 py-3 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-200"
                               placeholder="support@example.com">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label for="password" class="block text-sm font-medium">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-500"></i>
                        </div>
                        <input type="password" id="password" name="password" required 
                               class="input-field w-full pl-10 pr-3 py-3 bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-200"
                               placeholder="••••••••">
                    </div>
                </div>
                
                <div class="pt-2">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 py-3 px-4 rounded-lg font-medium transition-colors duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login to Dashboard</span>
                    </button>
                </div>
            </form>
            
            
        </div>
    </div>
</body>
</html>