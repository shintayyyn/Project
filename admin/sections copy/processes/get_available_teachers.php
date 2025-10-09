<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $mode = $_GET['mode'] ?? '';
    $current_advisor = isset($_GET['current_advisor']) && $_GET['current_advisor'] !== '' 
                        ? intval($_GET['current_advisor']) 
                        : null;

    /**
     * Fetch teachers:
     * - With fewer than 2 sections
     * - OR the current advisor (to allow keeping them when editing)
     */
    $query = "
        SELECT t.t_id, t.t_lname, t.t_fname, t.t_mname, 
               COUNT(sa.section_id) AS assigned_count
        FROM teachers t
        LEFT JOIN sections_advisors sa ON t.t_id = sa.t_id
        GROUP BY t.t_id
        HAVING assigned_count < 2
        OR t.t_id = ?
        ORDER BY t.t_lname, t.t_fname
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $current_advisor);
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
        'teachers' => $teachers
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch teachers: ' . $e->getMessage()
    ]);
}
