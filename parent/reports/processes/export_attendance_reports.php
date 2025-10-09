<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/../../../includes/PhpMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// --- Get filters ---
$month     = $_REQUEST['month']     ?? date('Y-m');
$semester  = $_REQUEST['semester']  ?? 0;

$start_date = $month . "-01";
$end_date   = date("Y-m-t", strtotime($start_date));

// --- Fetch parent info ---
$stmt = $conn->prepare("SELECT p_fname, p_lname, p_email FROM parents WHERE p_id = ?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$parent) die("Parent not found");

// --- Fetch children of this parent ---
$stmt = $conn->prepare("
    SELECT s.s_id,
           CONCAT(s.s_lname, ', ', s.s_fname, 
                  IF(s.s_mname IS NOT NULL AND s.s_mname != '', CONCAT(' ', LEFT(s.s_mname,1), '.'), ''), 
                  IF(s.s_suffix IS NOT NULL AND s.s_suffix != '', CONCAT(' ', s.s_suffix), '')
           ) AS student_name
    FROM parent_student ps
    INNER JOIN students s ON ps.s_id = s.s_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname, s.s_fname
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (!$students) die("No children found for this parent");

// --- Fetch attendance per student ---
$attendance = [];
foreach ($students as $st) {
    $s_id = $st['s_id'];

    // Subjects enrolled
    $stmt = $conn->prepare("
        SELECT ss.subject_code, sec.section_code, ss.day_of_week, ss.start_time, ss.end_time
        FROM students_sections stus
        INNER JOIN sections_schedules ss ON stus.section_id = ss.section_id
        INNER JOIN sections sec ON sec.section_id = stus.section_id
        WHERE stus.s_id = ?
    ");
    $stmt->bind_param("i", $s_id);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($subjects as $subj) {
        $subject_code = $subj['subject_code'];
        $present = $late = $absent = 0;

        // Attendance records
        $stmt = $conn->prepare("
            SELECT status
            FROM attendance
            WHERE s_id = ? 
              AND subject_code = ? 
              AND DATE(time_in) BETWEEN ? AND ?
        ");
        $stmt->bind_param("isss", $s_id, $subject_code, $start_date, $end_date);
        $stmt->execute();
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($records as $r) {
            $status = strtoupper($r['status']);
            if ($status === "PRESENT") $present++;
            elseif ($status === "LATE") $late++;
            elseif ($status === "ABSENT") $absent++;
        }

        $attendance[$s_id][$subject_code] = [
            "section" => $subj['section_code'],
            "days"    => $subj['day_of_week'] . " (" 
                        . date("g:i A", strtotime($subj['start_time'])) 
                        . " - " . date("g:i A", strtotime($subj['end_time'])) . ")",
            "present" => $present,
            "late"    => $late,
            "absent"  => $absent
        ];
    }
}

// --- Generate Excel ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance Report");

// Title
$sheet->mergeCells("A1:G1");
$sheet->setCellValue("A1", "Attendance Report - " . date("F Y", strtotime($month)));
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Column headers
$headers = ["#", "Student", "Subject", "Section", "Schedule", "Present", "Late", "Absent"];
$sheet->fromArray($headers, NULL, "A3");

// Style headers
$sheet->getStyle("A3:H3")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '033A70']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// Fill data
$row = 4;
$i = 1;
foreach ($students as $st) {
    $s_id = $st['s_id'];
    $student_name = $st['student_name'];

    if (!empty($attendance[$s_id])) {
        foreach ($attendance[$s_id] as $subject_code => $att) {
            $sheet->fromArray([
                $i++,
                $student_name,
                $subject_code,
                $att['section'],
                $att['days'],
                $att['present'],
                $att['late'],
                $att['absent']
            ], NULL, "A" . $row);

            // Apply borders
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]);

            $row++;
        }
    }
}

// Auto size columns
foreach (range("A", "H") as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- Save Excel ---
$filename = "Attendance_Report_Parent_" . date("Y_m", strtotime($month)) . ".xlsx";
$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
$writer = new Xlsx($spreadsheet);
$writer->save($tempPath);

// --- Send email ---
$subjectEmail = "Attendance Report - " . date("F Y", strtotime($month));
$bodyEmail = "Good day, {$parent['p_fname']} {$parent['p_lname']}!<br><br>"
           . "Attached is the attendance report for your child(ren) for <b>" . date("F Y", strtotime($month)) . "</b>."
           . "<br><br>Regards,<br>Attendify";

$sent = sendMail($parent['p_email'], $subjectEmail, $bodyEmail, $tempPath, $filename);

// Delete temp file
unlink($tempPath);

if ($sent) {
    echo json_encode(['success' => true, 'message' => "Attendance report sent to {$parent['p_email']}"]);
} else {
    echo json_encode(['success' => false, 'message' => "Failed to send attendance report."]);
}
exit();
