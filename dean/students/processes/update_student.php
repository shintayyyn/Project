
<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_id = $_POST['s_id'];
    $s_fname = $_POST['s_fname'];
    $s_lname = $_POST['s_lname'];
    $s_mname = $_POST['s_mname'];
    $s_suffix = $_POST['s_suffix'];
    $s_gender = $_POST['s_gender'];
    $s_bdate = $_POST['s_bdate'];
    $s_cnum = $_POST['s_cnum'];
    $s_email = $_POST['s_email'];
    $s_status = $_POST['s_status'];

    // Calculate age based on birthdate
    $birthDate = new DateTime($s_bdate);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;

    $sql = "UPDATE students SET 
            s_fname = ?, s_lname = ?, s_mname = ?, s_suffix = ?, 
            s_gender = ?, s_bdate = ?, s_age = ?, s_cnum = ?, 
            s_email = ?, s_status = ? 
            WHERE s_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssi", 
        $s_fname, $s_lname, $s_mname, $s_suffix, 
        $s_gender, $s_bdate, $age, $s_cnum, 
        $s_email, $s_status, $s_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'data' => [
            's_id' => $s_id,
            's_fname' => $s_fname,
            's_lname' => $s_lname,
            's_mname' => $s_mname,
            's_suffix' => $s_suffix,
            's_gender' => $s_gender,
            's_bdate' => $s_bdate,
            's_age' => $age,
            's_cnum' => $s_cnum,
            's_email' => $s_email,
            's_status' => $s_status
        ]]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);