<?php
require_once '../includes/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : '';
$selected_term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : '';

// Base query
$query = "
SELECT 
    a.s_id,
    s.idcode,
    a.subject_code,
    a.section_code,
    a.time_in,
    a.time_out,
    a.status,
    CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
FROM attendance a
INNER JOIN students s ON a.s_id = s.s_id
LEFT JOIN academic_terms t ON a.term_id = t.term_id
LEFT JOIN academic_years y ON t.ay_id = y.ay_id
WHERE a.s_id = ?
";

$params = [$student_id];
$types = "i";

if (!empty($selected_month)) {
    $query .= " AND MONTH(a.time_in) = ?";
    $params[] = $selected_month;
    $types .= "i";
}

if (!empty($selected_term_id)) {
    $query .= " AND t.term_id = ?";
    $params[] = $selected_term_id;
    $types .= "i";
}

$query .= " ORDER BY a.time_in DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $result]);
