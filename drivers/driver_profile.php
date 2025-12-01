<?php
session_start();
include '../connection.php';

if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$name = $_SESSION['name'] ?? 'Unknown Driver';


// Fetch driver data
$sql = "SELECT * FROM drivers WHERE driver_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();
$stmt->close();

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $contact = trim($_POST['contact']);
        $address = trim($_POST['address']);
        $vehicle = trim($_POST['vehicle']);
        $license_no = trim($_POST['license_no']);

        if (!empty($name) && !empty($email) && !empty($contact) && !empty($license_no)) {
            $profile_photo = $driver['photo_profile']; // Keep existing photo by default

            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/driver_photos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Validate file type and size
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (in_array($file_ext, $allowed_types)) {
                    if ($_FILES['profile_photo']['size'] <= $max_size) {
                        // Generate unique filename
                        $file_name = 'driver_' . $driver_id . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $file_path)) {
                            // Delete old photo if it exists
                            if (!empty($driver['photo_profile']) && file_exists($driver['photo_profile'])) {
                                unlink($driver['photo_profile']);
                            }
                            $profile_photo = $file_path;
                        } else {
                            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error uploading profile photo.</div>";
                        }
                    } else {
                        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Profile photo must be less than 2MB.</div>";
                    }
                } else {
                    $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Only JPG, PNG, and GIF images are allowed.</div>";
                }
            }
            
            $sql = "UPDATE drivers SET name = ?, email = ?, contact = ?, address = ?, vehicle = ?, license_no = ?, photo_profile = ? WHERE driver_id = ?";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sssssssi", $name, $email, $contact, $address, $vehicle, $license_no, $profile_photo, $driver_id);
                if ($stmt->execute()) {
                    $_SESSION['name'] = $name;
                    $message = "<div class='p-4 mb-6 text-sm text-green-300 bg-green-900/30 rounded-lg border border-green-800'>Profile updated successfully!</div>";
                    // Refresh driver data
                    $driver['name'] = $name;
                    $driver['email'] = $email;
                    $driver['contact'] = $contact;
                    $driver['address'] = $address;
                    $driver['vehicle'] = $vehicle;
                    $driver['license_no'] = $license_no;
                    $driver['photo_profile'] = $profile_photo;
                } else {
                    $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: " . htmlspecialchars($stmt->error) . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error preparing statement: " . htmlspecialchars($conn->error) . "</div>";
            }
        } else {
            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Name, email, contact and license number are required fields.</div>";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if (strlen($new_password) < 8) {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Password must be at least 8 characters long.</div>";
            } elseif ($new_password !== $confirm_password) {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>New passwords do not match.</div>";
            } else {
                // Verify current password
                $sql = "SELECT password FROM drivers WHERE driver_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $driver_data = $result->fetch_assoc();
                $stmt->close();
                
                if (password_verify($current_password, $driver_data['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE drivers SET password = ? WHERE driver_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $driver_id);
                    
                    if ($stmt->execute()) {
                        $message = "<div class='p-4 mb-6 text-sm text-green-300 bg-green-900/30 rounded-lg border border-green-800'>Password updated successfully!</div>";
                    } else {
                        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error updating password: " . htmlspecialchars($stmt->error) . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Current password is incorrect.</div>";
                }
            }
        } else {
            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>All password fields are required.</div>";
        }
    }
    
    // Handle username change
    if (isset($_POST['change_username'])) {
        $new_username = trim($_POST['new_username']);
        $current_password = trim($_POST['username_password']);
        
        if (!empty($new_username) && !empty($current_password)) {
            if (strlen($new_username) < 4) {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Username must be at least 4 characters long.</div>";
            } else {
                // Verify current password first
                $sql = "SELECT password FROM drivers WHERE driver_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $driver_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $driver_data = $result->fetch_assoc();
                $stmt->close();
                
                if (password_verify($current_password, $driver_data['password'])) {
                    // Check if username already exists
                    $sql = "SELECT driver_id FROM drivers WHERE username = ? AND driver_id != ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $new_username, $driver_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        // Update username
                        $sql = "UPDATE drivers SET username = ? WHERE driver_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $new_username, $driver_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['name'] = $new_username;
                            $driver['username'] = $new_username;
                            $message = "<div class='p-4 mb-6 text-sm text-green-300 bg-green-900/30 rounded-lg border border-green-800'>Username updated successfully!</div>";
                        } else {
                            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error updating username: " . htmlspecialchars($stmt->error) . "</div>";
                        }
                        $stmt->close();
                    } else {
                        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Username already exists. Please choose a different one.</div>";
                    }
                } else {
                    $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Current password is incorrect.</div>";
                }
            }
        } else {
            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Both fields are required.</div>";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash | Driver Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            700: '#1e1e2d',
                            800: '#1a1a1a',
                            900: '#121212',
                        },
                        primary: {
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                        },
                        success: {
                            500: '#10b981',
                            600: '#059669',
                        },
                        warning: {
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    },
                    boxShadow: {
                        'glow': '0 0 15px rgba(99, 102, 241, 0.3)',
                        'glow-sm': '0 0 8px rgba(99, 102, 241, 0.3)'
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
            background-color: #0f0f0f;
            color: #e5e7eb;
        }
        .profile-section {
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: linear-gradient(145deg, #1a1a1a, #151515);
        }
        .profile-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -10px rgba(0, 0, 0, 0.2);
            border-color: rgba(79, 70, 229, 0.3);
        }
        input, textarea, select {
            background-color: rgba(30, 30, 45, 0.7) !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
            color: #f3f4f6 !important;
            transition: all 0.2s ease;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
        }
        input:read-only, textarea:read-only {
            background-color: rgba(30, 30, 45, 0.4) !important;
            color: #9ca3af !important;
        }
        .nav-link {
            transition: all 0.2s ease;
            position: relative;
        }
        .nav-link:hover {
            background-color: rgba(79, 70, 229, 0.1);
            color: #818cf8;
        }
        .nav-link.active {
            background-color: rgba(79, 70, 229, 0.2);
            color: #818cf8;
        }
        .nav-link.active:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background-color: #6366f1;
            border-radius: 0 3px 3px 0;
        }
        .avatar:hover {
            transform: scale(1.05);
            border-color: #6366f1;
            box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        }
        .avatar-upload {
            background-color: rgba(79, 70, 229, 0.8);
            transition: all 0.2s ease;
        }
        .avatar-upload:hover {
            background-color: rgba(79, 70, 229, 1);
            transform: scale(1.1);
        }
        .toggle-bg:after {
            content: '';
            position: absolute;
            top: 0.125rem;
            left: 0.125rem;
            background: white;
            border-color: #333;
            border-radius: 50%;
            height: 1.25rem;
            width: 1.25rem;
            transition: all 0.2s ease;
        }
        input:checked + .toggle-bg:after {
            transform: translateX(100%);
            border-color: white;
        }
        input:checked + .toggle-bg {
            background-color: #6366f1;
            border-color: #6366f1;
        }
        .modal {
            transition: opacity 0.2s ease, visibility 0.2s ease;
            backdrop-filter: blur(5px);
        }
        .modal-content {
            transform: translateY(-10px);
            opacity: 0;
            transition: transform 0.2s ease, opacity 0.2s ease;
            background: linear-gradient(145deg, #1e1e2d, #1a1a2a);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        .modal.active .modal-content {
            transform: translateY(0);
            opacity: 1;
        }
        #loadingSpinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-primary {
            background-color: rgba(79, 70, 229, 0.2);
            color: #818cf8;
        }
        .badge-success {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            margin: 1rem 0;
        }
    </style>
