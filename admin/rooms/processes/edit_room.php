<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

// Check user
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendJSON(['status'=>'error','message'=>'Unauthorized access'], 401);
}

// Validate input
if (empty($_POST['room_id']) || empty($_POST['room_number']) || !isset($_POST['capacity'])) {
    sendJSON([
        'status'=>'error',
        'message'=>'All fields (Room ID, Room Number, Capacity) are required',
        'debug'=>$_POST
    ], 400);
}

$id = (int)$_POST['room_id'];
$room_number = trim($_POST['room_number']);
$capacity = (int)$_POST['capacity'];

// Validate capacity
if ($capacity < 1) {
    sendJSON(['status'=>'error','message'=>'Capacity must be greater than 0'], 400);
}

// Check for duplicate room number
$stmt = mysqli_prepare($conn, "SELECT room_id FROM rooms WHERE room_number=? AND room_id!=?");
mysqli_stmt_bind_param($stmt, 'si', $room_number, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    sendJSON(['status'=>'error','message'=>'Room number already exists. Please choose a different one.'], 400);
}
mysqli_stmt_close($stmt);

// Update room
$stmt = mysqli_prepare($conn, "UPDATE rooms SET room_number=?, capacity=? WHERE room_id=?");
mysqli_stmt_bind_param($stmt, 'sii', $room_number, $capacity, $id);

if (mysqli_stmt_execute($stmt)) {
    sendJSON(['status'=>'success','message'=>'Room updated successfully']);
} else {
    sendJSON([
        'status'=>'error',
        'message'=>'Failed to update room. Please try again later.',
        'error'=>mysqli_error($conn)
    ], 500);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
