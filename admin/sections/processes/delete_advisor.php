<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['section_id'])) {
        throw new Exception('Section ID is required');
    }

    $section_id = (int)$data['section_id'];

    if ($section_id <= 0) {
        throw new Exception('Invalid Section ID');
    }

    // ------------------ Fetch active term ------------------
    $term_stmt = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
    if (!$term_stmt || !($term_row = $term_stmt->fetch_assoc())) {
        throw new Exception('No active academic term found.');
    }
    $term_id = (int)$term_row['term_id'];

    // ------------------ Delete the advisor assignment for the current term ------------------
    $query = "DELETE FROM sections_advisors WHERE section_id = ? AND term_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $section_id, $term_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Advisor removed successfully for the current term',
            'term_id' => $term_id
        ]);
    } else {
        throw new Exception('Failed to remove advisor');
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
