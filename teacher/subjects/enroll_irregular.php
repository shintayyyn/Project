<?php
session_start();
require_once('../../includes/db.php');

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // prevent warnings from breaking JSON

// 🔹 Safe JSON response
function send_json($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

// 🔹 Check login
if (!isset($_SESSION['user_id'])) {
    send_json(['status' => 'danger', 'message' => 'Not logged in']);
}

// 🔹 Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_code = $_POST['idcode'] ?? '';
    $ss_id        = $_POST['ss_id'] ?? '';
    $subject_id   = $_POST['subject_id'] ?? '';
    $subject_code = $_POST['subject_code'] ?? '';
    $term_id      = $_POST['term_id'] ?? '';

    // 🔹 Validate required fields
    if (empty($student_code) || empty($ss_id) || empty($subject_id) || empty($term_id)) {
        send_json(['status' => 'danger', 'message' => 'Missing required data']);
    }

    // 🔹 Get student record
    $studentStmt = $conn->prepare("SELECT s_id, s_status FROM students WHERE idcode=? LIMIT 1");
    $studentStmt->bind_param("s", $student_code);
    $studentStmt->execute();
    $studentRes = $studentStmt->get_result();

    if ($studentRes->num_rows === 0) {
        send_json(['status' => 'danger', 'message' => 'Student not found']);
    }

    $student = $studentRes->fetch_assoc();
    $s_id = $student['s_id'];
    $current_status = $student['s_status'];

    // 🔹 Prevent duplicate enrollment for same subject + term
    $check = $conn->prepare("
        SELECT 1 
        FROM subject_enrollments 
        WHERE s_id=? AND subject_id=? AND term_id=? AND is_status=1
        LIMIT 1
    ");
    $check->bind_param("iii", $s_id, $subject_id, $term_id);
    $check->execute();
    $checkRes = $check->get_result();

    if ($checkRes->num_rows > 0) {
        send_json(['status' => 'warning', 'message' => 'Student is already enrolled in this subject for this term']);
    }

    // 🔹 Insert new enrollment
    $stmt = $conn->prepare("
        INSERT INTO subject_enrollments
            (s_id, subject_id, subject_code, section_code, term_id, is_status, enrollment_status, created_at, updated_at)
        VALUES
            (?, ?, ?, (SELECT section_code FROM sections_schedules WHERE ss_id=?), ?, 1, 'Enrolled', NOW(), NOW())
    ");
    $stmt->bind_param("iisis", $s_id, $subject_id, $subject_code, $ss_id, $term_id);

    if ($stmt->execute()) {
        // 🔹 Update student status if needed
        if ($current_status !== 'Active') {
           $updateStudent = $conn->prepare("
    UPDATE students 
    SET s_status = 'active', 
        enrollment_status = 'Enrolled'
    WHERE s_id = ?
");
$updateStudent->bind_param("i", $s_id);
$updateStudent->execute();
$updateStudent->close();

        }

        send_json(['status' => 'success', 'message' => 'Student enrolled successfully!']);
    } else {
        send_json(['status' => 'danger', 'message' => 'Failed to enroll student: ' . $stmt->error]);
    }
}

send_json(['status' => 'danger', 'message' => 'Invalid request method']);
