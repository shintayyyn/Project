<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$action = $_POST['action'] ?? '';
$user_id = $_POST['user_id'] ?? '';

$tables = [
    'students' => ['id_col' => 's_id', 'email_col' => 's_email'],
    'teachers' => ['id_col' => 't_id', 'email_col' => 't_email'],
    'parents'  => ['id_col' => 'p_id', 'email_col' => 'p_email']
];

// === SEND OTP ===
if ($action == "send_otp") {
    $email = $_POST['email'] ?? '';

    $userExists = false;
    foreach ($tables as $table => $cols) {
        $stmt = $conn->prepare("SELECT {$cols['id_col']} FROM $table WHERE {$cols['id_col']}=? AND {$cols['email_col']}=? LIMIT 1");
        $stmt->bind_param("is", $user_id, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $userExists = true;
            break;
        }
    }

    if (!$userExists) {
        echo json_encode(['error' => "User ID or Email not found"]);
        exit;
    }

    $otp = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Insert or update OTP
    $stmt = $conn->prepare("INSERT INTO password_resets(user_id, otp_code, otp_expiry, created_at) 
        VALUES(?,?,?,NOW()) 
        ON DUPLICATE KEY UPDATE otp_code=?, otp_expiry=?, created_at=NOW()");
    $stmt->bind_param("issss", $user_id, $otp, $expiry, $otp, $expiry);

    if ($stmt->execute()) {
        $subject = "Your OTP Code for Password Reset";
        $body = "Hello,<br><br>Your OTP code is: <b>$otp</b><br>It will expire in 5 minutes.<br><br>Regards,<br>Attendify";
        $mailSent = sendMail($email, $subject, $body);

        if ($mailSent) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => "Failed to send OTP email"]);
        }
    } else {
        echo json_encode(['error' => "Failed to generate OTP"]);
    }
    exit;
}

// === VERIFY OTP ===
if ($action == "verify_otp") {
    $otp = $_POST['otp'] ?? '';

    $stmt = $conn->prepare("
        SELECT otp_code, otp_expiry 
        FROM password_resets 
        WHERE user_id=? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && strval($res['otp_code']) == strval($otp) && strtotime($res['otp_expiry']) > time()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => "Invalid or expired OTP"]);
    }
    exit;
}

// === SAVE NEW PASSWORD ===
if ($action == "save_password") {
    $password = $_POST['password'] ?? '';
    if (empty($password)) {
        echo json_encode(['error'=>"Password cannot be empty"]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Update password_resets (optional)
    $stmt = $conn->prepare("UPDATE password_resets SET new_password=?, otp_code=NULL, otp_expiry=NULL WHERE user_id=?");
    $stmt->bind_param("si", $hashed, $user_id);
    $stmt->execute();

    // Update actual user table dynamically
    foreach ($tables as $table => $cols) {
        // Find password column dynamically
        $colResult = $conn->query("SHOW COLUMNS FROM `$table` LIKE '%password%'");
        $colRow = $colResult->fetch_assoc();
        if ($colRow) {
            $password_column = $colRow['Field'];

            $stmt2 = $conn->prepare("UPDATE $table SET `$password_column`=? WHERE {$cols['id_col']}=?");
            $stmt2->bind_param("si", $hashed, $user_id);
            $stmt2->execute();
        }
    }

    echo json_encode(['success' => true]);
    exit;
}
