<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
require_once __DIR__ . '/../../../includes/db.php';
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => ''];

try {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid teacher ID');
    }

    $teacher_id = intval($_GET['id']);
    $dean_id = $_SESSION['t_id'] ?? null;

    if (!$dean_id) {
        throw new Exception('Dean ID not found in session');
    }

    // Get dean's assigned department
    $dept_stmt = $conn->prepare("SELECT t_department FROM teachers WHERE t_id = ?");
    $dept_stmt->bind_param("i", $dean_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();

    if ($dept_result->num_rows === 0) {
        throw new Exception('Dean not found');
    }

    $dean = $dept_result->fetch_assoc();
    $dean_department = $dean['t_department'];
    $dept_stmt->close();

    // Now fetch the teacher within that department
    $sql = "SELECT 
                t.t_id, t.t_fname, t.t_lname, t.t_mname, t.t_suffix, 
                t.t_gender, t.t_bdate, t.t_cnum, t.t_email, 
                t.t_status, t.t_password
            FROM teachers t
            WHERE t.t_id = ? AND t.t_department = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $teacher_id, $dean_department);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['status'] = 'success';
            $response['data'] = $result->fetch_assoc();
        } else {
            $response['message'] = 'Teacher not found or not under your department';
        }
    } else {
        $response['message'] = 'Failed to execute query';
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode($response);
