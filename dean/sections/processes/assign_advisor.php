<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['section_id']) || !isset($data['advisor_id'])) {
        throw new Exception('Missing required fields');
    }

    $section_id = $data['section_id'];
    $advisor_id = $data['advisor_id'];

    // First, remove any existing advisor
    $delete_query = "DELETE FROM sections_advisors WHERE section_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $section_id);
    $delete_stmt->execute();

    // Then insert new advisor
    $insert_query = "INSERT INTO sections_advisors (section_id, t_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("ii", $section_id, $advisor_id);
    
    if ($insert_stmt->execute()) {
        // Get advisor details for response
        $advisor_query = "SELECT t_id, t_lname, t_fname, t_mname FROM teachers WHERE t_id = ?";
        $advisor_stmt = $conn->prepare($advisor_query);
        $advisor_stmt->bind_param("i", $advisor_id);
        $advisor_stmt->execute();
        $result = $advisor_stmt->get_result();
        $advisor = $result->fetch_assoc();

        if (!$advisor) {
            throw new Exception('Failed to retrieve advisor details');
        }

        // Format the advisor name
        $advisor_name = $advisor['t_lname'] . ', ' . $advisor['t_fname'];
        if (!empty($advisor['t_mname'])) {
            $advisor_name .= ' ' . substr($advisor['t_mname'], 0, 1) . '.';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Advisor assigned successfully',
            'advisor_name' => $advisor_name,
            't_id' => $advisor_id
        ]);
    } else {
        throw new Exception('Failed to assign advisor');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>