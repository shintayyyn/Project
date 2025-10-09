<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$student_id = $_SESSION['user_id'];
$last_check = isset($_GET['last_check']) ? floatval($_GET['last_check']) : 0;

// Get the latest update timestamp for this student's schedule
$query = "SELECT UNIX_TIMESTAMP(MAX(ss.updated_at)) as last_update,
                MAX(ss.updated_at) as formatted_time
          FROM sections_schedules ss
          JOIN students_sections sts ON ss.section_id = sts.section_id
          WHERE sts.s_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$current_update = floatval($row['last_update']);
if ($current_update <= 0) {
    $current_update = time();
}

// Add error logging
error_log("Check Updates - Last Check: " . $last_check . ", Current Update: " . $current_update . ", Raw Time: " . $row['formatted_time']);

header('Content-Type: application/json');
echo json_encode([
    'hasUpdates' => $current_update > $last_check,
    'timestamp' => $current_update,
    'debug' => [
        'last_check' => $last_check,
        'current_update' => $current_update,
        'formatted_time' => $row['formatted_time']
    ]
]);