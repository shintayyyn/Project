<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_id']) || !isset($data['section_id'])) {
        throw new Exception('Missing required fields');
    }
    
    $student_id = $data['student_id'];
    $section_id = $data['section_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    // Remove student from section
    $delete_query = "DELETE FROM students_sections 
                    WHERE s_id = ? AND section_id = ?";
    
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $student_id, $section_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to remove student from section');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Student removed successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno != 0) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
