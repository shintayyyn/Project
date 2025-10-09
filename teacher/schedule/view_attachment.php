<?php
require_once __DIR__ . '/../../includes/db.php';
session_start();

$ss_id = $_GET['ss_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';
$user_type = $_SESSION['user_type'] ?? '';

if (!$ss_id || !$user_id || !$user_type) {
    http_response_code(400);
    echo 'Missing required parameters.';
    exit();
}

$stmt = $conn->prepare("
    SELECT file_name, file_type, file_data 
    FROM attachment_files 
    WHERE ss_id = ? AND uploaded_by_id = ? AND uploaded_by_type = ?
");
$stmt->bind_param("iis", $ss_id, $user_id, $user_type);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    header('Content-Description: inline file');
    header("Content-Type: " . $row['file_type']);
    header("Content-Disposition: inline; filename=\"" . basename($row['file_name']) . "\"");
    header("Content-Length: " . strlen($row['file_data']));
    echo $row['file_data'];
    exit();
} else {
    http_response_code(404);
    echo "Attachment not found.";
    exit();
}
?>
