<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['schedule_id'])) {
        throw new Exception('Schedule ID is required');
    }

    $schedule_id = intval($data['schedule_id']);

    // Delete the schedule
    $delete_query = "DELETE FROM sections_schedules WHERE ss_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $schedule_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to delete schedule');
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Schedule not found');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
