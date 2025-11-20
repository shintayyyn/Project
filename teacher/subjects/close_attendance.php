<?php
require_once __DIR__ . '/../../includes/db.php';
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

// ✅ Get active term_id
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
// Check if schedule exists for this subject/section today (with term_id)
$schedStmt = $conn->prepare("
    SELECT teacher_id, start_time, end_time
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

$schedule    = $schedResult->fetch_assoc();
$teacher_id  = $schedule['teacher_id'];

// ------------------------
// ✅ Trap: Check if already dismissed by teacher
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
    // ------------------------
    // 1️⃣ Update students who already have time_in
    if ($only_time_in) {
        $stmt = $conn->prepare("
            UPDATE attendance
            SET time_out = ?
            WHERE subject_code = ? AND section_code = ? AND DATE(time_in) = ? AND time_out IS NULL AND term_id = ?
        ");
        $stmt->bind_param("ssssi", $timestamp, $subject_code, $section_code, $currentDate, $term_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // ------------------------
        // 2️⃣ Insert Absent only if NOT dismissed
        if ($dismissed == 0) {
            $studentsStmt = $conn->prepare("
                SELECT s.s_id
                FROM students s
                JOIN students_sections ss ON s.s_id = ss.s_id
                WHERE ss.section_id = ? AND ss.term_id = ?
            ");
            $studentsStmt->bind_param("ii", $section_id, $term_id);
            $studentsStmt->execute();
            $studentsRes = $studentsStmt->get_result();

            while ($s = $studentsRes->fetch_assoc()) {
                // Check if student already has a record today
                $checkStmt = $conn->prepare("
                    SELECT 1 FROM attendance
                    WHERE s_id = ? AND subject_code = ? AND section_code = ? AND DATE(time_in) = ? AND term_id = ?
                ");
                $checkStmt->bind_param("isssi", $s['s_id'], $subject_code, $section_code, $currentDate, $term_id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows === 0) {
                    $insertStmt = $conn->prepare("
                        INSERT INTO attendance (s_id, subject_code, section_code, term_id, time_in, time_out, status)
                        VALUES (?, ?, ?, ?, NULL, NULL, 'Absent')
                    ");
                    $insertStmt->bind_param("issi", $s['s_id'], $subject_code, $section_code, $term_id);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
                $checkStmt->close();
            }
            $studentsStmt->close();
        } else {
            $conn->commit();
            echo json_encode(['success' => false,'dismissed' => 1, 'message' => 'Class already dismissed. No new records inserted.']);
            exit;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'dismissed' => 1, 'message' => 'Attendance closed successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
