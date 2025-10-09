<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require __DIR__ . '/../../../includes/PHPMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// ===============================
// 1. PERMISSION CHECK
// ===============================
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SESSION['user_type'] === 'teacher' && (!isset($_SESSION['is_dean']) || !$_SESSION['is_dean'])) {
    echo json_encode(['success' => false, 'message' => 'Only the dean can upload data.']);
    exit;
}

// ===============================
// 2. VALIDATE FILE
// ===============================
if (!isset($_FILES['student_file']) || $_FILES['student_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$uploadedFile = $_FILES['student_file'];
$allowedExtensions = ['xlsx', 'xls'];
$ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit;
}

$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('upload_', true) . '.' . $ext;
if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

// ===============================
// 3. GET CURRENT TERM
// ===============================
$currentTerm = $conn->query("
    SELECT t.term_id, ay.year_start, ay.year_end, t.semester
    FROM academic_terms t
    JOIN academic_years ay ON ay.ay_id = t.ay_id
    WHERE t.is_active = 1
    LIMIT 1
")->fetch_assoc();

if (!$currentTerm) {
    unlink($tempPath);
    echo json_encode(['success' => false, 'message' => 'No active term found.']);
    exit;
}

$term_id   = $currentTerm['term_id'];
$yearStart = $currentTerm['year_start'];
$yearEnd   = $currentTerm['year_end'];
$semester  = $currentTerm['semester'];

// ===============================
// 4. READ EXCEL
// ===============================
try {
    $spreadsheet = IOFactory::load($tempPath);
    $sheet = $spreadsheet->getActiveSheet();

    $rows = [];
    $highestRow = $sheet->getHighestDataRow();

    for ($row = 3; $row <= $highestRow; $row++) {
        $idCode     = trim((string)$sheet->getCell('B'.$row)->getValue());
        $degreeCode = trim((string)$sheet->getCell('D'.$row)->getValue());
        $yearLevel  = trim((string)$sheet->getCell('E'.$row)->getValue());
        $sectionId  = trim((string)$sheet->getCell('G'.$row)->getValue());

        if ($idCode === '' || $degreeCode === '' || $sectionId === '') continue;

        $rows[] = [
            'id_code'     => $idCode,
            'degree_code' => $degreeCode,
            'year_level'  => (int)$yearLevel,
            'section_id'  => (int)$sectionId
        ];
    }

    if (empty($rows)) {
        unlink($tempPath);
        echo json_encode(['success' => false, 'message' => 'No valid data found.']);
        exit;
    }

} catch (Exception $e) {
    unlink($tempPath);
    echo json_encode(['success' => false, 'message' => 'Failed to read Excel: ' . $e->getMessage()]);
    exit;
}

// ===============================
// 5. PROCESS
// ===============================
$assigned = $skipped = 0;
$skippedStudents = [];
$conn->begin_transaction();

try {
    foreach ($rows as $student) {
        // ===============================
        // 5.1 FETCH STUDENT
        // ===============================
        $stmt = $conn->prepare("SELECT s_id, s_fname, s_lname, is_regular, year_level FROM students WHERE idcode=? LIMIT 1");
        $stmt->bind_param('s', $student['id_code']);
        $stmt->execute();
        $res = $stmt->get_result();
        $sRow = $res->fetch_assoc();
        $stmt->close();

        if (!$sRow) {
            $skipped++;
            $skippedStudents[] = [
                'id_code' => $student['id_code'],
                'name' => 'Unknown',
                'reason' => 'Student not found'
            ];
            continue;
        }

        if ((int)$sRow['is_regular'] !== 1) {
            $skipped++;
            $skippedStudents[] = [
                'id_code' => $student['id_code'],
                'name' => $sRow['s_fname'].' '.$sRow['s_lname'],
                'reason' => 'Irregular student'
            ];
            continue;
        }

        $s_id = $sRow['s_id'];
        $fullName = $sRow['s_fname'].' '.$sRow['s_lname'];

        // ===============================
        // 5.2 VALIDATE DEGREE
        // ===============================
        $degStmt = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_code=? LIMIT 1");
        $degStmt->bind_param('s', $student['degree_code']);
        $degStmt->execute();
        $degRes = $degStmt->get_result();
        $degRow = $degRes->fetch_assoc();
        $degStmt->close();

        if (!$degRow) {
            $skipped++;
            $skippedStudents[] = [
                'id_code' => $student['id_code'],
                'name' => $fullName,
                'reason' => 'Degree not found'
            ];
            continue;
        }
        $degree_id = $degRow['degree_id'];

        // ===============================
        // 5.3 VALIDATE SECTION WITH DEGREE & YEAR LEVEL
        // ===============================
        $stmt = $conn->prepare("
            SELECT section_id, section_code, year_level 
            FROM sections
            WHERE section_id=? AND degree_id=? AND year_level=?
            LIMIT 1
        ");
        $stmt->bind_param('iii', $student['section_id'], $degree_id, $student['year_level']);
        $stmt->execute();
        $secRes = $stmt->get_result();
        $secRow = $secRes->fetch_assoc();
        $stmt->close();

        if (!$secRow) {
            $skipped++;
            $skippedStudents[] = [
                'id_code' => $student['id_code'],
                'name' => $fullName,
                'reason' => 'Section does not match degree or year level'
            ];
            continue;
        }

        $section_id = $secRow['section_id'];
        $section_code = $secRow['section_code'];

        // ===============================
        // 5.4 UPDATE STUDENT DEGREE
        // ===============================
        $stmt = $conn->prepare("
            INSERT INTO students_degrees (s_id, term_id, degree_id, degree_code)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE degree_id=VALUES(degree_id), degree_code=VALUES(degree_code)
        ");
        $stmt->bind_param('iiis', $s_id, $term_id, $degree_id, $student['degree_code']);
        $stmt->execute();
        $stmt->close();

        // ===============================
        // 5.5 ASSIGN SECTION TO STUDENT
        // ===============================
        $stmt = $conn->prepare("
            INSERT INTO students_sections (s_id, term_id, section_id, section_code)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE section_id=VALUES(section_id), section_code=VALUES(section_code)
        ");
        $stmt->bind_param('iiss', $s_id, $term_id, $section_id, $section_code);
        $stmt->execute();
        $stmt->close();

        // ===============================
        // 5.5.1 UPDATE GENERATED QR SECTION
        // ===============================
       $checkQR = $conn->prepare("SELECT id FROM generatedqrcode WHERE id = ?");
    $checkQR->bind_param('i', $s_id);
    $checkQR->execute();
    $checkQR->store_result();

    if ($checkQR->num_rows > 0) {
        $checkQR->close();

        $updateQR = $conn->prepare("
            UPDATE generatedqrcode 
            SET section = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateQR->bind_param('si', $section_code, $s_id);
        $updateQR->execute();
        $updateQR->close();
    } else {
        $checkQR->close();
    }


        // ===============================
        // 5.6 UPDATE STUDENT STATUS
        // ===============================
        $stmt = $conn->prepare("UPDATE students SET s_status='active', year_level=? WHERE s_id=?");
        $stmt->bind_param('ii', $student['year_level'], $s_id);
        $stmt->execute();
        $stmt->close();

// ===============================
// 5.7 ENROLL STUDENT INTO SUBJECTS
// ===============================

// First, get the student's section_code from students_sections
$sectionStmt = $conn->prepare("
    SELECT section_code 
    FROM students_sections 
    WHERE s_id=? AND section_id=?
");
$sectionStmt->bind_param('ii', $s_id, $section_id);
$sectionStmt->execute();
$sectionRes = $sectionStmt->get_result();
$sectionRow = $sectionRes->fetch_assoc();
$sectionStmt->close();

if (!$sectionRow) {
    $students_with_notes[] = [
        'id_code' => $student['id_code'],
        'name' => $fullName,
        'reason' => 'Student section not found'
    ];
    continue;
}

$section_code = $sectionRow['section_code'];

// Now get the subjects for that section (ignore term_id)
$subStmt = $conn->prepare("
    SELECT subject_code 
    FROM sections_schedules 
    WHERE section_id=?
");
$subStmt->bind_param('i', $section_id);
$subStmt->execute();
$subRes = $subStmt->get_result();
$subStmt->close();

if ($subRes->num_rows > 0) {
    $enrollStmt = $conn->prepare("
        INSERT INTO subject_enrollments 
        (s_id, subject_code, section_code, term_id, is_status, enrollment_status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            section_code=VALUES(section_code), 
            enrollment_status=VALUES(enrollment_status),
            updated_at=NOW()
    ");

    while ($subRow = $subRes->fetch_assoc()) {
        $subject_code = $subRow['subject_code'];
        $status = 1;
        $enroll_status = 'Enrolled';

        $enrollStmt->bind_param('issiis', $s_id, $subject_code, $section_code, $term_id, $status, $enroll_status);
        $enrollStmt->execute();
    }

    $enrollStmt->close();
} else {
    // Section has no subjects – log reason but do not count as skipped
    $students_with_notes[] = [
        'id_code' => $student['id_code'],
        'name' => $fullName,
        'reason' => 'No subjects found for this section'
    ];
}


// Continue to next student



        $assigned++;
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    unlink($tempPath);
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
    exit;
}

unlink($tempPath);

// ===============================
// 6. RESPONSE
// ===============================
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
