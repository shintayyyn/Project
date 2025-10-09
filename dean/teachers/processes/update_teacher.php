<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t_id = $_POST['t_id'];
    $t_fname = $_POST['t_fname'];
    $t_lname = $_POST['t_lname'];
    $t_mname = $_POST['t_mname'];
    $t_suffix = $_POST['t_suffix'];
    $t_gender = $_POST['t_gender'];
    $t_bdate = $_POST['t_bdate'];
    $t_cnum = $_POST['t_cnum'];
    $t_email = $_POST['t_email'];
    $t_status = $_POST['t_status'];

    // Calculate age based on birthdate
    $birthDate = new DateTime($t_bdate);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;

    $sql = "UPDATE teachers SET 
            t_fname = ?, t_lname = ?, t_mname = ?, t_suffix = ?, 
            t_gender = ?, t_bdate = ?, t_age = ?, t_cnum = ?, 
            t_email = ?, t_status = ? 
            WHERE t_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssi", 
        $t_fname, $t_lname, $t_mname, $t_suffix, 
        $t_gender, $t_bdate, $age, $t_cnum, 
        $t_email, $t_status, $t_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'data' => [
            't_id' => $t_id,
            't_fname' => $t_fname,
            't_lname' => $t_lname,
            't_mname' => $t_mname,
            't_suffix' => $t_suffix,
            't_gender' => $t_gender,
            't_bdate' => $t_bdate,
            't_age' => $age,
            't_cnum' => $t_cnum,
            't_email' => $t_email,
            't_status' => $t_status
        ]]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
