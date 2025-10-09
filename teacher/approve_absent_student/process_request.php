<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../includes/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['teacher', 'admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['action'], $_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

$action = $_POST['action'];
$id = intval($_POST['id']);
$t_id = $_SESSION['user_id'];

if (!in_array($action, ['approve','reject'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit();
}

// Update absent_requests status
$status = $action === 'approve' ? 'Approved' : 'Rejected';
$stmt = $conn->prepare("UPDATE absent_requests SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();

// If approved, insert into attendance table as Excused
if ($action === 'approve') {
    $stmt2 = $conn->prepare("
        INSERT INTO attendance (s_id, subject_code, section_code, time_in, time_out, status, t_id, user_type)
        SELECT 
            ar.s_id,
            ss.subject_code,
            sec.section_code,
            CONCAT(ar.absent_date, ' 00:00:00') AS time_in,
            CONCAT(ar.absent_date, ' 00:00:00') AS time_out,
            'Excused',
            ?,
            'teacher'
        FROM absent_requests ar
        JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
        JOIN sections sec ON ss.section_id = sec.section_id
        WHERE ar.id = ? AND ss.teacher_id = ?
    ");
    $stmt2->bind_param("iii", $t_id, $id, $t_id);
    $stmt2->execute();
}

// If rejected, insert into attendance table as Absent
if ($action === 'reject') {
    $stmt3 = $conn->prepare("
        INSERT INTO attendance (s_id, subject_code, section_code, time_in, time_out, status, t_id, user_type)
        SELECT 
            ar.s_id,
            ss.subject_code,
            sec.section_code,
            CONCAT(ar.absent_date, ' 00:00:00') AS time_in,
            CONCAT(ar.absent_date, ' 00:00:00') AS time_out,
            'Absent',
            ?,
            'teacher'
        FROM absent_requests ar
        JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
        JOIN sections sec ON ss.section_id = sec.section_id
        WHERE ar.id = ? AND ss.teacher_id = ?
    ");
    $stmt3->bind_param("iii", $t_id, $id, $t_id);
    $stmt3->execute();
}



echo json_encode(['status'=>'success', 'message'=> ucfirst($action).' successfully !']);
