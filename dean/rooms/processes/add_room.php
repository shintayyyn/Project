<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    sendJSON(['status'=>'error','message'=>'Unauthorized'],401);
}

if (!isset($_POST['room_number']) || !isset($_POST['capacity'])) {
    sendJSON(['status'=>'error','message'=>'Missing fields'],400);
}

$room_number = trim(mysqli_real_escape_string($conn,$_POST['room_number']));
$capacity = (int)$_POST['capacity'];

if($capacity < 1) sendJSON(['status'=>'error','message'=>'Capacity must be > 0'],400);

$stmt = mysqli_prepare($conn,"SELECT room_id FROM rooms WHERE room_number=?");
mysqli_stmt_bind_param($stmt,'s',$room_number);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if(mysqli_stmt_num_rows($stmt)>0){
    mysqli_stmt_close($stmt);
    sendJSON(['status'=>'error','message'=>'Room number exists'],400);
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn,"INSERT INTO rooms (room_number,capacity) VALUES (?,?)");
mysqli_stmt_bind_param($stmt,'si',$room_number,$capacity);
if(mysqli_stmt_execute($stmt)){
    $id = mysqli_insert_id($conn);
    sendJSON(['status'=>'success','message'=>'Room added','data'=>['room_id'=>$id,'room_number'=>$room_number,'capacity'=>$capacity]]);
}else{
    sendJSON(['status'=>'error','message'=>'Failed to add room'],500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
