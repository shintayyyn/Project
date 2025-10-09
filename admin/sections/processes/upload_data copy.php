<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require __DIR__ . '/../../../includes/PHPMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// =========================
// 1. Permission Check
// =========================
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SESSION['user_type'] === 'teacher' && (!isset($_SESSION['is_dean']) || !$_SESSION['is_dean'])) {
    echo json_encode(['success' => false, 'message' => 'Only the dean can upload data.']);
    exit;
}

// =========================
// 2. Validate File Upload
// =========================
if (!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error.',
        'debug_files' => $_FILES
    ]);
    exit;
}

$uploadedFile = $_FILES['student_file'];
$allowedExtensions = ['xlsx', 'xls'];
$fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only Excel files (.xlsx, .xls) are allowed.'
    ]);
    exit;
}

// =========================
// 3. Move Uploaded File to Temp Directory
// =========================
$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upload_', true) . '.' . $fileExtension;

if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

// =========================
// 4. Get Current Active Term
// =========================
$currentTermQuery = $conn->query("
    SELECT t.term_id, ay.year_start, ay.year_end, t.semester
    FROM academic_terms t
    JOIN academic_years ay ON ay.ay_id = t.ay_id
    WHERE t.is_active = 1
    LIMIT 1
");

$currentTerm = $currentTermQuery->fetch_assoc();

if (!$currentTerm) {
    unlink($tempPath);
    echo json_encode(['success' => false, 'message' => 'No active academic term found.']);
    exit;
}

$term_id = $currentTerm['term_id'];

// =========================
// 5. Read Excel File
// =========================
try {
    $spreadsheet = IOFactory::load($tempPath);
    $sheet = $spreadsheet->getActiveSheet();

    // --- Extract Academic Year and Semester from cell A1 ---
    $metaData = trim((string)$sheet->getCell('A1')->getValue());
    $yearStart = null;
    $yearEnd = null;
    $semester = null;

    if (preg_match('/Academic Year:\s*(\d{4})\s*-\s*(\d{4})\s*\|\s*Semester:\s*(.*)/i', $metaData, $matches)) {
        $yearStart = $matches[1];
        $yearEnd = $matches[2];
        $semester = trim($matches[3]);
    }

    $rows = [];
    $highestRow = $sheet->getHighestDataRow();

    // Expected format:
    // Row 1: Academic Year + Semester
    // Row 2: Headers
    // Row 3 onward: Data
    for ($row = 3; $row <= $highestRow; $row++) {
        $idCode = trim((string)$sheet->getCell('B' . $row)->getValue());
        $degreeCode = trim((string)$sheet->getCell('C' . $row)->getValue());
        $sectionId = trim((string)$sheet->getCell('D' . $row)->getValue());

        if ($idCode === '' || $degreeCode === '') {
            continue; // skip blank rows
        }

        $rows[] = [
            'id_code' => $idCode,
            'degree_code' => $degreeCode,
            'section_id' => $sectionId
        ];
    }

    if (empty($rows)) {
        unlink($tempPath);
        echo json_encode(['success' => false, 'message' => 'No valid data found in the uploaded file.']);
        exit;
    }

} catch (Exception $e) {
    unlink($tempPath);
    echo json_encode(['success' => false, 'message' => 'Failed to read Excel file: ' . $e->getMessage()]);
    exit;
}

// =========================
// 6. Insert / Update Database
// =========================
$assigned = 0;
$skipped = 0;

$conn->begin_transaction();

try {
    foreach ($rows as $student) {
        // 1. Find student by ID Code
        $stmt = $conn->prepare("SELECT s_id FROM students WHERE idcode = ? LIMIT 1");
        $stmt->bind_param('s', $student['id_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $studentRow = $result->fetch_assoc();
        $stmt->close();

        if (!$studentRow) {
            $skipped++;
            continue;
        }

        $s_id = $studentRow['s_id'];

        // 2. Find the degree_id using degree_code
        $degreeStmt = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_code = ? LIMIT 1");
        $degreeStmt->bind_param('s', $student['degree_code']);
        $degreeStmt->execute();
        $degreeResult = $degreeStmt->get_result();
        $degreeRow = $degreeResult->fetch_assoc();
        $degreeStmt->close();

        if (!$degreeRow) {
            // Degree code not found, skip this student
            $skipped++;
            continue;
        }

        $degree_id = $degreeRow['degree_id'];

        // 3. Insert or Update student's degree using degree_id
        $insertDegreeStmt = $conn->prepare("
            INSERT INTO students_degrees (s_id, term_id, degree_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE degree_id = VALUES(degree_id)
        ");
        $insertDegreeStmt->bind_param('iii', $s_id, $term_id, $degree_id);
        $insertDegreeStmt->execute();
        $insertDegreeStmt->close();

        // 4. Insert or Update student's section (if provided)
        if (!empty($student['section_id'])) {
            $sectionStmt = $conn->prepare("
                INSERT INTO students_sections (s_id, term_id, section_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE section_id = VALUES(section_id)
            ");
            $sectionStmt->bind_param('iii', $s_id, $term_id, $student['section_id']);
            $sectionStmt->execute();
            $sectionStmt->close();
        }

        $assigned++;
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    unlink($tempPath);
    echo json_encode(['success' => false, 'message' => 'Error processing data: ' . $e->getMessage()]);
    exit;
}

// Clean up temporary file
unlink($tempPath);

// =========================
// 7. Final JSON Response
// =========================
echo json_encode([
    'success' => true,
    'message' => "Upload complete.",
    'assigned' => $assigned,
    'skipped' => $skipped,
    'parsed_meta' => [
        'year_start' => $yearStart,
        'year_end' => $yearEnd,
        'semester' => $semester
    ]
]);
exit;
