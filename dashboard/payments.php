<?php
include '../connection.php';

// Initialize search query
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the base query
$sql = "SELECT p.*, u.name AS user_name, d.dropoff_name, d.pickup_address, d.dropoff_address, 
               d.dropoff_contact, d.product_name, d.weight, d.delivery_option, d.box_size,
               d.category, d.status AS delivery_status, d.driver_id, dr.name AS driver_name
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.user_id
        LEFT JOIN deliveries d ON p.delivery_id = d.delivery_id
        LEFT JOIN drivers dr ON d.driver_id = dr.driver_id";

// Add search conditions if search term exists
if (!empty($search)) {
    $sql .= " WHERE p.payment_id LIKE '%$search%' 
              OR u.name LIKE '%$search%' 
              OR d.dropoff_name LIKE '%$search%'
              OR p.amount LIKE '%$search%'
              OR p.payment_method LIKE '%$search%'
              OR p.status LIKE '%$search%'";
}

$sql .= " ORDER BY p.transaction_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .status-Pending { background-color: #F59E0B; }
        .status-Completed { background-color: #10B981; }
        .status-Failed { background-color: #EF4444; }
        .status-Cancelled { background-color: #9CA3AF; }
        
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
                            <a href="../dashboard/payments.php" class="flex items-center p-3 rounded-md transition-all duration-200 bg-gray-700 text-white group">
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
                        <h2 class="text-xl font-semibold">Payments Management</h2>
                        <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <form method="GET" class="flex-1">
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        name="search" 
                                        placeholder="Search payments..." 
                                        class="w-full bg-gray-700 text-white px-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pl-10"
                                        value="<?= htmlspecialchars($search) ?>"
                                    >
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                            </form>
                            
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto max-h-[calc(96.5vh-10rem)]">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-700 sticky-header">
                                    <th class="p-3">Payment ID</th>
                                    <th class="p-3">User</th>
                                    <th class="p-3">Delivery</th>
                                    <th class="p-3">Amount</th>
                                    <th class="p-3">Method</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Date</th>
                                    <th class="p-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        // Determine status color
                                        $statusColor = 'status-' . $row['status'];
                                        
                                        echo "<tr class='border-b border-gray-700 hover:bg-gray-700 transition-all'>";
                                        echo "<td class='p-3 font-medium'>#" . $row['payment_id'] . "</td>";
                                        echo "<td class='p-3'>
                                                <div class='font-medium'>" . htmlspecialchars($row['user_name'] ?? 'N/A') . "</div>
                                                <div class='text-gray-400 text-sm'>User ID: " . $row['user_id'] . "</div>
                                              </td>";
                                        echo "<td class='p-3'>
                                                <div class='font-medium'>" . ($row['delivery_id'] ? '#' . $row['delivery_id'] : 'N/A') . "</div>
                                                <div class='text-gray-400 text-sm'>" . htmlspecialchars($row['dropoff_name'] ?? '') . "</div>
                                              </td>";
                                        echo "<td class='p-3 font-medium'>₱" . number_format($row['amount'], 2) . "</td>";
                                        echo "<td class='p-3'>" . ucwords(str_replace('_', ' ', $row['payment_method'])) . "</td>";
                                        echo "<td class='p-3'><span class='$statusColor text-white px-3 py-1 rounded-full text-xs font-medium'>" . $row['status'] . "</span></td>";
                                        echo "<td class='p-3'>" . date('M j, Y', strtotime($row['transaction_date'])) . "</td>";
                                        echo "<td class='p-3'>
                                                <div class='action-buttons'>
                                                    
                                                    <button onclick='printPayment(" . json_encode($row) . ")' class='bg-green-500 hover:bg-green-600 text-white p-2 rounded-full transition-all' title='Print'>
                                                        <i class='fas fa-print text-xs'></i>
                                                    </button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' class='text-center py-4 text-gray-400'>No payments found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Hidden PDF Content -->
    <div id="pdf-content" class="bg-white text-gray-800 p-6 hidden">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Payments Report</h1>
                <p class="text-sm text-gray-600">Generated on <?= date('F j, Y \a\t g:i A') ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-semibold">DeliverDash</h2>
                <p class="text-sm text-gray-600">Payments Management</p>
            </div>
        </div>
        
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2 border">Payment ID</th>
                    <th class="p-2 border">User</th>
                    <th class="p-2 border">Delivery</th>
                    <th class="p-2 border">Amount</th>
                    <th class="p-2 border">Method</th>
                    <th class="p-2 border">Status</th>
                    <th class="p-2 border">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Reset result pointer and loop again for PDF content
                $result->data_seek(0);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='border-b'>";
                        echo "<td class='p-2 border'>#" . $row['payment_id'] . "</td>";
                        echo "<td class='p-2 border'>" . htmlspecialchars($row['user_name'] ?? 'N/A') . " (ID: " . $row['user_id'] . ")</td>";
                        echo "<td class='p-2 border'>" . ($row['delivery_id'] ? '#' . $row['delivery_id'] : 'N/A') . "</td>";
                        echo "<td class='p-2 border'>₱" . number_format($row['amount'], 2) . "</td>";
                        echo "<td class='p-2 border'>" . ucwords(str_replace('_', ' ', $row['payment_method'])) . "</td>";
                        echo "<td class='p-2 border'>" . $row['status'] . "</td>";
                        echo "<td class='p-2 border'>" . date('M j, Y', strtotime($row['transaction_date'])) . "</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
        
        <div class="mt-8 pt-4 border-t text-center text-sm text-gray-500">
            <p>© <?= date('Y') ?> DeliverDash - Delivery Management System</p>
        </div>
    </div>

    <script>
        // Initialize jsPDF
        const { jsPDF } = window.jspdf;
        
        function generatePDF() {
            // Create a new jsPDF instance
            const doc = new jsPDF('landscape');
            
            // Add logo or header
            doc.setFontSize(20);
            doc.setTextColor(40, 40, 40);
            doc.text('DeliverDash - Payments Report', 145, 15, { align: 'center' });
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 145, 22, { align: 'center' });
            
            // Add a line
            doc.setDrawColor(200, 200, 200);
            doc.line(10, 30, 280, 30);
            
            // Create table headers
            const headers = [
                "Payment ID",
                "User",
                "Delivery",
                "Amount",
                "Method",
                "Status",
                "Date"
            ];
            
            // Prepare data
            const data = [];
            <?php
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                echo "data.push([";
                echo "'#" . $row['payment_id'] . "',";
                echo "'" . addslashes(htmlspecialchars($row['user_name'] ?? 'N/A')) . " (ID: " . $row['user_id'] . ")',";
                echo "'" . ($row['delivery_id'] ? '#' . $row['delivery_id'] : 'N/A') . "',";
                echo "'₱" . number_format($row['amount'], 2) . "',";
                echo "'" . ucwords(str_replace('_', ' ', $row['payment_method'])) . "',";
                echo "'" . $row['status'] . "',";
                echo "'" . date('M j, Y', strtotime($row['transaction_date'])) . "'";
                echo "]);\n";
            }
            ?>
            
            // Set initial y position
            let y = 40;
            
            // Add table using autoTable
            doc.autoTable({
                startY: y,
                head: [headers],
                body: data,
                theme: 'grid',
                headStyles: {
                    fillColor: [55, 65, 81], // gray-700
                    textColor: 255,
                    fontStyle: 'bold'
                },
                alternateRowStyles: {
                    fillColor: [243, 244, 246] // gray-100
                },
                margin: { top: y },
                styles: {
                    fontSize: 9,
                    cellPadding: 3,
                    overflow: 'linebreak'
                },
                columnStyles: {
                    0: { cellWidth: 25 }, // Payment ID
                    1: { cellWidth: 50 }, // User
                    2: { cellWidth: 25 }, // Delivery
                    3: { cellWidth: 25 }, // Amount
                    4: { cellWidth: 30 }, // Method
                    5: { cellWidth: 25 }, // Status
                    6: { cellWidth: 30 }  // Date
                }
            });
            
            // Add footer
            doc.setFontSize(10);
            doc.setTextColor(150, 150, 150);
            doc.text('© DeliverDash - Delivery Management System', 145, 200, { align: 'center' });
            
            // Save the PDF
            doc.save(`payments_report_${new Date().toISOString().slice(0,10)}.pdf`);
        }
        
        function viewPayment(paymentData) {
            // Format the payment method
            const formattedMethod = paymentData.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            // Format the transaction date
            const transactionDate = new Date(paymentData.transaction_date);
            const formattedDate = transactionDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Create the HTML content for the modal
            const htmlContent = `
                <div class="text-left">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Payment Information</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Payment ID:</span> #${paymentData.payment_id}</p>
                                <p><span class="font-medium">Amount:</span> ₱${paymentData.amount.toFixed(2)}</p>
                                <p><span class="font-medium">Payment Method:</span> ${formattedMethod}</p>
                                <p><span class="font-medium">Status:</span> <span class="px-2 py-1 rounded-full text-xs font-medium ${paymentData.status === 'Completed' ? 'bg-green-500' : paymentData.status === 'Pending' ? 'bg-yellow-500' : paymentData.status === 'Failed' ? 'bg-red-500' : 'bg-gray-500'} text-white">${paymentData.status}</span></p>
                                <p><span class="font-medium">Transaction Date:</span> ${formattedDate}</p>
                                <p><span class="font-medium">Driver Fee:</span> ₱${paymentData.driver_fee.toFixed(2)}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold mb-2">User Information</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">User ID:</span> ${paymentData.user_id}</p>
                                <p><span class="font-medium">Name:</span> ${paymentData.user_name || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Delivery Information</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Delivery ID:</span> ${paymentData.delivery_id ? '#' + paymentData.delivery_id : 'N/A'}</p>
                                <p><span class="font-medium">Product:</span> ${paymentData.product_name || 'N/A'}</p>
                                <p><span class="font-medium">Weight:</span> ${paymentData.weight || 'N/A'} kg</p>
                                <p><span class="font-medium">Delivery Option:</span> ${paymentData.delivery_option ? paymentData.delivery_option.charAt(0).toUpperCase() + paymentData.delivery_option.slice(1) : 'N/A'}</p>
                                <p><span class="font-medium">Box Size:</span> ${paymentData.box_size ? paymentData.box_size.charAt(0).toUpperCase() + paymentData.box_size.slice(1) : 'N/A'}</p>
                                <p><span class="font-medium">Category:</span> ${paymentData.category || 'N/A'}</p>
                                <p><span class="font-medium">Delivery Status:</span> ${paymentData.delivery_status || 'N/A'}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Location Details</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Pickup Address:</span> ${paymentData.pickup_address || 'N/A'}</p>
                                <p><span class="font-medium">Dropoff Address:</span> ${paymentData.dropoff_address || 'N/A'}</p>
                                <p><span class="font-medium">Dropoff Contact:</span> ${paymentData.dropoff_contact || 'N/A'}</p>
                                <p><span class="font-medium">Dropoff Name:</span> ${paymentData.dropoff_name || 'N/A'}</p>
                            </div>
                            
                            ${paymentData.driver_id ? `
                            <h3 class="text-lg font-semibold mt-4 mb-2">Driver Information</h3>
                            <div class="space-y-2">
                                <p><span class="font-medium">Driver ID:</span> ${paymentData.driver_id}</p>
                                <p><span class="font-medium">Driver Name:</span> ${paymentData.driver_name || 'N/A'}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: 'Payment Details',
                html: htmlContent,
                width: '800px',
                icon: 'info',
                confirmButtonText: 'Close'
            });
        }
        
        function printPayment(paymentData) {
            // Format the payment method
            const formattedMethod = paymentData.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            // Create a new jsPDF instance
            const doc = new jsPDF();
            
            // Add header
            doc.setFontSize(20);
            doc.setTextColor(40, 40, 40);
            doc.text('DeliverDash - Payment Receipt', 105, 15, { align: 'center' });
            
            doc.setFontSize(12);
            doc.setTextColor(100, 100, 100);
            doc.text(`Payment ID: #${paymentData.payment_id}`, 105, 22, { align: 'center' });
            doc.text(`Date: ${new Date(paymentData.transaction_date).toLocaleDateString()}`, 105, 28, { align: 'center' });
            
            // Add a line
            doc.setDrawColor(200, 200, 200);
            doc.line(20, 35, 190, 35);
            
            // Add payment details
            doc.setFontSize(14);
            doc.setTextColor(40, 40, 40);
            doc.text('Payment Details', 20, 45);
            
            doc.setFontSize(12);
            doc.setTextColor(80, 80, 80);
            
            let y = 55;
            doc.text(`User: ${paymentData.user_name || 'N/A'} (ID: ${paymentData.user_id})`, 20, y);
            y += 10;
            doc.text(`Delivery: #${paymentData.delivery_id || 'N/A'}`, 20, y);
            y += 10;
            doc.text(`Dropoff Location: ${paymentData.dropoff_name || 'N/A'}`, 20, y);
            y += 10;
            doc.text(`Amount: ${parseFloat(paymentData.amount).toFixed(2)} PESOS`, 20, y);
            y += 10;
            doc.text(`Payment Method: ${formattedMethod}`, 20, y);
            y += 10;
            doc.text(`Status: ${paymentData.status}`, 20, y);
            y += 10;
            doc.text(`Driver Fee: ${parseFloat(paymentData.driver_fee).toFixed(2)} PESOS`, 20, y);
            y += 10;
            doc.text(`Transaction Date: ${new Date(paymentData.transaction_date).toLocaleDateString()}`, 20, y);
            
            // Add footer
            doc.setFontSize(10);
            doc.setTextColor(150, 150, 150);
            doc.text('© DeliverDash - Delivery Management System', 105, 280, { align: 'center' });
            
            // Save the PDF
            doc.save(`payment_receipt_${paymentData.payment_id}.pdf`);
        }
    </script>
</body>
</html>