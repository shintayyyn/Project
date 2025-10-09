<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

// Permission check
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

// Get active term
$current_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$term_id = $current_term['term_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS unassigned_count
    FROM students s
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id AND ss.term_id = ?
    WHERE ss.section_id IS NULL AND s.term_id = ?
");
$stmt->bind_param("ii", $term_id, $term_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'count' => $result['unassigned_count'] ?? 0
]);
exit();
