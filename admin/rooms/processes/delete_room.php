<?php
session_start();
require_once('../../../includes/db.php');
require_once('../../../includes/json_response.php');

if(!isset($_SESSION['user_type']) || $_SESSION['user_type']!=='admin') sendJSON(['status'=>'error','message'=>'Unauthorized'],401);

if(!isset($_POST['id'])) sendJSON(['status'=>'error','message'=>'Room ID required'],400);

$id = (int)$_POST['id'];

// Check usage in schedules
$stmt = mysqli_prepare($conn,"SELECT COUNT(*) as cnt FROM sections_schedules WHERE room_id=?");
mysqli_stmt_bind_param($stmt,'i',$id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if($row['cnt']>0) sendJSON(['status'=>'error','message'=>'Room used in schedules'],400);
mysqli_stmt_close($stmt);

// Delete
$stmt = mysqli_prepare($conn,"DELETE FROM rooms WHERE room_id=?");
mysqli_stmt_bind_param($stmt,'i',$id);
if(mysqli_stmt_execute($stmt)){
    sendJSON(['status'=>'success','message'=>'Room deleted']);
}else{
    sendJSON(['status'=>'error','message'=>'Failed to delete'],500);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
