<?php
require_once __DIR__ . '/../../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['parent_id'])) exit();

$parent_id = $_SESSION['parent_id'];

$month = $_GET['month'] ?? date('Y-m');
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

$startDate = $month . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// Fetch children
$stmt = $conn->prepare("
    SELECT s.s_id, CONCAT(s.s_fname,' ',IFNULL(s.s_mname,''),' ',s.s_lname,' ',IFNULL(s.s_suffix,'')) AS student_name
    FROM parent_student ps
    INNER JOIN students s ON ps.s_id = s.s_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname, s.s_fname
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch attendance
$attendance = [];
foreach ($students as $stu) {
    $s_id = $stu['s_id'];

    $stmt = $conn->prepare("
        SELECT ss.section_id, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time, sec.section_code
        FROM students_sections stus
        INNER JOIN sections_schedules ss ON stus.section_id = ss.section_id
        INNER JOIN sections sec ON sec.section_id = stus.section_id
        WHERE stus.s_id = ?" . ($semester>0?" AND ss.semester=?":"")
    );

    if($semester>0) $stmt->bind_param("ii",$s_id,$semester);
    else $stmt->bind_param("i",$s_id);

    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($subjects as $subj) {
        $present = $late = $absent = 0;

        $stmt = $conn->prepare("
            SELECT status
            FROM attendance
            WHERE s_id=? AND subject_code=? AND DATE(time_in) BETWEEN ? AND ?
        ");
        $stmt->bind_param("isss",$s_id,$subj['subject_code'],$startDate,$endDate);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach($records as $r){
            $status = strtoupper($r['status']);
            if($status==='PRESENT') $present++;
            elseif($status==='LATE') $late++;
            elseif($status==='ABSENT') $absent++;
        }

        $attendance[$s_id][$subj['subject_code']] = [
            'section' => $subj['section_code'],
            'days' => $subj['day_of_week']." (".date("g:i A",strtotime($subj['start_time']))." - ".date("g:i A",strtotime($subj['end_time'])).")",
            'present'=>$present,
            'late'=>$late,
            'absent'=>$absent
        ];
    }
}

// Generate tbody HTML
$i = 1;
foreach($students as $stu){
    $s_id = $stu['s_id'];
    $student_name = $stu['student_name'];
    if(isset($attendance[$s_id])){
        foreach($attendance[$s_id] as $subject_code => $att){
            echo '<tr>
                <td></td>
                <td>'.htmlspecialchars($s_id).'</td>
                <td>'.htmlspecialchars($student_name).'</td>
                <td>'.htmlspecialchars($subject_code).'</td>
                <td>'.htmlspecialchars($att['days']).'</td>
                <td>'.$att['present'].'</td>
                <td>'.$att['late'].'</td>
                <td>'.$att['absent'].'</td>
            </tr>';
        }
    }
}
