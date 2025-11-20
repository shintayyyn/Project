<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');
header('Content-Type: application/json');

// ✅ 1. Ensure parent is logged in
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}
$parent_id = $_SESSION['parent_id'];

// ✅ 2. Collect and sanitize POST data
$s_id = $_POST['child_id'] ?? null;
$ss_id = $_POST['schedule_id'] ?? null;
$absent_date = $_POST['absent_date'] ?? null;
$reason = trim($_POST['reason'] ?? '');

if (!$s_id || !$ss_id || !$absent_date || !$reason || $ss_id === 'undefined') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit();
}

// ✅ 3. Validate schedule existence
$stmt = $conn->prepare("SELECT 1 FROM sections_schedules WHERE ss_id = ?");
$stmt->bind_param("i", $ss_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid schedule selection.']);
    exit();
}
$stmt->close();

// ✅ 4. Check if the child belongs to this parent
$stmt = $conn->prepare("SELECT 1 FROM parent_student WHERE p_id = ? AND s_id = ?");
$stmt->bind_param("ii", $parent_id, $s_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student selection.']);
    $stmt->close();
    exit();
}
$stmt->close();

// ✅ 5. Validate date (1 day before) unless emergency
date_default_timezone_set('Asia/Manila');
$today = new DateTime('today');
$absentDate = DateTime::createFromFormat('Y-m-d', $absent_date);
if (!$absentDate) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
    exit();
}
$absentDate->setTime(0, 0, 0);

// Check if the reason contains "emergency"
$isEmergency = stripos($reason, 'emergency') !== false;

// Only enforce 1-day-before rule if NOT emergency
if (!$isEmergency && $absentDate <= $today) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You can only file an absence at least one day before the selected date.'
    ]);
    exit();
}

// ✅ 6. Check for duplicate absence request (once per week) — skip if emergency
if (!$isEmergency) {
   // ✅ 6. Check for duplicate absence request
$stmt = $conn->prepare("
    SELECT absent_date 
    FROM absent_requests 
    WHERE s_id = ? AND ss_id = ?
");
$stmt->bind_param("ii", $s_id, $ss_id);
$stmt->execute();
$result = $stmt->get_result();

$weekBlocked = false;
$dateExists = false;
while ($row = $result->fetch_assoc()) {
    $existingDate = new DateTime($row['absent_date']);

    // Check if same date
    if ($existingDate->format('Y-m-d') === $absentDate->format('Y-m-d')) {
        $dateExists = true;
        break;
    }

    // Only block once-per-week if not emergency
    if (!$isEmergency) {
        $existingWeek = (int)$existingDate->format("W");
        $existingYear = (int)$existingDate->format("o");
        $currentWeek = (int)$absentDate->format("W");
        $currentYear = (int)$absentDate->format("o");

        if ($existingWeek === $currentWeek && $existingYear === $currentYear) {
            $weekBlocked = true;
        }
    }
}
$stmt->close();

// Prevent duplicate date always
if ($dateExists) {
    echo json_encode([
        'status' => 'error',
        'message' => 'An absence request for this date already exists.'
    ]);
    exit();
}

// Prevent weekly duplicate if not emergency
if ($weekBlocked) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You can only file an absence for this subject once per week.'
    ]);
    exit();
}

}

// ✅ 7. Handle attachment upload
$attachment_path = null;
if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/absent_attachments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['attachment']['name']));
    $file_name = time() . '_' . $safe_name;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
        $attachment_path = 'uploads/absent_attachments/' . $file_name;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload attachment.']);
        exit();
    }
}

// ✅ Require attachment if emergency
if ($isEmergency && !$attachment_path) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Emergency absences require an attachment.'
    ]);
    exit();
}


// ✅ 8. Get active term
$activeTerm = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$term_id = $activeTerm['term_id'] ?? null;

if (!$term_id) {
    echo json_encode(['status' => 'error', 'message' => 'No active term found.']);
    exit();
}

// ✅ 9. Insert absence request with term_id
$stmt = $conn->prepare("
    INSERT INTO absent_requests (p_id, s_id, ss_id, absent_date, reason, attachment, term_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iiisssi", $parent_id, $s_id, $ss_id, $absent_date, $reason, $attachment_path, $term_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Absent request submitted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>
