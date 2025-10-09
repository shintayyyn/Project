<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$parent_id = $_POST['p_id'] ?? null;
if (!$parent_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parent ID']);
    exit();
}

// Delete parent_student links first
$link_stmt = $conn->prepare("DELETE FROM parent_student WHERE p_id = ?");
$link_stmt->bind_param("i", $parent_id);
$link_stmt->execute();

// Delete parent
$stmt = $conn->prepare("DELETE FROM parents WHERE p_id = ?");
$stmt->bind_param("i", $parent_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete parent']);
}
?>
