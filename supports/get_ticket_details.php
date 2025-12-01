<?php
session_start();
include '../connection.php';

if (!isset($_SESSION['support_logged_in'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['message_id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Message ID is required']);
    exit();
}

$message_id = (int)$_GET['message_id'];

// Get the original ticket details
$ticket_stmt = $conn->prepare("
    SELECT m.message_id, m.user_id, u.name, m.message, m.status, m.created_at, m.updated_at 
    FROM support_messages m
    JOIN users u ON m.user_id = u.user_id
    WHERE m.message_id = ?
");
$ticket_stmt->bind_param("i", $message_id);
$ticket_stmt->execute();
$ticket = $ticket_stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Ticket not found']);
    exit();
}

// Get all messages in this thread (original message + replies)
$messages_stmt = $conn->prepare("
    SELECT m.message_id, m.user_id, 
           CASE WHEN m.message_from = 'support' THEN s.name ELSE u.name END as name,
           m.message_from, m.message, m.status, m.created_at 
    FROM support_messages m
    LEFT JOIN users u ON m.user_id = u.user_id
    LEFT JOIN support s ON m.support_id = s.support_id
    WHERE m.message_id = ? OR m.related_to = ?
    ORDER BY m.created_at ASC
");
$messages_stmt->bind_param("ii", $message_id, $message_id);
$messages_stmt->execute();
$messages = $messages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'ticket' => $ticket,
    'messages' => $messages
]);
?>