<?php
$host = "localhost";  // Change if using a remote server
$user = "root";       // Default XAMPP username
$password = "";       // Default is empty in XAMPP
$database = "deliverdash_db"; // Your database name

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]));
}

?>