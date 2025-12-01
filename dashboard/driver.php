<?php
include '../connection.php'; 

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update driver
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $driverId = $_POST['driver_id'];
        $name = $_POST['name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $contact = $_POST['contact'];
        $address = $_POST['address'];
        $vehicle = $_POST['vehicle'];
        $licenseNo = $_POST['license_no'];
        $status = $_POST['status'];

        $sql = "UPDATE drivers SET 
                name = ?, 
                username = ?, 
                email = ?, 
                contact = ?, 
                address = ?, 
                vehicle = ?, 
                license_no = ?, 
                status = ? 
                WHERE driver_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $name, $username, $email, $contact, $address, $vehicle, $licenseNo, $status, $driverId);
        
        if ($stmt->execute()) {
            $successMessage = "Driver updated successfully";
        } else {
            $errorMessage = "Failed to update driver";
        }
    }
    
    // Delete driver
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $driverId = $_POST['driver_id'];

        // First delete any related assignments
        $stmt = $conn->prepare("DELETE FROM driver_assignments WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();

        // Then delete the driver
        $stmt = $conn->prepare("DELETE FROM drivers WHERE driver_id = ?");
        $stmt->bind_param("i", $driverId);
        
        if ($stmt->execute()) {
            $successMessage = "Driver deleted successfully";
        } else {
            $errorMessage = "Failed to delete driver";
        }
    }
    
    // Update all drivers status
    if (isset($_POST['action']) && ($_POST['action'] === 'set_all_inactive' || $_POST['action'] === 'set_all_available')) {
        $status = ($_POST['action'] === 'set_all_inactive') ? 'Inactive' : 'Available';
        
        $sql = "UPDATE drivers SET status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $status);
        
        if ($stmt->execute()) {
            $successMessage = "All drivers set to " . $status . " successfully";
        } else {
            $errorMessage = "Failed to update drivers status";
        }
    }
}

// Fetch drivers from the database
$sql = "SELECT driver_id, name, username, email, contact, address, vehicle, license_no, status, created_at FROM drivers";
$result = $conn->query($sql);

