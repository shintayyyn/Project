<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require __DIR__ . '/../../../includes/PHPMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// 1. Permission Check
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SESSION['user_type'] === 'teacher' && (!isset($_SESSION['is_dean']) || !$_SESSION['is_dean'])) {
    echo json_encode(['success' => false, 'message' => 'Only the dean can upload data.']);
    exit;
}

// 2. Validate File Upload
if (!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$uploadedFile = $_FILES['student_file'];
$allowedExtensions = ['xlsx', 'xls'];
$fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only Excel files (.xlsx, .xls) are allowed.']);
    exit;
}

// 3. Move Uploaded File to Temp Directory
$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upload_', true) . '.' . $fileExtension;

if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

// 4. Get Current Active Term
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
$yearStart = $currentTerm['year_start'];
$yearEnd = $currentTerm['year_end'];
$semester = $currentTerm['semester'];

// 5. Read Excel File
try {
    $spreadsheet = IOFactory::load($tempPath);
    $sheet = $spreadsheet->getActiveSheet();

    $metaData = trim((string)$sheet->getCell('A1')->getValue());
    if (preg_match('/Academic Year:\s*(\d{4})\s*-\s*(\d{4})\s*\|\s*Semester:\s*(.*)/i', $metaData, $matches)) {
        $yearStart = $matches[1];
        $yearEnd = $matches[2];
        $semester = trim($matches[3]);
    }

    $rows = [];
    $highestRow = $sheet->getHighestDataRow();

    for ($row = 3; $row <= $highestRow; $row++) {
        $idCode     = trim((string)$sheet->getCell('B' . $row)->getValue());
        $degreeCode = trim((string)$sheet->getCell('D' . $row)->getValue());
        $yearLevel  = trim((string)$sheet->getCell('E' . $row)->getValue());
        $sectionId  = trim((string)$sheet->getCell('F' . $row)->getValue());

        if ($idCode === '' || $degreeCode === '') continue;

        $rows[] = [
            'row_number'  => $row,
            'id_code'     => $idCode,
            'degree_code' => $degreeCode,
            'year_level'  => $yearLevel,
            'section_id'  => $sectionId
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

// 6. Insert / Update Database & Subject Enrollments
$assigned = $skipped = 0;
$skippedStudents = []; // Track skipped students with reason
$conn->begin_transaction();

try {
    foreach ($rows as $student) {
        $stmt = $conn->prepare("SELECT s_id, s_fname, s_lname, enrollment_status, is_regular FROM students WHERE idcode = ? LIMIT 1");
        $stmt->bind_param('s', $student['id_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        $studentRow = $result->fetch_assoc();
        $stmt->close();

        if (!$studentRow) { 
            $skipped++;
            $skippedStudents[] = ['id_code' => $student['id_code'], 'name' => 'Unknown', 'reason' => 'Student not found'];
            continue; 
        }

        $s_id = $studentRow['s_id'];
        $is_regular = (int)$studentRow['is_regular'];
        $fullName = $studentRow['s_fname'] . ' ' . $studentRow['s_lname'];

        // Skip irregular students
        if ($is_regular !== 1) {
            $skipped++;
            $skippedStudents[] = ['id_code' => $student['id_code'], 'name' => $fullName, 'reason' => 'Irregular student'];
            continue;
        }

        // Update year_level
        if (!empty($student['year_level'])) {
            $updateYearStmt = $conn->prepare("UPDATE students SET year_level = ? WHERE s_id = ?");
            $updateYearStmt->bind_param("ii", $student['year_level'], $s_id);
            $updateYearStmt->execute();
            $updateYearStmt->close();
        }

        // Get degree_id
        $degreeStmt = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_code = ? LIMIT 1");
        $degreeStmt->bind_param('s', $student['degree_code']);
        $degreeStmt->execute();
        $degreeResult = $degreeStmt->get_result();
        $degreeRow = $degreeResult->fetch_assoc();
        $degreeStmt->close();

        if (!$degreeRow) { 
            $skipped++;
            $skippedStudents[] = ['id_code' => $student['id_code'], 'name' => $fullName, 'reason' => 'Degree code not found'];
            continue; 
        }

        $degree_id = $degreeRow['degree_id'];

        $insertDegreeStmt = $conn->prepare("
            INSERT INTO students_degrees (s_id, term_id, degree_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE degree_id = VALUES(degree_id)
        ");
        $insertDegreeStmt->bind_param('iii', $s_id, $term_id, $degree_id);
        $insertDegreeStmt->execute();
        $insertDegreeStmt->close();

        // Section alignment for regular students
        if (!empty($student['section_id'])) {
            $secQuery = $conn->prepare("
                SELECT section_id, section_code, degree_id, year_level
                FROM sections 
                WHERE section_id = ? 
                LIMIT 1
            ");
            $secQuery->bind_param('i', $student['section_id']);
            $secQuery->execute();
            $secResult = $secQuery->get_result();
            $secRow = $secResult->fetch_assoc();
            $secQuery->close();

            if (!$secRow || $secRow['degree_id'] != $degree_id || $secRow['year_level'] != $student['year_level']) {
                $skipped++;
                $skippedStudents[] = ['id_code' => $student['id_code'], 'name' => $fullName, 'reason' => 'Section mismatch'];
                continue;
            }

            $section_code = $secRow['section_code'];

            $sectionStmt = $conn->prepare("
                INSERT INTO students_sections (s_id, term_id, section_id, section_code)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE section_id = VALUES(section_id), section_code = VALUES(section_code)
            ");
            $sectionStmt->bind_param('iiss', $s_id, $term_id, $student['section_id'], $section_code);
            $sectionStmt->execute();
            $sectionStmt->close();

            // Populate subject_enrollments
            $scheduleStmt = $conn->prepare("
                SELECT ss.subject_code, s.section_code
                FROM sections_schedules ss
                JOIN sections s ON s.section_id = ss.section_id
                WHERE ss.section_id = ? AND ss.term_id = ?
            ");
            $scheduleStmt->bind_param('ii', $student['section_id'], $term_id);
            $scheduleStmt->execute();
            $scheduleResult = $scheduleStmt->get_result();

            $enrollStmt = $conn->prepare("
                INSERT INTO subject_enrollments
                (s_id, subject_code, section_code, term_id, is_status, enrollment_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    is_status = IF(enrollment_status = 'Enrolled', 1, is_status),
                    enrollment_status = VALUES(enrollment_status),
                    section_code = VALUES(section_code),
                    updated_at = NOW()
            ");

            while ($schedRow = $scheduleResult->fetch_assoc()) {
                $enrollment_status = 'Enrolled';
                $is_status = ($enrollment_status === 'Enrolled') ? 1 : 2;

                $enrollStmt->bind_param(
                    'ississ',
                    $s_id,
                    $schedRow['subject_code'],
                    $schedRow['section_code'],
                    $term_id,
                    $is_status,
                    $enrollment_status
                );
                $enrollStmt->execute();
            }

            $enrollStmt->close();
            $scheduleStmt->close();
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

unlink($tempPath);

// 7. Response with skipped student details
echo json_encode([
    'success' => true,
    'message' => "Upload complete.",
    'assigned' => $assigned,
    'skipped' => $skipped,
    'skipped_students' => $skippedStudents,
    'parsed_meta' => [
        'year_start' => $yearStart,
        'year_end' => $yearEnd,
        'semester' => $semester
    ]
]);
exit;
