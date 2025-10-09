<?php
require_once('../../../includes/db.php');

header('Content-Type: application/json');


// Get POST data
$s_id = $_POST['id'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$section = $_POST['section'] ?? '';

// Validate required fields
if (!$s_id || !$full_name || !$section) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit();
}

// Generate QR Code string
$randomHash = substr(md5(uniqid(mt_rand(), true)), 0, 8);
$qrCodeText = "STUDENT-{$s_id}-{$randomHash}";

// Check if already exists
$check = $conn->prepare("SELECT id FROM generatedqrcode WHERE id = ?");
$check->bind_param("i", $s_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'QR Code already exists for this student.']);
    exit();
}
$check->close();

// Insert into database
$stmt = $conn->prepare("
    INSERT INTO generatedqrcode (id, full_name, section, generated_qrcode, created_at, updated_at)
    VALUES (?, ?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("isss", $s_id, $full_name, $section, $qrCodeText);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'qr' => [
            'id' => $s_id,
            'full_name' => $full_name,
            'section' => $section,
            'generated_qrcode' => $qrCodeText,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
