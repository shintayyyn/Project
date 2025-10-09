<?php
require_once __DIR__ . '/../../../includes/db.php';
header('Content-Type: application/json');

try {
    $mode = $_GET['mode'] ?? '';
    $current_advisor = isset($_GET['current_advisor']) && $_GET['current_advisor'] !== '' 
                        ? intval($_GET['current_advisor']) 
                        : 0;

    // Optional: get term_id from query string, fallback to active term
    $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
    if ($term_id <= 0) {
        $term_stmt = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
        if (!$term_stmt || !($term_row = $term_stmt->fetch_assoc())) {
            throw new Exception('No active academic term found.');
        }
        $term_id = (int)$term_row['term_id'];
    }

    $query = "
        SELECT t.t_id, t.t_lname, t.t_fname, t.t_mname, 
               COUNT(sa.section_id) AS assigned_count
        FROM teachers t
        LEFT JOIN sections_advisors sa 
               ON t.t_id = sa.t_id AND sa.term_id = ?
        GROUP BY t.t_id
        HAVING assigned_count < 2 OR t.t_id = ?
        ORDER BY t.t_lname, t.t_fname
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $term_id, $current_advisor);
    $stmt->execute();
    $result = $stmt->get_result();

    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = [
            't_id' => $row['t_id'],
            't_lname' => $row['t_lname'],
            't_fname' => $row['t_fname'],
            't_mname' => $row['t_mname']
        ];
    }

    echo json_encode([
        'success' => true,
        'teachers' => $teachers,
        'term_id' => $term_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch teachers: ' . $e->getMessage()
    ]);
}
