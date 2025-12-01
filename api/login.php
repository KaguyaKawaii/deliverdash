<?php
include "connection_api.php"; // Ensure correct DB connection

header("Content-Type: application/json");

// Get data from the request
$username = $_POST['username'];
$password = $_POST['password'];

// Prepare and bind
$stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);

// Execute the statement
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($user_id, $hashed_password);
    $stmt->fetch();

    // Verify password
    if (password_verify($password, $hashed_password)) {
        echo json_encode(array("status" => "success", "user_id" => $user_id));
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid password"));
    }
} else {
    echo json_encode(array("status" => "error", "message" => "User not found"));
}

$stmt->close();
$conn->close();
?>
