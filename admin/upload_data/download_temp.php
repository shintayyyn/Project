<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__ . '/../../includes/PHPMailer/vendor/autoload.php';

// Permission check: Only admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit("Unauthorized access.");
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Upload Template');

// Define columns including the new ones
$columns = [
    'student_first_name',
    'student_last_name',
    'student_middle_name',
    'student_suffix',
    'student_gender',
    'student_birthdate (YYYY-MM-DD)',
    'student_contact_number',
    'student_email',
    'student_password',
    'student_status',
    'student_degree',
    'is_regular',      // new column
    'is_solo',         // new column
    'year_level',      // new column
    'p_fname',
    'p_lname',
    'p_mname',
    'p_suffix',
    'p_gender',
    'p_bdate (YYYY-MM-DD)',
    'p_age',
    'p_email',
    'p_cnum'
];

// Add headers to the first row
$colIndex = 1;
foreach ($columns as $header) {
    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $sheet->setCellValue($columnLetter . '1', $header);
    $colIndex++;
}

// Auto-size all columns for readability
foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Send file as downloadable Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="upload_template.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
