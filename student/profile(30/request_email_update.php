<?php
session_start();
require_once('../../includes/db.php');
require_once('../../includes/mailer.php'); // PHPMailer config
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
$new_email = trim($_POST['new_email']);

// 1️⃣ Check if email is already used by another user
$stmt = $conn->prepare("SELECT s_id FROM students WHERE s_email = ? AND s_id != ?");
$stmt->bind_param("si", $new_email, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode([
        "status" => "exists",
        "message" => "This email is already used by another user."
    ]);
    exit;
}
$stmt->close();

// 2️⃣ Check if a recent OTP already exists for this email
$stmt = $conn->prepare("SELECT otp_code, expires_at, created_at 
                        FROM email_verifications 
                        WHERE s_id = ? AND new_email = ? 
                        ORDER BY id DESC 
                        LIMIT 1");
$stmt->bind_param("is", $student_id, $new_email);
$stmt->execute();
$result = $stmt->get_result();
$lastOtp = $result->fetch_assoc();
$stmt->close();

$now = new DateTime();

if ($lastOtp) {
    $expiresAt = new DateTime($lastOtp['expires_at']);
    $createdAt = new DateTime($lastOtp['created_at']);

    // If OTP is still valid
    if ($expiresAt > $now) {
        echo json_encode([
            'status' => '',
            'message' => 'An OTP was already sent and is still valid. Please check your email.',
            'otp_valid_until' => $lastOtp['expires_at']
        ]);
        exit();
    }

    // If cooldown not yet passed (2 minutes)
    $cooldown = clone $createdAt;
    $cooldown->modify('+2 minutes');
    if ($cooldown > $now) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Please wait at least 2 minutes before requesting a new OTP.'
        ]);
        exit();
    }
}

// 3️⃣ Generate new OTP
$otp = rand(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));
$created_at = date("Y-m-d H:i:s");

$stmt = $conn->prepare("INSERT INTO email_verifications (s_id, new_email, otp_code, expires_at, created_at) 
                        VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $student_id, $new_email, $otp, $expires_at, $created_at);
$stmt->execute();
$stmt->close();

// 4️⃣ Send OTP email using PHPMailer
$subject = "Email Verification OTP";
$body = "Your OTP code is: <b>$otp</b>. It will expire in 10 minutes.";

if (sendMail($new_email, $subject, $body)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP sent to your new email. Please verify.',
        'otp_valid_until' => $expires_at
    ]);
} else {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to send OTP email.'
    ]);
}
?>
