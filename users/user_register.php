<?php
include '../connection.php'; // Connect to your MySQL database

$error = ''; // Initialize error variable

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);

    // Validate required fields
    if (empty($name) || empty($email) || empty($contact) || empty($username) || empty($password) || empty($confirm_password) || empty($address)) {
        $error = "All fields are required.";
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }
    // Validate phone number (PH format)
    elseif (!preg_match("/^9[0-9]{9}$/", $contact)) {
        $error = "Invalid phone number format. Use 9XXXXXXXXX (PH mobile format).";
    }
    // Validate password length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    // Validate password match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email or Username already exists.";
        }
        $stmt->close();

        if (empty($error)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $stmt = $conn->prepare("INSERT INTO users (name, email, contact, username, password, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $email, $contact, $username, $hashed_password, $address);

            if ($stmt->execute()) {
                echo "<script>
                    alert('Registration successful!');
                    window.location.href = '../users/user_login.php';
                </script>";
                exit;
            } else {
                $error = "Error: Registration failed. Please try again.";
            }

            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash - User Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <style>
        .gradient-sidebar {
            background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%);
        }
        .form-container {
            transition: all 0.3s ease;
        }
        .input-field {
            transition: all 0.3s ease;
        }
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.3);
        }
        .register-btn {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(74, 222, 128, 0.3), 0 2px 4px -1px rgba(74, 222, 128, 0.1);
        }
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(74, 222, 128, 0.3), 0 4px 6px -2px rgba(74, 222, 128, 0.1);
        }
        .register-btn:active {
            transform: translateY(0);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        .error-message {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body class="flex justify-center items-center min-h-screen bg-gray-50">
    <main class="flex w-full max-w-5xl bg-white rounded-3xl shadow-xl overflow-hidden form-container animate-fade-in">
        <!-- Left Side - Branding -->
        <div class="hidden md:flex flex-col justify-center items-center w-1/2 gradient-sidebar p-12 text-center">
            <img src="../picture/icon/logo.png" alt="DeliverDash Logo" class="w-48 mb-6">
            <h2 class="text-4xl text-white font-bold mb-4">Join DeliverDash</h2>
            <p class="text-gray-300 text-lg mb-8">Fast, reliable deliveries at your fingertips</p>
            <div class="flex space-x-4">
                <div class="bg-green-500 bg-opacity-20 p-3 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="text-left">
                    <h3 class="font-bold text-white">Real-time Tracking</h3>
                    <p class="text-gray-300 text-sm">Follow your delivery every step</p>
                </div>
            </div>
            <div class="flex space-x-4 mt-4">
                <div class="bg-green-500 bg-opacity-20 p-3 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="text-left">
                    <h3 class="font-bold text-white">Fast Delivery</h3>
                    <p class="text-gray-300 text-sm">Get your items in record time</p>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="w-full md:w-1/2 p-8 md:p-12">
            <div class="max-w-md mx-auto">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-extrabold text-gray-800">Create Account</h1>
                    <p class="text-gray-500 mt-2">Join thousands of happy customers</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 error-message">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input id="name" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="text" name="name" placeholder="John Doe" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input id="email" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="email" name="email" placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
    <label for="contact" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
    <input 
        id="contact" 
        class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" 
        type="text" 
        name="contact" 
        placeholder="9XXXXXXXXX" 
        maxlength="10" 
        pattern="^9\d{9}$" 
        title="Enter a 10-digit phone number starting with 9" 
        value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" 
        required 
        oninput="this.value = this.value.replace(/[^0-9]/g, '')">
</div>



                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input id="username" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="text" name="username" placeholder="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
    <input id="password" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="password" name="password" placeholder="••••••••" required>
    <p id="password-error" class="text-xs text-red-500 mt-1 hidden">Password must be at least 8 characters</p>
    <p id="password-valid" class="text-xs text-green-500 mt-1 hidden">✓ Password is valid</p>
</div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input id="confirm_password" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 h-12 w-full px-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" type="password" name="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="address" class="input-field bg-gray-50 text-gray-900 placeholder-gray-400 min-h-[100px] w-full p-4 rounded-lg border border-gray-200 focus:border-green-400 focus:ring-0" name="address" placeholder="Your complete address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <div class="flex items-center">
                        <input id="terms" name="terms" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-green-500 focus:ring-green-400" required>
                        <label for="terms" class="ml-2 block text-sm text-gray-700">
                            I agree to the <a href="#" class="text-green-500 hover:text-green-600">Terms and Conditions</a>
                        </label>
                    </div>

                    <button type="submit" class="register-btn bg-green-500 w-full h-12 rounded-lg text-white font-semibold text-base cursor-pointer hover:bg-green-600">
                        Create Account
                    </button>

                    <div class="text-center text-sm text-gray-600 pt-2">
                        <p>Already have an account? <a class="font-medium text-green-500 hover:text-green-600 transition duration-200" href="../users/user_login.php">Sign in</a></p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
    const passwordInput = document.getElementById('password');
    const passwordError = document.getElementById('password-error');
    const passwordValid = document.getElementById('password-valid');

    passwordInput.addEventListener('input', () => {
        if (passwordInput.value.length > 0 && passwordInput.value.length < 8) {
            passwordError.classList.remove('hidden');
            passwordValid.classList.add('hidden');
            passwordInput.setCustomValidity("Password must be at least 8 characters.");
        } else if (passwordInput.value.length >= 8) {
            passwordError.classList.add('hidden');
            passwordValid.classList.remove('hidden');
            passwordInput.setCustomValidity(""); // Valid input
        } else {
            passwordError.classList.add('hidden');
            passwordValid.classList.add('hidden');
            passwordInput.setCustomValidity(""); // Empty input
        }
    });
</script>

</body>
</html>