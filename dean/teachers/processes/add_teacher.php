<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../includes/db.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Check authorization
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    $required_fields = ['t_fname', 't_lname', 't_gender', 't_bdate', 't_cnum', 't_email', 't_password_plain'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucfirst(str_replace('t_', '', $field)) . ' is required');
        }
    }

    // Validate email format
    if (!filter_var($_POST['t_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Prepare all variables before binding
        $fname = $_POST['t_fname'];
        $lname = $_POST['t_lname'];
        $mname = $_POST['t_mname'] ?? '';
        $suffix = $_POST['t_suffix'] ?? '';
        $gender = $_POST['t_gender'];
        $bdate = $_POST['t_bdate'];
        $cnum = $_POST['t_cnum'];
        $email = $_POST['t_email'];
        $password = $_POST['t_password_plain'];
        $status = $_POST['t_status'] ?? 'active';

        // Check for duplicate email
        $check_email = $conn->prepare("SELECT t_id FROM teachers WHERE t_email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            throw new Exception('Duplicate entry for email');
        }
        $check_email->close();

        // Insert into teachers table
        $stmt = $conn->prepare("INSERT INTO teachers (t_fname, t_lname, t_mname, t_suffix, t_gender, 
            t_bdate, t_cnum, t_email, t_password, t_status) 
            VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssssssss", 
            $fname, $lname, $mname, $suffix, $gender, 
            $bdate, $cnum, $email, $password, $status
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();

            $conn->commit();
            
            // Modify the success response to include the teacher data
            $response = [
                'success' => true,
                'message' => 'Teacher added successfully',
                'data' => [
                    't_id' => $new_id,
                    't_fname' => $fname,
                    't_lname' => $lname,
                    't_mname' => $mname,
                    't_suffix' => $suffix,
                    't_gender' => $gender,
                    't_bdate' => $bdate,
                    't_cnum' => $cnum,
                    't_email' => $email,
                    't_password' => $password,
                    't_status' => $status
                ]
            ];

            echo json_encode($response);
            exit;

        } else {
            throw new Exception('Failed to add teacher');
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'title' => 'Error!'
    ]);
    exit;
}
