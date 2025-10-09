
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

// ------------------ Fetch active term ------------------
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$term_id = ($term_result && $row = $term_result->fetch_assoc()) ? $row['term_id'] : 0;

// ------------------ Fetch students for the active term ------------------
$query = "
    SELECT s.s_id, s.idcode, s.s_lname, s.s_fname, s.s_mname, sd.degree_code
    FROM students_sections ss
    INNER JOIN students s ON s.s_id = ss.s_id
    LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
    WHERE ss.section_id = ? AND ss.term_id = ?
    ORDER BY s.s_lname, s.s_fname
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $section_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$students_count = count($students);

// If no students, return a placeholder message
if ($students_count === 0) {
    $students[] = [
        'message' => 'No students assigned to this section for the active term.'
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'students' => $students,
    'count' => $students_count
]);
exit();
?>
