<?php
require_once __DIR__ . '/../../includes/db.php';
session_start();

header('Content-Type: application/json');

$subject_code = $_POST['subject_code'] ?? null;
$section_id   = $_POST['section_id'] ?? null;

if (!$subject_code || !$section_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Current day + time
$currentDay  = strtolower(date('l'));  // e.g. "thursday"
$currentTime = date('H:i:s');          // current server time

// ✅ Fetch schedule for today (case-insensitive match)
$stmt = $conn->prepare("
    SELECT ss_id, schedule_group_id, section_id, subject_id,
           section_code, subject_code, teacher_id, teacher_name,
           day_of_week, start_time, end_time, room_id, term_id,
           status, description, created_at, updated_at
    FROM sections_schedules
    WHERE subject_code = ?
      AND section_id = ?
      AND LOWER(day_of_week) = LOWER(?)
    LIMIT 1
");
$stmt->bind_param("sis", $subject_code, $section_id, $currentDay);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $start = $row['start_time'];
    $end   = $row['end_time'];

    $valid = false;

    // ✅ Handle overnight schedules (end < start)
    if ($end < $start) {
        // Example: 23:00 → 12:00
        if ($currentTime >= $start || $currentTime <= $end) {
            $valid = true;
        }
    } else {
        // Normal case: start → end same day
        if ($currentTime >= $start && $currentTime <= $end) {
            $valid = true;
        }
    }

    if ($valid) {
        echo json_encode([
            'success'     => true,
            'ss_id'       => $row['ss_id'],
            'subject_code'=> $row['subject_code'],
            'section_id'  => $row['section_id'],
            'section_code'=> $row['section_code'],
            'teacher_id'  => $row['teacher_id'],
            'teacher_name'=> $row['teacher_name'],
            'day_of_week' => $row['day_of_week'],
            'start_time'  => $row['start_time'],
            'end_time'    => $row['end_time'],
            'room_id'     => $row['room_id'],
            'term_id'     => $row['term_id'],
            'status'      => $row['status'],
            'description' => $row['description']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active class at this time.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No class schedule today.']);
}

$stmt->close();
$conn->close();
