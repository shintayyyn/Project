<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/mailer.php';
require_once __DIR__ . '/../../../includes/PhpMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(["status"=>"error","message"=>"Please login"]);
    exit;
}

$parent_id = $_SESSION['parent_id'];
$semester = $_REQUEST['semester'] ?? null;
$child_id = $_REQUEST['child_id'] ?? null;


// --- Fetch parent info ---
$stmt = $conn->prepare("SELECT p_fname, p_lname, p_email FROM parents WHERE p_id=?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$parent) {
    echo json_encode(["status"=>"error","message"=>"Parent not found"]);
    exit;
}

// --- Check email verification ---
$stmt = $conn->prepare("SELECT verified FROM email_verifications WHERE new_email=? LIMIT 1");
$stmt->bind_param("s", $parent['p_email']);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$verification || $verification['verified'] != 1) {
    echo json_encode(["status"=>"error","message"=>"Parent email not verified"]);
    exit;
}

// --- Fetch children ---
if ($child_id) {
    $stmt = $conn->prepare("
        SELECT s.s_id, s.idcode, s.s_email, s.s_fname, s.s_lname, s.s_mname, s.s_suffix, s.is_regular, s.year_level
        FROM parent_student ps
        JOIN students s ON ps.s_id = s.s_id
        WHERE ps.p_id = ? AND s.s_id = ?
    ");
    $stmt->bind_param("ii", $parent_id, $child_id);
} else {
    $stmt = $conn->prepare("
        SELECT s.s_id, s.idcode, s.s_email, s.s_fname, s.s_lname, s.s_mname, s.s_suffix, s.is_regular, s.year_level
        FROM parent_student ps
        JOIN students s ON ps.s_id = s.s_id
        WHERE ps.p_id = ?
        ORDER BY s.s_lname, s.s_fname
    ");
    $stmt->bind_param("i", $parent_id);
}
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$children) {
    echo json_encode(["status"=>"error","message"=>"No children found"]);
    exit;
}

// -----------------------------------------------------------
// FUNCTIONS FOR STYLING
// -----------------------------------------------------------
function styleHeaderRow($sheet, $row){
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
        'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
        'fill'=>['fillType'=>'solid','color'=>['rgb'=>'033A70']],
        'alignment'=>['horizontal'=>'center','vertical'=>'center'],
        'borders'=>['allBorders'=>['borderStyle'=>'thin']]
    ]);
}
function styleBody($sheet,$start,$end){
    $sheet->getStyle("A{$start}:E{$end}")->applyFromArray([
        'borders'=>['allBorders'=>['borderStyle'=>'thin']],
        'alignment'=>['vertical'=>'center']
    ]);
}

// -----------------------------------------------------------
// CREATE SPREADSHEET
// -----------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheetIndex = 0;

