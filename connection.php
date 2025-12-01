<?php

// Database connection details
$host = 'localhost'; // Change if your database is hosted elsewhere
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password (empty by default)
$database = 'deliverdash_db'; // Change to your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);

} 


?>
