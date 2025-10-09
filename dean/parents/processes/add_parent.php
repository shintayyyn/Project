<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$fname   = $_POST['p_fname'] ?? '';
$lname   = $_POST['p_lname'] ?? '';
$mname   = $_POST['p_mname'] ?? '';
$suffix  = $_POST['p_suffix'] ?? '';
$gender  = $_POST['p_gender'] ?? '';
$bdate   = $_POST['p_bdate'] ?? '';
$cnum    = $_POST['p_cnum'] ?? '';
$email   = $_POST['p_email'] ?? '';
$status  = $_POST['p_status'] ?? 'active';
$child_id = $_POST['child_id'] ?? null;

// Validate required fields
if (!$fname || !$lname || !$gender || !$bdate || !$cnum || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Auto-generate password: FirstInitial + Lastname + MMDDYYYY
$formatted_bdate = date('mdY', strtotime($bdate));
$password_plain = strtoupper(substr($fname, 0, 1)) . $lname . $formatted_bdate;
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Insert parent
$stmt = $conn->prepare("INSERT INTO parents (
    p_fname, p_lname, p_mname, p_suffix, p_gender, p_bdate, 
    p_cnum, p_email, p_password, p_password_plain, p_status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("sssssssssss",
    $fname, $lname, $mname, $suffix, $gender, $bdate,
    $cnum, $email, $password_hash, $password_plain, $status
);

if ($stmt->execute()) {
    $parent_id = $stmt->insert_id;

    // Link to child if provided
    if (!empty($child_id)) {
        $link_stmt = $conn->prepare("INSERT INTO parent_student (p_id, s_id) VALUES (?, ?)");
        $link_stmt->bind_param("ii", $parent_id, $child_id);
        $link_stmt->execute();
    }

    echo json_encode(['success' => true, 'parent_id' => $parent_id, 'p_password_plain' => $password_plain]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add parent', 'details' => $stmt->error]);
}
?>
