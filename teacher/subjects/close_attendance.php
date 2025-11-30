<?php
require_once __DIR__ . '/../../includes/db.php';
require_once '../../includes/mailer.php';
header('Content-Type: application/json');
session_start();

$subject_code = $_POST['subject_code'] ?? null;
$section_id   = $_POST['section_id'] ?? null;
$timestamp    = $_POST['timestamp'] ?? null;
$only_time_in = isset($_POST['only_time_in']) ? true : false;

if (!$subject_code || !$section_id || !$timestamp) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$currentDay  = strtolower(date('l'));
$currentDate = date('Y-m-d');

// ------------------------
// Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if (!$term_result || $term_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No active term found']);
    exit;
}
$term_id = $term_result->fetch_assoc()['term_id'];

// ------------------------
// Get section_code
$secStmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
$secStmt->bind_param("i", $section_id);
$secStmt->execute();
$secRes = $secStmt->get_result();
if ($secRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Section not found']);
    exit;
}
$section_code = $secRes->fetch_assoc()['section_code'];
$secStmt->close();

// ------------------------
// Get room number for this subject/section
$roomStmt = $conn->prepare("
    SELECT r.room_number
    FROM sections_schedules ss
    LEFT JOIN rooms r ON ss.room_id = r.room_id
    WHERE ss.subject_code = ? AND ss.section_id = ? AND ss.term_id = ?
    LIMIT 1
");
$roomStmt->bind_param("sii", $subject_code, $section_id, $term_id);
$roomStmt->execute();
$roomRes = $roomStmt->get_result();
$roomRow = $roomRes->fetch_assoc();
$room_number = $roomRow['room_number'] ?? '';
$roomStmt->close();

// ------------------------
// Check if schedule exists for this subject/section today
$schedStmt = $conn->prepare("
    SELECT teacher_id
    FROM sections_schedules
    WHERE subject_code = ? 
      AND section_id = ? 
      AND LOWER(day_of_week) = ?
      AND term_id = ?
      AND is_active = 1
    LIMIT 1
");
$schedStmt->bind_param("sisi", $subject_code, $section_id, $currentDay, $term_id);
$schedStmt->execute();
$schedResult = $schedStmt->get_result();
$schedStmt->close();

if ($schedResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No class schedule today. Attendance not allowed.']);
    exit;
}

$schedule   = $schedResult->fetch_assoc();
$teacher_id = $schedule['teacher_id'];

// ------------------------
// Check if already dismissed by teacher
$checkDismiss = $conn->prepare("
    SELECT dismissed 
    FROM teacher_attendance
    WHERE t_id = ? AND subject_code = ? AND section_code = ? AND attendance_date = CURDATE() AND term_id = ?
");
$checkDismiss->bind_param("issi", $teacher_id, $subject_code, $section_code, $term_id);
$checkDismiss->execute();
$resDismiss = $checkDismiss->get_result();

$dismissed = 0;
if ($resDismiss->num_rows > 0) {
    $rowDismiss = $resDismiss->fetch_assoc();
    $dismissed  = (int)$rowDismiss['dismissed'];
}
$checkDismiss->close();

$conn->begin_transaction();

try {
    if ($only_time_in) {
        // Update time_out for students who already have time_in
        $stmt = $conn->prepare("
            UPDATE attendance
            SET time_out = ?
            WHERE subject_code = ? AND section_code = ? AND DATE(time_in) = ? AND time_out IS NULL AND term_id = ?
        ");
        $stmt->bind_param("ssssi", $timestamp, $subject_code, $section_code, $currentDate, $term_id);
        $stmt->execute();
        $stmt->close();
    } 

    // Fetch students for this section and term
    $studentsStmt = $conn->prepare("
        SELECT s.s_id, s.s_fname, s.s_mname, s.s_lname, s.s_suffix, ps.p_id, p.p_email
        FROM students s
        JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN parent_student ps ON s.s_id = ps.s_id
        LEFT JOIN parents p ON ps.p_id = p.p_id
        WHERE ss.section_id = ? AND ss.term_id = ?
    ");
    $studentsStmt->bind_param("ii", $section_id, $term_id);
    $studentsStmt->execute();
    $studentsRes = $studentsStmt->get_result();

    $emailsToSend = [];
    
    while ($s = $studentsRes->fetch_assoc()) {
        // Only insert/update attendance if class not dismissed
        if ($dismissed == 0) {
            $checkStmt = $conn->prepare("
                SELECT 1 FROM attendance
                WHERE s_id = ? AND subject_code = ? AND section_code = ? AND DATE(time_in) = ? AND term_id = ?
            ");
            $checkStmt->bind_param("isssi", $s['s_id'], $subject_code, $section_code, $currentDate, $term_id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                $insertStmt = $conn->prepare("
                    INSERT INTO attendance (s_id, subject_code, section_code, term_id, time_in, time_out, status, room)
                    VALUES (?, ?, ?, ?, ?, NULL, 'Present', ?)
                ");
                $insertStmt->bind_param("ississ", $s['s_id'], $subject_code, $section_code, $term_id, $timestamp, $room_number);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();
        }

        // Prepare emails to send AFTER responding
        if (!empty($s['p_email'])) {
            $emailsToSend[] = [
                'email' => $s['p_email'],
                'name'  => trim($s['s_fname'].' '.$s['s_mname'].' '.$s['s_lname'].' '.$s['s_suffix']),
                'subject_code' => $subject_code,
                'section_code' => $section_code,
                'time_type'    => 'Time-Out',
                'timestamp'    => $timestamp,
                'room'         => $room_number
            ];
        }
    }
    $studentsStmt->close();
    $conn->commit();

    // ------------------------
    // Send JSON response first
    $message = $dismissed
        ? 'Class already dismissed. Parent emails will be sent.'
        : 'Attendance closed successfully. Parent emails will be sent.';
    echo json_encode(['success' => true, 'dismissed' => $dismissed, 'message' => $message]);

    // ------------------------
    // Then send emails asynchronously
    foreach ($emailsToSend as $emailData) {
        sendEmail(
            $emailData['email'],
            $emailData['name'],
            $emailData['subject_code'],
            $emailData['section_code'],
            $emailData['time_type'],
            $emailData['timestamp'],
            $emailData['room']
        );
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
$conn->close();
?>
