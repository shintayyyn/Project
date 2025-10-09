
<?php
require_once '../../../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $data['student_id'];
$section_id = $data['section_id'];

$stmt = $conn->prepare("DELETE FROM students_sections WHERE s_id = ? AND section_id = ?");
$stmt->bind_param("ii", $student_id, $section_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to unassign student']);
}
?>