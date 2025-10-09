<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['code']) || trim($_GET['code']) === '') {
    echo json_encode(['error' => 'Missing subject code']);
    exit;
}

$code = trim($_GET['code']);
$stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode(['subject_id' => $row['subject_id']]);
} else {
    echo json_encode(['error' => 'Invalid subject_code']);
}
