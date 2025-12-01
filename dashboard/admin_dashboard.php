<?php
include '../connection.php'; 

// Initialize search and filter variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build the base SQL query
$sql = "SELECT u.user_id, u.name, u.email, u.contact, u.address, u.created_at, u.status, u.username, u.profile_photo
        FROM users u WHERE 1=1";

// Add search condition if search term exists
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (u.name LIKE '%$search_term%' OR u.email LIKE '%$search_term%' OR u.contact LIKE '%$search_term%' OR u.address LIKE '%$search_term%')";
}

// Add status filter if selected
if (!empty($status_filter) && $status_filter != 'all') {
    $status_filter = $conn->real_escape_string($status_filter);
    $sql .= " AND u.status = '$status_filter'";
}

// Add sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'user_id';
$order = isset($_GET['order']) && strtoupper($_GET['order']) == 'DESC' ? 'DESC' : 'ASC';
$sql .= " ORDER BY $sort $order";

$result = $conn->query($sql);

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // First, check if the user exists
    $check_sql = "SELECT * FROM users WHERE user_id = $delete_id";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        // Delete related records first to maintain referential integrity
        $conn->begin_transaction();
        try {
            // Delete related deliveries
            $conn->query("DELETE FROM deliveries WHERE user_id = $delete_id");
            // Delete related payments
            $conn->query("DELETE FROM payments WHERE user_id = $delete_id");
            // Delete related support messages
            $conn->query("DELETE FROM support_messages WHERE user_id = $delete_id");
            // Finally delete the user
            $delete_sql = "DELETE FROM users WHERE user_id = $delete_id";
            
            if ($conn->query($delete_sql)) {
                $conn->commit();
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'User deleted successfully',
                    }).then(() => {
                        window.location.href='admin_dashboard.php';
                    });
                </script>";
            } else {
                throw new Exception("Error deleting user");
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '".addslashes($e->getMessage())."',
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'User not found',
            });
        </script>";
    }
}

// Handle suspend action
if (isset($_POST['suspend_user'])) {
    $user_id = $_POST['user_id'];
    $suspend_until = $_POST['suspend_until'];
    
    // Validate suspend date
    if (strtotime($suspend_until) <= time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Suspension date must be in the future',
            });
        </script>";
    } else {
        $update_sql = "UPDATE users SET status = 'suspended', suspend_until = '$suspend_until' WHERE user_id = $user_id";
        if ($conn->query($update_sql)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'User suspended successfully',
                }).then(() => {
                    window.location.href='admin_dashboard.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error suspending user',
                });
            </script>";
        }
    }
}

