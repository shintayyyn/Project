<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../includes/db.php';

    if (session_status() === PHP_SESSION_NONE) session_start();

    // Authorization check
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'title' => 'Unauthorized',
            'message' => 'You are not allowed to perform this action. Please log in as an admin.'
        ]);
        exit;
    }

    // Required fields
    $required_fields = [
        't_fname' => 'First Name',
        't_lname' => 'Last Name',
        't_gender' => 'Gender',
        't_bdate' => 'Birthdate',
        't_cnum' => 'Contact Number',
        't_email' => 'Email',
        't_department' => 'Department'
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'title' => 'Missing Information',
                'message' => "$label is required. Please fill it in before submitting."
            ]);
            exit;
        }
    }

    // Validate email
    $email = filter_var($_POST['t_email'], FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'title' => 'Invalid Email',
            'message' => 'The email address you entered is not valid. Please check and try again.'
        ]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Duplicate email check
        $check = $conn->prepare("SELECT t_id FROM teachers WHERE t_email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'title' => 'Duplicate Email',
                'message' => 'This email is already registered. Try using another one.'
            ]);
            exit;
        }

        // Age calculation
        $birthDate = new DateTime($_POST['t_bdate']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;

        // Prepare data
        $fname = trim($_POST['t_fname']);
        $lname = trim($_POST['t_lname']);
        $mname = trim($_POST['t_mname'] ?? '');
        $suffix = trim($_POST['t_suffix'] ?? '');
        $gender = $_POST['t_gender'];
        $bdate = $_POST['t_bdate'];
        $cnum = trim($_POST['t_cnum']);
        
        // Password
        if (empty($_POST['t_password'])) {
            $default_password = strtolower(substr($fname, 0, 1)) .
                                strtolower($lname) .
                                date("mdY", strtotime($bdate));
            $password = password_hash($default_password, PASSWORD_BCRYPT);
        } else {
            $password = password_hash($_POST['t_password'], PASSWORD_BCRYPT);
        }

        $department = trim($_POST['t_department']);
        $status = $_POST['t_status'] ?? 'active';

        // Insert teacher
        $stmt = $conn->prepare("INSERT INTO teachers 
            (t_fname, t_lname, t_mname, t_suffix, t_gender, t_bdate, t_age, t_cnum, t_email, t_password, t_department, t_status, t_created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->bind_param(
            "ssssssisssss",
            $fname, $lname, $mname, $suffix, $gender, $bdate,
            $age, $cnum, $email, $password, $department, $status
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to add teacher.');
        }

        $insertedId = $conn->insert_id;

        // Fetch t_created_at
        $select = $conn->prepare("SELECT t_created_at FROM teachers WHERE t_id = ?");
        $select->bind_param("i", $insertedId);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $created_at = $row['t_created_at'];

        // Generate idcode
        $idcode = 'T' . date('Y', strtotime($created_at)) . str_pad($insertedId, 4, '0', STR_PAD_LEFT);

        $update = $conn->prepare("UPDATE teachers SET idcode = ? WHERE t_id = ?");
        $update->bind_param("si", $idcode, $insertedId);
        $update->execute();

        // Get degree_code
        $degreeStmt = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id = ?");
        $degreeStmt->bind_param("i", $department);
        $degreeStmt->execute();
        $degreeResult = $degreeStmt->get_result();
        $degreeRow = $degreeResult->fetch_assoc();
        $degreeCode = $degreeRow ? $degreeRow['degree_code'] : 'Unknown';

        $conn->commit();

        // Success
        echo json_encode([
            'success' => true,
            'title' => 'Teacher successfully added.',
            'message' => "$fname $lname has been added successfully!",
            'data' => [
                't_id' => $insertedId,
                'idcode' => $idcode,
                't_fname' => $fname,
                't_mname' => $mname,
                't_lname' => $lname,
                't_suffix' => $suffix,
                'full_name' => trim("$fname $mname $lname $suffix"),
                't_gender' => $gender,
                't_bdate' => $bdate,
                't_age' => $age,
                't_cnum' => $cnum,
                't_email' => $email,
                't_status' => $status,
                't_avatar' => null,
                'degree_code' => $degreeCode
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'title' => 'Something Went Wrong',
            'message' => 'We could not add the teacher right now. Please try again later.'
        ]);
        // For debugging only (log to server, not user)
        error_log("Add Teacher Error: " . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'title' => 'Critical Error',
        'message' => 'A critical error occurred. Please contact support if this continues.'
    ]);
    error_log("Critical Error: " . $e->getMessage());
}
