<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include "connection_api.php";

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input data
if (!isset($data['payment_id']) || !is_numeric($data['payment_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid payment_id']);
    exit();
}

$payment_id = intval($data['payment_id']);

// Update payment status to 'Completed'
$sql = "UPDATE payments SET status = 'Completed' WHERE payment_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $payment_id);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update payment status']);
    exit();
}

echo json_encode(['status' => 'success', 'message' => 'Payment marked as successful']);
?>