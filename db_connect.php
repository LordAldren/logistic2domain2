<?php
// db_connect.php

$servername = "localhost";
$username = "logi_admin"; // Palitan mo ito ng iyong database username
$password = "123";     // Palitan mo ito ng iyong database password
$dbname = "logi_admin"; // Pangalan ng database na ginawa sa database.sql

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character set to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

?>