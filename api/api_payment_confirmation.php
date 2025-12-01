<?php
// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST');
// header('Access-Control-Allow-Headers: Content-Type');

// include "connection_api.php";

// // Simple authentication check
// if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
//     echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
//     exit();
// }

// // Get JSON input
// $json = file_get_contents('php://input');
// $data = json_decode($json, true);

// // Validate input data
// if (!$data || !isset($data['delivery_id'])) {
//     echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
//     exit();
// }

// try {
//     $conn->autocommit(FALSE); // Start transaction
    
//     $delivery_id = intval($data['delivery_id']);

//     // 1. Update payment status
//     $sql = "UPDATE payments SET status = 'Completed', payment_date = NOW() WHERE delivery_id = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $delivery_id);
//     $stmt->execute();
    
//     // 2. Update delivery status
//     $sql = "UPDATE deliveries SET status = 'Processing' WHERE delivery_id = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $delivery_id);
//     $stmt->execute();
    
//     $conn->commit();
    
//     // Simplified response
//     echo json_encode([
//         'status' => 'success',
//         'message' => 'Payment confirmed successfully',
//         'delivery_id' => $delivery_id
//     ]);
    
// } catch (Exception $e) {
//     $conn->rollback();
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'Payment failed: ' . $e->getMessage()
//     ]);
// } finally {
//     $conn->autocommit(TRUE);
//     if (isset($stmt)) $stmt->close();
//     $conn->close();
// }
?>