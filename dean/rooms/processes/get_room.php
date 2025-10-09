<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

// Check if user is logged in and is dean
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    sendJSON(['status' => 'error', 'message' => 'Unauthorized access'], 401);
}

// Validate input
if (!isset($_GET['id'])) {
    sendJSON(['status' => 'error', 'message' => 'Room ID is required'], 400);
}

$room_id = (int)$_GET['id'];

// Get room details
$query = "SELECT * FROM rooms WHERE room_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($room = mysqli_fetch_assoc($result)) {
    sendJSON(['status' => 'success', 'data' => $room]);
} else {
    sendJSON(['status' => 'error', 'message' => 'Room not found'], 404);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
