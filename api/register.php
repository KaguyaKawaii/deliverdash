<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include('connection_api.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Use POST."]);
    exit();
}

$required_fields = ['name', 'username', 'email', 'password', 'contact', 'address'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit();
    }
}

$name = $_POST['name'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_BCRYPT);
$contact = $_POST['contact'];
$address = $_POST['address'];

$query = "INSERT INTO users (name, username, email, password, contact, address) VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param('ssssss', $name, $username, $email, $password, $contact, $address);
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User registered successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Query preparation failed."]);
}

$conn->close();
?>
