<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Method Not Allowed',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit();
}

$parent_id = $_POST['p_id'] ?? null;
if (!$parent_id) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Missing ID',
        'message' => 'Parent ID is required to delete.'
    ]);
    exit();
}

$conn->begin_transaction();

try {
    // Delete parent-student links first
    $link_stmt = $conn->prepare("DELETE FROM parent_student WHERE p_id = ?");
    $link_stmt->bind_param("i", $parent_id);
    $link_stmt->execute();
    $link_stmt->close();

    // Delete parent
    $stmt = $conn->prepare("DELETE FROM parents WHERE p_id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'title'   => 'Deleted',
        'message' => 'Parent has been deleted successfully.'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'title'   => 'Deletion Failed',
        'message' => 'Failed to delete parent: ' . $e->getMessage()
    ]);
}
