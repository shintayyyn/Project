<?php
require_once __DIR__ . '/../includes/db.php';

$s_id = $_GET['s_id'] ?? '';
$subject = $_GET['subject'] ?? '';

if (empty($s_id) || empty($subject)) {
    http_response_code(400);
    exit('Missing required parameters.');
}

// Fetch QR data from the database
$stmt = $conn->prepare("SELECT generated_qrcode FROM generatedqrcode WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $s_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $qrData = urlencode($row['generated_qrcode']);
    $qrUrl = "https://quickchart.io/qr?text={$qrData}&size=300";
    header("Location: $qrUrl");
    exit;
} else {
    http_response_code(404);
    exit('QR code not found.');
}
