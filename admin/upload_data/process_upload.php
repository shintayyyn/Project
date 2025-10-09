<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/PHPMailer/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/json; charset=utf-8');

// -----------------------------
// Permission Check
// -----------------------------
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// -----------------------------
// Receive Excel Data
// -----------------------------
if (!isset($_POST['excelData'])) {
    echo json_encode(['success' => false, 'message' => 'No Excel data received']);
    exit;
}

$rows = json_decode($_POST['excelData'], true);
$originalName = $_POST['filename'] ?? 'uploaded_excel';

if (!$rows || count($rows) < 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid or empty Excel data']);
    exit;
}

// -----------------------------
// Normalize Header
// -----------------------------
$header = $rows[0];
$map = [
    'student_first_name' => 'student_first_name',
    'student_last_name'  => 'student_last_name',
    'student_middle_name'=> 'student_middle_name',
    'student_suffix'     => 'student_suffix',
    'student_gender'     => 'student_gender',
    'student_birthdate (yyyy-mm-dd)' => 'student_birthdate',
    'student_birthdate' => 'student_birthdate',
    'student_contact_number' => 'student_contact_number',
    'student_email'     => 'student_email',
    'student_degree'    => 'student_degree',
    'year_level'        => 'year_level',
    'is_regular'        => 'is_regular',
    'is_solo'           => 'is_solo',
    'p_fname'           => 'p_fname',
    'p_lname'           => 'p_lname',
    'p_mname'           => 'p_mname',
    'p_suffix'          => 'p_suffix',
    'p_gender'          => 'p_gender',
    'p_bdate (yyyy-mm-dd)' => 'p_bdate',
    'p_bdate'           => 'p_bdate',
    'p_age'             => 'p_age',
    'p_email'           => 'p_email',
    'p_cnum'            => 'p_cnum'
];

$header = array_map(function($h) use ($map) {
    $h_clean = strtolower(trim($h));
    return $map[$h_clean] ?? preg_replace('/[^\w]/', '', $h_clean);
}, $header);

$dataRows = array_slice($rows, 1);

// -----------------------------
// Expected Columns
// -----------------------------
$expectedColumns = array_values($map);
$missingColumns = array_diff($expectedColumns, $header);
if (count($missingColumns) > 0) {
    echo json_encode(['success' => false, 'message' => 'Missing columns: ' . implode(', ', $missingColumns)]);
    exit;
}

$colIndex = array_flip($header);
$insertedCount = 0;
$errors = [];

$conn->begin_transaction();

