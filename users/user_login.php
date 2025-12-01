<?php
session_start(); // Start the session
include '../connection.php'; // Connect to your MySQL database

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Get user from database
        $sql = "SELECT user_id, name, password, status FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Check if account is suspended
            if ($user['status'] !== 'active') {
                $error = "Your account has been suspended.";
            }
            // Verify password
            elseif (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                session_regenerate_id(true); // Prevent session fixation

                // Redirect to user dashboard
                header("Location: ../users/user_dashboard.php");
                exit();
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash - User Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .form-container {
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .form-container:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        .input-field {
            transition: all 0.3s ease;
        }
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.3);
        }
        .login-btn {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(74, 222, 128, 0.3), 0 2px 4px -1px rgba(74, 222, 128, 0.1);
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(74, 222, 128, 0.3), 0 4px 6px -2px rgba(74, 222, 128, 0.1);
        }
        .login-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="flex justify-center items-center h-screen gradient-bg">
    <main class="flex justify-center items-center animate-fade-in">
        <div class="form-container bg-white border border-gray-200 h-[800px] w-[500px] rounded-2xl flex flex-col justify-center items-center p-10 backdrop-blur-sm bg-opacity-90">  
            <form action="" method="POST" class="w-full max-w-xs">
                <div class="flex flex-col gap-6 w-full">
                    <div class="flex justify-center items-center w-full mb-4">
                        <img src="../picture/icon/logo.png" alt="DeliverDash Logo" class="h-20 w-auto">
                    </div>
                    
                    <div class="flex flex-col justify-center items-center gap-2">   
                        <h1 class="font-montserrat text-3xl font-bold text-gray-800 text-center">
                            Welcome Back!
                        </h1>
                        <p class="text-gray-500 text-center text-sm">We're so excited to see you again!</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                            <p class="text-red-700 text-sm"><?= $error; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input id="username" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="text" name="username" placeholder="Enter your username" required>
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input id="password" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="password" name="password" placeholder="••••••••" required>
                        </div>
                        
                    </div>
                   
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-500 focus:ring-green-400">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                        </div>
                        <div class="text-sm">
                            <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="user_forgot_password.php">Forgot password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-btn bg-green-500 w-full h-12 rounded-lg text-white font-semibold text-base cursor-pointer hover:bg-green-600">
                        Login
                    </button>
                    
                    <div class="relative my-4">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Or continue with</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <a href="#" class="w-full inline-flex justify-center py-2 px-4 border border-gray-200 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-200">
                            <img class="h-5 w-5" src="https://www.svgrepo.com/show/355037/google.svg" alt="Google">
                            <span class="ml-2">Google</span>
                        </a>
                        <a href="#" class="w-full inline-flex justify-center py-2 px-4 border border-gray-200 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-200">
                            <img class="h-5 w-5" src="https://www.svgrepo.com/show/448224/facebook.svg" alt="Facebook">
                            <span class="ml-2">Facebook</span>
                        </a>
                    </div>
                    
                    <div class="text-center text-sm text-gray-600">
                        <p>Don't have an account? 
                            <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="user_register.php">Sign up</a>
                        </p>
                    </div>

                    <div class="text-center text-sm text-gray-600">
                        <p>Back to
                            <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="../index.html">Home Page</a>
                        </p>
                    </div>
                </div>
            </form> 
        </div>
    </main>
</body>
</html>