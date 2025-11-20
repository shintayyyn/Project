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
$stmt = $conn->prepare("SELECT p_id, p_fname, p_lname, p_mname, p_suffix, p_gender, p_bdate, p_status, p_cnum, p_email, p_address 
                        FROM parents WHERE p_id = ?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();

if (!$parent) {
    http_response_code(404);
    echo json_encode(['error' => 'Parent not found']);
    exit();
}

// Generate full name for frontend convenience
$parent['full_name'] = $parent['p_lname'] . ', ' . $parent['p_fname'] .
    (!empty($parent['p_mname']) ? ' ' . strtoupper(substr($parent['p_mname'], 0, 1)) . '.' : '') .
    (!empty($parent['p_suffix']) ? ' ' . $parent['p_suffix'] : '');

// Fetch current linked children IDs
$parent_child_stmt = $conn->prepare("SELECT s_id FROM parent_student WHERE p_id = ?");
$parent_child_stmt->bind_param("i", $parent_id);
$parent_child_stmt->execute();
$parent_child_result = $parent_child_stmt->get_result();

$linked_children_ids = [];
while ($row = $parent_child_result->fetch_assoc()) {
    $linked_children_ids[] = $row['s_id'];
}

// Determine which students to fetch
if (empty($linked_children_ids)) {
    // No existing links → fetch all students
    $child_stmt = $conn->prepare("
        SELECT s.s_id,
               CONCAT(s.s_lname, IF(s.s_suffix != '', CONCAT(' ', s.s_suffix), ''), ', ', s.s_fname, 
                      IF(s.s_mname != '', CONCAT(' ', LEFT(s.s_mname,1), '.'), '')
               ) AS full_name,
               sec.section_code,
               s.s_address
        FROM students s
        LEFT JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN sections sec ON ss.section_id = sec.section_id
        ORDER BY s.s_lname, s.s_fname
    ");
} else {
    // Has links → fetch only linked students
    $placeholders = implode(',', array_fill(0, count($linked_children_ids), '?'));
    $types = str_repeat('i', count($linked_children_ids));
    $child_stmt = $conn->prepare("
        SELECT s.s_id,
               CONCAT(s.s_lname, IF(s.s_suffix != '', CONCAT(' ', s.s_suffix), ''), ', ', s.s_fname, 
                      IF(s.s_mname != '', CONCAT(' ', LEFT(s.s_mname,1), '.'), '')
               ) AS full_name,
               sec.section_code,
               s.s_address
        FROM students s
        LEFT JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN sections sec ON ss.section_id = sec.section_id
        WHERE s.s_id IN ($placeholders)
        ORDER BY s.s_lname, s.s_fname
    ");
    $child_stmt->bind_param($types, ...$linked_children_ids);
}

$child_stmt->execute();
$child_result = $child_stmt->get_result();

$children = [];
while ($row = $child_result->fetch_assoc()) {
    $children[] = [
        's_id' => $row['s_id'],
        'full_name' => $row['full_name'],
        'section_code' => $row['section_code'] ?? 'N/A',
        's_address' => $row['s_address'] ?? ''
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'parent' => $parent,
    'children' => $children
]);
