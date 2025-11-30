<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$student_id = $_POST['student_id'] ?? null;
if (!$student_id) {
    echo json_encode(["status" => "error", "message" => "Student ID missing"]);
    exit;
}

// --- Fetch student info ---
$stmt = $conn->prepare("SELECT idcode, s_email, s_lname, s_fname, s_mname, s_suffix, is_regular, year_level FROM students WHERE s_id=?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$year_level = $student['year_level'] ?? '-';


// Fetch department (degree code)
$stmt = $conn->prepare("
    SELECT d.degree_code 
    FROM students_degrees sd
    JOIN degrees d ON sd.degree_id = d.degree_id
    WHERE sd.s_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$degreeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$department = $degreeRow['degree_code'] ?? '-';


if (!$student) {
    echo json_encode(["status" => "error", "message" => "Student not found"]);
    exit;
}

$student_idcode = $student['idcode'];
$student_type = $student['is_regular'];
$recipient = $student['s_email'] ?? null;
if (!$recipient) {
    echo json_encode(["status" => "error", "message" => "Student email not found"]);
    exit;
}

// --- Check email verification ---
$stmt = $conn->prepare("SELECT verified FROM email_verifications WHERE user_id=? AND user_type='student' AND new_email=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("is", $student_id, $recipient);
$stmt->execute();
$email_verification = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$email_verification || !$email_verification['verified']) {
    echo json_encode(["status" => "warning", "message" => "Email not verified"]);
    exit;
}

// --- Fetch all term IDs ---
$term_ids = [];
if ($student_type == 1) {
    $stmt = $conn->prepare("
        SELECT DISTINCT ss.term_id
        FROM sections_schedules ss
        JOIN students_sections ssec ON ssec.section_id = ss.section_id
        WHERE ssec.s_id=?
    ");
} else {
    $stmt = $conn->prepare("SELECT DISTINCT term_id FROM subject_enrollments WHERE s_id=?");
}
$stmt->bind_param("i", $student_id);
$stmt->execute();
$term_ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'term_id');
$stmt->close();

if (!$term_ids) {
    echo json_encode(["status" => "error", "message" => "No academic terms found for this student"]);
    exit;
}

// --- Fetch term labels ---
$term_labels = [];
foreach ($term_ids as $term_id) {
    $stmt = $conn->prepare("
        SELECT ay.year_start, ay.year_end, at.semester
        FROM academic_terms at
        JOIN academic_years ay ON at.ay_id = ay.ay_id
        WHERE at.term_id=?
    ");
    $stmt->bind_param("i", $term_id);
    $stmt->execute();
    $term = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $term_labels[$term_id] = "A.Y. {$term['year_start']}-{$term['year_end']} | {$term['semester']}";
}

// --- Fetch subjects per term ---
$subjects_per_term = [];
foreach ($term_ids as $term_id) {
    if ($student_type == 1) {
        $stmt = $conn->prepare("
            SELECT DISTINCT ss.subject_code, s.section_code
            FROM sections_schedules ss
            JOIN students_sections ssec ON ssec.section_id = ss.section_id
            JOIN sections s ON s.section_id = ss.section_id
            WHERE ssec.s_id=? AND ss.term_id=?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT subject_code, section_code
            FROM subject_enrollments
            WHERE s_id=? AND term_id=?
        ");
    }
    $stmt->bind_param("ii", $student_id, $term_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($results as $r) {
        $subjects_per_term[$term_id][$r['subject_code']] = $r['section_code'];
    }
}

// -----------------------------------------------------------
// FUNCTIONS FOR STYLES
// -----------------------------------------------------------
function styleHeaderRow($sheet, $row)
{
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '033A70']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders' => ['allBorders' => ['borderStyle' => 'thin']]
    ]);
}

function styleBody($sheet, $start, $end)
{
    $sheet->getStyle("A{$start}:E{$end}")->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => 'thin']],
        'alignment' => ['vertical' => 'center']
    ]);
}

// -----------------------------------------------------------
// CREATE SPREADSHEET
// -----------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheetIndex = 0;

