<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$action   = $_POST['action'] ?? '';
$input_id = $_POST['user_id'] ?? '';
$email    = $_POST['email'] ?? '';

$tables = [
    'students' => ['id_col' => 's_id', 'email_col' => 's_email', 'user_type' => 'student'],
    'teachers' => ['id_col' => 't_id', 'email_col' => 't_email', 'user_type' => 'teacher'],
    'parents'  => ['id_col' => 'p_id', 'email_col' => 'p_email', 'user_type' => 'parent']
];

// Helper
function resolveOfficialId($conn, $tables, $input_id, $email = '')
{
    foreach ($tables as $table => $cols) {

        if ($email !== '') {
            // For SEND OTP (requires both ID Code + Email)
            $stmt = $conn->prepare("SELECT {$cols['id_col']} FROM $table WHERE idcode = ? AND {$cols['email_col']} = ? LIMIT 1");
            $stmt->bind_param("ss", $input_id, $email);
        } else {
            // For verify / save password
            $stmt = $conn->prepare("SELECT {$cols['id_col']} FROM $table WHERE {$cols['id_col']}=? OR idcode=? LIMIT 1");
            $stmt->bind_param("ss", $input_id, $input_id);
        }

        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return [
                'id' => $row[$cols['id_col']],
                'user_type' => $cols['user_type']
            ];
        }
    }
    return false;
}



/* ============================================================
   SEND OTP
============================================================ */
if ($action == "send_otp") {

    if (empty($input_id) || empty($email)) {
        echo json_encode(['error' => "ID Code and Email are required"]);
        exit;
    }

    $resolved = resolveOfficialId($conn, $tables, $input_id, $email);
    if (!$resolved) {
        echo json_encode(['error' => "User ID or Email not found"]);
        exit;
    }

    $official_id = $resolved['id'];
    $user_type   = $resolved['user_type'];

    $otp = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("
        INSERT INTO password_resets (user_id, user_type, otp_code, otp_expiry, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE otp_code=?, otp_expiry=?, user_type=?, created_at=NOW()
    ");
    $stmt->bind_param("sssssss", $official_id, $user_type, $otp, $expiry, $otp, $expiry, $user_type);

    if ($stmt->execute()) {

        $subject = "Your OTP Code for Password Reset";
        $body = "Hello,<br><br>Your OTP code is: <b>$otp</b><br>It will expire in 10 minutes.<br><br>Regards,<br>Attendify";
        $mailSent = sendMail($email, $subject, $body);

        if ($mailSent) {
            echo json_encode([
                'success' => true,
                'user_type' => $user_type,
                'expires_at' => $expiry   // Added for JS display
            ]);
        } else {
            echo json_encode(['error' => "Failed to send OTP email"]);
        }

    } else {
        echo json_encode(['error' => "Failed to generate OTP"]);
    }

    exit;
}



/* ============================================================
   VERIFY OTP
============================================================ */
if ($action == "verify_otp") {

    $otp_input = $_POST['otp'] ?? '';

    if (empty($input_id) || empty($otp_input)) {
        echo json_encode(['error' => "OTP is required"]);
        exit;
    }

    $resolved = resolveOfficialId($conn, $tables, $input_id);
    if (!$resolved) {
        echo json_encode(['error' => "User not found"]);
        exit;
    }

    $official_id = $resolved['id'];
    $user_type   = $resolved['user_type'];

    // Only valid if expiry > NOW()
    $stmt = $conn->prepare("
        SELECT otp_code, otp_expiry FROM password_resets
        WHERE user_id=? ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("s", $official_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res) {
        echo json_encode(['error' => "No OTP found"]);
        exit;
    }

    // Check expiry
    if (strtotime($res['otp_expiry']) < time()) {
        echo json_encode(['error' => "OTP expired"]);
        exit;
    }

    // Check match
    if (hash_equals($res['otp_code'], $otp_input)) {
        echo json_encode([
            'success' => true,
            'user_type' => $user_type
        ]);
    } else {
        echo json_encode(['error' => "Invalid OTP"]);
    }

    exit;
}



/* ============================================================
   SAVE NEW PASSWORD
============================================================ */
if ($action == "save_password") {

    $password = $_POST['password'] ?? '';

    if (empty($input_id) || empty($password)) {
        echo json_encode(['error' => "User ID and Password are required"]);
        exit;
    }

    $resolved = resolveOfficialId($conn, $tables, $input_id);
    if (!$resolved) {
        echo json_encode(['error' => "User not found"]);
        exit;
    }

    $official_id = $resolved['id'];
    $user_type   = $resolved['user_type'];

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Update password_resets entry
    $stmt = $conn->prepare("
        UPDATE password_resets
        SET new_password=?, otp_code=NULL, otp_expiry=NULL, user_type=?
        WHERE user_id=?
    ");
    $stmt->bind_param("sss", $hashed, $user_type, $official_id);
    $stmt->execute();

    // Update actual user table
    foreach ($tables as $table => $cols) {
        if ($cols['user_type'] === $user_type) {

            $colResult = $conn->query("SHOW COLUMNS FROM `$table` LIKE '%password%'");
            $colRow = $colResult->fetch_assoc();

            if ($colRow) {
                $password_column = $colRow['Field'];

                $stmt2 = $conn->prepare("UPDATE $table SET `$password_column`=? WHERE {$cols['id_col']}=?");
                $stmt2->bind_param("ss", $hashed, $official_id);
                $stmt2->execute();
            }
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

?>
