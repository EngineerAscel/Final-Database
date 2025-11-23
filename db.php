<?php
// Database configuration
$servername = "localhost";
$username = "root";       // <--- Manually re-type this line
$password = "";           // <--- Manually re-type this line
$dbname = "1garage";       // <--- Manually re-type this line


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Optional: set character set to UTF-8
$conn->set_charset("utf8");
?>