<?php
header('Content-Type: application/json');
include "connection_api.php";

try {
    // Get user_id from GET parameters
    $user_id = $_GET['user_id'] ?? null;

    if (!$user_id || !is_numeric($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid user ID is required']);
        exit();
    }

    // Fetch user details
    $user_stmt = $conn->prepare("SELECT name, address FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    $user = $user_result->fetch_assoc();
    $response = [
        'user_name' => htmlspecialchars($user['name']),
        'address' => htmlspecialchars($user['address'])
    ];

    // Fetch delivery counts
    $count_query = "SELECT 
        SUM(CASE WHEN d.status NOT IN ('Completed', 'Cancelled') THEN 1 ELSE 0 END) AS active_deliveries,
        SUM(CASE WHEN p.status = 'Pending' THEN 1 ELSE 0 END) AS pending_payments,
        SUM(CASE WHEN d.status = 'Completed' THEN 1 ELSE 0 END) AS completed_deliveries
        FROM deliveries d
        LEFT JOIN payments p ON d.delivery_id = p.delivery_id
        WHERE d.user_id = ?";
    
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();

    $response = array_merge($response, [
        'active_deliveries' => (int)$count_result['active_deliveries'],
        'pending_payments' => (int)$count_result['pending_payments'],
        'completed_deliveries' => (int)$count_result['completed_deliveries']
    ]);

    // Fetch delivery details (FIXED SQL QUERY)
    $deliveries_query = "SELECT 
        d.delivery_id, 
        d.dropoff_name, 
        d.product_name, 
        d.status, 
        d.delivery_option, 
        COALESCE(dr.name, CONCAT('Driver (ID: ', d.driver_id, ')'), 'Not Assigned') AS driver_name,
        (COALESCE(p.amount, 0) + COALESCE(p.driver_fee, 0)) AS total_cost
        FROM deliveries d
        LEFT JOIN payments p ON d.delivery_id = p.delivery_id
        LEFT JOIN drivers dr ON d.driver_id = dr.driver_id
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC";
    
    $deliveries_stmt = $conn->prepare($deliveries_query);
    if (!$deliveries_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $deliveries_stmt->bind_param("i", $user_id);
    if (!$deliveries_stmt->execute()) {
        throw new Exception("Execute failed: " . $deliveries_stmt->error);
    }
    
    $deliveries_result = $deliveries_stmt->get_result();
    $response['deliveries'] = [];
    
    while ($delivery = $deliveries_result->fetch_assoc()) {
        $response['deliveries'][] = [
            'delivery_id' => $delivery['delivery_id'],
            'dropoff_name' => htmlspecialchars($delivery['dropoff_name']),
            'product_name' => htmlspecialchars($delivery['product_name']),
            'status' => $delivery['status'],
            'delivery_option' => $delivery['delivery_option'],
            'driver_name' => htmlspecialchars($delivery['driver_name']),
            'total_cost' => (float)$delivery['total_cost']
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} finally {
    // Close connections
    if (isset($user_stmt)) $user_stmt->close();
    if (isset($count_stmt)) $count_stmt->close();
    if (isset($deliveries_stmt)) $deliveries_stmt->close();
    if (isset($conn)) $conn->close();
}
?>