<?php
/**
 * get_booked_slots.php
 * --------------------
 * AJAX endpoint: returns booked appointment times for a given date.
 * Used by the dashboard date-picker to prevent overlapping bookings.
 *
 * GET ?date=YYYY-MM-DD  →  JSON array of "HH:MM" strings already taken.
 */
session_start();
require 'db.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['studentno'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$date = $_GET['date'] ?? '';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
    exit;
}

// Fetch all non-cancelled appointments for this date
$stmt = $conn->prepare(
    "SELECT DATE_FORMAT(appointment_datetime, '%H:%i') AS booked_time
     FROM appointments
     WHERE DATE(appointment_datetime) = ?
       AND status != 'Cancelled'
     ORDER BY booked_time"
);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$booked = [];
while ($row = $result->fetch_assoc()) {
    $booked[] = $row['booked_time'];
}

echo json_encode($booked);
