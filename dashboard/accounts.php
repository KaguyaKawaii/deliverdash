<?php
include '../connection.php';
session_start();

// Handle delete action
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']); // Always sanitize!

    // Start transaction
    $conn->begin_transaction();

    try {
        // Step 1: Find and delete related support messages
        $conn->query("DELETE FROM support_messages WHERE user_id = $delete_id");
        
        // Step 2: Find and delete related deliveries if needed
        $get_delivery_ids = $conn->query("SELECT delivery_id FROM deliveries WHERE user_id = $delete_id");
        
        if ($get_delivery_ids && $get_delivery_ids->num_rows > 0) {
            while ($row = $get_delivery_ids->fetch_assoc()) {
                $delivery_id = $row['delivery_id'];
                // Delete from driver_assignments first
                $conn->query("DELETE FROM driver_assignments WHERE delivery_id = $delivery_id");
                // Then delete from deliveries
                $conn->query("DELETE FROM deliveries WHERE delivery_id = $delivery_id");
            }
        }

        // Step 3: Delete any other related records (payments, etc.)
        // Add similar DELETE statements for other related tables
        
        // Step 4: Finally delete the user
        $delete_sql = "DELETE FROM users WHERE user_id = $delete_id";
        if ($conn->query($delete_sql)) {
            $_SESSION['message'] = "User deleted successfully!";
            $_SESSION['message_type'] = "success";
            $conn->commit();
        } else {
            throw new Exception("Error deleting user: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: accounts.php");
    exit();
}

// Handle edit action
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $username = $conn->real_escape_string($_POST['username']);
    
    $update_sql = "UPDATE users SET name='$name', username='$username' WHERE user_id=$user_id";
    if ($conn->query($update_sql)) {
        $_SESSION['message'] = "User updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating user: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
    header("Location: accounts.php");
    exit();
}

// Search functionality
$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT u.user_id, u.name, u.username, u.created_at 
            FROM users u
            WHERE u.name LIKE '%$search%' OR u.username LIKE '%$search%'";
} else {
    $sql = "SELECT u.user_id, u.name, u.username, u.created_at FROM users u";
}

$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-900 text-white font-montserrat">
    <main class="min-h-screen flex flex-col">
        <nav class="bg-gray-800 shadow-md py-3 px-6 flex justify-between items-center">
            <h1 class="text-xl font-semibold text-white">DeliverDash Administration</h1>
            
        </nav>

        <div class="flex flex-1">
            <!-- Sidebar -->
            <aside class="bg-gray-800 w-64 p-6 shadow-xl flex flex-col ml-5 mb-5 mt-5 rounded-lg sticky top-5">
                <div class="flex items-center justify-center mb-6">
                
                    <h2 class="text-xl font-bold text-gray-100">Admin Panel</h2>
                </div>
                <hr class="border-gray-700 mb-4">
                
                <nav class="flex-1">
                    <ul class="space-y-2">
                        <li>
                            <a href="../dashboard/admin_dashboard.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-user w-5 text-center mr-3 text-gray-300 group-hover:text-white"></i>
                                <span>Users</span>
                                
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/accounts.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
                                <i class="fas fa-user-circle w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Accounts</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/deliveries.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-truck w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Deliveries</span>
                                
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/driver.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-id-card w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Drivers</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/payments.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-credit-card w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Payments</span>
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/support.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
                                <i class="fas fa-headset w-5 text-center mr-3 group-hover:text-white"></i>
                                <span>Support</span>
                                
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="pt-4 mt-auto border-t border-gray-700">
                    
                    <a href="admin_logout.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-red-400 hover:bg-red-600 hover:text-white group">
                        <i class="fas fa-sign-out-alt w-5 text-center mr-3 group-hover:text-white"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>

            <!-- Main Content -->
            <section class="flex-1 p-6">
                <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold">Accounts</h2>
                        <form method="GET" action="" class="flex">
                            <input type="text" name="search" placeholder="Search users..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   class="bg-gray-700 text-white px-4 py-2 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded-r-md">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="mb-4 p-3 rounded-md <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-600' : 'bg-red-600'; ?>">
                            <?php echo $_SESSION['message']; ?>
                            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="overflow-y-auto max-h-[calc(96.5vh-10rem)]">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="p-3">User ID</th>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Username</th>
                                    <th class="p-3">Created At</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr class='border-b border-gray-700 hover:bg-gray-700 transition-all'>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['user_id']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['name']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['username']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['created_at']) . "</td>";
                                        echo "<td class='p-3'>
                                                <div class='flex justify-center space-x-2'>
                                                    <button onclick='showViewModal(" . htmlspecialchars(json_encode($row)) . ")' class='bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md'>
                                                        <i class='fas fa-eye'></i>
                                                    </button>
                                                    <button onclick='showEditModal(" . htmlspecialchars(json_encode($row)) . ")' class='bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded-md'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <button onclick='showDeleteModal(" . $row['user_id'] . ")' class='bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md'>
                                                        <i class='fas fa-trash'></i>
                                                    </button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center py-4 text-gray-400'>No users found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- View Modal -->
        <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">User Details</h3>
                    <button onclick="hideModal('viewModal')" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400">User ID</label>
                        <p id="view-user-id" class="mt-1 p-2 bg-gray-700 rounded"></p>
                    </div>
                    <div>
                        <label class="block text-gray-400">Name</label>
                        <p id="view-name" class="mt-1 p-2 bg-gray-700 rounded"></p>
                    </div>
                    <div>
                        <label class="block text-gray-400">Username</label>
                        <p id="view-username" class="mt-1 p-2 bg-gray-700 rounded"></p>
                    </div>
                    <div>
                        <label class="block text-gray-400">Created At</label>
                        <p id="view-created-at" class="mt-1 p-2 bg-gray-700 rounded"></p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="hideModal('viewModal')" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-md">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <form method="POST" action="">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Edit User</h3>
                        <button type="button" onclick="hideModal('editModal')" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="edit-name" class="block text-gray-400">Name</label>
                            <input type="text" name="name" id="edit-name" class="mt-1 w-full p-2 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="edit-username" class="block text-gray-400">Username</label>
                            <input type="text" name="username" id="edit-username" class="mt-1 w-full p-2 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideModal('editModal')" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" name="edit_user" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-md">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <form method="POST" action="">
                    <input type="hidden" name="delete_id" id="delete-user-id">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Confirm Deletion</h3>
                        <button type="button" onclick="hideModal('deleteModal')" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="mb-6">Are you sure you want to delete this user? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideModal('deleteModal')" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Modal functions
        function showViewModal(user) {
            document.getElementById('view-user-id').textContent = user.user_id;
            document.getElementById('view-name').textContent = user.name;
            document.getElementById('view-username').textContent = user.username;
            document.getElementById('view-created-at').textContent = user.created_at;
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function showEditModal(user) {
            document.getElementById('edit-user-id').value = user.user_id;
            document.getElementById('edit-name').value = user.name;
            document.getElementById('edit-username').value = user.username;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function showDeleteModal(userId) {
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.id === 'viewModal') hideModal('viewModal');
            if (event.target.id === 'editModal') hideModal('editModal');
            if (event.target.id === 'deleteModal') hideModal('deleteModal');
        }
    </script>
</body>
</html>