<?php
// This points directly to your Playit Tunnel using their custom account
$host = "inpatient-siam.with.playit.plus"; 
$port = 1087;                             
$user = "aqilfaris"; // Updated from root to friend
$pass = "12345";  // The password you just assigned them
$dbname = "dbstudentsphg"; 

// Create connection specifying your Playit port
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>