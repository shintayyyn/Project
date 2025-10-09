<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['section_id'])) {
        throw new Exception('Section ID is required');
    }

    $section_id = $data['section_id'];
    
    // Delete the advisor assignment
    $query = "DELETE FROM sections_advisors WHERE section_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $section_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Advisor removed successfully'
        ]);
    } else {
        throw new Exception('Failed to remove advisor');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>