<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

// Check if user is logged in and is dean
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    sendJSON(['status' => 'error', 'message' => 'Unauthorized access'], 401);
}

// Validate input
if (!isset($_POST['room_id']) || !isset($_POST['room_number']) || !isset($_POST['capacity'])) {
    sendJSON(['status' => 'error', 'message' => 'Missing required fields'], 400);
}

$room_id = (int)$_POST['room_id'];
$room_number = mysqli_real_escape_string($conn, $_POST['room_number']);
$capacity = (int)$_POST['capacity'];

// Validate capacity
if ($capacity < 1) {
    sendJSON(['status' => 'error', 'message' => 'Capacity must be greater than 0'], 400);
}

// Check if room number already exists for other rooms
$check_query = "SELECT room_id FROM rooms WHERE room_number = ? AND room_id != ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, 'si', $room_number, $room_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    sendJSON(['status' => 'error', 'message' => 'Room number already exists'], 400);
}
mysqli_stmt_close($stmt);

// Update room
$update_query = "UPDATE rooms SET room_number = ?, capacity = ? WHERE room_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, 'sii', $room_number, $capacity, $room_id);

if (mysqli_stmt_execute($stmt)) {
    sendJSON(['status' => 'success', 'message' => 'Room updated successfully']);
} else {
    sendJSON(['status' => 'error', 'message' => 'Failed to update room: ' . mysqli_error($conn)], 500);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