// Handle edit action
if (isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $contact = $conn->real_escape_string($_POST['contact']);
    $address = $conn->real_escape_string($_POST['address']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Invalid email format',
            });
        </script>";
    } else {
        $update_sql = "UPDATE users SET 
                      name = '$name', 
                      email = '$email', 
                      contact = '$contact', 
                      address = '$address', 
                      status = '$status' 
                      WHERE user_id = $user_id";
        
        if ($conn->query($update_sql)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'User updated successfully',
                }).then(() => {
                    window.location.href='admin_dashboard.php';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating user: ".addslashes($conn->error)."',
                });
            </script>";
        }
    }
}
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @media (max-width: 768px) {
            .responsive-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
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
                            <a href="../dashboard/admin_dashboard.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
                                <i class="fas fa-user w-5 text-center mr-3 text-gray-300 group-hover:text-white"></i>
                                <span>Users</span>
                                
                            </a>
                        </li>
                        <li>
                            <a href="../dashboard/accounts.php" class="flex items-center p-3 rounded-md transition-all duration-200 text-gray-300 hover:bg-gray-700 hover:text-white group">
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
                        <h2 class="text-xl font-semibold">Users Management</h2>
                        <div>
                            <button onclick="window.location.href='admin_dashboard.php'" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-all">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- Search and Filter Section -->
                    <div class="mb-6 flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <form method="GET" action="admin_dashboard.php" class="flex">
                                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" 
                                    class="w-full bg-gray-700 border border-gray-600 rounded-l-md p-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-r-md transition-all">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="w-full md:w-48">
                            <form method="GET" action="admin_dashboard.php" class="flex">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <select name="status_filter" onchange="this.form.submit()" 
                                    class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto max-h-[calc(96.5vh-10rem)] responsive-table">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="p-3 cursor-pointer" onclick="sortTable('user_id')">
                                        ID <i class="fas fa-sort ml-1"></i>
                                    </th>
                                    <th class="p-3 cursor-pointer" onclick="sortTable('name')">
                                        Name <i class="fas fa-sort ml-1"></i>
                                    </th>
                                    <th class="p-3">Username</th>
                                    <th class="p-3 cursor-pointer" onclick="sortTable('email')">
                                        Email <i class="fas fa-sort ml-1"></i>
                                    </th>
                                    <th class="p-3">Contact</th>
                                    <th class="p-3">Address</th>
                                    <th class="p-3 cursor-pointer" onclick="sortTable('status')">
                                        Status <i class="fas fa-sort ml-1"></i>
                                    </th>
                                    <th class="p-3 cursor-pointer" onclick="sortTable('created_at')">
                                        Created At <i class="fas fa-sort ml-1"></i>
                                    </th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $status_class = $row['status'] == 'active' ? 'bg-green-500' : ($row['status'] == 'suspended' ? 'bg-yellow-500' : 'bg-red-500');
                                        
                                        echo "<tr class='border-b border-gray-700 hover:bg-gray-700 transition-all'>";
                                        echo "<td class='p-3'>" . $row['user_id'] . "</td>";
                                        echo "<td class='p-3 font-medium'>" . htmlspecialchars($row['name']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['username']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['email']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['contact']) . "</td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['address']) . "</td>";
                                        echo "<td class='p-3'><span class='$status_class text-white text-xs px-2 py-1 rounded-full'>" . ucfirst($row['status']) . "</span></td>";
                                        echo "<td class='p-3'>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                        echo "<td class='p-3'>
                                            <div class='flex justify-center space-x-2'>
                                                <button onclick='viewDetails(" . json_encode($row) . ")' 
                                                        class='bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md transition-all' 
                                                        title='View Details' aria-label='View Details'>
                                                    <i class='fas fa-eye text-sm'></i>
                                                </button>
                                                <button onclick='editUser(" . json_encode($row) . ")' 
                                                        class='bg-green-500 hover:bg-green-600 text-white p-2 rounded-md transition-all' 
                                                        title='Edit User' aria-label='Edit User'>
                                                    <i class='fas fa-edit text-sm'></i>
                                                </button>
                                                <button onclick='confirmDelete(" . $row['user_id'] . ")' 
                                                        class='bg-red-500 hover:bg-red-600 text-white p-2 rounded-md transition-all' 
                                                        title='Delete User' aria-label='Delete User'>
                                                    <i class='fas fa-trash text-sm'></i>
                                                </button>
                                            </div>
                                        </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='9' class='text-center py-4 text-gray-400'>No users found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-96">
            <h3 class="text-xl font-semibold mb-4">Edit User</h3>
            <form id="editForm" method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Name</label>
                    <input type="text" name="name" id="edit_name" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Contact</label>
                    <input type="text" name="contact" id="edit_contact" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Address</label>
                    <textarea name="address" id="edit_address" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white" required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Status</label>
                    <select name="status" id="edit_status" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2 text-white" required>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-md transition-all">Cancel</button>
                    <button type="submit" name="edit_user" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md transition-all">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // View user details
        function viewDetails(userData) {
            const profilePhoto = userData.profile_photo ? 
                `<img src="${userData.profile_photo}" alt="Profile Photo" class="w-24 h-24 rounded-full mx-auto mb-4 object-cover">` : 
                '<div class="w-24 h-24 rounded-full bg-gray-600 mx-auto mb-4 flex items-center justify-center"><i class="fas fa-user text-4xl text-gray-400"></i></div>';
            
            Swal.fire({
                title: 'User Details',
                html: `<div class="text-left">
                        ${profilePhoto}
                        <p class="mb-2"><span class="font-semibold">ID:</span> ${userData.user_id}</p>
                        <p class="mb-2"><span class="font-semibold">Username:</span> ${userData.username}</p>
                        <p class="mb-2"><span class="font-semibold">Name:</span> ${userData.name}</p>
                        <p class="mb-2"><span class="font-semibold">Email:</span> ${userData.email}</p>
                        <p class="mb-2"><span class="font-semibold">Contact:</span> ${userData.contact}</p>
                        <p class="mb-2"><span class="font-semibold">Address:</span> ${userData.address}</p>
                        <p class="mb-2"><span class="font-semibold">Status:</span> <span class="${userData.status === 'active' ? 'text-green-500' : userData.status === 'suspended' ? 'text-yellow-500' : 'text-red-500'}">${userData.status.charAt(0).toUpperCase() + userData.status.slice(1)}</span></p>
                        <p class="mb-2"><span class="font-semibold">Joined:</span> ${new Date(userData.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</p>
                       </div>`,
                showConfirmButton: true,
                confirmButtonColor: '#3B82F6',
                width: '600px'
            });
        }
        
        // Edit user
        function editUser(userData) {
            document.getElementById('edit_user_id').value = userData.user_id;
            document.getElementById('edit_name').value = userData.name;
            document.getElementById('edit_email').value = userData.email;
            document.getElementById('edit_contact').value = userData.contact;
            document.getElementById('edit_address').value = userData.address;
            document.getElementById('edit_status').value = userData.status;
            
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        // Confirm delete
        function confirmDelete(userId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `admin_dashboard.php?delete_id=${userId}`;
                }
            });
        }
        
        // Sort table
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            let order = 'ASC';
            
            if (urlParams.get('sort') === column) {
                order = urlParams.get('order') === 'ASC' ? 'DESC' : 'ASC';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', order);
            window.location.href = `admin_dashboard.php?${urlParams.toString()}`;
        }
    </script>
</body>
</html>