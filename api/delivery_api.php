<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

session_start();
session_regenerate_id(true);

include "connection_api.php";

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'delivery_id' => null
];

try {
    // Get JSON input from Android request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate required fields
    if (!isset($data['user_id']) || !isset($data['dropoff_name']) || 
        !isset($data['dropoff_contact']) || !isset($data['pickup_address']) || 
        !isset($data['dropoff_address'])) {
        throw new Exception("All fields are required");
    }

    $user_id = $data['user_id'];
    $dropoff_name = trim($data['dropoff_name']);
    $dropoff_contact = trim($data['dropoff_contact']);
    $pickup_address = trim($data['pickup_address']);
    $dropoff_address = trim($data['dropoff_address']);

    // Validate empty fields
    if (empty($dropoff_name) || empty($dropoff_contact) || 
        empty($pickup_address) || empty($dropoff_address)) {
        throw new Exception("All fields must be filled");
    }

    // Validate contact number format
    if (!preg_match('/^[0-9]{10,15}$/', $dropoff_contact)) {
        throw new Exception("Invalid contact number format");
    }

    // Insert delivery into database
    $sql = "INSERT INTO deliveries (user_id, dropoff_name, dropoff_contact, pickup_address, dropoff_address, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("issss", $user_id, $dropoff_name, $dropoff_contact, $pickup_address, $dropoff_address);
    
    if ($stmt->execute()) {
        $delivery_id = $stmt->insert_id;
        
        $response = [
            'success' => true,
            'message' => 'Delivery request submitted successfully',
            'delivery_id' => $delivery_id
        ];
        
        // Optionally send notification email here
    } else {
        throw new Exception("Failed to create delivery: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    $conn->close();
    echo json_encode($response);
}
?>