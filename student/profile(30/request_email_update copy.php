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
$new_email = isset($_POST['new_email']) ? trim($_POST['new_email']) : "";

// Validate email
if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'danger', 'message' => 'Please provide a valid email address.']);
    exit();
}

// 1️⃣ Check if the email already exists in students (excluding current user)
$stmt = $conn->prepare("
    SELECT 1 FROM students WHERE s_email = ? AND s_id != ?
");
$stmt->bind_param("si", $new_email, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode([
        'status' => 'warning',
        'message' => "This email is already used by another student."
    ]);
    exit();
}
$stmt->close();

// 2️⃣ Check for existing OTP for this student + email
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

    // Still valid → don't resend
    if ($expiresAt > $now) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'An OTP has already been sent and is still valid. Please check your email.',
            'otp_valid_until' => $lastOtp['expires_at']
        ]);
        exit();
    }

    // Cooldown check (2 minutes since last request)
    $cooldown = clone $createdAt;
    $cooldown->modify('+2 minutes');
    if ($cooldown > $now) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Please wait at least 2 minutes before requesting another OTP.'
        ]);
        exit();
    }
}

// 3️⃣ Generate new OTP
$otp = random_int(100000, 999999); // cryptographically safer than rand()
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));
$created_at = date("Y-m-d H:i:s");

$stmt = $conn->prepare("INSERT INTO email_verifications (s_id, new_email, otp_code, expires_at, created_at) 
                        VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isiss", $student_id, $new_email, $otp, $expires_at, $created_at);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'danger', 'message' => 'Database error while saving OTP.']);
    exit();
}
$stmt->close();

// 4️⃣ Send OTP using PHPMailer
$subject = "Email Verification OTP";
$body = "Your OTP code is: <b>$otp</b>. It will expire in 10 minutes.";

if (sendMail($new_email, $subject, $body)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP sent successfully. Please verify your email.',
        'otp_valid_until' => $expires_at
    ]);
} else {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to send OTP email. Please try again later.'
    ]);
}
