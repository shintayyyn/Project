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
        $section_id = $_POST['section_id'];
        $subject_code = $_POST['subject_code'];
        $teacher_id = $_POST['teacher_id'];
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room_id = $_POST['room_id'];
        $term_id = $_SESSION['term_id']; // or however you handle terms

        // 🔹 Fetch teacher name automatically
        $teacher_stmt = $conn->prepare("SELECT CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''),'.') 
                                        AS teacher_name 
                                        FROM teachers 
                                        WHERE t_id = ?");
        $teacher_stmt->bind_param("i", $teacher_id);
        $teacher_stmt->execute();
        $teacher_result = $teacher_stmt->get_result();
        $teacher_name = ($row = $teacher_result->fetch_assoc()) ? $row['teacher_name'] : "Unknown";

        $stmt = $conn->prepare("INSERT INTO sections_schedules 
            (section_id, subject_code, teacher_id, teacher_name, day_of_week, start_time, end_time, room_id, term_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("isissssii", 
            $section_id, 
            $subject_code, 
            $teacher_id, 
            $teacher_name, 
            $day_of_week, 
            $start_time, 
            $end_time, 
            $room_id, 
            $term_id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;




case 'update_schedule':
    try {
        $required_fields = ['schedule_id', 'section_id', 'subject_code', 'teacher_id', 'day_of_week', 'start_time', 'end_time', 'room_id', 'term_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['error' => "Missing required field: $field"]);
                exit;
            }
        }

        $schedule_id  = intval($_POST['schedule_id']);
        $section_id   = intval($_POST['section_id']);
        $subject_code = $_POST['subject_code'];
        $teacher_id   = intval($_POST['teacher_id']);
        $day_of_week  = implode(',', $_POST['day_of_week']); // multiple days
        $start_time   = $_POST['start_time'];
        $end_time     = $_POST['end_time'];
        $room_id      = intval($_POST['room_id']);
        $term_id      = intval($_POST['term_id']);

        // Get subject_id
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $subject_id = $row ? intval($row['subject_id']) : null;

        // Get teacher_name
        $stmt = $conn->prepare("SELECT CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''),'.') AS teacher_name 
                                FROM teachers WHERE t_id = ? LIMIT 1");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $teacher_row = $stmt->get_result()->fetch_assoc();
        $teacher_name = $teacher_row ? $teacher_row['teacher_name'] : null;

        $update = $conn->prepare("UPDATE sections_schedules 
            SET section_id=?, subject_id=?, subject_code=?, teacher_id=?, teacher_name=?, day_of_week=?, start_time=?, end_time=?, room_id=?, term_id=? 
            WHERE ss_id=?");
        $update->bind_param("iisisssssii", 
            $section_id, $subject_id, $subject_code, $teacher_id, $teacher_name,
            $day_of_week, $start_time, $end_time, $room_id, $term_id, $schedule_id
        );

        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully!']);
        } else {
            echo json_encode(['error' => $update->error]);
        }

    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to update schedule: '.$e->getMessage()]);
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
