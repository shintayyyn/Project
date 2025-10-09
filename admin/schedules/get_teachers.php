<?php
require_once __DIR__ . '/../../includes/db.php';

$department_id = intval($_GET['department_id'] ?? 0);

$query = "SELECT t_id, 
                 CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1), ''), '.') AS teacher_name
          FROM teachers
          WHERE department_id = ? AND t_status = 'active'
          ORDER BY t_lname ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

echo json_encode($teachers);
