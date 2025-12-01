<?php
session_start();
include '../connection.php';

$support_id = $_SESSION['support_id'];

// Get current unread count
$unread_count = $conn->query("
    SELECT unread_notifications 
    FROM support
    WHERE support_id = $support_id
")->fetch_assoc()['unread_notifications'];

// Get tickets with new messages
$new_messages = $conn->query("
    SELECT DISTINCT related_to AS ticket_id 
    FROM support_messages 
    WHERE status = 'open' 
    AND message_from = 'user' 
    AND support_id = $support_id
    AND related_to IS NOT NULL
");

$ticket_ids = [];
while ($row = $new_messages->fetch_assoc()) {
    $ticket_ids[] = $row['ticket_id'];
}

echo json_encode([
    'unread_count' => $unread_count,
    'new_messages' => $ticket_ids
]);
?>