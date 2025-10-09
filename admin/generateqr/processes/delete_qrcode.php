<?php
require_once('../../../includes/db.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing QR Code ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM generatedqrcode WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'QR code deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete QR code.']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
