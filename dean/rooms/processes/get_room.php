<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    sendJSON(['status'=>'error','message'=>'Unauthorized'],401);
}

if(!isset($_GET['id'])) sendJSON(['status'=>'error','message'=>'Room ID required'],400);

$id = (int)$_GET['id'];
$stmt = mysqli_prepare($conn,"SELECT * FROM rooms WHERE room_id=?");
mysqli_stmt_bind_param($stmt,'i',$id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($room = mysqli_fetch_assoc($result)){
    sendJSON(['status'=>'success','data'=>$room]);
}else{
    sendJSON(['status'=>'error','message'=>'Room not found'],404);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
