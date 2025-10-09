<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

// Check if user is logged in and is dean
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    sendJSON(['status' => 'error', 'message' => 'Unauthorized access'], 401);
}

// Validate input
if (!isset($_POST['id'])) {
    sendJSON(['status' => 'error', 'message' => 'Room ID is required'], 400);
}

$room_id = (int)$_POST['id'];

// Check if room is being used in any schedules
$check_query = "SELECT COUNT(*) as count FROM sections_schedules WHERE room_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, 'i', $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['count'] > 0) {
    sendJSON(['status' => 'error', 'message' => 'Cannot delete room: It is currently being used in schedules'], 400);
}

// Delete room
$delete_query = "DELETE FROM rooms WHERE room_id = ?";
$stmt = mysqli_prepare($conn, $delete_query);
mysqli_stmt_bind_param($stmt, 'i', $room_id);

if (mysqli_stmt_execute($stmt)) {
    sendJSON(['status' => 'success', 'message' => 'Room deleted successfully']);
} else {
    sendJSON(['status' => 'error', 'message' => 'Failed to delete room: ' . mysqli_error($conn)], 500);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
