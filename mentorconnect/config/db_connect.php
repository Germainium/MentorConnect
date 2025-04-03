<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = ""; // Default XAMPP password is empty
$database = "mentorconnect";  // Changed from 'mentor_connect' to 'mentorconnect'

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// Function to close database connection
function closeConnection($conn) {
    $conn->close();
}
?>