<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow admin users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

if (!isset($_GET['section_id'])) {
    echo json_encode(['students' => [], 'count' => 0]);
    exit();
}

$section_id = intval($_GET['section_id']);

$query = "
    SELECT s.s_id, s.idcode, s.s_lname, s.s_fname, s.s_mname, sd.degree_code
    FROM students s
    JOIN students_sections ss ON s.s_id = ss.s_id
    JOIN students_degrees sd ON s.s_id = sd.s_id
    WHERE ss.section_id = ?
    ORDER BY s.s_lname, s.s_fname
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$students_count = $result->num_rows; // ✅ Accurate even after transfer

header('Content-Type: application/json');
echo json_encode([
    'students' => $students,
    'count' => $students_count
]);
exit();