</head>
<body class="min-h-screen bg-dark-900">
    <!-- Loading Spinner -->
    <div id="loadingSpinner" style="display: none;">
        <div class="spinner"></div>
    </div>

    <!-- Navigation -->
    <nav class="bg-dark-800 border-b border-gray-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Left side: Logo -->
                <div class="flex items-center space-x-3">
                    <a href="driver_dashboard.php" class="flex items-center space-x-3 text-white hover:text-red-400 transition-colors">
                        <img class="w-10 h-10 object-contain" src="../picture/icon/logo.png" alt="Logo">
                        <h1 class="text-xl font-bold">DeliverDash</h1>
                    </a>
                </div>

                <!-- Right side: Profile + Logout -->
                <div class="hidden md:flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <?php if (!empty($driver['photo_profile'])): ?>
                            <img src="<?php echo htmlspecialchars($driver['photo_profile']); ?>" class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-700/50" alt="Profile">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-primary-500/20 flex items-center justify-center text-white ring-1 ring-gray-700/50">
                                <?php echo strtoupper(substr($driver['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Logout -->
                    <form action="driver_logout.php" method="POST">
                        <button type="submit" 
                                class="flex items-center px-4 py-2 text-sm font-medium text-red-500 hover:text-red-400 transition-colors duration-200"
                                aria-label="Logout">
                            <i class="fas fa-sign-out-alt mr-2" aria-hidden="true"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full md:w-72 flex-shrink-0">
                <div class="bg-gradient-to-br from-dark-800 to-dark-900 rounded-xl shadow-xl border border-gray-800/50 overflow-hidden">
                    <div class="p-6 flex flex-col items-center">
                        <div class="avatar relative mb-4 group">
                            <?php if (!empty($driver['photo_profile'])): ?>
                                <img src="<?php echo htmlspecialchars($driver['photo_profile']); ?>" 
                                     class="h-28 w-28 rounded-full object-cover border-2 border-primary-500/30 group-hover:border-primary-500 transition-all" id="profileImage">
                            <?php else: ?>
                                <div class="h-28 w-28 rounded-full bg-primary-500/10 flex items-center justify-center text-4xl font-bold text-primary-500 border-2 border-primary-500/30 group-hover:border-primary-500 transition-all" id="profileInitials">
                                    <?php echo strtoupper(substr($driver['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <label for="profile_photo" class="avatar-upload absolute -bottom-1 -right-1 rounded-full p-2 border-2 border-dark-800 hover:shadow-glow-sm cursor-pointer">
                                <i class="fas fa-camera text-sm text-white"></i>
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                        <h2 class="text-xl font-semibold text-center text-gray-100"><?php echo htmlspecialchars($driver['name']); ?></h2>
                        <p class="text-gray-400 text-sm mt-1 flex items-center">
                            <i class="fas fa-calendar-alt mr-1.5 text-gray-500"></i>
                            Driver since <?php echo date('M Y', strtotime($driver['created_at'])); ?>
                        </p>
                        <div class="mt-3">
                            <span class="badge <?php echo $driver['status'] === 'available' ? 'badge-success' : ($driver['status'] === 'on_delivery' ? 'badge-warning' : 'badge-primary'); ?>">
                                <i class="fas fa-<?php echo $driver['status'] === 'available' ? 'check-circle' : ($driver['status'] === 'on_delivery' ? 'truck' : 'times-circle'); ?> mr-1.5 text-xs"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $driver['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-800/50 px-2 py-3">
                        <a href="#" class="nav-link active block px-6 py-3 text-sm font-medium rounded-lg mx-2">
                            <i class="fas fa-user-circle mr-3 w-5 text-center"></i> Profile
                        </a>
                        <a href="driver_settings.php" class="nav-link block px-6 py-3 text-sm font-medium rounded-lg mx-2">
                            <i class="fas fa-cog mr-3 w-5 text-center"></i> Settings
                        </a>
                        <form action="driver_logout.php" method="POST" class="inline">
                            <button type="submit" 
                                    class="nav-link block w-full px-6 py-3 text-left text-sm font-medium text-red-400 hover:text-red-300 transition-colors duration-200 rounded-lg mx-2"
                                    aria-label="Logout">
                                <i class="fas fa-sign-out-alt mr-3 w-5 text-center" aria-hidden="true"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Profile Content -->
            <div class="flex-1">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-100 mb-1">My Profile</h1>
                            <p class="text-gray-400 text-sm md:text-base">Manage your personal information and account settings</p>
                        </div>
                        <div class="hidden md:block">
                            <a href="driver_dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-700 rounded-lg text-sm font-medium text-gray-300 hover:bg-dark-700 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="divider"></div>
                </div>

                <div id="messageContainer">
                    <?php if (!empty($message)): ?>
                        <?php echo $message; ?>
                    <?php endif; ?>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6" id="profileForm">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Personal Information -->
                    <div class="profile-section rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="bg-primary-500/20 p-2 rounded-lg mr-3">
                                <i class="fas fa-user text-primary-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-100">Personal Information</h3>
                                <p class="text-xs text-red-500">Please contact the administrator to update your profile.</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-400 mb-1">Full Name*</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-500"></i>
                                    </div>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($driver['name']); ?>" required disabled
                                           class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Enter your full name">
                                </div>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-400 mb-1">Email Address*</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-500"></i>
                                    </div>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($driver['email']); ?>" required disabled
                                           class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Enter your email">
                                </div>
                            </div>
                            
                            <div>
                                <label for="contact" class="block text-sm font-medium text-gray-400 mb-1">Phone Number*</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-phone-alt text-gray-500"></i>
                                    </div>
                                    <input type="text" id="contact" name="contact" maxlength="10" disabled
                                           pattern="^9\d{9}$" title="Enter a 10-digit phone number starting with 9"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                           value="<?php echo htmlspecialchars($driver['contact']); ?>" 
                                           required
                                           class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Enter your phone number">
                                </div>
                            </div>
                            
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-at text-gray-500"></i>
                                    </div>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($driver['username']); ?>" readonly disabled
                                           class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Username">
                                </div>
                            </div>
                            
                            <div>
                                <label for="vehicle" class="block text-sm font-medium text-gray-400 mb-1">Vehicle Type*</label>
                                <select id="vehicle" name="vehicle" required disabled
                                        class="w-full pl-3 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-dark-700 border-gray-800">
                                    <option value="motorcycle" <?php echo $driver['vehicle'] === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                    <option value="truck" <?php echo $driver['vehicle'] === 'truck' ? 'selected' : ''; ?>>Truck</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="license_no" class="block text-sm font-medium text-gray-400 mb-1">License Number*</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-500"></i>
                                    </div>
                                    <input type="text" id="license_no" name="license_no" value="<?php echo htmlspecialchars($driver['license_no']); ?>" required disabled
                                           class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                           placeholder="Enter your license number">
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-gray-400 mb-1">Address</label>
                                <div class="relative">
                                    <div class="absolute top-3 left-3">
                                        <i class="fas fa-map-marker-alt text-gray-500"></i>
                                    </div>
                                    <textarea id="address" name="address" rows="3" disabled
                                              class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                              placeholder="Enter your full address"><?php echo htmlspecialchars($driver['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Security -->
                    <div class="profile-section rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="bg-yellow-500/20 p-2 rounded-lg mr-3">
                                <i class="fas fa-shield-alt text-yellow-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-100">Account Security</h3>
                                <p class="text-xs text-gray-500">Manage your account security settings</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Username</label>
                                <div class="flex items-center justify-between bg-dark-700/50 px-4 py-3 rounded-lg border border-gray-800/50">
                                    <div class="flex items-center">
                                        <i class="fas fa-at text-gray-500 mr-3"></i>
                                        <span class="text-gray-300"><?php echo htmlspecialchars($driver['username']); ?></span>
                                    </div>
                                    <button type="button" onclick="openModal('username')" class="text-sm text-primary-500 hover:text-primary-400 hover:underline flex items-center">
                                        <i class="fas fa-pen mr-1"></i> Change
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Password</label>
                                <div class="flex items-center justify-between bg-dark-700/50 px-4 py-3 rounded-lg border border-gray-800/50">
                                    <div class="flex items-center">
                                        <i class="fas fa-lock text-gray-500 mr-3"></i>
                                        <span class="text-gray-300">••••••••</span>
                                    </div>
                                    <button type="button" onclick="openModal('password')" class="text-sm text-primary-500 hover:text-primary-400 hover:underline flex items-center">
                                        <i class="fas fa-pen mr-1"></i> Change
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-200">
        <div class="modal-content bg-gradient-to-br from-dark-800 to-dark-900 rounded-xl shadow-2xl p-6 w-full max-w-md border border-gray-800/50">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-100 flex items-center">
                    <i class="fas fa-lock text-primary-500 mr-2"></i>
                    Change Password
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-300 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="" method="POST" id="passwordForm">
                <input type="hidden" name="change_password" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-500"></i>
                            </div>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Enter current password">
                        </div>
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-400 mb-1">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-500"></i>
                            </div>
                            <input type="password" id="new_password" name="new_password" required minlength="8"
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Enter new password (min 8 characters)">
                        </div>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-400 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-500"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Confirm new password">
                        </div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="flex justify-end gap-4 pt-2">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 rounded-lg font-medium border border-gray-700 hover:bg-dark-700 transition">
                            Cancel
                        </button>
                        <button type="submit" class="bg-gradient-to-r from-primary-500 to-primary-600 text-white py-2.5 px-6 rounded-lg font-medium hover:from-primary-600 hover:to-primary-700 transition duration-300 shadow-lg">
                            Update Password
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Username Change Modal -->
    <div id="usernameModal" class="modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-200">
        <div class="modal-content bg-gradient-to-br from-dark-800 to-dark-900 rounded-xl shadow-2xl p-6 w-full max-w-md border border-gray-800/50">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-100 flex items-center">
                    <i class="fas fa-at text-primary-500 mr-2"></i>
                    Change Username
                </h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-300 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form action="" method="POST" id="usernameForm">
                <input type="hidden" name="change_username" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label for="new_username" class="block text-sm font-medium text-gray-400 mb-1">New Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-500"></i>
                            </div>
                            <input type="text" id="new_username" name="new_username" required minlength="4"
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Enter new username (min 4 characters)" value="<?php echo htmlspecialchars($driver['username']); ?>">
                        </div>
                    </div>
                    
                    <div>
                        <label for="username_password" class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-key text-gray-500"></i>
                            </div>
                            <input type="password" id="username_password" name="username_password" required
                                   class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Enter your password">
                        </div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <div class="flex justify-end gap-4 pt-2">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 rounded-lg font-medium border border-gray-700 hover:bg-dark-700 transition">
                            Cancel
                        </button>
                        <button type="submit" class="bg-gradient-to-r from-primary-500 to-primary-600 text-white py-2.5 px-6 rounded-lg font-medium hover:from-primary-600 hover:to-primary-700 transition duration-300 shadow-lg">
                            Update Username
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Global variables
    let currentDriver = {
        id: <?php echo $driver_id; ?>,
        name: "<?php echo $driver['name']; ?>",
        username: "<?php echo $driver['username']; ?>",
        profilePhoto: "<?php echo $driver['photo_profile'] ?? ''; ?>"
    };

    // Modal functions
    function openModal(type) {
        // Close any open modals first
        closeModal();
        
        // Show the requested modal
        const modal = document.getElementById(`${type}Modal`);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    });

    // Show loading spinner
    function showLoading() {
        document.getElementById('loadingSpinner').style.display = 'flex';
    }

    // Hide loading spinner
    function hideLoading() {
        document.getElementById('loadingSpinner').style.display = 'none';
    }

    // Show message
    function showMessage(type, message) {
        const messageContainer = document.getElementById('messageContainer');
        const alertClass = type === 'success' ? 
            'p-4 mb-6 text-sm text-green-300 bg-green-900/30 rounded-lg border border-green-800' : 
            'p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800';
        
        messageContainer.innerHTML = `<div class="${alertClass}">${message}</div>`;
        
        // Scroll to message
        messageContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            messageContainer.innerHTML = '';
        }, 5000);
    }

    // Handle profile photo upload
    document.getElementById('profile_photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file type and size
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!allowedTypes.includes(file.type)) {
            showMessage('error', 'Only JPG, PNG, and GIF images are allowed.');
            return;
        }
        
        if (file.size > maxSize) {
            showMessage('error', 'Image size must be less than 2MB.');
            return;
        }

        // Preview the image
        const reader = new FileReader();
        reader.onload = function(event) {
            const avatar = document.querySelector('.avatar');
            const profileImage = document.getElementById('profileImage');
            const profileInitials = document.getElementById('profileInitials');
            const navProfileImage = document.querySelector('nav img');
            const navProfileInitials = document.querySelector('nav div');

            if (profileImage) {
                profileImage.src = event.target.result;
            } else if (profileInitials) {
                profileInitials.style.display = 'none';
                const newImg = document.createElement('img');
                newImg.src = event.target.result;
                newImg.className = 'h-28 w-28 rounded-full object-cover border-2 border-primary-500/30 group-hover:border-primary-500 transition-all';
                newImg.id = 'profileImage';
                avatar.insertBefore(newImg, profileInitials);
            }

            // Update nav profile image
            if (navProfileImage) {
                navProfileImage.src = event.target.result;
            } else if (navProfileInitials) {
                navProfileInitials.style.display = 'none';
                const newNavImg = document.createElement('img');
                newNavImg.src = event.target.result;
                newNavImg.className = 'h-8 w-8 rounded-full object-cover ring-1 ring-gray-700/50';
                document.querySelector('nav .flex.items-center.space-x-2').insertBefore(newNavImg, navProfileInitials);
            }
        };
        reader.readAsDataURL(file);

        // Upload the image to server
        const formData = new FormData();
        formData.append('profile_photo', file);
        formData.append('driver_id', currentDriver.id);
        formData.append('update_profile_photo', '1');

        showLoading();

        fetch('update_driver_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showMessage('success', data.message);
                currentDriver.profilePhoto = data.profile_photo;
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('error', 'An error occurred while uploading the photo.');
            console.error('Error:', error);
        });
    });

    // Handle profile form submission with AJAX
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('driver_id', currentDriver.id);

        showLoading();

        fetch('update_driver_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showMessage('success', data.message);
                if (data.name) {
                    currentDriver.name = data.name;
                    document.getElementById('name').value = currentDriver.name;
                    document.querySelector('.avatar + h2').textContent = currentDriver.name;
                }
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('error', 'An error occurred while updating the profile.');
            console.error('Error:', error);
        });
    });

    // Handle password form submission with AJAX
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('driver_id', currentDriver.id);

        showLoading();

        fetch('update_driver_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showMessage('success', data.message);
                closeModal();
                this.reset();
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('error', 'An error occurred while updating the password.');
            console.error('Error:', error);
        });
    });

    // Handle username form submission with AJAX
    document.getElementById('usernameForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('driver_id', currentDriver.id);

        showLoading();

        fetch('update_driver_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showMessage('success', data.message);
                if (data.username) {
                    currentDriver.username = data.username;
                    document.getElementById('username').value = currentDriver.username;
                    document.querySelector('.bg-dark-700/50 span').textContent = currentDriver.username;
                }
                closeModal();
            } else {
                showMessage('error', data.message);
            }
        })
        .catch(error => {
            hideLoading();
            showMessage('error', 'An error occurred while updating the username.');
            console.error('Error:', error);
        });
    });

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Display any PHP message that might have been set
        <?php if (!empty($message)): ?>
            const messageContainer = document.getElementById('messageContainer');
            messageContainer.innerHTML = `<?php echo $message; ?>`;
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        <?php endif; ?>
    });
</script>
</body>
</html>