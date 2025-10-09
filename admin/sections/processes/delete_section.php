<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$section_id = $data['section_id'];

$conn->begin_transaction();

try {
    // First unassign all students
    $unassign_query = "DELETE FROM students_sections WHERE section_id = ?";
    $stmt = $conn->prepare($unassign_query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();

    // Then delete the section
    $delete_query = "DELETE FROM sections WHERE section_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}