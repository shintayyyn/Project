<?php
require_once __DIR__ . '/../../../includes/db.php';
header('Content-Type: application/json');

try {
    session_start();
    $dean_id = $_SESSION['user_id'] ?? 0;

    // Fetch degrees assigned to the dean
    $degree_ids = [];
    $stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE dean_id = ?");
    $stmt->bind_param("i", $dean_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $degree_ids[] = intval($row['degree_id']);
    }
    $stmt->close();

    if (empty($degree_ids)) {
        throw new Exception('No degrees assigned to this dean.');
    }

    $placeholders = implode(',', array_fill(0, count($degree_ids), '?'));

    // Get active term
    $term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
    if (!$term_result || !($term_row = $term_result->fetch_assoc())) {
        throw new Exception('No active academic term found.');
    }
    $active_term_id = (int)$term_row['term_id'];

    // Fetch teachers under dean's degrees with <2 sections OR dean themselves
  $query = "
    SELECT DISTINCT 
        t.t_id, 
        t.t_lname, 
        t.t_fname, 
        t.t_mname, 
        COALESCE(sa_counts.assigned_count,0) AS assigned_count
    FROM teachers t
    INNER JOIN degrees d ON t.t_department = d.degree_id
    LEFT JOIN (
        SELECT t_id, COUNT(section_id) AS assigned_count
        FROM sections_advisors
        WHERE term_id = ? AND is_active = 1
        GROUP BY t_id
    ) sa_counts ON t.t_id = sa_counts.t_id
    WHERE d.degree_id IN ($placeholders)
      AND (COALESCE(sa_counts.assigned_count,0) < 2 OR t.t_id = ?)
    ORDER BY t.t_lname, t.t_fname
";


    $stmt = $conn->prepare($query);

    $params = array_merge([$active_term_id], $degree_ids, [$dean_id]);
    $types = str_repeat('i', count($params));

    $tmp = [];
    foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
    array_unshift($tmp, $types);

    call_user_func_array([$stmt, 'bind_param'], $tmp);
    $stmt->execute();
    $result = $stmt->get_result();

    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        // Use t_id as key to guarantee uniqueness
        $teachers[$row['t_id']] = [
            't_id' => $row['t_id'],
            't_lname' => $row['t_lname'],
            't_fname' => $row['t_fname'],
            't_mname' => $row['t_mname'],
            'assigned_count' => (int)$row['assigned_count']
        ];
    }

    echo json_encode([
        'success' => true,
        'teachers' => array_values($teachers), // re-index array
        'term_id' => $active_term_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch teachers: ' . $e->getMessage()
    ]);
}
