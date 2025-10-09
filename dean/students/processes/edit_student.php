<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../includes/db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $response = ['success' => false, 'message' => ''];

    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (empty($_POST['s_id'])) {
        throw new Exception('Student ID is required');
    }

    $id = filter_var($_POST['s_id'], FILTER_VALIDATE_INT);
    $email = !empty($_POST['s_email']) ? filter_var(trim($_POST['s_email']), FILTER_VALIDATE_EMAIL) : null;

    if ($id === false) {
        throw new Exception('Invalid student ID');
    }
    if ($email === false && !empty($_POST['s_email'])) {
        throw new Exception('Invalid email format');
    }

    $conn->begin_transaction();

    // Prepare variables
    $fname = trim($_POST['s_fname']);
    $lname = trim($_POST['s_lname']);
    $mname = trim($_POST['s_mname'] ?? '');
    $suffix = trim($_POST['s_suffix'] ?? '');
    $gender = $_POST['s_gender'];
    $bdate = $_POST['s_bdate'];
    $cnum = trim($_POST['s_cnum'] ?? '');
    $status = $_POST['s_status'];
    $degree_id = $_POST['degree_id'];

    // Get degree_code
    $degree_query = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id = ?");
    $degree_query->bind_param("i", $degree_id);
    $degree_query->execute();
    $degree_result = $degree_query->get_result();
    $degree_data = $degree_result->fetch_assoc();
    $degree_query->close();

    if (!$degree_data) {
        throw new Exception("Degree not found for ID: $degree_id");
    }

    $degree_code = $degree_data['degree_code'];

    // Update students table
    $stmt2 = $conn->prepare("UPDATE students SET 
        s_fname = ?, s_lname = ?, s_mname = NULLIF(?, ''), 
        s_suffix = NULLIF(?, ''), s_gender = ?, s_bdate = ?, 
        s_cnum = NULLIF(?, ''), s_email = NULLIF(?, ''), s_status = ? 
        WHERE s_id = ? LIMIT 1");

    $stmt2->bind_param("sssssssssi", 
        $fname, $lname, $mname, $suffix, $gender, $bdate,
        $cnum, $email, $status, $id
    );
    $stmt2->execute();
    $stmt2->close();

    // Update degree info in students_degrees
    $stmt3 = $conn->prepare("UPDATE students_degrees SET 
        degree_id = ?, degree_code = ? 
        WHERE s_id = ? LIMIT 1");

    $stmt3->bind_param("isi", $degree_id, $degree_code, $id);
    $stmt3->execute();
    $stmt3->close();

    // Fetch parent info (optional to return for UI update)
    $parent_sql = "
        SELECT p.p_id, p.p_fname, p.p_lname, p.p_mname, p.p_suffix, p.p_email, p.p_cnum
        FROM parents p
        INNER JOIN parent_student ps ON ps.p_id = p.p_id
        WHERE ps.s_id = ?
        LIMIT 1
    ";
    $parent_stmt = $conn->prepare($parent_sql);
    $parent_stmt->bind_param("i", $id);
    $parent_stmt->execute();
    $parent_result = $parent_stmt->get_result();
    $parent_data = $parent_result->fetch_assoc();
    $parent_stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student updated successfully',
        'title' => 'Success!',
        'data' => [
            's_id' => $id,
            's_fname' => $fname,
            's_lname' => $lname,
            's_mname' => $mname,
            's_suffix' => $suffix,
            's_gender' => $gender,
            's_bdate' => $bdate,
            's_cnum' => $cnum,
            's_email' => $email,
            's_status' => $status,
            'degree_id' => $degree_id,
            'degree_code' => $degree_code,
            'parent' => $parent_data ?: null
        ]
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'title' => 'Error!'
    ]);
    exit;
}
