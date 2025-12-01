<?php
session_start();
session_regenerate_id(true);

include '../connection.php';

if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit();
}

$driver_id = $_SESSION['driver_id'];
$name = $_SESSION['name'] ?? '';

// Fetch driver data
$sql = "SELECT * FROM drivers WHERE driver_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();
$stmt->close();

$message = "";

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeliverDash | Driver Settings</title>
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
        .avatar:hover {
            transform: scale(1.05);
            border-color: #6366f1;
            box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
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
        .badge-danger {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            margin: 1rem 0;
        }
    </style>
</head>
<body class="min-h-screen bg-dark-900">
    <!-- Navigation -->
    <nav class="bg-dark-800 border-b border-gray-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Left side: Logo -->
                <div class="flex items-center space-x-3">
                    <a href="driver_dashboard.php" class="flex items-center space-x-3 text-white hover:text-green-400 transition-colors">
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
                                <img src="<?php echo htmlspecialchars($driver['photo_profile']); ?>" class="h-28 w-28 rounded-full object-cover border-2 border-primary-500/30 group-hover:border-primary-500 transition-all">
                            <?php else: ?>
                                <div class="h-28 w-28 rounded-full bg-primary-500/10 flex items-center justify-center text-4xl font-bold text-primary-500 border-2 border-primary-500/30 group-hover:border-primary-500 transition-all">
                                    <?php echo strtoupper(substr($driver['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-semibold text-center text-gray-100"><?php echo htmlspecialchars($driver['name']); ?></h2>
                        <p class="text-gray-400 text-sm mt-1 flex items-center">
                            <i class="fas fa-calendar-alt mr-1.5 text-gray-500"></i>
                            Member since <?php echo date('M Y', strtotime($driver['created_at'])); ?>
                        </p>
                        <div class="mt-3">
                            <span class="badge <?php echo $driver['status'] === 'available' ? 'badge-success' : ($driver['status'] === 'on_delivery' ? 'badge-warning' : 'badge-danger'); ?>">
                                <i class="fas fa-circle mr-1.5 text-xs"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $driver['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-800/50 px-2 py-3">
                        <a href="driver_profile.php" class="nav-link block px-6 py-3 text-sm font-medium rounded-lg mx-2">
                            <i class="fas fa-user-circle mr-3 w-5 text-center"></i> Profile
                        </a>
                        <a href="driver_settings.php" class="nav-link active block px-6 py-3 text-sm font-medium rounded-lg mx-2">
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
            
            <!-- Settings Content -->
            <div class="flex-1">
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-100 mb-1">Driver Settings</h1>
                            <p class="text-gray-400 text-sm md:text-base">Configure your account preferences and settings</p>
                        </div>
                        <div class="hidden md:block">
                            <a href="driver_dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-700 rounded-lg text-sm font-medium text-gray-300 hover:bg-dark-700 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="divider"></div>
                </div>

                <!-- Notification Settings -->
                <div class="settings-section rounded-xl shadow-lg p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-500/20 p-2 rounded-lg mr-3">
                            <i class="fas fa-bell text-blue-500"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-100">Notification Preferences</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-300">Email Notifications</h4>
                                <p class="text-xs text-gray-500">Receive important updates via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked >
                                <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-300">SMS Notifications</h4>
                                <p class="text-xs text-gray-500">Get delivery alerts via text message</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" >
                                <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-300">Push Notifications</h4>
                                <p class="text-xs text-gray-500">Enable app notifications for new orders</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        
                        <div class="pt-4">
                            <button class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors ">
                                Save Notification Settings
                            </button>
                        </div>
                    </div>
                </div>

                <!-- App Preferences -->
                
              

                

                <!-- Account Actions -->
                <div class="settings-section rounded-xl shadow-lg p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-red-500/20 p-2 rounded-lg mr-3">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-100">Account Actions</h3>
                    </div>
                    
                    <div class="space-y-4">
                        
                        
                        <div>
                            <button class="w-full px-4 py-3 bg-dark-700 border border-blue-500 text-blue-500 rounded-lg text-sm font-medium hover:bg-blue-500/10 transition-colors" >
                                <i class="fas fa-file-export mr-2"></i> Export My Data
                            </button>
                            <p class="text-xs text-gray-500 mt-1">Download a copy of all your DeliverDash data</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Show toast message
    function showToast(message, type = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: type === 'success' ? '#1a1a2a' : '#1a1a2a',
            color: type === 'success' ? '#10b981' : '#ef4444',
            iconColor: type === 'success' ? '#10b981' : '#ef4444',
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        Toast.fire({
            icon: type === 'success' ? 'success' : 'error',
            title: message
        });
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handlers for all disabled buttons to show they don't work
        document.querySelectorAll('button:disabled').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                showToast('This feature is not implemented yet', 'error');
            });
        });
        
        // Add click handlers for all disabled select elements
        document.querySelectorAll('select:disabled').forEach(select => {
            select.addEventListener('click', function(e) {
                e.preventDefault();
                showToast('This setting cannot be changed in the demo', 'error');
            });
        });
        
        // Add click handlers for all disabled checkboxes
        document.querySelectorAll('input[type="checkbox"]:disabled').forEach(checkbox => {
            checkbox.addEventListener('click', function(e) {
                e.preventDefault();
                showToast('This setting cannot be changed in the demo', 'error');
            });
        });
    });
    </script>
</body>
</html>