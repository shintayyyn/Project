<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

// Permission check
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['dean', 'teacher'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

// Get active term
$current_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$term_id = $current_term['term_id'] ?? 0;

// =======================================
// If user is DEAN → get degree_code
// =======================================
$degree_filter_sql = "";
$degree_code_param = "";

if ($_SESSION['user_type'] === 'dean') {

    $stmtDean = $conn->prepare("
        SELECT degree_code
        FROM degrees
        WHERE dean_id = ?
        LIMIT 1
    ");
    $stmtDean->bind_param("i", $_SESSION['user_id']);
    $stmtDean->execute();
    $assignedDegree = $stmtDean->get_result()->fetch_assoc();

    if ($assignedDegree) {
        $degree_code_param = $assignedDegree['degree_code'];

        // apply filter
        $degree_filter_sql = " 
            AND EXISTS (
                SELECT 1 FROM students_degrees sd
                WHERE sd.s_id = s.s_id
                  AND sd.degree_code = ?
            )
        ";
    } else {
        echo json_encode([
            'success' => false,
            'count' => 0,
            'message' => 'Dean is not assigned to any degree.'
        ]);
        exit;
    }
}

// =======================================
// Main Query with optional degree filter
// =======================================

$sql = "
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
      $degree_filter_sql
";

$stmt = $conn->prepare($sql);

if ($_SESSION['user_type'] === 'dean') {
    // dean → bind term_id and degree_code
    $stmt->bind_param("is", $term_id, $degree_code_param);
} else {
    // teacher → bind only term_id
    $stmt->bind_param("i", $term_id);
}

$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'count' => $result['unassigned_count'] ?? 0
]);
exit();
