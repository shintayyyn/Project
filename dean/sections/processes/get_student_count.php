<?php
require_once '../../../includes/db.php';

$section_id = $_GET['section_id'] ?? 0;
$term_id = $_GET['term_id'] ?? 0;

if ($section_id && $term_id) {
    $query = "
        SELECT COUNT(DISTINCT s_id) AS student_count
        FROM students_sections
        WHERE section_id = ? AND term_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $section_id, $term_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    error_log("Section: $section_id | Term: $term_id");
error_log("Result: " . print_r($result, true));


    echo json_encode([
        'count' => $result['student_count'] ?? 0,
        'text' => ($result['student_count'] ?? 0) . ' ' . 
                  (($result['student_count'] ?? 0) == 1 ? 'Student' : 'Students'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}

