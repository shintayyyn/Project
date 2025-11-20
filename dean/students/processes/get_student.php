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

    $sql = "
        SELECT 
            s.*,
            sd.degree_id,
            sd.degree_code,
            ss.section_id,
            sec.section_code,
            CONCAT(
                p.p_fname, ' ',
                COALESCE(CONCAT(p.p_mname, ' '), ''),
                p.p_lname,
                COALESCE(CONCAT(' ', p.p_suffix), '')
            ) AS parent_fullname
        FROM students s
        LEFT JOIN (
    SELECT * FROM students_degrees WHERE s_status = 'active'
) sd ON s.s_id = sd.s_id

        LEFT JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN sections sec ON ss.section_id = sec.section_id
        LEFT JOIN parent_student ps ON s.s_id = ps.s_id
        LEFT JOIN parents p ON ps.p_id = p.p_id
        WHERE s.s_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $student_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();

            // Ensure age is always an integer
            $student['s_age'] = !empty($student['s_bdate']) ? (int)date_diff(date_create($student['s_bdate']), date_create('today'))->y : 0;

            // Ensure parent_fullname exists
            $student['parent_fullname'] = !empty($student['parent_fullname']) ? $student['parent_fullname'] : 'No record';

            $response['status'] = 'success';
            $response['data'] = $student;
        } else {
            $response['message'] = 'Student not found';
        }
    } else {
        $response['message'] = 'Failed to fetch student data';
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}

echo json_encode($response);
