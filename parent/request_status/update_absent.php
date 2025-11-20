<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');
header('Content-Type: application/json');

// Suppress warnings in JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// ---------------------------
// 1️⃣ Ensure parent is logged in
// ---------------------------
if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}
$parent_id = $_SESSION['parent_id'];

// ---------------------------
// 2️⃣ Collect and sanitize POST data
// ---------------------------
$request_id   = $_POST['request_id'] ?? null;
$s_id         = $_POST['child_id'] ?? null;
$ss_id        = $_POST['schedule_id'] ?? null;
$absent_date  = trim($_POST['absent_date'] ?? '');
$reason       = trim($_POST['reason'] ?? '');

if (!$request_id || !$s_id || !$ss_id || !$absent_date || !$reason || $ss_id === 'undefined') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit();
}

// ---------------------------
// 3️⃣ Validate request ownership
// ---------------------------
$stmt = $conn->prepare("SELECT * FROM absent_requests WHERE id=? AND p_id=? AND status='Pending'");
$stmt->bind_param("ii", $request_id, $parent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Request not found or cannot be edited']);
    exit();
}
$current = $result->fetch_assoc();
$stmt->close();

// ---------------------------
// 4️⃣ Validate schedule existence
// ---------------------------
$stmt = $conn->prepare("SELECT * FROM sections_schedules WHERE ss_id = ?");
$stmt->bind_param("i", $ss_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid schedule selection.']);
    exit();
}
$schedule = $res->fetch_assoc();
$stmt->close();

// ---------------------------
// 5️⃣ Validate child-parent relationship
// ---------------------------
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

// ---------------------------
// 6️⃣ Date parsing and emergency check
// ---------------------------
date_default_timezone_set('Asia/Manila');
$absentDate = DateTime::createFromFormat('Y-m-d', $absent_date);
if (!$absentDate) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
    exit();
}
$absentDate->setTime(0, 0, 0);
$today = new DateTime('today');
$isEmergency = stripos($reason, 'emergency') !== false;

// 1-day-before rule (skip for emergency)
if (!$isEmergency && $absentDate <= $today) {
    echo json_encode(['status' => 'error', 'message' => 'You can only file an absence at least one day before the selected date.']);
    exit();
}

// ---------------------------
// 7️⃣ Validate schedule day (skip if emergency)
// ---------------------------
if (!$isEmergency) {
    $stmt = $conn->prepare("SELECT day_of_week FROM sections_schedules WHERE schedule_group_id=?");
    $stmt->bind_param("i", $schedule['schedule_group_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $allowedDays = [];
    while ($row = $res->fetch_assoc()) {
        switch (strtoupper($row['day_of_week'])) {
            case 'SUNDAY': $allowedDays[] = 0; break;
            case 'MONDAY': $allowedDays[] = 1; break;
            case 'TUESDAY': $allowedDays[] = 2; break;
            case 'WEDNESDAY': $allowedDays[] = 3; break;
            case 'THURSDAY': $allowedDays[] = 4; break;
            case 'FRIDAY': $allowedDays[] = 5; break;
            case 'SATURDAY': $allowedDays[] = 6; break;
        }
    }
    $stmt->close();

    if (!in_array((int)$absentDate->format('w'), $allowedDays)) {
        echo json_encode(['status' => 'error', 'message' => 'Selected date does not match the scheduled days.']);
        exit();
    }
}

// ---------------------------
// 8️⃣ Duplicate/weekly check (skip if emergency)
// ---------------------------
if (!$isEmergency) {
    $stmt = $conn->prepare("SELECT absent_date FROM absent_requests WHERE s_id=? AND ss_id=? AND id<>?");
    $stmt->bind_param("iii", $s_id, $ss_id, $request_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $dateExists = false;
    $weekBlocked = false;
    while ($row = $res->fetch_assoc()) {
        $existingDate = new DateTime($row['absent_date']);
        if ($existingDate->format('Y-m-d') === $absentDate->format('Y-m-d')) $dateExists = true;

        if ((int)$existingDate->format("W") === (int)$absentDate->format("W") &&
            (int)$existingDate->format("o") === (int)$absentDate->format("o")) {
            $weekBlocked = true;
        }
    }
    $stmt->close();

    if ($dateExists) {
        echo json_encode(['status' => 'error', 'message' => 'An absence request for this date already exists.']);
        exit();
    }
    if ($weekBlocked) {
        echo json_encode(['status' => 'error', 'message' => 'You can only file an absence for this subject once per week.']);
        exit();
    }
}

// ---------------------------
// 9️⃣ Handle attachment upload
// ---------------------------
$attachment_path = $current['attachment'] ?? null;
$attachment_changed = false;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/absent_attachments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['attachment']['name']));
    $file_name = time() . '_' . $safe_name;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
        $attachment_path = $file_name;
        $attachment_changed = true;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload attachment.']);
        exit();
    }
} elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload attachment.']);
    exit();
}

