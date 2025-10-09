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
    $teachers_query = "
    SELECT 
    t.t_id,
    CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), '')) AS teacher_name,
    t.t_status,
    t.t_department,
    d.degree_id,
    d.degree_code
FROM teachers t
LEFT JOIN degrees d ON t.t_department = d.degree_id
ORDER BY t.t_lname, t.t_fname ";
$result = $conn->query($teachers_query);

    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }

    echo json_encode([
        'success' => true,
        'teachers' => $teachers,
        'hasTeachers' => count($teachers) > 0
    ]);
    exit;

     case 'add_schedule':
    try {
        // Required fields
        $required_fields = ['section_id', 'subject_code', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'room_id'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) $missing_fields[] = $field;
        }
        if (!empty($missing_fields)) {
            echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
            exit;
        }

        // Sanitize POST values
        $section_id = intval($_POST['section_id']);
        $subject_code = $_POST['subject_code'];
        $teacher_id = intval($_POST['teacher_id']);
        $day_of_week = $_POST['day_of_week']; // single day per request
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room_id = intval($_POST['room_id']);
        $description = "Ready for class!";

        // Get subject_id from subjects table
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
        $stmt->bind_param('s', $subject_code);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        if ($subject_result && $row = $subject_result->fetch_assoc()) {
            $subject_id = intval($row['subject_id']);
        } else {
            echo json_encode(['error' => 'Subject not found.']);
            exit;
        }

            
        // Check schedule conflict
        $conflict_query = "SELECT COUNT(*) as conflict_count 
                           FROM sections_schedules 
                           WHERE section_id = ? 
                           AND day_of_week = ?
                           AND ((start_time BETWEEN ? AND ? OR end_time BETWEEN ? AND ?)
                           OR (? BETWEEN start_time AND end_time))";
        $stmt = $conn->prepare($conflict_query);
        $stmt->bind_param('issssss', 
            $section_id, 
            $day_of_week, 
            $start_time, 
            $end_time, 
            $start_time, 
            $end_time, 
            $start_time
        );
        $stmt->execute();
        $conflict_count = $stmt->get_result()->fetch_assoc()['conflict_count'];
        if ($conflict_count > 0) {
            echo json_encode(['error' => 'Schedule conflict detected. Please choose a different time.']);
            exit;
        }

        // Get teacher name
        $stmt = $conn->prepare("SELECT CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''), '.') as teacher_name 
                                FROM teachers WHERE t_id = ? LIMIT 1");
        $stmt->bind_param('i', $teacher_id);
        $stmt->execute();
        $teacher_result = $stmt->get_result();
        if ($teacher_result && $row = $teacher_result->fetch_assoc()) {
            $teacher_name = $row['teacher_name'];
        } else {
            echo json_encode(['error' => 'Selected teacher not found.']);
            exit;
        }

        // Get active term
        $active_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
        $term_id = $active_term['term_id'] ?? null;

        // Insert schedule
        $insert_query = "INSERT INTO sections_schedules
            (section_id, subject_id, subject_code, teacher_id, teacher_name, day_of_week, start_time, end_time, room_id, term_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param(
            'iisisssssis',
            $section_id,
            $subject_id,
            $subject_code,
            $teacher_id,
            $teacher_name,
            $day_of_week,
            $start_time,
            $end_time,
            $room_id,
            $term_id,
            $description
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        // Update teacher status to active
        $stmt = $conn->prepare("UPDATE teachers SET t_status = 'active' WHERE t_id = ?");
        $stmt->bind_param('i', $teacher_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);

    } catch (Exception $e) {
        error_log("Error adding schedule: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to add schedule: ' . $e->getMessage()]);
    }
    break;


case 'update_schedule':
    try {
        $required_fields = ['schedule_id', 'section_id', 'subject_code', 'day_of_week', 'start_time', 'end_time', 'room_id', 'teacher_id'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || (is_array($_POST[$field]) ? count($_POST[$field]) === 0 : trim($_POST[$field]) === '')) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
            exit;
        }

        $schedule_id = intval($_POST['schedule_id']);
        $section_id = intval($_POST['section_id']);
        $subject_code = $_POST['subject_code'];
        $days_of_week = $_POST['day_of_week']; // array of selected days
        $start_time = date('H:i:s', strtotime($_POST['start_time']));
        $end_time = date('H:i:s', strtotime($_POST['end_time']));
        $room_id = intval($_POST['room_id']);
        $teacher_id = intval($_POST['teacher_id']);

        if (!$start_time || !$end_time) {
            echo json_encode(['error' => 'Invalid time format']);
            exit;
        }

        // Fetch subject_id from subjects table
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
        $stmt->bind_param('s', $subject_code);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        if ($subject_result && $row = $subject_result->fetch_assoc()) {
            $subject_id = intval($row['subject_id']);
        } else {
            echo json_encode(['error' => 'Subject not found.']);
            exit;
        }

        // Fetch teacher info and activate if needed
        $teacher_query = "SELECT t_status, CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''), '.') AS teacher_name 
                          FROM teachers WHERE t_id = ?";
        $stmt = $conn->prepare($teacher_query);
        $stmt->bind_param('i', $teacher_id);
        $stmt->execute();
        $teacher_result = $stmt->get_result();
        if (!$teacher_result || !$row = $teacher_result->fetch_assoc()) {
            echo json_encode(['error' => 'Teacher not found.']);
            exit;
        }
        $teacher_name = $row['teacher_name'];

        if ($row['t_status'] === 'inactive') {
            $stmt2 = $conn->prepare("UPDATE teachers SET t_status = 'active' WHERE t_id = ?");
            $stmt2->bind_param('i', $teacher_id);
            $stmt2->execute();
        }

        // Get active term
        $active_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
        $term_id = $active_term['term_id'] ?? null;
        if (!$term_id) {
            echo json_encode(['error' => 'No active academic term found.']);
            exit;
        }

        $conn->begin_transaction();

        foreach ($days_of_week as $day) {
            $day = trim($day);

            $query = "UPDATE sections_schedules
                      SET section_id = ?, subject_id = ?, subject_code = ?, teacher_id = ?, teacher_name = ?, day_of_week = ?, start_time = ?, end_time = ?, room_id = ?, term_id = ?
                      WHERE ss_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                'iisssssssii',
                $section_id,
                $subject_id,
                $subject_code,
                $teacher_id,
                $teacher_name,
                $day,
                $start_time,
                $end_time,
                $room_id,
                $term_id,
                $schedule_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update schedule for $day: " . $stmt->error);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully for selected days']);

    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        error_log("Error updating schedule: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to update schedule: ' . $e->getMessage()]);
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
