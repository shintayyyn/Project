<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_id']) || !isset($data['new_section_id'])) {
        throw new Exception('Missing required fields');
    }
    
    $student_id = $data['student_id'];
    $new_section_id = $data['new_section_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update the student's section
    $update_query = "UPDATE students_sections 
                    SET section_id = ? 
                    WHERE s_id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ii', $new_section_id, $student_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update student section');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Student transferred successfully']);
    
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
