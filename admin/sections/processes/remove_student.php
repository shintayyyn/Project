<?php
require_once '../../../includes/db.php';
header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['student_id']) || !isset($data['section_id'])) {
        throw new Exception('Missing required fields');
    }

    $student_id = (int)$data['student_id'];
    $section_id = (int)$data['section_id'];

    // Start transaction
    $conn->begin_transaction();

    // --- 1️⃣ Remove student-section link ---
    $delete_query = "DELETE FROM students_sections 
                     WHERE s_id = ? AND section_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $student_id, $section_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to unassign student from section');
    }

    // --- 2️⃣ Update generatedqrcode.section to NULL or 'Unassigned' ---
    $update_qr_query = "UPDATE generatedqrcode 
                        SET section = NULL, updated_at = NOW()
                        WHERE id = ?";
    $stmt2 = $conn->prepare($update_qr_query);
    $stmt2->bind_param('i', $student_id);
    if (!$stmt2->execute()) {
        throw new Exception('Failed to update generated QR record');
    }

    // Commit all operations
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student unassigned successfully, QR record updated.'
    ]);

} catch (Exception $e) {
    if ($conn->connect_errno == 0) {
        $conn->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
