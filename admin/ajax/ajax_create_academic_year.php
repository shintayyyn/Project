<?php
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json');

$year_start = intval($_POST['year_start']);
$year_end   = intval($_POST['year_end']);

if(!$year_start || !$year_end){
    echo json_encode(['success'=>false,'message'=>'Start and End year required']);
    exit();
}

// Check duplicate
$stmt = $conn->prepare("SELECT ay_id FROM academic_years WHERE year_start=? AND year_end=?");
$stmt->bind_param("ii", $year_start, $year_end);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows>0){
    echo json_encode(['success'=>false,'message'=>'Academic year already exists']);
    exit();
}

// Insert
$insert = $conn->prepare("INSERT INTO academic_years (year_start, year_end, created_at) VALUES (?,?,NOW())");
$insert->bind_param("ii",$year_start,$year_end);
$insert->execute();

echo json_encode([
    'success'=>true,
    'message'=>'Academic year created',
    'ay_id'=>$insert->insert_id,
    'year_start'=>$year_start,
    'year_end'=>$year_end
]);
