<?php
session_start();
require_once('../includes/db.php');
require_once('../includes/mailer.php');
header('Content-Type: application/json');

// 1️⃣ Detect user
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'parent' && isset($_SESSION['parent_id'])) {
        $user_id = $_SESSION['parent_id'];
        $user_type = 'parent';
    } elseif (in_array($_SESSION['user_type'], ['student','teacher']) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
    } else {
        echo json_encode(['status'=>'danger','message'=>'Unauthorized']);
        exit();
    }
} else {
    echo json_encode(['status'=>'danger','message'=>'Unauthorized']);
    exit();
}

// 2️⃣ Validate new email
$new_email = trim($_POST['new_email'] ?? '');
if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status'=>'danger','message'=>'Please provide a valid email address.']);
    exit();
}

// 3️⃣ Check if email exists in email_verifications as verified=1 (ignore main tables)
$stmt = $conn->prepare("
    SELECT 1 
    FROM email_verifications 
    WHERE new_email=? AND verified=1 AND NOT (user_id=? AND user_type=?)
");
$stmt->bind_param("sis", $new_email, $user_id, $user_type);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode([
        'status'=>'warning',
        'message'=>'This email is already used. Kindly enter a new one.'
    ]);
    exit();
}
$stmt->close();

// 4️⃣ Check existing unverified OTP for this user/email
$stmt = $conn->prepare("
    SELECT otp_code, expires_at, created_at 
    FROM email_verifications 
    WHERE user_id=? AND user_type=? AND new_email=? AND verified=0
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("iss", $user_id, $user_type, $new_email);
$stmt->execute();
$lastOtp = $stmt->get_result()->fetch_assoc();
$stmt->close();

$now = new DateTime();
if ($lastOtp) {
    $expiresAt = new DateTime($lastOtp['expires_at']);
    $createdAt = new DateTime($lastOtp['created_at']);

    if ($expiresAt > $now) {
        echo json_encode([
            'status'=>'warning',
            'message'=>'An OTP has already been sent. Please check your email.',
            'otp_valid_until'=>$lastOtp['expires_at']
        ]);
        exit();
    }

    // Optional cooldown: 2 minutes before requesting new OTP
    $cooldown = clone $createdAt;
    $cooldown->modify('+2 minutes');
    if ($cooldown > $now) {
        echo json_encode([
            'status'=>'warning',
            'message'=>'Please wait 2 minutes before requesting another OTP.',
            'next_request_time'=>$cooldown->format("Y-m-d H:i:s")
        ]);
        exit();
    }
}

// 5️⃣ Generate new OTP
$otp = random_int(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));
$created_at = date("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO email_verifications 
    (user_id, user_type, new_email, otp_code, expires_at, created_at) 
    VALUES (?,?,?,?,?,?)
");
$stmt->bind_param("isssss", $user_id, $user_type, $new_email, $otp, $expires_at, $created_at);
if (!$stmt->execute()) {
    echo json_encode(['status'=>'danger','message'=>'Database error while saving OTP.']);
    exit();
}
$stmt->close();

// 6️⃣ Send OTP email
$subject = "Email Verification OTP";
$body = "Your OTP code is: <b>$otp</b>. It will expire in 10 minutes.";

if (sendMail($new_email, $subject, $body)) {
    echo json_encode([
        'status'=>'success',
        'message'=>'OTP sent successfully. Please verify your email.',
        'otp_valid_until'=>$expires_at
    ]);
} else {
    echo json_encode(['status'=>'danger','message'=>'Failed to send OTP email.']);
}
?>
