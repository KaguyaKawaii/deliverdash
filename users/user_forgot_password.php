<?php
session_start();
include '../connection.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Generate OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 1) {
    $email = trim($_POST['email']);
    
    if (!empty($email)) {
        // Check if email exists
        $sql = "SELECT user_id, name FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate 6-digit OTP
            $otp = rand(100000, 999999);
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            $_SESSION['reset_user_id'] = $user['user_id'];
            
            // Store OTP in a variable to be used in JavaScript
            $success = "OTP generated successfully";
            header("Location: user_forgot_password.php?step=2&otp=".$otp);
            exit();
        } else {
            $error = "Email not found in our system.";
        }
        $stmt->close();
    } else {
        $error = "Please enter your email address.";
    }
}

// Verify OTP
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 2) {
    $user_otp = trim($_POST['otp']);
    
    if (!empty($user_otp)) {
        // Check if OTP matches and isn't expired
        if (isset($_SESSION['reset_otp']) && 
            $user_otp == $_SESSION['reset_otp'] && 
            time() < strtotime($_SESSION['reset_otp_expiry'])) {
            
            // OTP verified, proceed to password reset
            header("Location: user_forgot_password.php?step=3");
            exit();
        } else {
            $error = "Invalid or expired OTP. Please try again.";
        }
    } else {
        $error = "Please enter the OTP you received.";
    }
}

// Reset password
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step === 3) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            // Validate password strength
            if (strlen($new_password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } else {
                // Update password in database
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
                
                if ($stmt->execute()) {
                    // Clear reset session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_otp']);
                    unset($_SESSION['reset_otp_expiry']);
                    unset($_SESSION['reset_user_id']);
                    
                    $success = "Password updated successfully! You can now login with your new password.";
                    header("Location: user_forgot_password.php?success=" . urlencode($success));
                    exit();
                } else {
                    $error = "Error updating password. Please try again.";
                }
                $stmt->close();
            }
        } else {
            $error = "Passwords do not match.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Display success message from redirect
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
    $step = 1; // Reset to step 1 after success
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash - Forgot Password</title>
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
        .action-btn {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(74, 222, 128, 0.3), 0 2px 4px -1px rgba(74, 222, 128, 0.1);
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(74, 222, 128, 0.3), 0 4px 6px -2px rgba(74, 222, 128, 0.1);
        }
        .action-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="flex justify-center items-center h-screen gradient-bg">
    <main class="flex justify-center items-center animate-fade-in">
        <div class="form-container bg-white border border-gray-200 h-auto w-[500px] rounded-2xl flex flex-col justify-center items-center p-10 backdrop-blur-sm bg-opacity-90">  
            <div class="w-full max-w-xs">
                <div class="flex justify-center items-center w-full mb-4">
                    <img src="../picture/icon/logo.png" alt="DeliverDash Logo" class="h-20 w-auto">
                </div>
                
                <div class="flex flex-col justify-center items-center gap-2 mb-6">   
                    <h1 class="font-montserrat text-3xl font-bold text-gray-800 text-center">
                        <?php 
                        if ($step === 1) echo "Forgot Password";
                        elseif ($step === 2) echo "Verify OTP";
                        else echo "Reset Password";
                        ?>
                    </h1>
                    <p class="text-gray-500 text-center text-sm">
                        <?php 
                        if ($step === 1) echo "Enter your email to receive a verification code";
                        elseif ($step === 2) echo "Enter the 6-digit code from the alert";
                        else echo "Create a new password for your account";
                        ?>
                    </p>
                </div>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-4">
                        <p class="text-red-700 text-sm"><?= htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg mb-4">
                        <p class="text-green-700 text-sm"><?= htmlspecialchars($success); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['otp'])): ?>
                    <script>
                        alert("Your verification code is: <?php echo $_GET['otp']; ?>\n\nThis code is valid for 10 minutes.");
                    </script>
                <?php endif; ?>
                
                <?php if ($step === 1): ?>
                <form action="user_forgot_password.php?step=1" method="POST" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input id="email" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" 
                               type="email" name="email" placeholder="your@email.com" required>
                    </div>
                    
                    <button type="submit" class="action-btn bg-green-500 w-full h-12 rounded-lg text-white font-semibold text-base cursor-pointer hover:bg-green-600">
                        Get Verification Code
                    </button>
                    
                    <div class="text-center text-sm text-gray-600 mt-4">
                        <p>Remember your password? 
                            <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="user_login.php">Login</a>
                        </p>
                    </div>
                </form>
                
                <?php elseif ($step === 2): ?>
                <form action="user_forgot_password.php?step=2" method="POST" class="space-y-4">
                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">6-digit Verification Code</label>
                        <input id="otp" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" 
                               type="text" name="otp" placeholder="123456" maxlength="6" pattern="\d{6}" required>
                        <p class="text-xs text-gray-500 mt-1">Check the alert for your verification code.</p>
                    </div>
                    
                    <button type="submit" class="action-btn bg-green-500 w-full h-12 rounded-lg text-white font-semibold text-base cursor-pointer hover:bg-green-600">
                        Verify Code
                    </button>
                    
                    <div class="text-center text-sm text-gray-600 mt-4">
                        <p>Need a new code? 
                            <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="user_forgot_password.php?step=1">Request again</a>
                        </p>
                    </div>
                </form>
                
                <?php else: ?>
                <form action="user_forgot_password.php?step=3" method="POST" class="space-y-4">
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input id="new_password" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" 
                               type="password" name="new_password" placeholder="••••••••" required>
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input id="confirm_password" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" 
                               type="password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="action-btn bg-green-500 w-full h-12 rounded-lg text-white font-semibold text-base cursor-pointer hover:bg-green-600">
                        Reset Password
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>