<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$parent_id = $_POST['p_id'] ?? null;
$fname = $_POST['p_fname'] ?? '';
$lname = $_POST['p_lname'] ?? '';
$mname = $_POST['p_mname'] ?? '';
$suffix = $_POST['p_suffix'] ?? '';
$gender = $_POST['p_gender'] ?? '';
$bdate = $_POST['p_bdate'] ?? '';
$cnum = $_POST['p_cnum'] ?? '';
$email = $_POST['p_email'] ?? '';
$status = $_POST['p_status'] ?? 'active';
$child_id = $_POST['child_id'] ?? null;

// Validate required fields
if (!$parent_id || !$fname || !$lname || !$gender || !$bdate || !$cnum || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Update parent
$stmt = $conn->prepare("UPDATE parents SET p_fname = ?, p_lname = ?, p_mname = ?, p_suffix = ?, p_gender = ?, p_bdate = ?, p_cnum = ?, p_email = ?, p_status = ? WHERE p_id = ?");
$stmt->bind_param("sssssssssi", $fname, $lname, $mname, $suffix, $gender, $bdate, $cnum, $email, $status, $parent_id);

if ($stmt->execute()) {
    // Clear existing child links
    $link_stmt = $conn->prepare("DELETE FROM parent_student WHERE p_id = ?");
    $link_stmt->bind_param("i", $parent_id);
    $link_stmt->execute();

    // Re-link if child is selected
    if (!empty($child_id)) {
        $link_stmt = $conn->prepare("INSERT INTO parent_student (p_id, s_id) VALUES (?, ?)");
        $link_stmt->bind_param("ii", $parent_id, $child_id);
        $link_stmt->execute();
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update parent']);
}
?>