foreach ($subjects_per_term as $term_id => $subjects) {

    $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
    $sheet->setTitle(substr($term_labels[$term_id], 0, 31));

    // -----------------------------
    // HEADER + LOGO
    // -----------------------------
    $logo = new Drawing();
    $logo->setName('Logo');
    $logo->setDescription('School Logo');
    $logo->setPath(__DIR__ . '/../../assets/img/attendifylogo.png'); // adjust path
    $logo->setHeight(70);
    $logo->setCoordinates('A1');
    $logo->setOffsetX(5);
    $logo->setWorksheet($sheet);  // <-- correct method


    // Centered text
    $sheet->mergeCells("A2:E2");
    $sheet->mergeCells("A3:E3");
    $sheet->mergeCells("A4:E4");

    $sheet->setCellValue("A2", "COLLEGE SCHOOL");
    $sheet->setCellValue("A3", "1st Sample Street, Sample City");
    $sheet->setCellValue("A4", "Tel. (032) 123-456 | Email: attendifysys2025@gmail.com");

    $sheet->getStyle("A2:A4")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => 'center']
    ]);

    // Divider
    $sheet->getStyle("A5:E5")->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => 'medium']]
    ]);

    $row = 7;

    // --- Student Info ---
    $sheet->setCellValue("A{$row}", "Student ID")->setCellValue("B{$row}", $student_idcode);
    $row++;

    $sheet->setCellValue("A{$row}", "Academic Term")->setCellValue("B{$row}", $term_labels[$term_id]);
    $row++;

    $sheet->setCellValue("A{$row}", "Student Type")->setCellValue("B{$row}", $student_type ? 'Regular' : 'Irregular');
    $row++;

    $sheet->setCellValue("A{$row}", "Department")->setCellValue("B{$row}", $department);
    $row++;

    $sheet->setCellValue("A{$row}", "Year Level")->setCellValue("B{$row}", $year_level);
    $row += 2;


    // -----------------------------------------------------------
    // SUBJECT PROCESSING
    // -----------------------------------------------------------
    foreach ($subjects as $subject_code => $section_code) {

        $sheet->setCellValue("A{$row}", "Subject: $subject_code (Section: $section_code)");
        $row++;

        // Table headers
        $sheet->fromArray(["#", "Date", "Time In", "Time Out", "Status"], NULL, "A{$row}");
        styleHeaderRow($sheet, $row);
        $row++;

        // Attendance
        $stmt = $conn->prepare("
            SELECT status, time_in, time_out
            FROM attendance
            WHERE s_id=? AND subject_code=? AND section_code=?
            ORDER BY time_in ASC
        ");
        $stmt->bind_param("iss", $student_id, $subject_code, $section_code);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $present = $late = $absent = $excused = 0;
        $startRow = $row;

        if ($attendance) {
            $i = 1;
            foreach ($attendance as $rec) {
                $date = $rec['time_in'] ? date('M d, Y', strtotime($rec['time_in'])) : '-';
                $time_in = $rec['time_in'] ? date('h:i A', strtotime($rec['time_in'])) : '-';
                $time_out = $rec['time_out'] ? date('h:i A', strtotime($rec['time_out'])) : '-';
                $status = $rec['status'];

                $s = strtolower($status);
                if ($s === 'present') $present++;
                elseif ($s === 'late') $late++;
                elseif ($s === 'absent') $absent++;
                elseif ($s === 'excuse') $excused++;

                $sheet->fromArray([$i++, $date, $time_in, $time_out, $status], NULL, "A{$row}");
                $row++;
            }
        } else {
            $sheet->fromArray([1, '-', '-', '-', '-'], NULL, "A{$row}");
            $row++;
        }

        styleBody($sheet, $startRow, $row - 1);

        // Summary
        $sheet->fromArray(["Summary", "Present: $present, Late: $late, Absent: $absent, Excused: $excused"], NULL, "A{$row}");
        $row += 2;
    }

    $sheetIndex++;
}

// --- Save and send ---
$temp_file = tempnam(sys_get_temp_dir(), "attendance_report_") . ".xlsx";
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

$subject = "Attendance Report - {$student_idcode}";
$body = "Dear Student,<br><br>Please find attached your attendance report.<br><br>Regards,<br>Attendify";

$mailResult = sendMail(
    $recipient,
    $subject,
    $body,
    [file_get_contents($temp_file)],
    ["Attendance_Report_{$student_idcode}.xlsx"],
    true
);

unlink($temp_file);

echo json_encode([
    "status" => "success",
    "message" => "Report generated and email sent.",
    "emailSent" => $mailResult['success']
]);
exit;
