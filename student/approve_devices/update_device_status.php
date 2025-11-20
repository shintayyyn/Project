<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

header('Content-Type: application/json');

// ✅ TEMP: Show PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Capture all fatal errors
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        "success" => false,
        "message" => "PHP Error",
        "error_type" => $errno,
        "error_message" => $errstr,
        "file" => $errfile,
        "line" => $errline
    ]);
    exit();
}
set_error_handler("jsonErrorHandler");

// ✅ Catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL) {
        echo json_encode([
            "success" => false,
            "message" => "Fatal Error",
            "error" => $error
        ]);
    }
});


// ✅ Read POST safely
$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

// ✅ TEMP debug — see exactly what the server receives
if (!$id || !$status) {
    echo json_encode([
        "success" => false,
        "message" => "Missing Parameters",
        "received_POST" => $_POST,
        "raw_body" => file_get_contents("php://input")
    ]);
    exit();
}


// ✅ Update DB
$stmt = $conn->prepare("UPDATE approved_devices SET status = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();

if ($stmt->affected_rows >= 0) {

    // ✅ Get updated timestamp
    $stmt2 = $conn->prepare("SELECT updated_at FROM approved_devices WHERE id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->bind_result($updated_at);
    $stmt2->fetch();
    $stmt2->close();

    echo json_encode([
        "success" => true,
        "message" => "Device status updated",
        "updated_at" => $updated_at
    ]);
    exit();
}

echo json_encode([
    "success" => false,
    "message" => "Database update failed",
]);
exit();
