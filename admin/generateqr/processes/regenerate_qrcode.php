<?php
require_once('../../../includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Get student ID first
    $stmt = $conn->prepare("SELECT id FROM generatedqrcode WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'QR record not found.']);
        exit();
    }

    $stmt->bind_result($student_id);
    $stmt->fetch();
    $stmt->close();

    // Format: STUDENT-{id}-{unique}
    $newCode = "STUDENT-{$id}-" . substr(md5(uniqid('', true)), 0, 8);

    // Update QR
    $update = $conn->prepare("UPDATE generatedqrcode SET generated_qrcode = ?, updated_at = NOW() WHERE id = ?");
    $update->bind_param("si", $newCode, $id);

    if ($update->execute()) {
        // Fetch updated timestamp
        $fetch = $conn->prepare("SELECT updated_at FROM generatedqrcode WHERE id = ?");
        $fetch->bind_param("i", $id);
        $fetch->execute();
        $fetch->bind_result($updated_at);
        $fetch->fetch();
        $fetch->close();

        echo json_encode([
            'status' => 'success',
            'qr' => [
                'id' => $id,
                'generated_qrcode' => $newCode,
                'updated_at' => $updated_at
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to regenerate QR.']);
    }

    $update->close();
}
?>
