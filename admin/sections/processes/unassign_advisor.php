<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['section_id'])) {
        throw new Exception('Section ID is required');
    }

    $section_id = intval($data['section_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete from sections_advisors table
        $stmt = $conn->prepare("DELETE FROM sections_advisors WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        
        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Advisor unassigned successfully'
            ]);
        } else {
            throw new Exception("Failed to unassign advisor");
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
