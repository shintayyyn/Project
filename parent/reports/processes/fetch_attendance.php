<?php

require_once __DIR__ . '/../../../includes/db.php';
session_start();
$parent_id = $_SESSION['user_id'];

$student_id = $_GET['child'] ?? '';
$selected_month = $_GET['month'] ?? '';
$selected_term_id = $_GET['term_id'] ?? '';

// Attendance query
$query = "SELECT a.s_id, s.idcode, s.s_fname, a.subject_code, a.section_code,
                 a.time_in, a.time_out, a.status,
                 CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
          FROM attendance a
          INNER JOIN students s ON a.s_id = s.s_id
          LEFT JOIN academic_terms t ON a.term_id = t.term_id
          LEFT JOIN academic_years y ON t.ay_id = y.ay_id
          WHERE a.s_id = ?";
$params = [$student_id];
$types = "i";

if(!empty($selected_month)){
    // $selected_month is "YYYY-MM"
    $parts = explode('-', $selected_month);
    $year = intval($parts[0]);
    $month = intval($parts[1]);

    $query .= " AND MONTH(a.time_in) = ? AND YEAR(a.time_in) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= "ii";
}


if(!empty($selected_term_id)){
    $query .= " AND t.term_id = ?";
    $params[] = intval($selected_term_id);
    $types .= "i";
}

$query .= " ORDER BY a.time_in DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = [
        'idcode' => $row['idcode'],
        'subject_code' => $row['subject_code'],
        'section_code' => $row['section_code'],
        'date' => date('M d, Y', strtotime($row['time_in'])),
        'time' => date('h:i A', strtotime($row['time_in'])) . ' - ' . ($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-'),
        'term_name' => $row['term_name'],
        'status' => ucfirst($row['status'])
    ];
}
echo json_encode($data);
