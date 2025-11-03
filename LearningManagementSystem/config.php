<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";  // Change if your MySQL username is different
$password = "Rs12ks34@2004";      // Add your MySQL password if you have one
$dbname = "lms_db";  // Change to your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
