<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['attendance_id']);
    $subject_code = $_POST['subject_code'] ?? '';
    $time_in = $_POST['time_in'] ?? '';
    $name = $_POST['name'] ?? '';
    $course_section = $_POST['course_section'] ?? '';

    // You'll need to reverse engineer the student ID from name and section.
    // For now, we'll block restore unless you store more data.
    echo json_encode(['success' => false, 'message' => 'Manual restore not supported without full info.']);
    exit();
}
