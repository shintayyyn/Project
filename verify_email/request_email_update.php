<?php
session_start();
require_once('../includes/db.php');
require_once('../includes/mailer.php');
header('Content-Type: application/json');

// --------------------
// 1️⃣ Detect logged-in user
// --------------------
if (!isset($_SESSION['user_type'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
    exit();
}

switch ($_SESSION['user_type']) {
    case 'parent':
        if (!isset($_SESSION['parent_id'])) break;
        $user_id = $_SESSION['parent_id'];
        $user_type = 'parent';
        $table = 'parents';
        $id_col = 'p_id';
        $email_col = 'p_email';
        break;
    case 'student':
        if (!isset($_SESSION['user_id'])) break;
        $user_id = $_SESSION['user_id'];
        $user_type = 'student';
        $table = 'students';
        $id_col = 's_id';
        $email_col = 's_email';
        break;
    case 'teacher':
        if (!isset($_SESSION['user_id'])) break;
        $user_id = $_SESSION['user_id'];
        $user_type = 'teacher';
        $table = 'teachers';
        $id_col = 't_id';
        $email_col = 't_email';
        break;
    default:
        echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
        exit();
}

// --------------------
// 2️⃣ Validate email input
// --------------------
$new_email = trim($_POST['new_email'] ?? '');
if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'danger', 'message' => 'Please provide a valid email address.']);
    exit();
}

// --------------------
// 3️⃣ Get current email
// --------------------
$stmt = $conn->prepare("SELECT $email_col FROM $table WHERE $id_col = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentEmail = $userData[$email_col] ?? null;

// --------------------
// 4️⃣ Determine if new email matches current email
// --------------------
$isSameEmail = ($new_email === $currentEmail);

// --------------------
// 5️⃣ Check email_verifications status for THIS user + email
// --------------------
$stmt = $conn->prepare("
    SELECT verified 
    FROM email_verifications
    WHERE user_id = ? 
      AND user_type = ? 
      AND new_email = ?
    ORDER BY id DESC 
    LIMIT 1
");
$stmt->bind_param("iss", $user_id, $user_type, $new_email);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$stmt->close();

$hasRecord = $verification ? 1 : 0;
$isVerified = $verification['verified'] ?? 0;

// ----------------------------------------------------------------
// 🎯 FINAL DECISION RULES:
// 1. Same email + verified → BLOCK
// 2. Same email + NOT verified → ALLOW
// 3. New email → ALLOW
// ----------------------------------------------------------------
if ($isSameEmail && $hasRecord && $isVerified == 1) {
    echo json_encode([
        'status' => 'warning',
        'message' => 'It is still your current email, kindly use a new one to proceed.'
    ]);
    exit();
}

// --------------------
// 4️⃣a Check if another user already verified this email
// --------------------
$tables = [
    ['table' => 'students', 'email_col' => 's_email', 'id_col' => 's_id'],
    ['table' => 'teachers', 'email_col' => 't_email', 'id_col' => 't_id'],
    ['table' => 'parents',  'email_col' => 'p_email', 'id_col' => 'p_id']
];

foreach ($tables as $tbl) {
    $check = $conn->prepare("
        SELECT 1 
        FROM email_verifications ev
        JOIN {$tbl['table']} u 
          ON u.{$tbl['id_col']} = ev.user_id AND ev.user_type = ?
        WHERE ev.new_email = ?
        AND ev.verified = 1
        AND NOT (ev.user_id = ? AND ev.user_type = ?)
        LIMIT 1
    ");
    $check->bind_param("ssis", $tbl['table'], $new_email, $user_id, $user_type);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows > 0) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'This email is already verified by another account. Kindly enter a new one.'
        ]);
        exit();
    }
    $check->close();
}

// --------------------
// 6️⃣ Check last unverified OTP cooldown
// --------------------
$stmt = $conn->prepare("
    SELECT id, new_email, expires_at, created_at
    FROM email_verifications
    WHERE user_id = ? AND user_type = ? AND verified = 0
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("is", $user_id, $user_type);
$stmt->execute();
$lastOtp = $stmt->get_result()->fetch_assoc();
$stmt->close();

$now = new DateTime();

if ($lastOtp) {
    $expiresAt = new DateTime($lastOtp['expires_at']);
    $createdAt = new DateTime($lastOtp['created_at']);

    // Only enforce cooldown while OTP is still valid
    if ($expiresAt > $now) {
        $cooldown = clone $createdAt;
        $cooldown->modify('+2 minutes');

        if ($cooldown > $now) {
            echo json_encode([
                'status' => 'warning',
                'message' => 'Please wait 2 minutes before requesting another OTP. Request again by ' . $cooldown->format("Y-m-d H:i:s"),
                'next_request_time' => $cooldown->format("Y-m-d H:i:s")
            ]);
            exit();
        }
    }
}

// --------------------
// 7️⃣ Generate and save new OTP (10 min expiration)
// --------------------
$otp = random_int(100000, 999999);
$expires_at = (new DateTime())->modify('+10 minutes')->format("Y-m-d H:i:s");
$created_at = (new DateTime())->format("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO email_verifications (user_id, user_type, new_email, otp_code, expires_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssss", $user_id, $user_type, $new_email, $otp, $expires_at, $created_at);
if (!$stmt->execute()) {
    echo json_encode(['status' => 'danger', 'message' => 'Database error while saving OTP.']);
    exit();
}
$stmt->close();

// --------------------
// 8️⃣ Send OTP email
// --------------------
$subject = "Email Verification OTP";
$body = "
    <h3>Your Attendify OTP Code</h3>
    <p>Use this code to verify your email:</p>
    <h2 style='color:#007bff;'>$otp</h2>
    <p>This code will expire in <b>10 minutes</b>.</p>
    <br>
    <p style='font-size:12px;color:#555;'>If you didn’t request this, you can safely ignore this message.</p>
";

$mailResult = sendMail($new_email, $subject, $body);

if ($mailResult['success']) {
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP sent successfully. Please verify your email.',
        'otp_valid_until' => $expires_at
    ]);
} else {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to send OTP email.',
        'error' => $mailResult['error'] ?? '',
        'exception' => $mailResult['exception'] ?? ''
    ]);
}
?>
