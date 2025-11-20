<?php
session_start();
require_once('../../includes/db.php');

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

function send_json($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

// 🔹 Check login
if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'danger', 'message' => 'Not logged in']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['status' => 'danger', 'message' => 'Invalid request method']);
}

$teacher_id   = $_SESSION['user_id'];
$student_code = $_POST['idcode'] ?? '';
$ss_id        = $_POST['ss_id'] ?? '';
$subject_id   = $_POST['subject_id'] ?? '';
$subject_code = $_POST['subject_code'] ?? '';
$term_id      = $_POST['term_id'] ?? '';

if (empty($student_code) || empty($ss_id) || empty($subject_id) || empty($term_id)) {
    send_json(['status' => 'danger', 'message' => 'Missing required data']);
}

// 🔹 Get schedule + section info exactly like attendance
$getSchedule = $conn->prepare("
    SELECT ss.section_id, sec.section_code, ss.subject_id
    FROM sections_schedules ss
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.ss_id = ? AND ss.teacher_id = ? AND ss.is_active = 1
    LIMIT 1
");
$getSchedule->bind_param("ii", $ss_id, $teacher_id);
$getSchedule->execute();
$scheduleRes = $getSchedule->get_result();

if ($scheduleRes->num_rows === 0) {
    send_json(['status' => 'danger', 'message' => 'Invalid or unauthorized schedule.']);
}

$schedule = $scheduleRes->fetch_assoc();
$section_id   = $schedule['section_id'];
$section_code = trim($schedule['section_code']);
$subject_id   = $schedule['subject_id']; // ✅ ensure subject_id always from schedule
$getSchedule->close();

// 🔹 Get student info
$studentStmt = $conn->prepare("
    SELECT 
        s_id, 
        s_status, 
        is_regular,
        CONCAT(s_fname, ' ', COALESCE(s_mname, ''), ' ', s_lname, ' ', COALESCE(s_suffix, '')) AS full_name
    FROM students 
    WHERE idcode = ? 
    LIMIT 1
");
$studentStmt->bind_param("s", $student_code);
$studentStmt->execute();
$studentRes = $studentStmt->get_result();

if ($studentRes->num_rows === 0) {
    send_json(['status' => 'danger', 'message' => 'Student not found']);
}

$student = $studentRes->fetch_assoc();
$s_id            = $student['s_id'];
$current_status  = $student['s_status'];
$is_regular      = (int)$student['is_regular'];
$full_name       = trim($student['full_name']);

// 🔹 Prevent duplicate enrollment
$check = $conn->prepare("
    SELECT 1 
    FROM subject_enrollments 
    WHERE s_id = ? AND subject_id = ? AND term_id = ? AND is_status = 1
    LIMIT 1
");
$check->bind_param("iii", $s_id, $subject_id, $term_id);
$check->execute();
$checkRes = $check->get_result();

if ($checkRes->num_rows > 0) {
    send_json(['status' => 'warning', 'message' => 'Student is already enrolled in this subject for this term']);
}

// 🔹 Insert new enrollment (with correct section_code)
$stmt = $conn->prepare("
    INSERT INTO subject_enrollments
        (s_id, subject_id, subject_code, section_code, term_id, is_status, enrollment_status, created_at, updated_at)
    VALUES
        (?, ?, ?, ?, ?, 1, 'Enrolled', NOW(), NOW())
");
$stmt->bind_param("iissi", $s_id, $subject_id, $subject_code, $section_code, $term_id);

if (!$stmt->execute()) {
    send_json(['status' => 'danger', 'message' => 'Failed to enroll student: ' . $stmt->error]);
}
$stmt->close();

// 🔹 Update student status if inactive
if (strtolower($current_status) !== 'active') {
    $updateStudent = $conn->prepare("
        UPDATE students 
        SET s_status = 'active', enrollment_status = 'Enrolled'
        WHERE s_id = ?
    ");
    $updateStudent->bind_param("i", $s_id);
    $updateStudent->execute();
    $updateStudent->close();
}

// 🔹 Check or create record in generatedqrcode
$fetchQR = $conn->prepare("SELECT id, section FROM generatedqrcode WHERE id = ? LIMIT 1");
$fetchQR->bind_param("i", $s_id);
$fetchQR->execute();
$qrRes = $fetchQR->get_result();

if ($qrRes->num_rows === 0) {
    // 🔸 Create new QR record
    $insertQR = $conn->prepare("
        INSERT INTO generatedqrcode (id, full_name, section, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $insertQR->bind_param("iss", $s_id, $full_name, $section_code);
    $insertQR->execute();
    $insertQR->close();
} else {
    // 🔸 Update existing QR record
    $qrRow = $qrRes->fetch_assoc();
    $existing_sections = trim($qrRow['section'] ?? '');

    if ($is_regular === 1) {
        $new_section_value = $section_code;
    } else {
        $sections_array = array_filter(array_map('trim', explode(',', $existing_sections)));
        if (!in_array($section_code, $sections_array)) {
            $sections_array[] = $section_code;
        }
        $new_section_value = implode(', ', $sections_array);
    }

    $updateQR = $conn->prepare("
        UPDATE generatedqrcode 
        SET section = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateQR->bind_param("si", $new_section_value, $s_id);
    $updateQR->execute();
    $updateQR->close();
}

send_json([
    'status' => 'success',
    'message' => 'Student enrolled successfully!',
    'debug' => [
        'ss_id' => $ss_id,
        'section_id' => $section_id,
        'section_code' => $section_code,
        'subject_id' => $subject_id
    ]
]);
?>
