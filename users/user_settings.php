<?php
session_start();
session_regenerate_id(true);

include '../connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['user_name'];

// Fetch user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$message = "";

// Handle account deletion
if (isset($_POST['delete_account'])) {
    $current_password = trim($_POST['delete_password']);
    
    if (!empty($current_password)) {
        // Verify current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>User not found.</div>";
            $stmt->close();
        } else {
            $user_data = $result->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $user_data['password'])) {
                // Start transaction for account deletion
                $conn->begin_transaction();
                
                try {
                    // Define tables to clean up in correct order (child tables first)
                    $tables = [
                        'driver_assignments',  // Must come before deliveries
                        'payments',            // References deliveries
                        'deliveries',          // References users
                        'support_messages'     // References users
                    ];
                    
                    foreach ($tables as $table) {
                        // Check if table exists (for safety)
                        $check = $conn->query("SHOW TABLES LIKE '$table'");
                        if ($check->num_rows > 0) {
                            $sql = "DELETE FROM $table WHERE ";
                            // Handle different table structures
                            if ($table === 'driver_assignments') {
                                // Need to delete assignments for this user's deliveries
                                $sql .= "delivery_id IN (SELECT delivery_id FROM deliveries WHERE user_id = ?)";
                            } else {
                                $sql .= "user_id = ?";
                            }
                            
                            $stmt = $conn->prepare($sql);
                            if (!$stmt) {
                                throw new Exception("Failed to prepare statement for $table deletion: " . $conn->error);
                            }
                            $stmt->bind_param("i", $user_id);
                            if (!$stmt->execute()) {
                                throw new Exception("Failed to delete user data from $table: " . $stmt->error);
                            }
                            $stmt->close();
                        }
                    }
                    
                    // Finally delete the user account
                    $sql = "DELETE FROM users WHERE user_id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare statement for user deletion.");
                    }
                    $stmt->bind_param("i", $user_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to delete user account: " . $stmt->error);
                    }
                    $stmt->close();
                    
                    $conn->commit();
                    
                    // Logout and redirect
                    session_destroy();
                    header("Location: user_login.php?message=Your+account+has+been+deleted+successfully");
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Error: Account deletion failed. Please try again later.</div>";
                    error_log("Account deletion error for user $user_id: " . $e->getMessage());
                }
            } else {
                $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Current password is incorrect.</div>";
            }
        }
    } else {
        $message = "<div class='p-4 mb-6 text-sm text-red-300 bg-red-900/30 rounded-lg border border-red-800'>Please enter your current password to confirm account deletion.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash | Settings</title>
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
                        danger: {
                            500: '#ef4444',
                            600: '#dc2626',
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
        .settings-section {
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: linear-gradient(145deg, #1a1a1a, #151515);
        }
        .settings-section:hover {
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
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            margin: 1rem 0;
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
                    <a href="../users/user_dashboard.php" class="flex items-center space-x-3 text-white hover:text-red-400 transition-colors">
                        <img class="w-10 h-10 object-contain" src="../picture/icon/logo.png" alt="Logo">
                        <h1 class="text-xl font-bold">DeliverDash</h1>
                    </a>
                </div>

                <!-- Right side: Profile + Logout -->
                <div class="hidden md:flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <?php if (!empty($user['profile_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-700/50" alt="Profile">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-primary-500/20 flex items-center justify-center text-white ring-1 ring-gray-700/50">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>

                    <!-- Logout -->
                    <form action="../users/user_logout.php" method="POST">
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
                            <?php if (!empty($user['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     class="h-28 w-28 rounded-full object-cover border-2 border-primary-500/30 group-hover:border-primary-500 transition-all" id="profileImage">
                            <?php else: ?>
                                <div class="h-28 w-28 rounded-full bg-primary-500/10 flex items-center justify-center text-4xl font-bold text-primary-500 border-2 border-primary-500/30 group-hover:border-primary-500 transition-all" id="profileInitials">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-semibold text-center text-gray-100"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p class="text-gray-400 text-sm mt-1 flex items-center">
                            <i class="fas fa-calendar-alt mr-1.5 text-gray-500"></i>
                            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div class="border-t border-gray-800/50 px-2 py-3">
                        <a href="user_profile.php" class="nav-link block px-6 py-3 text-sm font-medium rounded-lg mx-2">
                            <i class="fas fa-user-circle mr-3 w-5 text-center"></i> Profile
                        </a>
                        <a href="settings.php" class="nav-link active block px-6 py-3 text-sm font-medium rounded-lg mx-2">
                            <i class="fas fa-cog mr-3 w-5 text-center"></i> Settings
                        </a>
                        <form action="../users/user_logout.php" method="POST" class="inline">
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
            
            <!-- Settings Content -->
            <div class="flex-1">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-100 mb-1">Account Settings</h1>
                            <p class="text-gray-400 text-sm md:text-base">Manage your account preferences and security</p>
                        </div>
                        <div class="hidden md:block">
                            <a href="../users/user_dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-700 rounded-lg text-sm font-medium text-gray-300 hover:bg-dark-700 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="divider"></div>
                </div>

                <div id="messageContainer">
                    <?php if (!empty($message)) echo $message; ?>
                </div>

                <!-- Notification Preferences -->
                <form action="" method="POST" class="settings-section rounded-xl shadow-lg p-6 mb-6">
                    <input type="hidden" name="update_notifications" value="1">
                    
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-500/20 p-2 rounded-lg mr-3">
                            <i class="fas fa-bell text-blue-500"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100">Notification Preferences</h3>
                            <p class="text-xs text-gray-500">Customize how you receive notifications</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Email Notifications</label>
                                <p class="text-xs text-gray-500">Receive important updates via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="email_notifications" value="1" class="sr-only peer" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">SMS Notifications</label>
                                <p class="text-xs text-gray-500">Get delivery updates via text</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="sms_notifications" value="1" class="sr-only peer" <?php echo ($user['sms_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Promotional Offers</label>
                                <p class="text-xs text-gray-500">Receive special offers and discounts</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="promotional_offers" value="1" class="sr-only peer" <?php echo ($user['promotional_offers'] ?? 0) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Dark Mode</label>
                                <p class="text-xs text-gray-500">Toggle between light and dark theme</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="dark_mode" value="1" class="sr-only peer" <?php echo ($user['dark_mode'] ?? 1) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        
                    </div>
                </form>

                <!-- Privacy Settings -->
                <div class="settings-section rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-purple-500/20 p-2 rounded-lg mr-3">
                            <i class="fas fa-user-shield text-purple-500"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100">Privacy Settings</h3>
                            <p class="text-xs text-gray-500">Control your privacy and data sharing</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Profile Visibility</label>
                                <p class="text-xs text-gray-500">Make your profile visible to other users</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Activity Tracking</label>
                                <p class="text-xs text-gray-500">Allow DeliverDash to track app usage to personalize your experience.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Data Sharing</label>
                                <p class="text-xs text-gray-500">Share anonymous usage data with us to help improve the app.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Account Actions -->
                <div class="settings-section rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-red-500/20 p-2 rounded-lg mr-3">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100">Account Actions</h3>
                            <p class="text-xs text-gray-500">Danger zone - proceed with caution</p>
                        </div>
                    </div>
                    
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-md font-medium text-gray-300 mb-2">Export Account Data</h4>
                            <p class="text-sm text-gray-500 mb-4">Download a copy of all your data associated with this account</p>
                            <button type="button" onclick="exportData()" class="bg-gradient-to-r from-gray-700 to-gray-800 text-white py-2 px-4 rounded-lg font-medium hover:from-gray-600 hover:to-gray-700 transition duration-300 shadow">
                                <i class="fas fa-file-export mr-2"></i> Request Data Export
                            </button>
                        </div>
                        
                        
                        <div>
                            <h4 class="text-md font-medium text-gray-300 mb-2">Delete Account</h4>
                            <p class="text-sm text-gray-500 mb-4">Permanently delete your account and all associated data. This action cannot be undone.</p>
                            
                            <form action="" method="POST" id="deleteAccountForm">
                                <input type="hidden" name="delete_account" value="1">
                                
                                <div class="mb-4">
                                    <label for="delete_password" class="block text-sm font-medium text-gray-400 mb-1">Current Password*</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-key text-gray-500"></i>
                                        </div>
                                        <input type="password" id="delete_password" name="delete_password" required
                                               class="w-full pl-10 pr-4 py-2.5 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                               placeholder="Enter your current password">
                                    </div>
                                </div>
                                
                                <button type="button" onclick="confirmDelete()" class="bg-gradient-to-r from-red-600 to-red-700 text-white py-2.5 px-6 rounded-lg font-medium hover:from-red-500 hover:to-red-600 transition duration-300 shadow-lg">
                                    <i class="fas fa-trash-alt mr-2"></i> Delete Account Permanently
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentUser = {
            id: <?php echo $user_id; ?>,
            name: "<?php echo $user['name']; ?>"
        };

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

        // Export data function
        function exportData() {
            showLoading();
            
            // In a real implementation, you would make an AJAX call to generate the export
            setTimeout(() => {
                hideLoading();
                Swal.fire({
                    title: 'Data Export Requested',
                    text: 'We are preparing your data export. You will receive an email with download instructions shortly.',
                    icon: 'success',
                    confirmButtonColor: '#6366f1',
                });
            }, 1500);
        }

        // Deactivate account function
        function deactivateAccount() {
            Swal.fire({
                title: 'Deactivate Account?',
                text: 'Your account will be temporarily disabled. You can reactivate it by logging in again.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, deactivate',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    // In a real implementation, you would make an AJAX call to deactivate the account
                    setTimeout(() => {
                        hideLoading();
                        Swal.fire({
                            title: 'Account Deactivated',
                            text: 'Your account has been deactivated. We hope to see you again soon!',
                            icon: 'success',
                            confirmButtonColor: '#6366f1',
                        }).then(() => {
                            window.location.href = '../users/user_logout.php';
                        });
                    }, 1500);
                }
            });
        }

        // Confirm account deletion
        function confirmDelete() {
            const password = document.getElementById('delete_password').value;
            if (!password) {
                showMessage('error', 'Please enter your current password to confirm account deletion.');
                return;
            }

            Swal.fire({
                title: 'Delete Account Permanently?',
                html: `<p class="text-red-500">This action cannot be undone. All your data will be permanently deleted.</p>
                       <p class="mt-2">Please type <strong>DELETE</strong> to confirm:</p>
                       <input type="text" id="swal-confirm" class="swal2-input mt-2" placeholder="Type DELETE here">`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete permanently',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const confirmValue = document.getElementById('swal-confirm').value;
                    if (confirmValue !== 'DELETE') {
                        Swal.showValidationMessage('Please type DELETE in all caps to confirm');
                    }
                    return confirmValue === 'DELETE';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteAccountForm').submit();
                }
            });
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle switches
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // You can add AJAX here if you want to save preferences immediately
                    console.log(`Setting ${this.name} ${this.checked ? 'enabled' : 'disabled'}`);
                });
            });
        });
    </script>
</body>
</html>