<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../includes/db.php';

// ✅ Teacher-only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Only teachers can update schedule.']);
    exit();
}

$user_type = $_SESSION['user_type'];
$user_id   = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// ✅ Fetch active term
$term_sql = "SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1";
$term_result = $conn->query($term_sql);
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $current_term_id = $term_row['term_id'];
} else {
    echo json_encode(['success' => false, 'error' => 'No active term found.']);
    exit();
}

// ✅ Get POST data
$block_key = $_POST['block_key'] ?? '';
$status    = $_POST['status'] ?? '';
$remarks   = trim($_POST['remarks'] ?? '');

// ✅ Validate block key
$parts = explode('|', $block_key);
if (count($parts) !== 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid schedule block.']);
    exit();
}
[$section_id, $subject_code, $day, $time_start, $time_end] = $parts;

// ✅ Default values if remarks empty
if ($status === '') {
    $status = 'Available';
}
if ($status === 'Available' && $remarks === '') {
    $remarks = 'Ready for class';
}
if (($status === 'Asynchronous' || $status === 'Not Available') && $remarks === '') {
    $remarks = 'Unavailable – reason not specified';
}

// ✅ Find schedule row
$schedule_sql = "
    SELECT ss_id 
    FROM sections_schedules 
    WHERE section_id = ? 
      AND subject_code = ? 
      AND day_of_week = ? 
      AND TIME(start_time) = TIME(?) 
      AND TIME(end_time) = TIME(?) 
      AND teacher_id = ? 
      AND term_id = ? 
      AND is_active = 1
    LIMIT 1
";
$schedule_stmt = $conn->prepare($schedule_sql);
$schedule_stmt->bind_param("issssii", $section_id, $subject_code, $day, $time_start, $time_end, $user_id, $current_term_id);
$schedule_stmt->execute();
$schedule_result = $schedule_stmt->get_result();
$schedule_row = $schedule_result->fetch_assoc();

if (!$schedule_row) {
    echo json_encode(['success' => false, 'error' => 'Schedule not found for this teacher in the current term.']);
    exit();
}

$ss_id = $schedule_row['ss_id'];

// ✅ Update status + description
$update_stmt = $conn->prepare("UPDATE sections_schedules SET status = ?, description = ? WHERE ss_id = ? AND is_active =1");
$update_stmt->bind_param("ssi", $status, $remarks, $ss_id);
$update_stmt->execute();

$update_message = ($update_stmt->affected_rows === 0) 
    ? "No changes made (same values as before)." 
    : "Schedule updated successfully.";

// ✅ Handle optional attachment
$uploadDir = __DIR__ . '/../../uploads/remarks/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file_name = null;
$file_type = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $originalName = $_FILES['attachment']['name'];
    $tmpName      = $_FILES['attachment']['tmp_name'];
    $extension    = pathinfo($originalName, PATHINFO_EXTENSION);

    $safeFileName = uniqid('file_', true) . '.' . $extension;
    $destination  = $uploadDir . $safeFileName;

    if (move_uploaded_file($tmpName, $destination)) {
        $file_name = $safeFileName;
        $file_type = $_FILES['attachment']['type'];

        // Check if file already exists for this schedule
        $check_stmt = $conn->prepare("SELECT id FROM attachment_files WHERE ss_id = ? AND uploaded_by_id = ? AND uploaded_by_type = ?");
        $check_stmt->bind_param("iis", $ss_id, $user_id, $user_type);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $id = $row['id'];
            $update_file = $conn->prepare("
                UPDATE attachment_files 
                SET file_name = ?, file_type = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update_file->bind_param("ssi", $file_name, $file_type, $id);
            $update_file->execute();
        } else {
            $insert_file = $conn->prepare("
                INSERT INTO attachment_files (ss_id, uploaded_by_id, uploaded_by_type, file_name, file_type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert_file->bind_param("iisss", $ss_id, $user_id, $user_type, $file_name, $file_type);
            $insert_file->execute();
        }
    }
}

// ✅ Final response
echo json_encode([
    'success'     => true,
    'message'     => $update_message,
    'file_name'   => $file_name,
    'file_type'   => $file_type,
    'ss_id'       => $ss_id,
    'status'      => $status,
    'description' => $remarks
]);
exit();
