<?php
session_start();
include '../connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$message_id = (int)$data['message_id'];
$support_id = $_SESSION['support_id'];

// Mark all messages in this thread as read
$conn->query("
    UPDATE support_messages 
    SET status = 'read' 
    WHERE related_to = $message_id 
    AND message_from = 'user' 
    AND status = 'open'
");

// Update unread count
$unread_count = $conn->query("
    SELECT COUNT(*) AS count 
    FROM support_messages 
    WHERE status = 'open' 
    AND message_from = 'user' 
    AND support_id = $support_id
")->fetch_assoc()['count'];

$conn->query("UPDATE support SET unread_notifications = $unread_count WHERE support_id = $support_id");

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count
]);
?>