<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require __DIR__ . '/../../../includes/PHPMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// =========================
// 1. Permission Check
// =========================
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['dean', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SESSION['user_type'] === 'teacher' && (!isset($_SESSION['is_dean']) || !$_SESSION['is_dean'])) {
    echo json_encode(['success' => false, 'message' => 'Only dean can export']);
    exit;
}

// =========================
// 2. Identify Dean's Degree via degrees table
// =========================
$dean_degree_code = null;
if ($_SESSION['user_type'] === 'dean') {
    $stmtDean = $conn->prepare("
        SELECT degree_code 
        FROM degrees
        WHERE dean_id = ?
        LIMIT 1
    ");
    $stmtDean->bind_param("i", $_SESSION['user_id']);
    $stmtDean->execute();
    $resultDean = $stmtDean->get_result()->fetch_assoc();

    if ($resultDean) {
        $dean_degree_code = $resultDean['degree_code'];
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Your account is not assigned to any degree.'
        ]);
        exit;
    }
}

// =========================
// 3. Get Active Term
// =========================
$current_term = $conn->query("
    SELECT t.*, ay.year_start, ay.year_end 
    FROM academic_terms t
    JOIN academic_years ay ON t.ay_id = ay.ay_id
    WHERE t.is_active = 1
    LIMIT 1
")->fetch_assoc();

if (!$current_term) {
    echo json_encode(['success' => false, 'message' => 'No active term found']);
    exit;
}

$term_id = $current_term['term_id'];
$academic_year = $current_term['year_start'] . ' - ' . $current_term['year_end'];
$semester = $current_term['semester'];

// =========================
// 4. Fetch Students (Filtered by Dean Degree, Inactive & Not Enrolled / Promoted)
// =========================
$stmt = $conn->prepare("
SELECT 
    s.s_id, s.idcode, s.s_fname, s.s_lname, s.s_mname, s.s_suffix,
    s.year_level, s.is_regular, s.s_status, s.enrollment_status,
    COALESCE(sd.degree_code, 'UNKNOWN') AS degree_code,
    ss.section_id
FROM students s
LEFT JOIN students_degrees sd
       ON s.s_id = sd.s_id
LEFT JOIN students_sections ss
       ON s.s_id = ss.s_id AND ss.term_id = ?
WHERE s.s_status = 'inactive'
  AND s.is_regular = 1
  AND (s.enrollment_status = 'Not yet Enrolled' OR s.enrollment_status LIKE 'Promoted%')
  AND sd.degree_code = ?
  AND (ss.section_id IS NULL OR ss.section_id = '')
ORDER BY sd.degree_code ASC, s.s_lname ASC
");

$stmt->bind_param("is", $term_id, $dean_degree_code);
$stmt->execute();
$students_result = $stmt->get_result();

$all_students = [];
while ($student = $students_result->fetch_assoc()) {
    $student['degree_code'] = $student['degree_code'] ?? 'UNKNOWN';
    $all_students[] = $student;
}

// =========================
// 5. Fetch Sections (Filtered By Dean Degree)
// =========================
$sections_by_term = [];
$terms_res = $conn->query("
    SELECT t.term_id, t.semester, ay.year_start, ay.year_end
    FROM academic_terms t
    JOIN academic_years ay ON t.ay_id = ay.ay_id
    ORDER BY ay.year_start ASC, t.semester ASC
");

while ($term = $terms_res->fetch_assoc()) {
    $term_key = $term['year_start'] . '-' . $term['year_end'] . " Sem " . $term['semester'];

    $sec_stmt = $conn->prepare("
        SELECT sec.section_id, sec.section_code, d.degree_code
        FROM sections sec
        JOIN degrees d ON sec.degree_id = d.degree_id
        WHERE sec.term_id = ?
          AND d.degree_code = ?
        ORDER BY sec.section_code ASC
    ");

    $sec_stmt->bind_param("is", $term['term_id'], $dean_degree_code);
    $sec_stmt->execute();
    $sec_res = $sec_stmt->get_result();

    $sections_by_term[$term_key] = [];
    while ($sec = $sec_res->fetch_assoc()) {
        $sections_by_term[$term_key][$sec['degree_code']][] = $sec;
    }
}

// =========================
// 6. Create Spreadsheet
// =========================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Unassigned Students');

$sheet->setCellValue('A1', "Academic Year: $academic_year | Semester: $semester");
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true);

$sheet->setCellValue('H1', 'Note: Refer to the Section Legend sheets for Section IDs.');
$sheet->getStyle('H1')->getFont()->setItalic(true);

$sheet->setCellValue('A2', 'No.')
      ->setCellValue('B2', 'ID Code')
      ->setCellValue('C2', 'Full Name')
      ->setCellValue('D2', 'Degree Code')
      ->setCellValue('E2', 'Year Level')
      ->setCellValue('F2', 'Student Type')
      ->setCellValue('G2', 'Section ID');

$row_num = 3;
$counter = 1;

foreach ($all_students as $s) {
    $fullName = trim($s['s_lname'])
              . (!empty($s['s_suffix']) ? ' ' . $s['s_suffix'] : '')
              . ', ' . $s['s_fname']
              . (!empty($s['s_mname']) ? ' ' . strtoupper($s['s_mname'][0]) . '.' : '');
    $regularity = ($s['is_regular'] == 1) ? 'Regular' : 'Irregular';

    $sheet->setCellValue("A{$row_num}", $counter)
          ->setCellValue("B{$row_num}", $s['idcode'])
          ->setCellValue("C{$row_num}", $fullName)
          ->setCellValue("D{$row_num}", $s['degree_code'])
          ->setCellValue("E{$row_num}", $s['year_level'])
          ->setCellValue("F{$row_num}", $regularity)
          ->setCellValue("G{$row_num}", $s['section_id'] ?? '');

    $row_num++;
    $counter++;
}

// Auto-size columns
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// =========================
// 7. Build Section Legend Sheets
// =========================
$sheet_index = 1;
foreach ($sections_by_term as $term_key => $degrees) {
    $sheet = $spreadsheet->createSheet($sheet_index++);
    $sheet->setTitle(substr("Sections $term_key", 0, 31));

    $row_num = 1;
    foreach ($degrees as $degree_code => $sections) {
        $sheet->setCellValue("A{$row_num}", "Degree: $degree_code");
        $sheet->getStyle("A{$row_num}")->getFont()->setBold(true);
        $row_num++;

        $sheet->setCellValue("A{$row_num}", 'Section ID')
              ->setCellValue("B{$row_num}", 'Section Code');
        $sheet->getStyle("A{$row_num}:B{$row_num}")->getFont()->setBold(true);
        $row_num++;

        foreach ($sections as $sec) {
            $sheet->setCellValue("A{$row_num}", $sec['section_id'])
                  ->setCellValue("B{$row_num}", $sec['section_code']);
            $row_num++;
        }

        $row_num++;
    }

    foreach (range('A', 'Z') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// =========================
// 8. Save & Return
// =========================
$filename = "Dean_Unassigned_Students_{$academic_year}_Sem{$semester}.xlsx";
$temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

echo json_encode([
    'success' => true,
    'message' => 'Export ready',
    'file' => $filename
]);
exit;
