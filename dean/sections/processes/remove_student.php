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

    // --- 2️⃣ Update generatedqrcode.section = NULL ---
    $update_qr_query = "UPDATE generatedqrcode 
                        SET section = NULL, updated_at = NOW()
                        WHERE id = ?";

    $stmt2 = $conn->prepare($update_qr_query);
    $stmt2->bind_param('i', $student_id);

    if (!$stmt2->execute()) {
        throw new Exception('Failed to update generated QR record');
    }

    // --- 3️⃣ Check remaining assigned sections ---
    $check_query = "SELECT COUNT(*) AS total FROM students_sections WHERE s_id = ?";
    $stmt3 = $conn->prepare($check_query);
    $stmt3->bind_param('i', $student_id);
    $stmt3->execute();
    $result = $stmt3->get_result()->fetch_assoc();

    $remaining = (int)$result['total'];

    // --- 4️⃣ If no more sections, update enrollment_status ---
    if ($remaining === 0) {
        $update_status_query = "UPDATE students 
                                SET enrollment_status = 'Not yet Enrolled'
                                WHERE s_id = ?";

        $stmt4 = $conn->prepare($update_status_query);
        $stmt4->bind_param('i', $student_id);

        if (!$stmt4->execute()) {
            throw new Exception('Failed to update student enrollment status');
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student unassigned successfully, QR updated, and enrollment status updated.'
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
