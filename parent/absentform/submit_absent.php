<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

// Return JSON for AJAX
header('Content-Type: application/json');

// 1️⃣ Check if parent is logged in
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$parent_id = $_SESSION['parent_id'];

// 2️⃣ Collect POST data safely
$s_id = $_POST['child_id'] ?? null;
$ss_id = $_POST['schedule_id'] ?? null;
$absent_date = $_POST['absent_date'] ?? null;
$reason = trim($_POST['reason'] ?? '');

if (!$s_id || !$ss_id || !$absent_date || !$reason) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

// 3️⃣ Validate that the student belongs to this parent
$stmt = $conn->prepare("SELECT 1 FROM parent_student WHERE p_id = ? AND s_id = ?");
$stmt->bind_param("ii", $parent_id, $s_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid student selection']);
    exit();
}

// 4️⃣ Validate that the schedule exists
$stmt = $conn->prepare("SELECT 1 FROM sections_schedules WHERE ss_id = ?");
$stmt->bind_param("i", $ss_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid schedule selection']);
    exit();
}

// 5️⃣ Handle optional file upload
$attachment_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/absent_attachments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_name = time() . '_' . basename($_FILES['attachment']['name']);
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
        $attachment_path = $target_file;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload attachment']);
        exit();
    }
}

// 6️⃣ Insert into absent_requests
$stmt = $conn->prepare("
    INSERT INTO absent_requests (p_id, s_id, ss_id, absent_date, reason, attachment)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iiisss", $parent_id, $s_id, $ss_id, $absent_date, $reason, $attachment_path);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Absent request submitted successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}
