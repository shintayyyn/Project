<?php
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$id = (int)$_POST['id'];
$stmt = $conn->prepare("SELECT raw_data FROM upload_history WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($raw_data_json);
$stmt->fetch();
$stmt->close();

if (!$raw_data_json) {
    http_response_code(404);
    echo 'No data found.';
    exit;
}

// Convert JSON back to CSV format string
$data = json_decode($raw_data_json, true);
if (!$data || !is_array($data)) {
    http_response_code(500);
    echo 'Invalid data format.';
    exit;
}

// Convert to CSV-like string for JS
$csv = "";
foreach ($data as $row) {
    $csv .= implode(',', array_map(fn($v) => str_replace(",", " ", $v), $row)) . "\n";
}

echo $csv;
