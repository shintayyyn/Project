<?php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

if (!isset($_POST['ss_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing student ID"]);
    exit();
}

$ss_id = intval($_POST['ss_id']);

// First, find the section of this student
$stmt = $conn->prepare("SELECT section_id FROM students_sections WHERE ss_id = ?");
$stmt->bind_param("i", $ss_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    echo json_encode(["status" => "error", "message" => "Student not found"]);
    exit();
}

$section_id = $student['section_id'];

// Reset mayor in this section
$stmt = $conn->prepare("UPDATE students_sections SET is_Mayor = 0 WHERE section_id = ?");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$stmt->close();

// Assign new mayor
$stmt = $conn->prepare("UPDATE students_sections SET is_Mayor = 1 WHERE ss_id = ?");
$stmt->bind_param("i", $ss_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(["status" => "success", "message" => "Mayor has been updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update mayor"]);
}
?>
