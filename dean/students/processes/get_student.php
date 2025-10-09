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
        throw new Exception('Invalid student ID');
    }

    $student_id = $_GET['id'];

    $sql = "SELECT 
                s.s_id, s.s_fname, s.s_lname, s.s_mname, s.s_suffix, s.s_gender, 
                s.s_bdate, s.s_cnum, s.s_email, s.s_status, 

                d.degree_id, d.degree_code,

                p.p_id as parent_id, 
                p.p_fname as parent_fname, 
                p.p_lname as parent_lname, 
                p.p_mname as parent_mname, 
                p.p_suffix as parent_suffix,
                p.p_gender as parent_gender,
                p.p_bdate as parent_bdate,
                p.p_cnum as parent_cnum,
                p.p_email as parent_email,
                p.p_status as parent_status,
                p.p_password_plain as parent_password_plain

            FROM students s
            LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
            LEFT JOIN degrees d ON sd.degree_id = d.degree_id
            LEFT JOIN parent_student ps ON s.s_id = ps.s_id
            LEFT JOIN parents p ON ps.p_id = p.p_id
            WHERE s.s_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $student_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['status'] = 'success';
            $response['data'] = $result->fetch_assoc();
        } else {
            $response['message'] = 'Student not found';
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
?>