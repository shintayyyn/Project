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
    SELECT COUNT(DISTINCT s.s_id) AS unassigned_count
    FROM students s
    LEFT JOIN students_sections ss
           ON s.s_id = ss.s_id
          AND ss.term_id = ?
    WHERE ss.section_id IS NULL
      AND s.is_regular = 1
      AND s.s_status = 'active'
      AND (
            s.enrollment_status LIKE 'Promoted%' 
            OR s.enrollment_status = 'Not yet Enrolled'
          )
");
$stmt->bind_param("i", $term_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'count' => $result['unassigned_count'] ?? 0
]);
exit();
