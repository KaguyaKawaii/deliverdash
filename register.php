<?php
session_start();
include 'connection.php';

global $conn;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $user = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $contact = trim($_POST["contact"]);
    $pass = $_POST["password"];
    $confirm_pass = $_POST["confirm_password"];

    // Validation
    if (empty($name) || empty($user) || empty($email) || empty($contact) || empty($pass)) {
        echo "<script>alert('All fields are required.'); window.history.back();</script>";
        exit;
    }

    if (!preg_match("/^9[0-9]{9}$/", $contact)) {
        echo "<script>alert('Invalid phone number format. Use 9XXXXXXXXX (PH mobile format).'); window.history.back();</script>";
        exit;
    }

    if ($pass !== $confirm_pass) {
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit;
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $user, $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Username or Email already exists.'); window.history.back();</script>";
        exit;
    }

    // Hash password and insert data
    $hashed_password = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO admins (name, username, email, contact, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $user, $email, $contact, $hashed_password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful!'); window.location.href = 'login.php';</script>";
    } else {
        echo "<script>alert('Error: Registration failed.'); window.history.back();</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
</head>
<body class="h-screen bg-gray-900 flex justify-center items-center">
    <main class="w-full h-full flex bg-[#363B40]">
        <!-- Left Side -->
        <div class="hidden md:flex flex-col justify-center items-center w-1/2 text-white p-12">
            <img class="w-[300px] mb-6" src="picture/icon/logo.png" alt="DeliverDash Logo">
            <h1 class="text-4xl font-bold font-montserrat text-center">Welcome to DeliverDash!</h1>
            <p class="text-lg text-center mt-4 opacity-75">Delivering Smiles, One Package at a Time!</p>
        </div>
        <!-- Right Side (Form) -->
        <div class="bg-white w-full md:w-1/2 flex flex-col justify-center items-center p-12 shadow-lg rounded-l-3xl md:rounded-none">
            <form action="" method="POST" class="w-full max-w-[400px] space-y-6">
                <div class="text-center">
                    <h1 class="font-montserrat text-3xl font-bold text-gray-800">Admin Registration</h1>
                </div>
                <!-- Input Fields -->
                <div class="space-y-4">
                    <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="text" name="name" placeholder="Full Name" required>
                    <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="text" name="username" placeholder="Username" required>
                    <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="email" name="email" placeholder="Email Address" required>
                    <div class="relative flex">
                        <div class="bg-gray-100 w-[60px] p-4 rounded-xl shadow-md text-center mr-2">
                            <p class="font-montserrat font-semibold">+63</p>
                        </div>
                        <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="text" name="contact" placeholder="Phone Number" required>
                    </div>
                    <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="password" name="password" placeholder="Password" required>
                    <input class="bg-gray-100 w-full p-4 rounded-xl shadow-md focus:outline-none focus:ring-2 focus:ring-green-400 placeholder-gray-500" type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <!-- Submit Button -->
                <div class="flex flex-col justify-center items-center gap-6 pt-7">
                    <button type="submit" class="bg-green-400 w-full h-[50px] rounded-full text-white font-bold text-lg cursor-pointer hover:bg-green-500 transition duration-200 ease-in-out shadow-lg">Register</button>
                    <div class="text-center font-montserrat font-semibold">
                        <a class="text-blue-600 hover:text-blue-800 transition duration-200" href="login.php">Back to Login</a>
                    </div>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
