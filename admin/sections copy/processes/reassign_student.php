
<?php
require_once '../../../includes/db.php';

$student_id = $_POST['student_id'];
$new_section_id = $_POST['new_section_id'];

$conn->begin_transaction();

try {
    // Remove current assignment
    $stmt = $conn->prepare("DELETE FROM students_sections WHERE s_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    // Add new assignment
    $stmt = $conn->prepare("INSERT INTO students_sections (s_id, section_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $new_section_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}