<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_type']!=='teacher'){
    die("Unauthorized");
}

$teacher_id     = $_SESSION['user_id'];
$subject_select = $_POST['subject_select'] ?? 'all';
$term_id        = $_POST['semester'] ?? null;
$start_date     = $_POST['start_date'] ?? null;
$end_date       = $_POST['end_date'] ?? null;

if(!$term_id || !$start_date || !$end_date){
    die("Missing required fields");
}

// --- Fetch term info ---
$stmt = $conn->prepare("
    SELECT ay.year_start, ay.year_end, at.semester
    FROM academic_terms at
    JOIN academic_years ay ON at.ay_id = ay.ay_id
    WHERE at.term_id = ?
");
$stmt->bind_param("i", $term_id);
$stmt->execute();
$term = $stmt->get_result()->fetch_assoc();
$stmt->close();

$term_label = "A.Y. {$term['year_start']}-{$term['year_end']} | {$term['semester']}";

// --- Determine subjects ---
$subjects = [];
if($subject_select==='all'){
    $stmt = $conn->prepare("SELECT DISTINCT ss.section_id, ss.subject_code 
                            FROM sections_schedules ss
                            WHERE ss.teacher_id=? AND ss.term_id=?");
    $stmt->bind_param("ii",$teacher_id,$term_id);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    list($section_id,$subject_code) = explode('|',$subject_select);
    $subjects[] = ['section_id'=>$section_id,'subject_code'=>$subject_code];
}

// --- Create spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheetIndex = 0;

// Sort subjects array by section code (assuming section code has year level info like BSIT 1A)
usort($subjects, function($a, $b) use ($conn) {
    // Fetch section codes for sorting
    $stmtA = $conn->prepare("SELECT section_code FROM sections WHERE section_id=?");
    $stmtA->bind_param("i", $a['section_id']);
    $stmtA->execute();
    $secA = $stmtA->get_result()->fetch_assoc()['section_code'];
    $stmtA->close();

    $stmtB = $conn->prepare("SELECT section_code FROM sections WHERE section_id=?");
    $stmtB->bind_param("i", $b['section_id']);
    $stmtB->execute();
    $secB = $stmtB->get_result()->fetch_assoc()['section_code'];
    $stmtB->close();

    return strcmp($secA, $secB); // ascending order
});

foreach($subjects as $sheetIndex => $subj){
    $sheet = $sheetIndex===0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();

    // Fetch section info
    $stmt = $conn->prepare("SELECT section_code, section_name FROM sections WHERE section_id=?");
    $stmt->bind_param("i",$subj['section_id']);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Set sheet title: Subject Code + Section Code in parentheses
    $sheetTitle = $subj['subject_code'] . ' (' . $section['section_code'] . ')';
    $sheet->setTitle(substr($sheetTitle,0,31)); // Excel sheet title max 31 chars

    // Fetch section info
    $stmt = $conn->prepare("SELECT section_code, section_name FROM sections WHERE section_id=?");
    $stmt->bind_param("i",$subj['section_id']);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // --- Fetch schedule info for subject and selected term ---
    $stmt = $conn->prepare("
        SELECT day_of_week, start_time, end_time 
        FROM sections_schedules 
        WHERE section_id=? AND subject_code=? AND term_id=? 
    ");
    $stmt->bind_param("isi", $subj['section_id'], $subj['subject_code'], $term_id);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $dayMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
    $scheduleStrs = [];
    foreach($schedules as $sch){
        $dayName = is_numeric($sch['day_of_week']) ? ($dayMap[(int)$sch['day_of_week']] ?? $sch['day_of_week']) : ucfirst(substr($sch['day_of_week'],0,3));
        $timeRange = (!empty($sch['start_time']) && !empty($sch['end_time'])) 
                     ? date('h:i A', strtotime($sch['start_time'])) . ' - ' . date('h:i A', strtotime($sch['end_time'])) 
                     : '';
        $scheduleStrs[] = "{$dayName} {$timeRange}";
    }
    $subject_header = $subj['subject_code'] . (!empty($scheduleStrs) ? ' ['.implode(', ',$scheduleStrs).']' : '');

    // Fetch students (from both students_sections and subject_enrollments)
    $students = [];
    $stmt = $conn->prepare("
        SELECT DISTINCT s.s_id,
               s.is_regular,  -- ✅ Fetch is_regular
              CONCAT(
                s.s_lname,
                IF(s.s_suffix<>'', CONCAT(' ', s.s_suffix), ''),
                ', ',
                s.s_fname,
                IF(s.s_mname<>'', CONCAT(' ', LEFT(s.s_mname,1), '.'), '')
            ) AS student_name

        FROM students s
        LEFT JOIN students_sections ss 
            ON s.s_id = ss.s_id 
           AND ss.section_id = ? 
           AND ss.term_id = ? 
        LEFT JOIN subject_enrollments se 
            ON s.s_id = se.s_id 
           AND se.subject_code = ? 
           AND se.section_code = ? 
           AND se.term_id = ?
        WHERE (ss.section_id IS NOT NULL) OR (se.se_id IS NOT NULL)
        ORDER BY s.s_lname, s.s_fname
    ");
    $stmt->bind_param("iissi", $subj['section_id'], $term_id, $subj['subject_code'], $section['section_code'], $term_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("
    SELECT CONCAT(
        t.t_fname,
        IF(t.t_mname<>'', CONCAT(' ', LEFT(t.t_mname,1), '.'), ''),
        ' ',
        t.t_lname,
        IF(t.t_suffix<>'', CONCAT(' ', t.t_suffix), '')
    ) AS teacher_name
    FROM teachers t
    WHERE t.t_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$teacher_name = $teacher['teacher_name'] ?? 'Unknown';


// Function to calculate expected number of attendance days based on schedule
if (!function_exists('calcExpectedDays')) {
    function calcExpectedDays($schedules, $weeks = 18) {
        $daysPerWeek = count($schedules); // number of scheduled days per week
        return $daysPerWeek * $weeks; // total expected sessions in semester
    }
}


// --- Calculate accumulated days info ---
// Convert start and end dates to timestamps
$startDateTS = strtotime($start_date);
$endDateTS   = strtotime($end_date);

// Calculate total days between start and end (inclusive)
$totalCalendarDays = ($endDateTS - $startDateTS) / (60 * 60 * 24) + 1;

// Calculate weeks dynamically
$weeks = floor($totalCalendarDays / 7); // full weeks
$extraDays = $totalCalendarDays % 7;    // remaining days

$daysPerWeek = count($schedules);
$totalDays = $weeks * $daysPerWeek;

// Add extra scheduled days for remaining days
$scheduledWeekdays = array_map(function($sch){
    return (int)$sch['day_of_week'];
}, $schedules);
$scheduledWeekdays = array_unique($scheduledWeekdays);

$extraScheduledDays = 0;
for($i = 0; $i < $extraDays; $i++){
    $dayNum = (int)date('N', $startDateTS + $i * 86400); // day number of extra days
    if(in_array($dayNum, $scheduledWeekdays)){
        $extraScheduledDays++;
    }
}
$totalDays += $extraScheduledDays;

// Total hours calculation
$totalHours = 0;
foreach($schedules as $sch){
    if(!empty($sch['start_time']) && !empty($sch['end_time'])){
        $start = strtotime($sch['start_time']);
        $end   = strtotime($sch['end_time']);
        $hoursPerDay = ($end - $start)/3600; // decimal hours

        // Split hours into hours + minutes
        $hours = floor($hoursPerDay);
        $minutes = round(($hoursPerDay - $hours) * 60);

        $totalHoursPerDay = $hours * 60 + $minutes; // total minutes per day
        $totalHours += $totalHoursPerDay * $weeks;  // multiply by full weeks
    }
}

// Add extra days hours
foreach($schedules as $sch){
    if(!empty($sch['start_time']) && !empty($sch['end_time'])){
        $start = strtotime($sch['start_time']);
        $end   = strtotime($sch['end_time']);
        $hoursPerDay = ($end - $start)/3600; 
        $hours = floor($hoursPerDay);
        $minutes = round(($hoursPerDay - $hours) * 60);
        $totalHours += ($hours * 60 + $minutes) * $extraScheduledDays; // extra days
    }
}

// Convert total minutes back to hours + minutes
$hoursPart = floor($totalHours / 60);
$minutesPart = $totalHours % 60;

$accumulatedInfo = "{$weeks} weeks";
if($extraDays > 0){
    $accumulatedInfo .= " + {$extraDays} days";
}
$accumulatedInfo .= " | {$totalDays} days | {$hoursPart}h";
if($minutesPart > 0){
    $accumulatedInfo .= " {$minutesPart}m";
}
// --- Sheet Header ---
$sheet->setCellValue("A1","Section")->setCellValue("B1",$section['section_code'].' - '.$section['section_name']);
$sheet->setCellValue("A2","Subject")->setCellValue("B2",$subject_header);
$sheet->setCellValue("A3","Term")->setCellValue("B3",$term_label);
$sheet->setCellValue("A4","Period")->setCellValue("B4",$start_date.' to '.$end_date);
$sheet->setCellValue("A5","Teacher")->setCellValue("B5",$teacher_name);
$sheet->setCellValue("A6","Accumulated Days")->setCellValue("B6",$accumulatedInfo);

// Table headers (added "Student Type" and "Excused")
    $sheet->fromArray(["#","Student","Student Type","Present","Late","Absent","Excused","Total (with .5 Late/Excused)","Percentage"],NULL,"A8");

    // Fill data
    $row = 9;
    $i = 1;
    foreach($students as $st){
        // --- Fetch attendance ---
        $stmt = $conn->prepare("SELECT status FROM attendance 
                                WHERE s_id=? AND subject_code=? AND DATE(time_in) BETWEEN ? AND ?");
        $stmt->bind_param("isss",$st['s_id'],$subj['subject_code'],$start_date,$end_date);
        $stmt->execute();
        $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

       $present = $late = $absent = $excused = 0;
foreach($att as $a){
    $status = strtolower($a['status']);
    if($status === 'present') $present++;
    elseif($status === 'late') $late++;
    elseif($status === 'absent') $absent++;
    elseif($status === 'excused') $excused++; // ✅ Count Excused
}

// Total calculation: Present = 1, Late = 0.5, Excused = 0.5, Absent = 0
$total = $present + ($late * 0.5) + ($excused * 0.5);

// ✅ Use expected days instead of actual count
$expected_days = calcExpectedDays($schedules); // function should calculate based on schedule and 18 weeks
$percentage = $expected_days > 0 ? round(($total / $expected_days) * 100, 1) : 0;

        // --- Determine student type ---
        $student_type = $st['is_regular'] == 1 ? 'Regular' : 'Irregular';

        $sheet->fromArray([$i++,$st['student_name'],$student_type,$present,$late,$absent,$excused,$total,$percentage.'%'],NULL,'A'.$row);
        $row++;
    }

    $sheetIndex++;
}

// --- Download Excel to browser ---
$filename = "Attendance_Report_{$term_label}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
