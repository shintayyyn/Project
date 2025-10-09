<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Update device status
$stmt = $conn->prepare("UPDATE approved_devices SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