foreach ($children as $child) {
    $s_id = $child['s_id'];
    $student_name = $child['s_fname']." ".$child['s_lname'];
    $student_idcode = $child['idcode'];
    $year_level = $child['year_level'];
    $student_type = $child['is_regular'] ? 'Regular' : 'Irregular';

    // Fetch department
    $stmt = $conn->prepare("
        SELECT d.degree_code 
        FROM students_degrees sd
        JOIN degrees d ON sd.degree_id=d.degree_id
        WHERE sd.s_id=? LIMIT 1
    ");
    $stmt->bind_param("i",$s_id);
    $stmt->execute();
    $degreeRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $department = $degreeRow['degree_code'] ?? '-';

    // -----------------------------------------------------------
    // Determine semesters for this student
    // -----------------------------------------------------------
    $term_ids = [];
    $term_labels = [];

   if ($semester && $semester !== 'all') {
    // fetch specific semester
    $stmt = $conn->prepare("
        SELECT at.term_id, ay.year_start, ay.year_end, at.semester
        FROM academic_terms at
        JOIN academic_years ay ON at.ay_id = ay.ay_id
        WHERE at.term_id=?
    ");
    $stmt->bind_param("i", $semester);
    $stmt->execute();
    $term = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($term) {
        $term_ids[] = $term['term_id'];
        $term_labels[$term['term_id']] = "A.Y. {$term['year_start']}-{$term['year_end']} | {$term['semester']}";
    }
} else {
    // fetch all semesters
    $stmt = $conn->prepare("
        SELECT at.term_id, ay.year_start, ay.year_end, at.semester
        FROM academic_terms at
        JOIN academic_years ay ON at.ay_id = ay.ay_id
        ORDER BY ay.year_start DESC, at.semester ASC
    ");
    $stmt->execute();
    $terms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach($terms as $t){
        $term_ids[] = $t['term_id'];
        $term_labels[$t['term_id']] = "A.Y. {$t['year_start']}-{$t['year_end']} | {$t['semester']}";
    }
}


    if(!$term_ids) continue;

   // -----------------------------------------------------------
// Loop through semesters to create sheets
// -----------------------------------------------------------
foreach($term_ids as $term_id){
    $semester_label = $term_labels[$term_id] ?? '';
    $sheet = $sheetIndex===0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
    $sheet->setTitle(substr($student_name." (".$semester_label.")",0,31));

    // Logo
    $logo = new Drawing();
    $logo->setName('Logo');
    $logo->setDescription('School Logo');
    $logo->setPath(__DIR__.'/../../../assets/img/attendifylogo.png');
    $logo->setHeight(70);
    $logo->setCoordinates('A1');
    $logo->setOffsetX(5);
    $logo->setWorksheet($sheet);

    // Header text
    $sheet->mergeCells("A2:E2");
    $sheet->mergeCells("A3:E3");
    $sheet->mergeCells("A4:E4");
    $sheet->setCellValue("A2","COLLEGE SCHOOL");
    $sheet->setCellValue("A3","1st Sample Street, Sample City");
    $sheet->setCellValue("A4","Tel. (032) 123-456 | Email: attendifysys2025@gmail.com");
    $sheet->getStyle("A2:A4")->applyFromArray(['font'=>['bold'=>true,'size'=>14],'alignment'=>['horizontal'=>'center']]);
    $sheet->getStyle("A5:E5")->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

   // Student info
$row = 7;
$sheet->setCellValue("A{$row}","Student ID")->setCellValue("B{$row}",$student_idcode); $row++;
$sheet->setCellValue("A{$row}","Student Name")->setCellValue("B{$row}",$student_name); $row++;
$sheet->setCellValue("A{$row}","Student Type")->setCellValue("B{$row}",$student_type); $row++;
$sheet->setCellValue("A{$row}","Department")->setCellValue("B{$row}",$department); $row++;
$sheet->setCellValue("A{$row}","Year Level / Semester")->setCellValue("B{$row}", $year_level . " | " . $semester_label); 
$row += 2;

    // -----------------------------------------------------------
    // Fetch subjects for this term
    // -----------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT DISTINCT ss.subject_code, s.section_code
        FROM sections_schedules ss
        JOIN students_sections ssec ON ssec.section_id=ss.section_id
        JOIN sections s ON s.section_id=ss.section_id
        WHERE ssec.s_id=? AND ss.term_id=?
    ");
    $stmt->bind_param("ii",$s_id,$term_id);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // -----------------------------------------------------------
    // Fill attendance per subject
    // -----------------------------------------------------------
    if($subjects){
        foreach($subjects as $sub){
            $subject_code = $sub['subject_code'];
            $section_code = $sub['section_code'];

            $sheet->setCellValue("A{$row}","Subject: $subject_code (Section: $section_code)"); $row++;
            $sheet->fromArray(["#","Date","Time In","Time Out","Status"],NULL,"A{$row}"); styleHeaderRow($sheet,$row); $row++;

            $stmt = $conn->prepare("
                SELECT status, time_in, time_out
                FROM attendance
                WHERE s_id=? AND subject_code=? AND section_code=? 
                ORDER BY time_in ASC
            ");
            $stmt->bind_param("iss",$s_id,$subject_code,$section_code);
            $stmt->execute();
            $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $present=$late=$absent=$excused=0;
            $startRow = $row;

            if($records){
                $i=1;
                foreach($records as $r){
                    $date = $r['time_in'] ? date('M d, Y',strtotime($r['time_in'])) : '-';
                    $time_in = $r['time_in'] ? date('h:i A',strtotime($r['time_in'])) : '-';
                    $time_out = $r['time_out'] ? date('h:i A',strtotime($r['time_out'])) : '-';
                    $status = $r['status'];
                    $s = strtolower($status);
                    if($s==='present') $present++;
                    elseif($s==='late') $late++;
                    elseif($s==='absent') $absent++;
                    elseif($s==='excuse') $excused++;
                    $sheet->fromArray([$i++,$date,$time_in,$time_out,$status],NULL,"A{$row}");
                    $row++;
                }
            } else {
                // If no records for this subject, put "No records yet"
                $sheet->fromArray([1,'-','-','-','No records yet'],NULL,"A{$row}"); $row++;
            }

            styleBody($sheet,$startRow,$row-1);
            $sheet->fromArray(["Summary","Present: $present, Late: $late, Absent: $absent, Excused: $excused"],NULL,"A{$row}"); $row+=2;
        }
    } else {
        // If no subjects at all for this term, create one row indicating no data
        $sheet->fromArray(["No records yet for this semester"],NULL,"A{$row}");
    }

    $sheetIndex++;
}

}

// -----------------------------------------------------------
// SAVE AND EMAIL
// -----------------------------------------------------------
$temp_file = tempnam(sys_get_temp_dir(),"attendance_report_").".xlsx";
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

$subject = "Attendance Report - Parent: {$parent['p_fname']} {$parent['p_lname']}";
$body = "Good day {$parent['p_fname']} {$parent['p_lname']},<br><br>Attached is the attendance report for your child(ren).<br><br>Regards,<br>Attendify";

$mailResult = sendMail(
    $parent['p_email'],
    $subject,
    $body,
    [file_get_contents($temp_file)],
    ["Attendance_Report_Parent_".date("Y_m").".xlsx"],
    true
);

unlink($temp_file);

echo json_encode([
    "success" => $mailResult['success'], // true/false
    "message" => $mailResult['success'] ? "Attendance report sent." : "Failed to send report."
]);


exit;
