<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

try {
    // --------------------- Validate input ---------------------
    if (!isset($_POST['schedule_id'], $_POST['section_id'], $_POST['subject_code'], 
               $_POST['subject_description'], $_POST['teacher_id'], $_POST['day_of_week'], 
               $_POST['start_time'], $_POST['end_time'], $_POST['room'])) {
        throw new Exception('Missing required fields');
    }

    // --------------------- Sanitize inputs ---------------------
    $schedule_id = intval($_POST['schedule_id']);
    $section_id = intval($_POST['section_id']);
    $subject_code = $conn->real_escape_string($_POST['subject_code']);
    $subject_description = $conn->real_escape_string($_POST['subject_description']);
    $teacher_id = intval($_POST['teacher_id']);
    $day_of_week = $conn->real_escape_string($_POST['day_of_week']);
    $start_time = $conn->real_escape_string($_POST['start_time']);
    $end_time = $conn->real_escape_string($_POST['end_time']);
    $room = $conn->real_escape_string($_POST['room']);

    // --------------------- Validate time ---------------------
    if (!strtotime($start_time) || !strtotime($end_time)) {
        throw new Exception('Invalid time format');
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        throw new Exception('End time must be after start time');
    }

    // --------------------- Get active term ---------------------
    $termResult = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
    if ($termRow = $termResult->fetch_assoc()) {
        $activeTermId = $termRow['term_id'];
    } else {
        throw new Exception('No active term found');
    }

    // --------------------- Validate room ---------------------
    $room_query = "SELECT * FROM rooms WHERE room_code = ? AND status = 'Active'";
    $stmt = $conn->prepare($room_query);
    $stmt->bind_param("s", $room);
    $stmt->execute();
    $room_result = $stmt->get_result();
    if (!$room_data = $room_result->fetch_assoc()) {
        throw new Exception('Selected room is not available');
    }

    // --------------------- Validate section size vs room ---------------------
    $section_query = "SELECT COUNT(*) as student_count FROM students_sections WHERE section_id = ?";
    $stmt = $conn->prepare($section_query);
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $section_result = $stmt->get_result();
    $section_data = $section_result->fetch_assoc();
    
    if ($section_data['student_count'] > $room_data['capacity']) {
        throw new Exception("Room capacity ({$room_data['capacity']}) is less than section size ({$section_data['student_count']})");
    }

    // --------------------- Check schedule conflicts (term-aware) ---------------------
    $conflict_check_query = "SELECT * FROM sections_schedules 
                           WHERE section_id = ? 
                           AND day_of_week = ?
                           AND term_id = ?
                           AND ss_id != ?
                           AND ((start_time BETWEEN ? AND ?) 
                                OR (end_time BETWEEN ? AND ?)
                                OR (start_time <= ? AND end_time >= ?))";
    $stmt = $conn->prepare($conflict_check_query);
    $stmt->bind_param("isisssssss", $section_id, $day_of_week, $activeTermId, $schedule_id,
                      $start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Schedule conflict detected for this section');
    }

    // --------------------- Teacher schedule conflicts (term-aware) ---------------------
    $teacher_conflict_check = "SELECT * FROM sections_schedules 
                             WHERE teacher_id = ? 
                             AND day_of_week = ?
                             AND term_id = ?
                             AND ss_id != ?
                             AND ((start_time BETWEEN ? AND ?) 
                                  OR (end_time BETWEEN ? AND ?)
                                  OR (start_time <= ? AND end_time >= ?))";
    $stmt = $conn->prepare($teacher_conflict_check);
    $stmt->bind_param("iissssssss", $teacher_id, $day_of_week, $activeTermId, $schedule_id,
                      $start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Teacher schedule conflict detected');
    }

    // --------------------- Room schedule conflicts (term-aware) ---------------------
    $room_conflict_check = "SELECT * FROM sections_schedules 
                          WHERE room = ? 
                          AND day_of_week = ?
                          AND term_id = ?
                          AND ss_id != ?
                          AND ((start_time BETWEEN ? AND ?) 
                               OR (end_time BETWEEN ? AND ?)
                               OR (start_time <= ? AND end_time >= ?))";
    $stmt = $conn->prepare($room_conflict_check);
    $stmt->bind_param("sissssssss", $room, $day_of_week, $activeTermId, $schedule_id,
                      $start_time, $end_time, $start_time, $end_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('Room schedule conflict detected');
    }

    // --------------------- Update schedule ---------------------
    $update_query = "UPDATE sections_schedules 
                    SET section_id = ?, 
                        subject_code = ?, 
                        subject_description = ?,
                        teacher_id = ?, 
                        day_of_week = ?, 
                        start_time = ?,
                        end_time = ?, 
                        room = ?,
                        term_id = ?
                    WHERE ss_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ississssii", $section_id, $subject_code, $subject_description,
                      $teacher_id, $day_of_week, $start_time, $end_time, $room, $activeTermId, $schedule_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update schedule');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
