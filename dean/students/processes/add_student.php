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
    $required_fields = ['s_fname', 's_lname', 's_gender', 's_bdate', 's_cnum', 's_email', 's_password', 'degree_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucfirst(str_replace('s_', '', $field)) . ' is required');
        }
    }

    // Validate email format
    if (!filter_var($_POST['s_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Prepare all variables before binding
        $fname = $_POST['s_fname'];
        $lname = $_POST['s_lname'];
        $mname = $_POST['s_mname'] ?? '';
        $suffix = $_POST['s_suffix'] ?? '';
        $gender = $_POST['s_gender'];
        $bdate = $_POST['s_bdate'];
        $cnum = $_POST['s_cnum'];
        $email = $_POST['s_email'];
        $password = $_POST['s_password'];
        $status = $_POST['s_status'] ?? 'active';

        // Check for duplicate email
        $check_email = $conn->prepare("SELECT s_id FROM students WHERE s_email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        if ($check_email->num_rows > 0) {
            throw new Exception('Duplicate entry for email');
        }
        $check_email->close();

        // Insert into students table
        $stmt = $conn->prepare("INSERT INTO students (s_fname, s_lname, s_mname, s_suffix, s_gender, 
            s_bdate, s_cnum, s_email, s_password, s_status) 
            VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssssssss", 
            $fname, $lname, $mname, $suffix, $gender, 
            $bdate, $cnum, $email, $password, $status
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();

            // Get degree information
            $degree_id = $_POST['degree_id'];
            $degree_query = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id = ?");
            $degree_query->bind_param("i", $degree_id);
            $degree_query->execute();
            $degree_result = $degree_query->get_result();
            $degree_data = $degree_result->fetch_assoc();
            $degree_code = $degree_data['degree_code'];
            $degree_query->close();
                
            // Insert into students_degrees table with selected degree
            $stmt = $conn->prepare("INSERT INTO students_degrees (s_id, degree_id, degree_code, s_fname, s_lname, s_mname, s_gender, status) 
                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, 'Active')");

            $stmt->bind_param("iisssss",
                $new_id,
                $degree_id,
                $degree_code,
                $fname,
                $lname,
                $mname,
                $gender
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();
            
            // Modify the success response to include the student data
            $response = [
                'success' => true,
                'message' => 'Student added successfully',
                'data' => [
                    's_id' => $new_id,
                    's_fname' => $fname,
                    's_lname' => $lname,
                    's_mname' => $mname,
                    's_suffix' => $suffix,
                    's_gender' => $gender,
                    's_bdate' => $bdate,
                    's_cnum' => $cnum,
                    's_email' => $email,
                    's_password' => $password,
                    's_status' => $status,
                    'degree_id' => $degree_id,
                    'degree_code' => $degree_code
                ]
            ];

            echo json_encode($response);
            exit;

        } else {
            throw new Exception('Failed to add student');
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
