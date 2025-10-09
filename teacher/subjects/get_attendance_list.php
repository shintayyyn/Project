<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$subject_code = $_GET['subject_code'] ?? null;
$section_id   = $_GET['section_id'] ?? null;

if (!$subject_code || !$section_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    echo json_encode(['success' => false, 'message' => 'No active term found']);
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        a.attendance_id AS id, 
        CONCAT(s.s_fname, ' ', s.s_lname) AS name,
        sec.section_code AS course_section,
        a.time_in, 
        a.time_out, 
        a.status,
        DATE(COALESCE(a.time_in, a.time_out)) AS date_only
    FROM attendance a
    JOIN students s ON a.s_id = s.s_id
    JOIN sections sec ON a.section_code = sec.section_code
    JOIN sections_schedules ss 
        ON ss.subject_code = a.subject_code 
       AND ss.section_id = sec.section_id
       AND ss.term_id = a.term_id   -- ✅ Match attendance term with schedule term
    WHERE ss.teacher_id = ? 
      AND a.subject_code = ? 
      AND sec.section_id = ?
      AND a.term_id = ?             -- ✅ Only current term’s attendance
    ORDER BY a.time_in DESC
");
$stmt->bind_param("isii", $teacher_id, $subject_code, $section_id, $term_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['time_in'] = $row['time_in'] ? date('F j, Y g:i A', strtotime($row['time_in'])) : null;
    $row['time_out'] = $row['time_out'] ? date('F j, Y g:i A', strtotime($row['time_out'])) : null;
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data]);