// Require attachment if emergency
if ($isEmergency && !$attachment_path) {
    echo json_encode(['status' => 'error', 'message' => 'Emergency absences require an attachment.']);
    exit();
}

// ---------------------------
// 🔟 Check if any changes were made
// ---------------------------
if (trim($absent_date) === trim($current['absent_date']) && trim($reason) === trim($current['reason']) && !$attachment_changed) {
    echo json_encode(['status' => 'primary', 'message' => 'No changes were made.']);
    exit();
}

// ---------------------------
// 1️⃣1️⃣ Update record
// ---------------------------
if ($attachment_changed) {
    $stmt = $conn->prepare("UPDATE absent_requests SET absent_date=?, reason=?, attachment=? WHERE id=?");
    $stmt->bind_param("sssi", $absent_date, $reason, $attachment_path, $request_id);
} else {
    $stmt = $conn->prepare("UPDATE absent_requests SET absent_date=?, reason=? WHERE id=?");
    $stmt->bind_param("ssi", $absent_date, $reason, $request_id);
}

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    exit();
}
$stmt->close();

// ---------------------------
// 1️⃣2️⃣ Return updated row data
// ---------------------------
$stmt2 = $conn->prepare("
    SELECT ar.id, ar.absent_date, ar.reason, ar.attachment, ar.status,
           COALESCE(s.s_id,'') AS s_id,
           COALESCE(CONCAT(s.s_fname,' ',s.s_lname),'') AS student_name,
           COALESCE(ss.subject_code,'') AS subject_code,
           COALESCE(sec.section_code,'') AS section_code,
           COALESCE(at.term_id, '') AS term_id,  -- <--- include term_id
           GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN ss2.day_of_week='Sunday' THEN 'SU'
                    WHEN ss2.day_of_week='Monday' THEN 'M'
                    WHEN ss2.day_of_week='Tuesday' THEN 'T'
                    WHEN ss2.day_of_week='Wednesday' THEN 'W'
                    WHEN ss2.day_of_week='Thursday' THEN 'TH'
                    WHEN ss2.day_of_week='Friday' THEN 'F'
                    WHEN ss2.day_of_week='Saturday' THEN 'S'
                END
                ORDER BY FIELD(ss2.day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
                SEPARATOR ''
           ) AS days_abbr,
           DATE_FORMAT(MIN(ss2.start_time), '%h:%i %p') AS start_time,
           DATE_FORMAT(MAX(ss2.end_time), '%h:%i %p') AS end_time
    FROM absent_requests ar
    LEFT JOIN students s ON ar.s_id = s.s_id
    LEFT JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
    LEFT JOIN sections_schedules ss2 
        ON ss.schedule_group_id = ss2.schedule_group_id
        AND ss2.section_id = ss.section_id
        AND ss2.subject_code = ss.subject_code
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    LEFT JOIN academic_terms at ON ss.term_id = at.term_id  -- <--- join academic_terms
    WHERE ar.id = ?
    GROUP BY ar.id
");

$stmt2->bind_param("i", $request_id);
$stmt2->execute();
$updated_row = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// ---------------------------
// 1️⃣3️⃣ Prepare row data for DataTable
// ---------------------------
$rowData = [
    htmlspecialchars($updated_row['student_name']),
    htmlspecialchars($updated_row['section_code']),
    htmlspecialchars($updated_row['subject_code']),
    $updated_row['days_abbr'].' '.substr($updated_row['start_time'],0,5).'-'.substr($updated_row['end_time'],0,5),
    $updated_row['absent_date'],
    htmlspecialchars($updated_row['reason']),
    '<button class="btn btn-sm view-edit-request fw-bold" 
        style="background: var(--primary); color: var(--tertiary);"
        data-id="'.$updated_row['id'].'"
        data-child="'.htmlspecialchars($updated_row['student_name']).'"
        data-child_id="'.$updated_row['s_id'].'"
        data-section="'.htmlspecialchars($updated_row['section_code']).'"
        data-subject="'.htmlspecialchars($updated_row['subject_code']).'"
        data-schedule="'.$updated_row['days_abbr'].' '.substr($updated_row['start_time'],0,5).'-'.substr($updated_row['end_time'],0,5).'"
        data-term_id="'.$updated_row['term_id'].'"
        data-absent_date="'.$updated_row['absent_date'].'"
        data-reason="'.htmlspecialchars($updated_row['reason']).'"
        data-attachment="'.(!empty($updated_row['attachment']) ? basename($updated_row['attachment']) : '').'">
        <i class="bi bi-pencil-square"></i>
    </button>',
    '<span class="badge '.
        ($updated_row['status'] === 'Pending' ? 'bg-warning text-dark' :
        ($updated_row['status'] === 'Approved' ? 'bg-success' : 'bg-danger')).'">'
        .$updated_row['status'].'</span>'
];

echo json_encode([
    'status' => 'success',
    'message' => 'Request updated successfully.',
    'updated_row_id' => $request_id,
    'updated_row_data' => $rowData
]);
