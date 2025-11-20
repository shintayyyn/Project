<?php
require_once '../includes/db.php';
session_start();
date_default_timezone_set('Asia/Manila');

// ✅ Validate request
if (!isset($_SESSION['user_id']) || !isset($_POST['ss_id']) || !isset($_POST['subject_code'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$student_id   = $_SESSION['user_id'];
$subject_code = $_POST['subject_code'];
$ss_id        = $_POST['ss_id']; // ✅ schedule session id

// ✅ Fetch schedule row using ss_id along with its term_id
$schedule_query = "
    SELECT start_time, end_time, subject_id, term_id
    FROM sections_schedules
    WHERE ss_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("i", $ss_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No schedule found for this session.'
    ]);
    exit;
}

$schedule    = $result->fetch_assoc();
$subject_id  = $schedule['subject_id'];
$term_id     = $schedule['term_id']; // ✅ use term_id from schedule
$start_time  = strtotime(date('Y-m-d') . ' ' . $schedule['start_time']);
$end_time    = strtotime(date('Y-m-d') . ' ' . $schedule['end_time']);
$current_time = time();

// ✅ Allow generation from 15 mins before to 15 mins after start
$allowed_start = $start_time - (15 * 60);
$allowed_end   = $start_time + (15 * 60);

// ✅ Regeneration window after class ends (2–15 mins)
$end_gen_start = $end_time + (2 * 60);
$end_gen_expire = $end_time + (15 * 60);

// 🕒 Time window checks
if ($current_time < $allowed_start) {
    echo json_encode([
        'status' => 'error',
        'message' => '⏰ Too early to generate QR. Come back between ' . date('h:i A', $allowed_start) . '–' . date('h:i A', $allowed_end)
    ]);
    exit;
}

if ($current_time > $allowed_end && $current_time < $end_gen_start) {
    echo json_encode([
        'status' => 'error',
        'message' => '⏳ Too early to regenerate. Regeneration allowed between ' . date('h:i A', $end_gen_start) . '–' . date('h:i A', $end_gen_expire)
    ]);
    exit;
}

if ($current_time >= $allowed_start && $current_time <= $allowed_end) {
    $expires_at = date('Y-m-d H:i:s', $start_time + (15 * 60));
} elseif ($current_time >= $end_gen_start && $current_time <= $end_gen_expire) {
    $expires_at = date('Y-m-d H:i:s', $end_time + (15 * 60));
} else {
    echo json_encode([
        'status' => 'error',
        'message' => '⚠️ QR generation window closed.'
    ]);
    exit;
}

// ✅ Check if QR already exists
$check_query = "
    SELECT * FROM generatedqrcode
    WHERE id = ? AND generated_qrcode LIKE ? AND DATE(created_at) = CURDATE() AND expires_at > NOW()
";
$like_pattern = "%STUDENT:$student_id|SUBJECT_ID:$subject_id|SS_ID:$ss_id|TERM:$term_id%";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("is", $student_id, $like_pattern);
$stmt->execute();
$existing_qr = $stmt->get_result();

if ($existing_qr->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => '⚠️ You already have an active QR. Wait until it expires before generating another.'
    ]);
    exit;
}

// ✅ Generate unique QR text
$token = bin2hex(random_bytes(8));
$qr_text = "STUDENT:$student_id|SUBJECT_ID:$subject_id|SS_ID:$ss_id|SUBJECT:$subject_code|TERM:$term_id|TOKEN:$token";

// ✅ Save QR
$query = "
INSERT INTO generatedqrcode (id, generated_qrcode, created_at, updated_at, expires_at)
VALUES (?, ?, NOW(), NOW(), ?)
ON DUPLICATE KEY UPDATE
generated_qrcode = VALUES(generated_qrcode),
updated_at = NOW(),
expires_at = VALUES(expires_at)
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $student_id, $qr_text, $expires_at);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'qr_code' => $qr_text,
        'expires_at' => $expires_at,
        'message' => '✅ QR generated successfully! Valid until ' . date('h:i A', strtotime($expires_at))
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to generate QR code: ' . $stmt->error
    ]);
}
?>
