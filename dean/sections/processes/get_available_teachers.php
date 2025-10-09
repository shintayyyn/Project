<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

$mode = $_GET['mode'] ?? '';
$current_advisor = $_GET['current_advisor'] ?? '';

if ($mode === 'edit' && $current_advisor) {
    // For edit mode, include the current advisor and unassigned teachers
    $query = "SELECT t.t_id, t.t_lname, t.t_fname, t.t_mname 
              FROM teachers t
              LEFT JOIN sections_advisors sa ON t.t_id = sa.t_id
              WHERE sa.t_id IS NULL OR t.t_id = ?
              ORDER BY t.t_lname, t.t_fname";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $current_advisor);
} else {
    // For add mode, only show unassigned teachers
    $query = "SELECT t.t_id, t.t_lname, t.t_fname, t.t_mname 
              FROM teachers t
              LEFT JOIN sections_advisors sa ON t.t_id = sa.t_id
              WHERE sa.t_id IS NULL
              ORDER BY t.t_lname, t.t_fname";
    
    $stmt = $conn->prepare($query);
}

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

echo json_encode(['success' => true, 'teachers' => $teachers]);
