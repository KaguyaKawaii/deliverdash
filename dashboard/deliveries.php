<?php
include '../connection.php';

header("Content-Type: text/html; charset=UTF-8");

// Handle delete action
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM deliveries WHERE delivery_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
}

// Build base SQL query with joins to get all related data
$sql = "
    SELECT 
        d.*,
        u.name AS sender_name, 
        u.contact AS sender_contact,
        u.address AS sender_address,
        dr.name AS driver_name,
        dr.contact AS driver_contact,
        dr.vehicle AS driver_vehicle,
        dr.license_no AS driver_license,
        p.payment_method,
        p.transaction_date AS payment_date,
        p.status AS payment_status,
        p.amount AS driver_fee
    FROM deliveries d
    LEFT JOIN users u ON d.user_id = u.user_id
    LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
    LEFT JOIN payments p ON d.delivery_id = p.delivery_id";

// Add filters if they exist
$where = [];
$params = [];
$types = "";

if (!empty($_GET['status_filter'])) {
    $where[] = "d.status = ?";
    $params[] = $_GET['status_filter'];
    $types .= "s";
}

if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where[] = "(d.delivery_id LIKE ? OR u.name LIKE ? OR d.dropoff_name LIKE ? OR d.dropoff_address LIKE ?)";
    array_push($params, $search, $search, $search, $search);
    $types .= str_repeat("s", 4);
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY d.delivery_id ORDER BY d.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        .status-available { background-color: #10B981; }
        .status-on_delivery { background-color: #F59E0B; }
        .status-inactive { background-color: #EF4444; }
        .status-Pending { background-color: #F59E0B; }
        .status-Completed { background-color: #10B981; }
        .status-Pending-Driver-Acceptance { background-color: #EF4444; }
        .status-In-Progress { background-color: #3B82F6; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1F2937;
        }
        ::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6B7280;
        }
        
        /* Gradient background */
        .gradient-bg {
            background: linear-gradient(135deg, #1E3A8A 0%, #111827 100%);
        }
        
        /* Card hover effect */
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Modal backdrop blur */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        
        /* For PDF printing */
        @media print {
            body * {
                visibility: hidden;
            }
            #pdf-content, #pdf-content * {
                visibility: visible;
            }
            #pdf-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
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
                            <a href="../dashboard/deliveries.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
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
                <div class="bg-gray-800 p-6 rounded-lg shadow-lg h-[89.5vh]">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <h2 class="text-xl font-semibold">Deliveries Management</h2>
                        <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <form method="GET" class="flex-1">
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        name="search" 
                                        placeholder="Search deliveries..." 
                                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                        class="w-full bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pl-10"
                                    >
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                            </form>
                            <form method="GET" class="flex-1 md:flex-none">
                                <select 
                                    name="status_filter" 
                                    onchange="this.form.submit()" 
                                    class="w-full bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="Pending" <?= (isset($_GET['status_filter'])) && $_GET['status_filter'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Completed" <?= (isset($_GET['status_filter'])) && $_GET['status_filter'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Accepted" <?= (isset($_GET['status_filter'])) && $_GET['status_filter'] === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                                    <option value="Pending Driver Acceptance" <?= (isset($_GET['status_filter'])) && $_GET['status_filter'] === 'Pending Driver Acceptance' ? 'selected' : '' ?>>Pending Driver Acceptance</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto max-h-[calc(96.5vh-10rem)]">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-700 sticky-header">
                                    <th class="p-3">Delivery ID</th>
                                    <th class="p-3">Sender</th>
                                    <th class="p-3">Pickup Address</th>
                                    <th class="p-3">Recipient</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Created At</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        // Determine status color
                                        $statusColor = 'bg-gray-500';
                                        if ($row['status'] == 'Completed') $statusColor = 'bg-green-500';
                                        if ($row['status'] == 'Accepted') $statusColor = 'bg-blue-500';
                                        if ($row['status'] == 'Pending') $statusColor = 'bg-yellow-500';
                                        if ($row['status'] == 'Pending Driver Acceptance') $statusColor = 'bg-gray-500';
                                        
                                        echo "<tr class='border-b border-gray-700 hover:bg-gray-700 transition-all'>";
                                        echo "<td class='p-3 font-medium'>#" . htmlspecialchars($row['delivery_id']) . "</td>";
                                        echo "<td class='p-3'>
                                                <div class='font-medium'>" . htmlspecialchars($row['sender_name']) . "</div>
                                                <div class='text-gray-400 text-sm'>" . htmlspecialchars($row['sender_contact']) . "</div>
                                              </td>";
                                        echo "<td class='p-3'>" . htmlspecialchars($row['pickup_address']) . "</td>";
                                        echo "<td class='p-3'>
                                                <div class='font-medium'>" . htmlspecialchars($row['dropoff_name']) . "</div>
                                                <div class='text-gray-400 text-sm'>" . htmlspecialchars($row['dropoff_contact']) . "</div>
                                              </td>";
                                        echo "<td class='p-3'><span class='$statusColor text-white px-2 py-1 rounded-full text-xs'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        echo "<td class='p-3'>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
                                        echo "<td class='p-3 text-center'>
                                                <div class='flex justify-center gap-2'>
                                                    <button onclick='openViewModal(" . json_encode($row) . ")' 
                                                            class='bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md transition-all' 
                                                            title='View Delivery' aria-label='View Delivery'>
                                                        <i class='fas fa-eye text-sm'></i>
                                                    </button>
                                                    <button onclick='confirmDelete(" . $row['delivery_id'] . ")' 
                                                            class='bg-red-500 hover:bg-red-600 text-white p-2 rounded-md transition-all' 
                                                            title='Delete Delivery' aria-label='Delete Delivery'>
                                                        <i class='fas fa-trash text-sm'></i>
                                                    </button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center py-4 text-gray-400'>No deliveries found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 modal-backdrop flex justify-center items-center z-50 p-4">
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto border border-gray-700/50 card-hover">
            <div class="flex justify-between items-center mb-6 sticky top-0 bg-gradient-to-r from-gray-800 to-gray-900 py-4 px-2 -mx-2 rounded-t-lg z-10">
                <div>
                    <h2 class="text-2xl font-bold text-white">Delivery Details</h2>
                    <p class="text-sm text-gray-400">ID: #<span id="viewDeliveryId" class="font-mono"></span></p>
                </div>
                
            </div>
            
            <!-- PDF Content - This will be used for generating the PDF -->
            <div id="pdf-content" class="bg-white text-gray-800 p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold">Delivery Details</h1>
                        <p class="text-sm text-gray-600">ID: #<span id="pdfDeliveryId"></span></p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-xl font-semibold">DeliverDash</h2>
                        <p class="text-sm text-gray-600"><?= date('F j, Y') ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <!-- Sender Information -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Sender Information</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Name:</span>
                                <span id="pdfSenderName"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Contact:</span>
                                <span id="pdfSenderContact"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Address:</span>
                                <span id="pdfSenderAddress"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recipient Information -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Recipient Information</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Name:</span>
                                <span id="pdfRecipientName"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Contact:</span>
                                <span id="pdfRecipientContact"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-24">Address:</span>
                                <span id="pdfRecipientAddress"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delivery Details -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Delivery Details</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Product Name:</span>
                                <span id="pdfProductName"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Weight:</span>
                                <span id="pdfWeight"></span> kg
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Delivery Option:</span>
                                <span id="pdfDeliveryOption"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Box Size:</span>
                                <span id="pdfBoxSize"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Information -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Status Information</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Status:</span>
                                <span id="pdfStatus"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Created At:</span>
                                <span id="pdfCreatedAt"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Completed At:</span>
                                <span id="pdfCompletedAt"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Driver Information -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Driver Information</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Driver Name:</span>
                                <span id="pdfDriverName"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Contact:</span>
                                <span id="pdfDriverContact"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Vehicle:</span>
                                <span id="pdfDriverVehicle"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="border p-4 rounded-lg">
                        <h3 class="font-bold text-lg border-b pb-2 mb-3">Payment Information</h3>
                        <div class="space-y-2">
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Box Price:</span>
                                <span>₱<span id="pdfBoxPrice"></span></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Driver Fee:</span>
                                <span>₱<span id="pdfDriverFee"></span></span>
                            </div>
                            <div class="flex gap-2 pt-2 border-t">
                                <span class="font-medium w-32">Total Amount:</span>
                                <span class="font-bold">₱<span id="pdfTotalAmount"></span></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Payment Method:</span>
                                <span id="pdfPaymentMethod"></span>
                            </div>
                            <div class="flex gap-2">
                                <span class="font-medium w-32">Payment Status:</span>
                                <span id="pdfPaymentStatus"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-4 border-t text-center text-sm text-gray-500">
                    <p>Generated by DeliverDash on <?= date('F j, Y \a\t g:i A') ?></p>
                </div>
            </div>
            
            <!-- Visible Modal Content -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Sender Information -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-blue-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-blue-500/10 p-3 rounded-lg">
                            <i class="fas fa-user text-blue-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Sender Information</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Name:</span>
                            <span id="viewSenderName" class="font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Contact:</span>
                            <span id="viewSenderContact"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Address:</span>
                            <span id="viewSenderAddress" class="flex-1"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">User ID:</span>
                            <span id="viewUserId" class="font-mono"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Recipient Information -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-purple-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-purple-500/10 p-3 rounded-lg">
                            <i class="fas fa-user-tag text-purple-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Recipient Information</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Name:</span>
                            <span id="viewRecipientName" class="font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Contact:</span>
                            <span id="viewRecipientContact"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-24">Address:</span>
                            <span id="viewRecipientAddress" class="flex-1"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Delivery Details -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-green-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-green-500/10 p-3 rounded-lg">
                            <i class="fas fa-box-open text-green-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Delivery Details</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Product Name:</span>
                            <span id="viewProductName" class="font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Weight:</span>
                            <span id="viewWeight"></span> kg
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Delivery Option:</span>
                            <span id="viewDeliveryOption"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Box Size:</span>
                            <span id="viewBoxSize" class="capitalize"></span>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Status Information -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-yellow-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-yellow-500/10 p-3 rounded-lg">
                            <i class="fas fa-info-circle text-yellow-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Status Information</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Status:</span>
                            <span id="viewStatus" class="px-3 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Created At:</span>
                            <span id="viewCreatedAt"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Completed At:</span>
                            <span id="viewCompletedAt"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Delivery Status:</span>
                            <span id="viewDeliveryStatus" class="px-3 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Payment Status:</span>
                            <span id="viewPaymentStatus" class="px-3 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Driver Information -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-teal-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-teal-500/10 p-3 rounded-lg">
                            <i class="fas fa-id-card-alt text-teal-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Driver Information</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Driver Name:</span>
                            <span id="viewDriverName" class="font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Contact:</span>
                            <span id="viewDriverContact"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Vehicle:</span>
                            <span id="viewDriverVehicle" class="capitalize"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">License No:</span>
                            <span id="viewDriverLicense" class="font-mono"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <div class="bg-gray-800/50 p-5 rounded-xl border border-gray-700/30 hover:border-indigo-500/30 transition-all card-hover">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="bg-indigo-500/10 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-indigo-500 text-xl"></i>
                        </div>
                        <h3 class="font-medium text-lg">Payment Information</h3>
                    </div>
                    <div class="space-y-3 text-gray-300">
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Box Price:</span>
                            <span>₱<span id="viewBoxPrice" class="font-medium">0.00</span></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Driver Fee:</span>
                            <span>₱<span id="viewDriverFee" class="font-medium">0.00</span></span>
                        </div>
                        <div class="flex gap-2 pt-2 border-t border-gray-700/50">
                            <span class="text-gray-400 w-32">Total Amount:</span>
                            <span class="text-lg font-bold text-green-400">₱<span id="viewTotalAmount">0.00</span></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Payment Method:</span>
                            <span id="viewPaymentMethod"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Payment Status:</span>
                            <span id="viewPaymentStatus2" class="px-3 py-1 rounded-full text-xs font-medium"></span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-400 w-32">Payment Date:</span>
                            <span id="viewPaymentDate"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 flex justify-end gap-3 sticky bottom-0 bg-gradient-to-t from-gray-900 to-transparent pt-6 -mb-6 -mx-6 px-6">
                <button onclick="closeModal('viewModal')" class="px-5 py-2.5 rounded-lg border border-gray-600 hover:bg-gray-700/50 transition-all flex items-center gap-2">
                    <i class="fas fa-times"></i> Close
                </button>
                <button onclick="generatePDF()" class="px-5 py-2.5 rounded-lg bg-blue-500 hover:bg-blue-600 transition-all flex items-center gap-2">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 modal-backdrop flex justify-center items-center z-50">
        <div class="bg-gray-800 p-6 rounded-xl shadow-2xl w-96 border border-gray-700 card-hover">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Confirm Deletion</h2>
                <button onclick="closeModal('deleteModal')" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-300 mb-6">Are you sure you want to delete this delivery? This action cannot be undone.</p>
            <form id="deleteForm" method="POST" class="flex justify-end gap-3">
                <input type="hidden" name="delete_id" id="deleteIdInput">
                <button type="button" onclick="closeModal('deleteModal')" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-500 transition-all">Cancel</button>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-500 transition-all">Delete</button>
            </form>
        </div>
    </div>

    <script>
        // Initialize jsPDF
        const { jsPDF } = window.jspdf;
        
        function openViewModal(data) {
            // Calculate box price based on size
            let boxPrice = 0;
            if (data.box_size === 'small') boxPrice = 50;
            if (data.box_size === 'medium') boxPrice = 100;
            if (data.box_size === 'large') boxPrice = 150;
            
            // Calculate total amount (box price + driver fee)
            const driverFee = data.driver_fee ? parseFloat(data.driver_fee) : 300; // Default to 300 if not set
            const totalAmount = boxPrice + driverFee;
            
            // Set status colors
            const statusColor = getStatusColor(data.status);
            const deliveryStatusColor = getStatusColor(data.delivery_status);
            const paymentStatusColor = getStatusColor(data.payment_status);
            
            // Format dates
            const formatDate = (dateString) => {
                if (!dateString || dateString === '0000-00-00 00:00:00') return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleString();
            };
            
            // Populate view modal with all details
            document.getElementById('viewDeliveryId').textContent = data.delivery_id;
            document.getElementById('viewUserId').textContent = data.user_id || 'N/A';
            document.getElementById('viewSenderName').textContent = data.sender_name || 'N/A';
            document.getElementById('viewSenderContact').textContent = data.sender_contact || 'N/A';
            document.getElementById('viewSenderAddress').textContent = data.sender_address || 'N/A';
            document.getElementById('viewRecipientName').textContent = data.dropoff_name || 'N/A';
            document.getElementById('viewRecipientContact').textContent = data.dropoff_contact || 'N/A';
            document.getElementById('viewRecipientAddress').textContent = data.dropoff_address || 'N/A';
            document.getElementById('viewProductName').textContent = data.product_name || 'N/A';
            document.getElementById('viewWeight').textContent = data.weight || '0';
            document.getElementById('viewDeliveryOption').textContent = data.delivery_option ? data.delivery_option.charAt(0).toUpperCase() + data.delivery_option.slice(1) : 'N/A';
            document.getElementById('viewBoxSize').textContent = data.box_size ? data.box_size.charAt(0).toUpperCase() + data.box_size.slice(1) : 'N/A';
            document.getElementById('viewBoxPrice').textContent = boxPrice.toFixed(2);
            document.getElementById('viewDriverFee').textContent = driverFee.toFixed(2);
            document.getElementById('viewTotalAmount').textContent = totalAmount.toFixed(2);
            
            document.getElementById('viewStatus').textContent = data.status || 'N/A';
            document.getElementById('viewStatus').className = `${statusColor} px-3 py-1 rounded-full text-xs font-medium`;
            document.getElementById('viewCreatedAt').textContent = formatDate(data.created_at);
            document.getElementById('viewCompletedAt').textContent = data.completed_at ? formatDate(data.completed_at) : 'N/A';
            document.getElementById('viewDeliveryStatus').textContent = data.delivery_status || 'N/A';
            document.getElementById('viewDeliveryStatus').className = `${deliveryStatusColor} px-3 py-1 rounded-full text-xs font-medium`;
            document.getElementById('viewPaymentStatus').textContent = data.payment_status || 'N/A';
            document.getElementById('viewPaymentStatus').className = `${paymentStatusColor} px-3 py-1 rounded-full text-xs font-medium`;
            document.getElementById('viewDriverName').textContent = data.driver_name || 'Not assigned';
            document.getElementById('viewDriverContact').textContent = data.driver_contact || 'N/A';
            document.getElementById('viewDriverVehicle').textContent = data.driver_vehicle ? data.driver_vehicle.charAt(0).toUpperCase() + data.driver_vehicle.slice(1) : 'N/A';
            document.getElementById('viewDriverLicense').textContent = data.driver_license || 'N/A';
            document.getElementById('viewPaymentMethod').textContent = data.payment_method ? data.payment_method.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') : 'N/A';
            document.getElementById('viewPaymentStatus2').textContent = data.payment_status || 'N/A';
            document.getElementById('viewPaymentStatus2').className = `${paymentStatusColor} px-3 py-1 rounded-full text-xs font-medium`;
            document.getElementById('viewPaymentDate').textContent = data.payment_date ? formatDate(data.payment_date) : 'N/A';
            
            // Also populate the PDF content (hidden)
            document.getElementById('pdfDeliveryId').textContent = data.delivery_id;
            document.getElementById('pdfSenderName').textContent = data.sender_name || 'N/A';
            document.getElementById('pdfSenderContact').textContent = data.sender_contact || 'N/A';
            document.getElementById('pdfSenderAddress').textContent = data.sender_address || 'N/A';
            document.getElementById('pdfRecipientName').textContent = data.dropoff_name || 'N/A';
            document.getElementById('pdfRecipientContact').textContent = data.dropoff_contact || 'N/A';
            document.getElementById('pdfRecipientAddress').textContent = data.dropoff_address || 'N/A';
            document.getElementById('pdfProductName').textContent = data.product_name || 'N/A';
            document.getElementById('pdfWeight').textContent = data.weight || '0';
            document.getElementById('pdfDeliveryOption').textContent = data.delivery_option ? data.delivery_option.charAt(0).toUpperCase() + data.delivery_option.slice(1) : 'N/A';
            document.getElementById('pdfBoxSize').textContent = data.box_size ? data.box_size.charAt(0).toUpperCase() + data.box_size.slice(1) : 'N/A';
            document.getElementById('pdfBoxPrice').textContent = boxPrice.toFixed(2);
            document.getElementById('pdfDriverFee').textContent = driverFee.toFixed(2);
            document.getElementById('pdfTotalAmount').textContent = totalAmount.toFixed(2);
            document.getElementById('pdfStatus').textContent = data.status || 'N/A';
            document.getElementById('pdfCreatedAt').textContent = formatDate(data.created_at);
            document.getElementById('pdfCompletedAt').textContent = data.completed_at ? formatDate(data.completed_at) : 'N/A';
            document.getElementById('pdfDriverName').textContent = data.driver_name || 'Not assigned';
            document.getElementById('pdfDriverContact').textContent = data.driver_contact || 'N/A';
            document.getElementById('pdfDriverVehicle').textContent = data.driver_vehicle ? data.driver_vehicle.charAt(0).toUpperCase() + data.driver_vehicle.slice(1) : 'N/A';
            document.getElementById('pdfPaymentMethod').textContent = data.payment_method ? data.payment_method.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ') : 'N/A';
            document.getElementById('pdfPaymentStatus').textContent = data.payment_status || 'N/A';
            
            document.getElementById('viewModal').classList.remove('hidden');
        }
        
        function generatePDF() {
            // Create a new jsPDF instance
            const doc = new jsPDF();
            
            // Add logo or header
            doc.setFontSize(20);
            doc.setTextColor(40, 40, 40);
            doc.text('DeliverDash - Delivery Details', 105, 20, { align: 'center' });
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(`Delivery ID: #${document.getElementById('pdfDeliveryId').textContent}`, 105, 30, { align: 'center' });
            doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 35, { align: 'center' });
            
            // Add a line
            doc.setDrawColor(200, 200, 200);
            doc.line(20, 40, 190, 40);
            
            // Set initial y position
            let y = 50;
            
            // Add sender information
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Sender Information', 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text(`Name: ${document.getElementById('pdfSenderName').textContent}`, 20, y);
            y += 7;
            doc.text(`Contact: ${document.getElementById('pdfSenderContact').textContent}`, 20, y);
            y += 7;
            doc.text(`Address: ${document.getElementById('pdfSenderAddress').textContent}`, 20, y);
            y += 15;
            
            // Add recipient information
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Recipient Information', 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text(`Name: ${document.getElementById('pdfRecipientName').textContent}`, 20, y);
            y += 7;
            doc.text(`Contact: ${document.getElementById('pdfRecipientContact').textContent}`, 20, y);
            y += 7;
            doc.text(`Address: ${document.getElementById('pdfRecipientAddress').textContent}`, 20, y);
            y += 15;
            
            // Add delivery details
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Delivery Details', 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text(`Product: ${document.getElementById('pdfProductName').textContent}`, 20, y);
            y += 7;
            doc.text(`Weight: ${document.getElementById('pdfWeight').textContent} kg`, 20, y);
            y += 7;
            doc.text(`Delivery Option: ${document.getElementById('pdfDeliveryOption').textContent}`, 20, y);
            y += 7;
            doc.text(`Box Size: ${document.getElementById('pdfBoxSize').textContent}`, 20, y);
            y += 15;
            
            // Add status information
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Status Information', 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text(`Status: ${document.getElementById('pdfStatus').textContent}`, 20, y);
            y += 7;
            doc.text(`Created At: ${document.getElementById('pdfCreatedAt').textContent}`, 20, y);
            y += 7;
            doc.text(`Completed At: ${document.getElementById('pdfCompletedAt').textContent}`, 20, y);
            y += 15;
            
            // Add driver information if available
            if (document.getElementById('pdfDriverName').textContent !== 'Not assigned') {
                doc.setFontSize(14);
                doc.setTextColor(40, 40, 40);
                doc.text('Driver Information', 20, y);
                y += 10;
                
                doc.setFontSize(12);
                doc.setTextColor(80, 80, 80);
                doc.text(`Name: ${document.getElementById('pdfDriverName').textContent}`, 20, y);
                y += 7;
                doc.text(`Contact: ${document.getElementById('pdfDriverContact').textContent}`, 20, y);
                y += 7;
                doc.text(`Vehicle: ${document.getElementById('pdfDriverVehicle').textContent}`, 20, y);
                y += 15;
            }
            
            // Add payment information
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Payment Information', 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            doc.text(`Box Price: ₱${document.getElementById('pdfBoxPrice').textContent}`, 20, y);
            y += 7;
            doc.text(`Driver Fee: ₱${document.getElementById('pdfDriverFee').textContent}`, 20, y);
            y += 7;
            
            // Total amount with emphasis
            doc.setFontSize(13);
            doc.setTextColor(0, 0, 0);
            doc.setFont(undefined, 'bold');
            doc.text(`Total Amount: ₱${document.getElementById('pdfTotalAmount').textContent}`, 20, y);
            y += 10;
            
            doc.setFontSize(12);
            doc.setFont(undefined, 'normal');
            doc.text(`Payment Method: ${document.getElementById('pdfPaymentMethod').textContent}`, 20, y);
            y += 7;
            doc.text(`Payment Status: ${document.getElementById('pdfPaymentStatus').textContent}`, 20, y);
            
            // Add footer
            doc.setFontSize(10);
            doc.setTextColor(150, 150, 150);
            doc.text('© DeliverDash - Delivery Management System', 105, 285, { align: 'center' });
            
            // Save the PDF
            doc.save(`delivery_${document.getElementById('pdfDeliveryId').textContent}.pdf`);
        }
        
        function confirmDelete(deliveryId) {
            document.getElementById('deleteIdInput').value = deliveryId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }
        
        function getStatusColor(status) {
            if (!status) return 'bg-gray-500';
            
            switch(status.toLowerCase()) {
                case 'completed': return 'bg-green-800';
                case 'accepted': 
                case 'on delivery': 
                case 'accepted': return 'bg-blue-800';
                case 'pending': return 'bg-yellow-800';
                case 'Pending Driver Acceptance': 
                case 'failed': return 'bg-red-800';
                default: return 'bg-gray-500';
            }
        }

        // Handle form submission with SweetAlert
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.text();
                }
                throw new Error('Network response was not ok.');
            })
            .then(() => {
                closeModal('deleteModal');
                Swal.fire({
                    title: 'Success!',
                    text: 'Delivery has been deleted.',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    background: '#1F2937',
                    color: '#FFFFFF'
                }).then(() => {
                    window.location.reload();
                });
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'There was a problem deleting the delivery.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    background: '#1F2937',
                    color: '#FFFFFF'
                });
            });
        });

        // Make the table header sticky when scrolling
        window.addEventListener('DOMContentLoaded', () => {
            const tableContainer = document.querySelector('.max-h-[calc(96.5vh-10rem)]');
            if (tableContainer) {
                tableContainer.addEventListener('scroll', () => {
                    const thead = document.querySelector('thead');
                    if (thead) {
                        thead.style.transform = `translateY(${tableContainer.scrollTop}px)`;
                    }
                });
            }
        });
    </script>
</body>
</html>