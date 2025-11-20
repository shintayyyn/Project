<?php
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    echo json_encode([]);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$term_id = (int)($_GET['semester'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
$startDate = $month.'-01';
$endDate = date('Y-m-t', strtotime($startDate));

$section_subject = $_GET['section_subject'] ?? '';
$section_id = null;
$subject_code = null;
if($section_subject){
    list($section_id, $subject_code) = explode('|', $section_subject);
    $section_id = (int)$section_id;
}

// Fetch teacher's sections and subjects
$stmt = $conn->prepare("
    SELECT s.section_id, s.section_name, ss.subject_code
    FROM sections_schedules ss
    INNER JOIN sections s ON ss.section_id = s.section_id
    WHERE ss.teacher_id = ? AND ss.term_id = ?
");
$stmt->bind_param("ii", $teacher_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

$teacher_sections = [];
$section_subject_map = [];
while($row = $result->fetch_assoc()){
    $teacher_sections[$row['section_id']] = $row['section_name'];
    $section_subject_map[$row['section_id']][] = $row['subject_code'];
}
$stmt->close();

// Get students filtered by section
$students_by_section = [];
$all_section_ids = $section_id ? [$section_id] : array_keys($teacher_sections);
if($all_section_ids){
    $placeholders = implode(',', array_fill(0,count($all_section_ids),'?'));
    $types = str_repeat('i', count($all_section_ids));
    $stmt = $conn->prepare("
        SELECT ss.section_id, s.s_id,
               CONCAT(s.s_lname, IF(s.s_suffix!='', CONCAT(' ',s.s_suffix), ''), ', ',
                      s.s_fname, IF(s.s_mname!='', CONCAT(' ',LEFT(s.s_mname,1),'.'), '')) AS student_name
        FROM students_sections ss
        JOIN students s ON ss.s_id = s.s_id
        WHERE ss.section_id IN ($placeholders)
        ORDER BY ss.section_id, s.s_lname, s.s_fname
    ");
    $stmt->bind_param($types, ...$all_section_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $students_by_section[$row['section_id']][] = $row;
    }
    $stmt->close();
}

// Fetch attendance counts
$data = [];
foreach($students_by_section as $section_id => $students){
    foreach($students as $stu){
        $subjects = $subject_code ? [$subject_code] : $section_subject_map[$section_id];
        foreach($subjects as $subj){
            $stmt = $conn->prepare("
                SELECT status, COUNT(*) AS count
                FROM attendance
                WHERE s_id = ? AND subject_code = ? AND term_id = ? AND DATE(time_in) BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->bind_param("isiss", $stu['s_id'], $subj, $term_id, $startDate, $endDate);
            $stmt->execute();
            $res = $stmt->get_result();
            $counts = ['Present'=>0,'Late'=>0,'Absent'=>0,'Excused'=>0];
            while($row = $res->fetch_assoc()){
                $status = ucfirst(strtolower(trim($row['status'])));
                if(isset($counts[$status])) $counts[$status] = (int)$row['count'];
            }
            $stmt->close();

            $data[] = [
                'student_name'=>$stu['student_name'],
                'subject_code'=>$subj,
                'Present'=>$counts['Present'],
                'Late'=>$counts['Late'],
                'Absent'=>$counts['Absent'],
                'Excused'=>$counts['Excused']
            ];
        }
    }
}

echo json_encode($data);
