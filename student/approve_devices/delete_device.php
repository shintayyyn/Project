<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

header('Content-Type: application/json');

// Ensure only Mayors can delete devices
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing device ID.']);
    exit;
}

$device_id = intval($_POST['id']);

// Check if device exists
$check = $conn->prepare("SELECT * FROM approved_devices WHERE id = ?");
$check->bind_param("i", $device_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Device not found.']);
    exit;
}

// Delete record
$del = $conn->prepare("DELETE FROM approved_devices WHERE id = ?");
$del->bind_param("i", $device_id);

if ($del->execute()) {
    echo json_encode(['success' => true, 'message' => 'Deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete device.']);
}
?>
