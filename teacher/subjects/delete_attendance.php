<?php
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_id'])) {
    $id = intval($_POST['attendance_id']);

    $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