try {
    // Get current active term
    $term_id = $conn->query("SELECT term_id FROM academic_terms WHERE is_active=1 LIMIT 1")
                    ->fetch_assoc()['term_id'] ?? null;

    foreach ($dataRows as $index => $row) {
        if (empty(array_filter($row))) continue;

        $rowNum = $index + 2; // Excel row number

        // -----------------------------
        // Student values
        // -----------------------------
        $s_fname    = $row[$colIndex['student_first_name']] ?? '';
        $s_lname    = $row[$colIndex['student_last_name']] ?? '';
        $s_mname    = $row[$colIndex['student_middle_name']] ?? '';
        $s_suffix   = $row[$colIndex['student_suffix']] ?? '';
        $s_gender   = $row[$colIndex['student_gender']] ?? '';
        $s_bdate    = $row[$colIndex['student_birthdate']] ?? '';
        $s_cnum     = $row[$colIndex['student_contact_number']] ?? '';
        $s_email    = $row[$colIndex['student_email']] ?? '';
        $s_password = strtolower(substr($s_fname,0,1).$s_lname.($s_bdate ? date('mdY', strtotime($s_bdate)) : ''));
        $s_status   = 'inactive';
        $degree_code_excel = $row[$colIndex['student_degree']] ?? '';
        $year_level = intval($row[$colIndex['year_level']] ?? 1);
        $is_regular = intval($row[$colIndex['is_regular']] ?? 1);
        $is_solo    = intval($row[$colIndex['is_solo']] ?? 1);
        $remarks    = ($is_solo === 2) ? 'Solo (No Parent)' : 'Living with Parents/Guardians';

        // Validate required student fields
        if (empty($s_fname) || empty($s_lname) || empty($s_email) || empty($s_bdate)) {
            $errors[] = "Row $rowNum: Missing required student data";
            continue;
        }
        if (!filter_var($s_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row $rowNum: Invalid student email $s_email";
            continue;
        }

        // Check duplicate student email
        $stmt = $conn->prepare("SELECT s_id FROM students WHERE s_email=?");
        $stmt->bind_param("s", $s_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Row $rowNum: Student already exists with email $s_email";
            $stmt->close();
            continue;
        }
        $stmt->close();

        // -----------------------------
        // Insert Student
        // -----------------------------
        $stmt = $conn->prepare("INSERT INTO students
            (s_fname, s_lname, s_mname, s_suffix, s_gender, s_bdate, s_cnum, s_email, s_password, s_status, year_level, is_regular, is_solo, remarks, term_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssiiiisi",
            $s_fname, $s_lname, $s_mname, $s_suffix,
            $s_gender, $s_bdate, $s_cnum, $s_email, $s_password,
            $s_status, $year_level, $is_regular, $is_solo, $remarks, $term_id
        );
        $stmt->execute();
        $s_id = $stmt->insert_id;
        $stmt->close();

        // -----------------------------
        // Generate Student ID Code
        // -----------------------------
        $year = date('Y', strtotime($s_bdate));
        $student_idcode = "S{$year}".str_pad($s_id,4,"0",STR_PAD_LEFT);
        $upd = $conn->prepare("UPDATE students SET idcode=? WHERE s_id=?");
        $upd->bind_param("si", $student_idcode, $s_id);
        $upd->execute();
        $upd->close();

        // -----------------------------
        // Insert Student Degree
        // -----------------------------
        if (!empty($degree_code_excel)) {
            $deg_stmt = $conn->prepare("SELECT degree_id, degree_code FROM degrees WHERE degree_code=?");
            $deg_stmt->bind_param("s", $degree_code_excel);
            $deg_stmt->execute();
            $degree_result = $deg_stmt->get_result();

            if ($degree_result->num_rows === 0) {
                $errors[] = "Row $rowNum: Invalid degree code '$degree_code_excel'";
            } else {
                $degree_row = $degree_result->fetch_assoc();
                $degree_id   = $degree_row['degree_id'];
                $degree_code = $degree_row['degree_code'];

                $stmt = $conn->prepare("INSERT INTO students_degrees
                    (s_id, degree_id, degree_code, s_fname, s_lname, s_mname, s_gender, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->bind_param("iisssss", $s_id, $degree_id, $degree_code, $s_fname, $s_lname, $s_mname, $s_gender);
                $stmt->execute();
                $stmt->close();
            }
            $deg_stmt->close();
        }
// -----------------------------
// Parent Handling
// -----------------------------
if ($is_solo === 2) {
    // Dummy parent
    $p_email = "solo_" . uniqid() . "@dummy.local";
    $p_status = 'inactive';
    $p_fname = '(No Parent)';
    $p_lname = 'Solo';
    $p_mname = '';
    $p_gender= 'N/A';
    $p_bdate = $s_bdate;
    $p_cnum  = $s_cnum;
    $p_password = $s_password; // same as student

    $stmt = $conn->prepare("INSERT INTO parents (p_fname, p_lname, p_mname, p_gender, p_bdate, p_cnum, p_email, p_status, p_password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $p_fname, $p_lname, $p_mname, $p_gender, $p_bdate, $p_cnum, $p_email, $p_status, $p_password);
    $stmt->execute();
    $p_id = $stmt->insert_id;
    $stmt->close();

    // Generate parent idcode
    $year = date('Y', strtotime($p_bdate));
    $parent_idcode = "P{$year}".str_pad($p_id,4,"0",STR_PAD_LEFT);
    $upd = $conn->prepare("UPDATE parents SET idcode=? WHERE p_id=?");
    $upd->bind_param("si", $parent_idcode, $p_id);
    $upd->execute();
    $upd->close();

} else {
    // Real parent info
    $p_fname = $row[$colIndex['p_fname']] ?? '';
    $p_lname = $row[$colIndex['p_lname']] ?? '';
    $p_mname = $row[$colIndex['p_mname']] ?? '';
    $p_gender= $row[$colIndex['p_gender']] ?? '';
    $p_bdate = $row[$colIndex['p_bdate']] ?? '';
    $p_cnum  = $row[$colIndex['p_cnum']] ?? '';
    $p_email = $row[$colIndex['p_email']] ?? '';
    $p_status= 'inactive';
    $p_password = $s_password; // optional: set same as student if needed

    if (!empty($p_fname) && !empty($p_lname) && !empty($p_bdate)) {
        $stmt = $conn->prepare("INSERT INTO parents (p_fname, p_lname, p_mname, p_gender, p_bdate, p_cnum, p_email, p_status, p_password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $p_fname, $p_lname, $p_mname, $p_gender, $p_bdate, $p_cnum, $p_email, $p_status, $p_password);
        $stmt->execute();
        $p_id = $stmt->insert_id;
        $stmt->close();

        // Generate parent idcode
        $year = date('Y', strtotime($p_bdate));
        $parent_idcode = "P{$year}".str_pad($p_id,4,"0",STR_PAD_LEFT);
        $upd = $conn->prepare("UPDATE parents SET idcode=? WHERE p_id=?");
        $upd->bind_param("si", $parent_idcode, $p_id);
        $upd->execute();
        $upd->close();
    }
}


        // -----------------------------
        // Link Parent-Student
        // -----------------------------
        if (isset($p_id)) {
            $stmt = $conn->prepare("INSERT INTO parent_student (s_id, p_id, term_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $s_id, $p_id, $term_id);
            $stmt->execute();
            $stmt->close();
        }

        $insertedCount++;
    }

    // -----------------------------
    // Commit main transaction
    // -----------------------------
    $conn->commit();

    // -----------------------------
    // Save Upload History
    // -----------------------------

$admin_id = $_SESSION['user_id'] ?? 0; // adjust if you store admin id differently
$raw_json = json_encode($rows);

$stmt = $conn->prepare("INSERT INTO upload_history 
    (filename, total_records, uploaded_by, raw_data, uploaded_at, status, is_deleted)
    VALUES (?, ?, ?, ?, NOW(), 'success', 0)");
$stmt->bind_param("siis", $originalName, $insertedCount, $admin_id, $raw_json);
$stmt->execute();
$stmt->close();


    echo json_encode(['success'=>true,'message'=>"Successfully processed $insertedCount records.", 'errors'=>$errors]);

} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit;
}
?>
