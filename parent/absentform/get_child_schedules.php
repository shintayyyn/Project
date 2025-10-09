<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_GET['s_id'])) {
    echo json_encode([]);
    exit();
}

$s_id = intval($_GET['s_id']);

// ✅ Fetch the student's section(s) from students_sections
$section_stmt = $conn->prepare("
    SELECT section_id 
    FROM students_sections 
    WHERE s_id = ?
");
$section_stmt->bind_param("i", $s_id);
$section_stmt->execute();
$section_result = $section_stmt->get_result();

$sections = [];
while ($row = $section_result->fetch_assoc()) {
    $sections[] = $row['section_id'];
}

if (empty($sections)) {
    echo json_encode([]);
    exit();
}

// ✅ Build IN clause dynamically
$placeholders = implode(',', array_fill(0, count($sections), '?'));
$types = str_repeat('i', count($sections));

// ✅ Fetch schedules including status (Available, Asynchronous, Not Available)
$sql = "SELECT ss_id, section_id, section_code, subject_code, 
               COALESCE(teacher_name, '') AS teacher_name,
               COALESCE(day_of_week, '') AS day_of_week,
               COALESCE(start_time, '') AS start_time,
               COALESCE(end_time, '') AS end_time,
               COALESCE(room_id, '') AS room_id,
               COALESCE(status, '') AS status,
               COALESCE(remarks, '') AS remarks
        FROM sections_schedules
        WHERE section_id IN ($placeholders)
        ORDER BY section_code, subject_code, day_of_week, start_time";

$schedule_stmt = $conn->prepare($sql);
$schedule_stmt->bind_param($types, ...$sections);
$schedule_stmt->execute();
$result = $schedule_stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    foreach ($row as $key => $value) {
        $row[$key] = $value ?? '';
    }
    $schedules[] = $row;
}

echo json_encode($schedules);
