<?php
error_reporting(0);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../includes/db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    // --- Authorization check ---
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    // --- Method check ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // --- Validation ---
    if (empty($_POST['s_id'])) throw new Exception('Student ID is required');

    $id = filter_var($_POST['s_id'], FILTER_VALIDATE_INT);
    $email = !empty($_POST['s_email']) ? filter_var($_POST['s_email'], FILTER_VALIDATE_EMAIL) : null;

    if ($id === false) throw new Exception('Invalid student ID');
    if ($email === false && !empty($_POST['s_email'])) throw new Exception('Invalid email format');

    // --- Fetch existing student and degree ---
    $stmt = $conn->prepare("
        SELECT s.s_fname, s.s_lname, s.s_mname, s.s_suffix,
               s.s_gender, s.s_bdate, s.s_cnum, s.s_address,
               s.s_email, s.s_status, s.year_level, s.is_regular, sd.degree_id
        FROM students s
        LEFT JOIN (
            SELECT * FROM students_degrees WHERE status = 'Active'
        ) sd ON s.s_id = sd.s_id
        WHERE s.s_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentData = $result->fetch_assoc();
    $stmt->close();

    if (!$currentData) throw new Exception('Student record not found.');

    // --- Prepare new input data ---
    $newData = [
        's_fname'    => $_POST['s_fname'],
        's_mname'    => $_POST['s_mname'] ?? '',
        's_lname'    => $_POST['s_lname'],
        's_suffix'   => $_POST['s_suffix'] ?? '',
        's_gender'   => $_POST['s_gender'],
        's_bdate'    => $_POST['s_bdate'],
        's_cnum'     => $_POST['s_cnum'] ?? '',
        's_address'  => $_POST['s_address'] ?? '',
        's_email'    => $email,
        's_status'   => $_POST['s_status'],
        'year_level' => $_POST['year_level'] ?? null,
        'is_regular' => isset($_POST['is_regular']) && $_POST['is_regular'] ? 1 : 0,
        'degree_id'  => $_POST['degree_id']
    ];

    // --- Compare old and new data to detect changes ---
    $changes = array_diff_assoc($newData, $currentData);

    // --- Get degree code ---
    $degree_query = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id = ?");
    $degree_query->bind_param("i", $newData['degree_id']);
    $degree_query->execute();
    $degree_result = $degree_query->get_result();
    $degree_data = $degree_result->fetch_assoc();
    $degree_code = $degree_data['degree_code'] ?? null;
    $degree_query->close();
    if (!$degree_code) throw new Exception('Invalid degree selected.');

    $conn->begin_transaction();

    // --- Update main student record ---
    $stmt2 = $conn->prepare("
        UPDATE students SET 
            s_fname = ?, s_lname = ?, s_mname = NULLIF(?, ''), 
            s_suffix = NULLIF(?, ''), s_gender = ?, s_bdate = ?, 
            s_cnum = NULLIF(?, ''), s_address = NULLIF(?, ''), 
            s_email = NULLIF(?, ''), s_status = ?, year_level = ?, is_regular = ?
        WHERE s_id = ?
    ");
  $stmt2->bind_param(
    "ssssssssssiii", // 10 strings + 3 integers = 13
    $newData['s_fname'],
    $newData['s_lname'],
    $newData['s_mname'],
    $newData['s_suffix'],
    $newData['s_gender'],
    $newData['s_bdate'],
    $newData['s_cnum'],
    $newData['s_address'],
    $newData['s_email'],
    $newData['s_status'], // string
    $newData['year_level'], // integer
    $newData['is_regular'], // integer
    $id // integer
);


    $stmt2->execute();
    $stmt2->close();

    // --- Update degree record if changed ---
    if ($newData['degree_id'] != $currentData['degree_id']) {
        // Fetch active degree
        $stmt_deg_check = $conn->prepare("
            SELECT sd_id FROM students_degrees 
            WHERE s_id = ? AND status = 'Active' 
            ORDER BY enrollment_date DESC LIMIT 1
        ");
        $stmt_deg_check->bind_param("i", $id);
        $stmt_deg_check->execute();
        $res_deg = $stmt_deg_check->get_result();
        $existingDeg = $res_deg->fetch_assoc();
        $stmt_deg_check->close();

        if ($existingDeg) {
            // Update existing active degree
            $stmt_update_deg = $conn->prepare("
                UPDATE students_degrees 
                SET degree_id = ?, degree_code = ?
                WHERE sd_id = ?
            ");
            $stmt_update_deg->bind_param("isi", $newData['degree_id'], $degree_code, $existingDeg['sd_id']);
            $stmt_update_deg->execute();
            $stmt_update_deg->close();
        } else {
            // Insert new degree
            $stmt_insert_deg = $conn->prepare("
                INSERT INTO students_degrees (s_id, degree_id, degree_code, status) 
                VALUES (?, ?, ?, 'Active')
            ");
            $stmt_insert_deg->bind_param("iis", $id, $newData['degree_id'], $degree_code);
            $stmt_insert_deg->execute();
            $stmt_insert_deg->close();
        }
    }

    $conn->commit();

    // --- Fetch updated student with section and parent ---
    $stmt4 = $conn->prepare("
        SELECT 
            s.*, sd.degree_code, ss.section_id, sec.section_code,
            CONCAT(
                p.p_fname, ' ',
                COALESCE(CONCAT(p.p_mname, ' '), ''),
                p.p_lname,
                COALESCE(CONCAT(' ', p.p_suffix), '')
            ) AS parent_fullname
        FROM students s
        LEFT JOIN (
            SELECT * FROM students_degrees sd1 WHERE status = 'Active'
        ) sd ON s.s_id = sd.s_id
        LEFT JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN sections sec ON ss.section_id = sec.section_id
        LEFT JOIN parent_student ps ON s.s_id = ps.s_id
        LEFT JOIN parents p ON ps.p_id = p.p_id
        WHERE s.s_id = ?
        LIMIT 1
    ");
    $stmt4->bind_param("i", $id);
    $stmt4->execute();
    $result4 = $stmt4->get_result();
    $student = $result4->fetch_assoc();
    $stmt4->close();

    // --- Calculate age ---
    $student['age'] = !empty($student['s_bdate']) ? (int)date_diff(date_create($student['s_bdate']), date_create('today'))->y : null;
    $student['parent_fullname'] = !empty($student['parent_fullname']) ? $student['parent_fullname'] : 'No record';

    echo json_encode([
        'success' => true,
        'message' => 'Student updated successfully.',
        'title'   => 'Success!',
        'data' => $student
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping()) $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'title'   => 'Error!'
    ]);
    exit;
}
?>
