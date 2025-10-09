<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/mailer.php'; // your mailer
require_once __DIR__ . '/../../../includes/PhpMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// --- Get filters ---
$section_id     = $_REQUEST['section_id']     ?? null;
$subject_code   = $_REQUEST['subject_code']   ?? null;
$semester       = $_REQUEST['semester']       ?? null;
$start_date     = $_REQUEST['start_date']     ?? null;
$end_date       = $_REQUEST['end_date']       ?? null;
$scheduled_days = $_REQUEST['scheduled_days'] ?? null;
$total_days     = $_REQUEST['total_days']     ?? null;

if (!$section_id || !$subject_code || !$semester || !$start_date || !$end_date || !$scheduled_days || !$total_days) {
    die("Missing required filters");
}

// --- Fetch teacher info ---
$stmt = $conn->prepare("SELECT t_fname, t_lname, t_email FROM teachers WHERE t_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$teacher) die("Teacher not found");

// --- Fetch section info ---
$stmt = $conn->prepare("SELECT section_code, section_name FROM sections WHERE section_id = ?");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$section) die("Section not found");

// --- Fetch students ---
$stmt = $conn->prepare("
    SELECT s.s_id, 
           CONCAT(
               s.s_lname, ', ', 
               s.s_fname,
               IF(s.s_mname IS NOT NULL AND s.s_mname != '', CONCAT(' ', LEFT(s.s_mname,1), '.'), ''), 
               IF(s.s_suffix IS NOT NULL AND s.s_suffix != '', CONCAT(' ', s.s_suffix), '')
           ) AS student_name
    FROM students_sections ss
    JOIN students s ON ss.s_id = s.s_id
    WHERE ss.section_id = ?
    ORDER BY s.s_lname, s.s_fname
");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (!$students) die("No students in section");

// --- Fetch attendance ---
$attendance = [];
foreach ($students as $st) {
    $stmt = $conn->prepare("
        SELECT status, DATE(time_in) as att_date
        FROM attendance
        WHERE s_id=? AND subject_code=? 
          AND DATE(time_in) BETWEEN ? AND ?
    ");
    $stmt->bind_param("isss", $st['s_id'], $subject_code, $start_date, $end_date);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $days = [];
    if (!empty($scheduled_days)) {
        if (is_string($scheduled_days)) $scheduled_days = explode(',', $scheduled_days);
        $days = array_map('intval', (array)$scheduled_days);
    }

    foreach ($records as $r) {
        if (!empty($days)) {
            $dayNum = (int)date('N', strtotime($r['att_date']));
            if (!in_array($dayNum, $days)) continue;
        }
        $attendance[$st['s_id']][] = $r['status'];
    }
}

// --- Fetch schedule for header ---
$stmt = $conn->prepare("
    SELECT day_of_week, start_time, end_time 
    FROM sections_schedules 
    WHERE section_id = ? AND subject_code = ?
");
$stmt->bind_param("is", $section_id, $subject_code);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$dayMap = [1 => "Mon", 2 => "Tue", 3 => "Wed", 4 => "Thu", 5 => "Fri", 6 => "Sat", 7 => "Sun"];
$dayTimeStrs = [];
foreach ($schedules as $sch) {
    $dayName = is_numeric($sch['day_of_week']) ? ($dayMap[(int)$sch['day_of_week']] ?? $sch['day_of_week']) : ucfirst(substr(trim($sch['day_of_week']),0,3));
    $timeRange = (!empty($sch['start_time']) && !empty($sch['end_time'])) ? date('h:i A', strtotime($sch['start_time'])) . " - " . date('h:i A', strtotime($sch['end_time'])) : '';
    $dayTimeStrs[] = trim($dayName . ($timeRange ? " ($timeRange)" : ""));
}
$dayTimeHeader = $dayTimeStrs ? implode(", ", $dayTimeStrs) : "No schedule found";

// --- Generate Excel ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance Report");

// Header info
$sheet->setCellValue("A1","Teacher")->setCellValue("B1",$teacher['t_fname']." ".$teacher['t_lname']);
$sheet->setCellValue("A2","Section")->setCellValue("B2",$section['section_code']." - ".$section['section_name']);
$sheet->setCellValue("A3","Semester")->setCellValue("B3",$semester);
$sheet->setCellValue("A4","Subject")->setCellValue("B4",$subject_code);
$sheet->setCellValue("A5","Period")->setCellValue("B5",$start_date." to ".$end_date);
$sheet->setCellValue("A6","Total Class Days")->setCellValue("B6",$total_days);
$sheet->setCellValue("A7","Day/s of the Week (Time)")->setCellValue("B7",$dayTimeHeader);

// Table headers
$sheet->fromArray(["#","Student","Present","Late","Absent","Total (with .5 Late)","Percentage"],NULL,"A9");

// Fill data
$row = 10;
$i = 1;
foreach ($students as $st) {
    $att = $attendance[$st['s_id']] ?? [];
    $present = $late = $absent = 0;
    foreach($att as $a){
        if(strcasecmp($a,"Present")===0) $present++;
        elseif(strcasecmp($a,"Late")===0) $late++;
        elseif(strcasecmp($a,"Absent")===0) $absent++;
    }
    $total = $present + ($late * 0.5);
    $percentage = $total_days > 0 ? round(($total / $total_days) * 100,1) : 0;
    $sheet->fromArray([$i++,$st['student_name'],$present ?:0,$late ?:0,$absent ?:0,$total ?:0,$percentage."%"],NULL,"A".$row);
    $row++;
}

// --- Save Excel to temp file ---
$filename = "Attendance_Report_{$section['section_code']}_{$subject_code}.xlsx";
$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($tempPath);

// --- Send email ---
$subjectEmail = "Attendance Report: {$section['section_code']} - {$subject_code}";
$bodyEmail = "Goodday! Teacher {$teacher['t_lname']},<br><br>Here's your attendance reports for this semester.<br><br>Regards,<br>Attendify";

// Send with updated mailer (accepts attachment)
$sent = sendMail($teacher['t_email'], $subjectEmail, $bodyEmail, $tempPath, $filename);

// Delete temp file
unlink($tempPath);
if ($sent) {
    echo json_encode(['success' => true, 'message' => "Attendance report sent to {$teacher['t_email']}."]);
} else {
    echo json_encode(['success' => false, 'message' => "Failed to send attendance report."]);
}
exit();
