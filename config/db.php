<?php
// Check if the website is running locally on YOUR machine
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    // 🏠 YOUR SETTINGS (Local Laptop Connection)
    $host = "127.0.0.1"; 
    $user = "root"; 
    $pass = "12345"; // Change to "12345" if your local XAMPP root actually has a password
    $port = 3306; 
} else {
    // 🌐 YOUR FRIEND'S SETTINGS (Playit Tunnel Connection)
    $host = "inpatient-siam.with.playit.plus"; 
    $user = "aqilf"; 
    $pass = "12345";  
    $port = 1087; // Connects through Playit's routed port
}

$dbname = "dbstudentsphg"; 

// Dynamic connection string
$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>