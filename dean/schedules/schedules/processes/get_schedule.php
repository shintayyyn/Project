<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Schedule ID is required');
    }

    $schedule_id = intval($_GET['id']);

    // Get schedule details
    $query = "SELECT ss.*, 
                     CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') as teacher_name
              FROM sections_schedules ss
              LEFT JOIN teachers t ON ss.teacher_id = t.t_id
              WHERE ss.ss_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Schedule not found');
    }

    $schedule = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'schedule' => $schedule
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