// Fetch driver details for view modal if requested
if (isset($_GET['view_id'])) {
    $viewId = $_GET['view_id'];
    $viewSql = "SELECT * FROM drivers WHERE driver_id = ?";
    $viewStmt = $conn->prepare($viewSql);
    $viewStmt->bind_param("i", $viewId);
    $viewStmt->execute();
    $viewResult = $viewStmt->get_result();
    $driverDetails = $viewResult->fetch_assoc();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-available { background-color: #10B981; }
        .status-on_delivery { background-color: #F59E0B; }
        .status-inactive { background-color: #EF4444; }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-900 text-white font-montserrat">
    

    <!-- Edit Driver Modal -->
    <div id="editDriverModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Edit Driver</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="editDriverId" name="driver_id">
                <div class="space-y-4">
                    <div>
                        <label for="editName" class="block mb-2">Name</label>
                        <input type="text" id="editName" name="name" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editUsername" class="block mb-2">Username</label>
                        <input type="text" id="editUsername" name="username" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editEmail" class="block mb-2">Email</label>
                        <input type="email" id="editEmail" name="email" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editContact" class="block mb-2">Contact</label>
                        <input type="text" id="editContact" name="contact" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editAddress" class="block mb-2">Address</label>
                        <input type="text" id="editAddress" name="address" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editVehicle" class="block mb-2">Vehicle</label>
                        <select id="editVehicle" name="vehicle" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                            <option value="motorcycle">Motorcycle</option>
                            <option value="truck">Truck</option>
                        </select>
                    </div>
                    <div>
                        <label for="editLicense" class="block mb-2">License No</label>
                        <input type="text" id="editLicense" name="license_no" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                    </div>
                    <div>
                        <label for="editStatus" class="block mb-2">Status</label>
                        <select id="editStatus" name="status" class="w-full bg-gray-700 border border-gray-600 rounded-md p-2" required>
                            <option value="available">Available</option>
                            <option value="on_delivery">On Delivery</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-600 rounded-md hover:bg-gray-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-500">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Driver Modal -->
    <?php if (isset($driverDetails)): ?>
<div id="viewDriverModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-8 w-full max-w-xl relative">
        <div class="flex justify-between items-center border-b border-gray-700 pb-4 mb-6">
            <h3 class="text-2xl font-bold text-gray-100">Driver Details</h3>
            <button onclick="closeViewModal()" class="text-gray-400 hover:text-white text-lg">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <?php
                $fields = [
                    'Driver ID' => 'driver_id',
                    'Name' => 'name',
                    'Username' => 'username',
                    'Email' => 'email',
                    'Contact' => 'contact',
                    'Address' => 'address',
                    'Vehicle' => 'vehicle',
                    'License No' => 'license_no',
                    'Status' => 'status',
                    'Created At' => 'created_at'
                ];
                foreach ($fields as $label => $key): ?>
            <div>
                <label class="block mb-1 font-semibold text-gray-300"><?php echo $label; ?></label>
                <p class="bg-gray-600 text-white p-2 rounded-lg">
                    <?php if ($key === 'status'): ?>
                        <span class="px-2 py-1  text-xs">
                            <?php echo htmlspecialchars($driverDetails[$key]); ?>
                        </span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($driverDetails[$key]); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 text-right">
            <button onclick="closeViewModal()" class="px-5 py-2 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition">
                Close
            </button>
        </div>
    </div>
</div>
<?php endif; ?>


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
                            <a href="../dashboard/driver.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
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

            <!-- Success/Error Messages -->
    <?php if (isset($successMessage)): ?>
        <div class="bg-green-600 text-white p-3 rounded mb-4" id="alert">
            <?php echo $successMessage; ?>
            
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="bg-green-600 text-white p-3 rounded mb-4" id="alert">
            <?php echo $errorMessage; ?>
            
        </div>
    <?php endif; ?>


                <div class="bg-gray-800 p-6 rounded-lg shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Drivers</h2>
                        <div class="flex gap-2">
                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="set_all_available">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-md" onclick="return confirm('Are you sure you want to set all drivers to available?')">
                                    Set All Available
                                </button>
                            </form>
                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="set_all_inactive">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-md" onclick="return confirm('Are you sure you want to set all drivers to inactive?')">
                                    Set All Inactive
                                </button>
                            </form>
                           
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto max-h-[calc(96.5vh-10rem)]">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-700">
                                    <th class="p-3">Driver ID</th>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Username</th>
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Contact</th>
                                    <th class="p-3">Address</th>
                                    <th class="p-3">Vehicle</th>
                                    <th class="p-3">License No</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Created At</th>
                                    <th class="p-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-700 hover:bg-gray-700 transition-all">
                                            <td class="p-3"><?php echo htmlspecialchars($row['driver_id']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['contact']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['address']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['vehicle']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['license_no']); ?></td>
                                            <td class="p-3">
                                                <span class="px-2 py-1 rounded-full text-xs status-<?php echo htmlspecialchars($row['status']); ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['created_at']); ?></td>
                                            <td class="p-3 text-center">
    
    <div class="flex justify-center gap-2">
        <a href="?view_id=<?php echo $row['driver_id']; ?>" 
           class="bg-blue-500 hover:bg-green-600 text-white p-2 rounded-lg transition-all" 
           title="View Driver" aria-label="View Driver">
            <i class="fas fa-eye text-sm"></i>
        </a>

        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                class="bg-green-500 hover:bg-blue-600 text-white p-2 rounded-lg transition-all" 
                title="Edit Driver" aria-label="Edit Driver">
            <i class="fas fa-edit text-sm"></i>
        </button>

        <form method="POST" action="" class="inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="driver_id" value="<?php echo $row['driver_id']; ?>">
            <button type="button" onclick="confirmDelete(this)" 
                    class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg transition-all" 
                    title="Delete Driver" aria-label="Delete Driver">
                <i class="fas fa-trash text-sm"></i>
            </button>
        </form>
    </div>
</td>
                                    

                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4 text-gray-400">No drivers found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Edit Modal Functions
        function openEditModal(driver) {
            document.getElementById('editDriverId').value = driver.driver_id;
            document.getElementById('editName').value = driver.name;
            document.getElementById('editUsername').value = driver.username;
            document.getElementById('editEmail').value = driver.email;
            document.getElementById('editContact').value = driver.contact;
            document.getElementById('editAddress').value = driver.address;
            document.getElementById('editVehicle').value = driver.vehicle;
            document.getElementById('editLicense').value = driver.license_no;
            document.getElementById('editStatus').value = driver.status;
            
            document.getElementById('editDriverModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editDriverModal').classList.add('hidden');
        }

        // View Modal Functions
        function closeViewModal() {
            window.location.href = window.location.pathname;
        }

        // Delete Confirmation Function
        function confirmDelete(button) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    button.closest('form').submit();
                }
            });
        }

        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById('alert');
            if (alert) alert.remove();
        }, 5000);
    </script>
</body>
</html>