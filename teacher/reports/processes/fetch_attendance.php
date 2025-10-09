<?php
require_once __DIR__ . '/../../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Teacher authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$teacher_id   = $_SESSION['user_id'];
$section_id   = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$month        = $_GET['month'] ?? date('Y-m');
$subject_code = $_GET['subject_code'] ?? '';
$semester     = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;

if (!$section_id || !$subject_code) {
    echo json_encode([]);
    exit();
}

// Date range for the month
$startDate = $month . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// Fetch days + time for this subject
$stmt_days = $conn->prepare("
    SELECT day_of_week, start_time, end_time
    FROM sections_schedules
    WHERE section_id = ? AND subject_code = ? AND teacher_id = ?
");
$stmt_days->bind_param("isi", $section_id, $subject_code, $teacher_id);
$stmt_days->execute();
$days_rows = $stmt_days->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_days->close();

$days_texts = [];
foreach ($days_rows as $row) {
    $day_name = date('D', strtotime("Sunday +{$row['day_of_week']} days")); // 1=Mon ... 7=Sun
    $time     = date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time']));
    $days_texts[] = "$day_name ($time)";
}
$days_string = implode(', ', $days_texts);

// Fetch students
$stmt_s = $conn->prepare("
    SELECT s.s_id, CONCAT(s.s_fname,' ',IFNULL(s.s_mname,''),' ',s.s_lname,' ',IFNULL(s.s_suffix,'')) AS student_name
    FROM students_sections ss
    JOIN students s ON ss.s_id = s.s_id
    WHERE ss.section_id = ?
    ORDER BY s.s_lname, s.s_fname
");
$stmt_s->bind_param("i", $section_id);
$stmt_s->execute();
$students = $stmt_s->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_s->close();

$result = [];
foreach ($students as $student) {
   $stmt_count = $conn->prepare("
    SELECT
        SUM(UPPER(TRIM(status)) = 'PRESENT') AS present,
        SUM(UPPER(TRIM(status)) = 'LATE') AS late,
        SUM(UPPER(TRIM(status)) = 'ABSENT') AS absent
    FROM attendance
    WHERE s_id = ?
      AND subject_code = ?
      AND (
          (time_in IS NOT NULL AND DATE(time_in) BETWEEN ? AND ?)
          OR
          (time_out IS NOT NULL AND DATE(time_out) BETWEEN ? AND ?)
      )
");
$stmt_count->bind_param("isssss", $student['s_id'], $subject_code, $startDate, $endDate, $startDate, $endDate);
    $stmt_count->execute();
    $counts = $stmt_count->get_result()->fetch_assoc();
    $stmt_count->close();

    $result[] = [
        's_id'         => $student['s_id'],
        'student_name' => $student['student_name'],
        'subject_code' => $subject_code,
        'days_of_week' => $days_string, // ✅ NEW field
        'present'      => (int)($counts['present'] ?? 0),
        'late'         => (int)($counts['late'] ?? 0),
        'absent'       => (int)($counts['absent'] ?? 0)
    ];
}

header('Content-Type: application/json');
echo json_encode($result);
