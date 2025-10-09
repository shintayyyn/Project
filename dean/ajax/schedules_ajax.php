<?php
require_once __DIR__ . '/../../includes/db.php';

// Ensure no errors are output in the response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_subject_teachers':
            $subject_code = $_POST['subject_code'] ?? '';
            
            if (empty($subject_code)) {
                echo json_encode(['error' => 'Subject code is required']);
                exit;
            }

            try {
                // Get teachers assigned to the subject
                $query = "SELECT DISTINCT 
                            t.t_id,
                            CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') as teacher_name
                         FROM subjects s
                         LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id
                         LEFT JOIN teachers t ON st.t_id = t.t_id
                         WHERE s.subject_code = ?
                         ORDER BY t.t_lname, t.t_fname";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('s', $subject_code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $teachers = [];
                while ($row = $result->fetch_assoc()) {
                    if ($row['t_id'] !== null) {
                        $teachers[] = $row;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'teachers' => $teachers,
                    'hasTeachers' => count($teachers) > 0
                ]);
            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to fetch teachers: ' . $e->getMessage()]);
            }
            break;

        case 'add_schedule':
            try {
                // Validate required fields
                $required_fields = ['section_id', 'subject_code', 'day_of_week', 'start_time', 'end_time', 'room_id'];
                $missing_fields = [];
                
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $missing_fields[] = $field;
                    }
                }
                
                if (!empty($missing_fields)) {
                    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
                    exit;
                }

                // Extract and sanitize values from POST
                $section_id = intval($_POST['section_id']);
                $subject_code = $_POST['subject_code'];
                $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
                $day_of_week = $_POST['day_of_week'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room_id = intval($_POST['room_id']);

                // Check for schedule conflicts
                $conflict_query = "SELECT COUNT(*) as conflict_count 
                                 FROM sections_schedules 
                                 WHERE section_id = ? 
                                 AND day_of_week = ?
                                 AND subject_code != ?  
                                 AND ((start_time BETWEEN ? AND ? OR end_time BETWEEN ? AND ?)
                                 OR (? BETWEEN start_time AND end_time))";
                
                $stmt = $conn->prepare($conflict_query);
                $stmt->bind_param('isssssss', 
                    $section_id, 
                    $day_of_week,
                    $subject_code,
                    $start_time, 
                    $end_time, 
                    $start_time, 
                    $end_time,
                    $start_time
                );
                $stmt->execute();
                $conflict_result = $stmt->get_result()->fetch_assoc();

                if ($conflict_result['conflict_count'] > 0) {
                    echo json_encode(['error' => 'Schedule conflict detected. Please choose a different time.']);
                    exit;
                }

                // Get teacher details if teacher_id is provided
                $teacher_name = '';
                if ($teacher_id) {
                    $teacher_query = "SELECT CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname, 1), ''), '.') as teacher_name 
                                    FROM teachers WHERE t_id = ?";
                    $stmt = $conn->prepare($teacher_query);
                    $stmt->bind_param('i', $teacher_id);
                    $stmt->execute();
                    $teacher_result = $stmt->get_result();
                    
                    if ($teacher_result && $row = $teacher_result->fetch_assoc()) {
                        $teacher_name = $row['teacher_name'];
                    } else {
                        // If teacher not found, clear the teacher_id
                        $teacher_id = null;
                        $teacher_name = '';
                    }
                }

                // Insert the schedule
                $insert_query = "INSERT INTO sections_schedules 
                               (section_id, subject_code, teacher_id, teacher_name, day_of_week, start_time, end_time, room_id) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param('isissssi', 
                    $section_id,
                    $subject_code,
                    $teacher_id,
                    $teacher_name,
                    $day_of_week,
                    $start_time,
                    $end_time,
                    $room_id
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);
                } else {
                    throw new Exception('Failed to add schedule');
                }
            } catch (Exception $e) {
                error_log("Error adding schedule: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to add schedule: ' . $e->getMessage()]);
            }
            break;

        case 'update_schedule':
            try {
                // Validate required fields
                $required_fields = ['schedule_id', 'section_id', 'subject_code', 'day_of_week', 'start_time', 'end_time', 'room_id'];
                $missing_fields = [];
                
                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                        $missing_fields[] = $field;
                    }
                }
                
                if (!empty($missing_fields)) {
                    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
                    exit;
                }

                // Ensure time format is correct (HH:MM:SS)
                $start_time = date('H:i:s', strtotime($_POST['start_time']));
                $end_time = date('H:i:s', strtotime($_POST['end_time']));
                
                if (!$start_time || !$end_time) {
                    echo json_encode(['error' => 'Invalid time format']);
                    exit;
                }
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get teacher details if teacher_id is provided
                    $teacher_name = '';
                    if (!empty($_POST['teacher_id'])) {
                        $teacher_query = "SELECT CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname, 1), ''), '.') as teacher_name 
                                        FROM teachers WHERE t_id = ?";
                        $stmt = $conn->prepare($teacher_query);
                        $stmt->bind_param('i', $_POST['teacher_id']);
                        $stmt->execute();
                        $teacher_result = $stmt->get_result();
                        
                        if ($teacher_result && $row = $teacher_result->fetch_assoc()) {
                            $teacher_name = $row['teacher_name'];
                        }
                    }

                    // Update the schedule
                    $query = "UPDATE sections_schedules 
                             SET section_id = ?,
                                 subject_code = ?,
                                 teacher_id = ?,
                                 teacher_name = ?,
                                 day_of_week = ?,
                                 start_time = ?,
                                 end_time = ?,
                                 room_id = ?
                             WHERE ss_id = ?";
                    
                    $stmt = $conn->prepare($query);
                    
                    // Handle null teacher_id
                    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
                    
                    $stmt->bind_param('isissssis',
                        $_POST['section_id'],
                        $_POST['subject_code'],
                        $teacher_id,
                        $teacher_name,
                        $_POST['day_of_week'],
                        $start_time,
                        $end_time,
                        $_POST['room_id'],
                        $_POST['schedule_id']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update schedule: " . $stmt->error);
                    }

                    // Commit transaction
                    $conn->commit();
                    
                    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
                    exit;
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    throw $e;
                }
            } catch (Exception $e) {
                error_log("Error updating schedule: " . $e->getMessage());
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            break;

        case 'delete_schedule':
            try {
                if (empty($_POST['schedule_id'])) {
                    echo json_encode(['error' => 'Schedule ID is required']);
                    exit;
                }

                $schedule_id = intval($_POST['schedule_id']);
                
                $delete_query = "DELETE FROM sections_schedules WHERE ss_id = ?";
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param('i', $schedule_id);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
                } else {
                    throw new Exception('Failed to delete schedule');
                }
            } catch (Exception $e) {
                error_log("Error deleting schedule: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to delete schedule: ' . $e->getMessage()]);
            }
            break;

        case 'check_teacher_updates':
            try {
                $subject_codes = json_decode($_POST['subject_codes'] ?? '[]', true);
                
                if (empty($subject_codes)) {
                    echo json_encode(['success' => true, 'updates' => []]);
                    exit;
                }

                // Prepare placeholders for the IN clause
                $placeholders = str_repeat('?,', count($subject_codes) - 1) . '?';
                
                $query = "SELECT DISTINCT 
                            s.subject_code,
                            CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') as teacher_name
                         FROM subjects s
                         LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id
                         LEFT JOIN teachers t ON st.t_id = t.t_id
                         WHERE s.subject_code IN ($placeholders)
                         ORDER BY s.subject_code";
                
                $stmt = $conn->prepare($query);
                
                // Bind all subject codes as strings
                $types = str_repeat('s', count($subject_codes));
                $stmt->bind_param($types, ...$subject_codes);
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                $updates = [];
                while ($row = $result->fetch_assoc()) {
                    $updates[$row['subject_code']] = $row['teacher_name'];
                }
                
                echo json_encode([
                    'success' => true,
                    'updates' => $updates
                ]);
            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to check teacher updates: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
