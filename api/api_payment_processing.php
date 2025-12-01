<?php
// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST');
// header('Access-Control-Allow-Headers: Content-Type');

// include "connection_api.php";

// // Ensure the request method is POST
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
//     exit();
// }

// // Get JSON input
// $json = file_get_contents('php://input');
// $data = json_decode($json, true);

// // Validate input data
// if (!isset($data['user_id']) || !isset($data['delivery_id']) || !isset($data['amount']) || !isset($data['payment_method'])) {
//     echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
//     exit();
// }

// $user_id = intval($data['user_id']);
// $delivery_id = intval($data['delivery_id']);
// $amount = floatval($data['amount']);
// $payment_method = $data['payment_method'];
// $driver_fee = 300.00;
// $total_amount = $amount + $driver_fee;

// // Fetch box_size from the deliveries table
// $query = "SELECT box_size FROM deliveries WHERE delivery_id = ?";
// $stmt = $conn->prepare($query);
// if (!$stmt) {
//     echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
//     exit();
// }
// $stmt->bind_param("i", $delivery_id);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows === 0) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid delivery ID']);
//     exit();
// }
// $row = $result->fetch_assoc();
// $box_size = $row['box_size'];

// // Start transaction
// $conn->begin_transaction();

// try {
//     // Set statuses based on payment method
//     $payment_status = ($payment_method == "credit_card") ? "Completed" : "Pending";
//     $delivery_status = ($payment_method == "credit_card") ? "Pending" : "Pending Driver Acceptance";
    
//     // Insert the payment
//     $sql = "INSERT INTO payments (user_id, delivery_id, amount, driver_fee, payment_method, status, transaction_date) 
//             VALUES (?, ?, ?, ?, ?, ?, NOW())";
//     $stmt = $conn->prepare($sql);
//     if (!$stmt) {
//         throw new Exception("Payment processing error: " . $conn->error);
//     }
//     $stmt->bind_param("iidsss", $user_id, $delivery_id, $amount, $driver_fee, $payment_method, $payment_status);
//     if (!$stmt->execute()) {
//         throw new Exception("Failed to process payment.");
//     }
//     $payment_id = $conn->insert_id;

//     // Update delivery status
//     $update_delivery = "UPDATE deliveries 
//                        SET payment_status = ?,
//                            status = ?
//                        WHERE delivery_id = ?";
//     $stmt = $conn->prepare($update_delivery);
//     if (!$stmt) {
//         throw new Exception("Delivery update error: " . $conn->error);
//     }
//     $stmt->bind_param("ssi", $payment_status, $delivery_status, $delivery_id);
//     if (!$stmt->execute()) {
//         throw new Exception("Failed to update delivery status.");
//     }

//     // For COD orders, assign a driver
//     if ($payment_method == "cash_on_delivery") {
//         $find_driver = "SELECT driver_id FROM drivers WHERE status = 'available' LIMIT 1 FOR UPDATE";
//         $driver_result = $conn->query($find_driver);
        
//         if ($driver_result && $driver_result->num_rows > 0) {
//             $driver_row = $driver_result->fetch_assoc();
//             $driver_id = $driver_row['driver_id'];
            
//             // Create assignment
//             $insert_assignment = "INSERT INTO driver_assignments 
//                                 (driver_id, delivery_id, assigned_at, status)
//                                 VALUES (?, ?, NOW(), 'pending')";
//             $stmt = $conn->prepare($insert_assignment);
//             if (!$stmt || !$stmt->execute([$driver_id, $delivery_id])) {
//                 throw new Exception("Failed to assign driver.");
//             }
            
//             // Update driver status
//             $update_driver = "UPDATE drivers SET status = 'on_delivery' WHERE driver_id = ?";
//             $stmt = $conn->prepare($update_driver);
//             if (!$stmt || !$stmt->execute([$driver_id])) {
//                 throw new Exception("Failed to update driver status.");
//             }
//         } else {
//             throw new Exception("No available drivers. Please try again later.");
//         }
//     }

//     $conn->commit();
//     echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully', 'payment_id' => $payment_id]);
//     exit();

// } catch (Exception $e) {
//     $conn->rollback();
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
//     exit();
// }
?>