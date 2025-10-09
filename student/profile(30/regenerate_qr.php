<?php
session_start();
require_once('../../includes/db.php');
header('Content-Type: application/json');

// Check auth
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
$randomHash = bin2hex(random_bytes(8)); // 16-char random hash
$new_qr = "STUDENT-{$student_id}-{$randomHash}"; // format: STUDENT-ID-HASH

// Update or insert
$stmt = $conn->prepare("INSERT INTO generatedqrcode (id, generated_qrcode) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE generated_qrcode = VALUES(generated_qrcode)");
$stmt->bind_param("is", $student_id, $new_qr);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'QR Code regenerated successfully!',
        'qr' => $new_qr
    ]);
} else {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Database error: ' . $stmt->error
    ]);
}
$stmt->close();
