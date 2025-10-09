<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$parent_id = $_GET['id'] ?? null;
if (!$parent_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parent ID']);
    exit();
}

// Fetch parent info
$stmt = $conn->prepare("SELECT p_id, p_fname, p_lname, p_mname, p_suffix, p_gender, p_bdate, p_status, p_cnum, p_email,p_password, p_password_plain FROM parents WHERE p_id = ?");

$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();

if (!$parent) {
    http_response_code(404);
    echo json_encode(['error' => 'Parent not found']);
    exit();
}

// Fetch children (with section info)
$child_stmt = $conn->prepare("
    SELECT 
        s.s_id,
        CONCAT(s.s_fname, ' ', s.s_mname, ' ', s.s_lname) AS full_name,
        sec.section_code
    FROM students s
    JOIN parent_student ps ON s.s_id = ps.s_id
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ps.p_id = ?
");
$child_stmt->bind_param("i", $parent_id);
$child_stmt->execute();
$child_result = $child_stmt->get_result();

$children = [];
while ($row = $child_result->fetch_assoc()) {
    $children[] = [
        's_id' => $row['s_id'],
        'full_name' => $row['full_name'],
        'section_code' => $row['section_code'] ?? 'N/A'
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'parent' => $parent,
    'children' => $children
]);
?>
