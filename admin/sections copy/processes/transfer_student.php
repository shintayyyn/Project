<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_id']) || !isset($data['new_section_id'])) {
        throw new Exception('Missing required fields');
    }

    $student_id = (int)$data['student_id'];
    $new_section_id = (int)$data['new_section_id'];
    
    // Start transaction
    $conn->begin_transaction();

    /**
     * 1. Get the student's current section
     */
    $stmt = $conn->prepare("
        SELECT ss.section_id, ss.section_code 
        FROM students_sections ss
        WHERE ss.s_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $stmt->bind_result($current_section_id, $current_section_code);

    if (!$stmt->fetch()) {
        throw new Exception('Student not found in any section');
    }
    $stmt->close();

    /**
     * 2. If the current section is the same as the new one, no changes should be made
     */
    if ($current_section_id === $new_section_id) {
        throw new Exception('No changes made. The student is already in this section.');
    }

    /**
     * 3. Get the new section_code
     */
    $stmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
    $stmt->bind_param('i', $new_section_id);
    $stmt->execute();
    $stmt->bind_result($new_section_code);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid section_id provided');
    }
    $stmt->close();

    /**
     * 4. Update the student's section
     */
    $update_query = "
        UPDATE students_sections 
        SET section_id = ?, section_code = ?, updated_at = NOW()
        WHERE s_id = ?
    ";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('isi', $new_section_id, $new_section_code, $student_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update student section');
    }

    // Check if any row was actually updated
    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made. Update did not affect any rows.');
    }

    $stmt->close();

    /**
     * 5. Commit transaction
     */
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Student transferred successfully',
        'section_code' => $new_section_code,
        'old_section_id' => $current_section_id,
        'new_section_id' => $new_section_id
    ]);

} catch (Exception $e) {
    // Rollback if an error occurs during transaction
    if ($conn->errno) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
