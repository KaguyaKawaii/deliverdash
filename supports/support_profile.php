<?php
session_start();

// Check if support staff is logged in
if (!isset($_SESSION['support_logged_in']) || $_SESSION['support_logged_in'] !== true) {
    header("Location: support_login.php");
    exit();
}

include '../connection.php';

$success_message = '';
$error_message = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update name and email
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        if (empty($name) || empty($email)) {
            $error_message = "Name and email are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
        } else {
            $stmt = $conn->prepare("UPDATE support SET name = ?, email = ? WHERE support_id = ?");
            $stmt->bind_param("ssi", $name, $email, $_SESSION['support_id']);
            
            if ($stmt->execute()) {
                $_SESSION['support_name'] = $name;
                $_SESSION['support_email'] = $email;
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_password'])) {
        // Update password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Password must be at least 8 characters";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM support WHERE support_id = ?");
            $stmt->bind_param("i", $_SESSION['support_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $support = $result->fetch_assoc();
                if (password_verify($current_password, $support['password'])) {
                    // Update password
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE support SET password = ? WHERE support_id = ?");
                    $updateStmt->bind_param("si", $new_hashed_password, $_SESSION['support_id']);
                    
                    if ($updateStmt->execute()) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Error updating password";
                    }
                    $updateStmt->close();
                } else {
                    $error_message = "Current password is incorrect";
                }
            }
            $stmt->close();
        }
    }
}

// Get current support info
$stmt = $conn->prepare("SELECT name, email FROM support WHERE support_id = ?");
$stmt->bind_param("i", $_SESSION['support_id']);
$stmt->execute();
$result = $stmt->get_result();
$support = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | DeliverDash Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-900 text-white font-montserrat">
    <!-- Navigation Bar -->
    <nav class="bg-gray-800 shadow-md py-3 px-6 flex justify-between items-center">
        <h1 class="text-xl font-semibold">DeliverDash Support</h1>
        <div class="flex gap-6">
            <span class="text-gray-300">Welcome, <?= htmlspecialchars($_SESSION['support_name']) ?></span>
            <a href="support_logout.php" class="hover:text-red-500 transition-colors">Logout</a>
        </div>
    </nav>

    <!-- Content Area -->
    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside class="bg-gray-800 w-64 p-6 shadow-lg flex flex-col h-screen sticky top-0">
            <h2 class="text-lg font-semibold text-center mb-4">Support Menu</h2>
            <hr class="border-gray-700">
            <ul class="mt-4 space-y-2">
                <li>
                    <a href="support_dashboard.php" class="block hover:bg-gray-700 hover:text-white p-3 rounded-md transition-all duration-300">
                        <i class="fas fa-tachometer-alt pr-2"></i> Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="support_profile.php" class="block hover:bg-gray-700 hover:text-white p-3 rounded-md transition-all duration-300 sidebar-link active">
                        <i class="fas fa-user-cog pr-2"></i> Profile
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="bg-gray-800 rounded-lg p-6 shadow-lg">
                <h2 class="text-xl font-semibold mb-6">Profile Settings</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="bg-green-600 text-white p-3 rounded mb-4">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-600 text-white p-3 rounded mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Profile Information -->
                    <div class="bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-lg font-medium mb-4">Profile Information</h3>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="mb-4">
                                <label class="block mb-2 text-sm">Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($support['name']) ?>" required readonly
                                       class="w-full p-3 bg-gray-600 rounded focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block mb-2 text-sm">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($support['email']) ?>" required readonly
                                       class="w-full p-3 bg-gray-600 rounded focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                        </form>
                    </div>
                    
                    
                </div>
            </div>
        </main>
    </div>
</body>
</html>