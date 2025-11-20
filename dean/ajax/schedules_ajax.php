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
    // Fetch all teachers linked to any sections/advisors, regardless of term
    $teachers_query = "
    SELECT DISTINCT
        t.t_id,
        CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), '')) AS teacher_name,
        t.t_status,
        t.t_department,
        d.degree_id,
        d.degree_code
    FROM teachers t
    LEFT JOIN degrees d ON t.t_department = d.degree_id
    LEFT JOIN sections_advisors sa ON t.t_id = sa.t_id
    LEFT JOIN sections s ON sa.section_id = s.section_id
    ORDER BY t.t_lname, t.t_fname
    ";

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
    $days_of_week = is_array($_POST['day_of_week']) ? $_POST['day_of_week'] : [$_POST['day_of_week']];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room_id = intval($_POST['room_id']);
    $description = "Ready for class!";

    // Get subject_id
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

    // Get teacher name
    $stmt = $conn->prepare("SELECT t_lname, t_fname, t_mname, CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''), '.') as teacher_name 
                            FROM teachers WHERE t_id = ? LIMIT 1");
    $stmt->bind_param('i', $teacher_id);
    $stmt->execute();
    $teacher_result = $stmt->get_result();
    if ($teacher_result && $row = $teacher_result->fetch_assoc()) {
        $teacher_name = $row['teacher_name'];
        $teacher_lname = $row['t_lname'];
        $teacher_fname = $row['t_fname'];
        $teacher_mname = $row['t_mname'] ?? '';
    } else {
        echo json_encode(['error' => 'Selected teacher not found.']);
        exit;
    }

    // Get active term
    $active_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $term_id = $active_term['term_id'] ?? null;

    // Determine schedule_group_id
    $stmt = $conn->prepare("SELECT schedule_group_id FROM sections_schedules 
                            WHERE section_id = ? AND subject_code = ? AND teacher_id = ? AND term_id = ? 
                            ORDER BY schedule_group_id DESC LIMIT 1");
    $stmt->bind_param('isii', $section_id, $subject_code, $teacher_id, $term_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $schedule_group_id = intval($row['schedule_group_id']); // reuse existing group
    } else {
        $stmt = $conn->prepare("SELECT MAX(schedule_group_id) as max_group FROM sections_schedules WHERE section_id = ? AND term_id = ?");
        $stmt->bind_param('ii', $section_id, $term_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $schedule_group_id = ($row['max_group'] ?? 0) + 1;
    }

    $inserted_days = [];

    foreach ($days_of_week as $day_of_week) {

        // Skip if subject already exists for this section/day/term
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
                                FROM sections_schedules 
                                WHERE section_id = ? AND subject_id = ? AND day_of_week = ? AND term_id = ?");
        $stmt->bind_param('iisi', $section_id, $subject_id, $day_of_week, $term_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        if ($count > 0) continue;

        // Section conflict check
        $conflict_query = "SELECT s.section_id, s.subject_code, s.start_time, s.end_time, sec.section_code
                           FROM sections_schedules s
                           INNER JOIN sections sec ON s.section_id = sec.section_id
                           WHERE s.section_id = ? 
                           AND s.day_of_week = ?
                           AND ((s.start_time BETWEEN ? AND ? OR s.end_time BETWEEN ? AND ?)
                           OR (? BETWEEN s.start_time AND s.end_time))";
        $stmt_conflict = $conn->prepare($conflict_query);
        $stmt_conflict->bind_param('issssss', $section_id, $day_of_week, $start_time, $end_time, $start_time, $end_time, $start_time);
        $stmt_conflict->execute();
        $conflict_row = $stmt_conflict->get_result()->fetch_assoc();
        if ($conflict_row) {
            echo json_encode([
                'error' => "Schedule conflict detected for Section {$conflict_row['section_code']}, Subject {$conflict_row['subject_code']} on $day_of_week ({$conflict_row['start_time']} - {$conflict_row['end_time']})"
            ]);
            exit;
        }

        // Room conflict check
        $room_conflict_query = "SELECT s.section_id, s.subject_code, s.day_of_week, s.start_time, s.end_time, r.room_number
                                FROM sections_schedules s
                                INNER JOIN rooms r ON s.room_id = r.room_id
                                WHERE s.room_id = ?
                                AND s.day_of_week = ?
                                AND ((s.start_time BETWEEN ? AND ? OR s.end_time BETWEEN ? AND ?)
                                OR (? BETWEEN s.start_time AND s.end_time))";
        $stmt_room = $conn->prepare($room_conflict_query);
        $stmt_room->bind_param('issssss', $room_id, $day_of_week, $start_time, $end_time, $start_time, $end_time, $start_time);
        $stmt_room->execute();
        $room_conflict_row = $stmt_room->get_result()->fetch_assoc();
        if ($room_conflict_row) {
            echo json_encode([
                'error' => "Room conflict detected for Room {$room_conflict_row['room_number']}, Section {$room_conflict_row['section_id']}, Subject {$room_conflict_row['subject_code']} on $day_of_week ({$room_conflict_row['start_time']} - {$room_conflict_row['end_time']})"
            ]);
            exit;
        }

        // Insert schedule
        $insert_query = "INSERT INTO sections_schedules
            (schedule_group_id, section_id, subject_id, subject_code, teacher_id, teacher_name, day_of_week, start_time, end_time, room_id, term_id, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_query);
        $stmt_insert->bind_param(
            'iiisisssssis',
            $schedule_group_id,
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
        if (!$stmt_insert->execute()) {
            throw new Exception("Failed to add schedule for $day_of_week: " . $stmt_insert->error);
        }

        $inserted_days[] = $day_of_week;
    }

    if (empty($inserted_days)) {
        echo json_encode(['error' => 'All selected days already exist for this subject in this section.']);
        exit;
    }

    // Insert teacher into subjects_teachers if not exists
    $stmt_check_teacher = $conn->prepare("SELECT COUNT(*) as count 
                                          FROM subjects_teachers 
                                          WHERE subject_id = ? AND t_id = ? AND term_id = ?");
    $stmt_check_teacher->bind_param('iii', $subject_id, $teacher_id, $term_id);
    $stmt_check_teacher->execute();
    $teacher_assigned = $stmt_check_teacher->get_result()->fetch_assoc()['count'];

    if ($teacher_assigned == 0) {
        $stmt_insert_teacher = $conn->prepare("INSERT INTO subjects_teachers 
            (subject_id, subject_code, t_id, t_lname, t_fname, t_mname, term_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_insert_teacher->bind_param(
            'isisssi',
            $subject_id,
            $subject_code,
            $teacher_id,
            $teacher_lname,
            $teacher_fname,
            $teacher_mname,
            $term_id
        );
        $stmt_insert_teacher->execute();
    }

    // Update teacher status
    $stmt = $conn->prepare("UPDATE teachers SET t_status = 'active' WHERE t_id = ?");
    $stmt->bind_param('i', $teacher_id);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Schedule added successfully',
        'days_added' => $inserted_days
    ]);

} catch (Exception $e) {
    error_log("Error adding schedule: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to add schedule: ' . $e->getMessage()]);
}
break;




case 'update_schedule':
try {
    ini_set('display_errors',1);
    ini_set('display_startup_errors',1);
    error_reporting(E_ALL);
    header('Content-Type: application/json');

    // ---------------------
    // Required fields
    // ---------------------
    $required_fields = ['schedule_id','section_id','subject_code','day_of_week','start_time','end_time','room_id','teacher_id'];
    $missing = [];
    foreach($required_fields as $f){
        if(!isset($_POST[$f]) || (is_array($_POST[$f]) ? count($_POST[$f])===0 : trim($_POST[$f])==='')) $missing[]=$f;
    }
    if($missing) throw new Exception("Missing fields: ".implode(', ',$missing));

    // ---------------------
    // Collect values
    // ---------------------
    $schedule_id = intval($_POST['schedule_id']);
    $section_id = intval($_POST['section_id']);
    $subject_code = $_POST['subject_code'];
    $days_of_week = is_array($_POST['day_of_week']) ? $_POST['day_of_week'] : [$_POST['day_of_week']];
    $start_time = date('H:i:s',strtotime($_POST['start_time']));
    $end_time = date('H:i:s',strtotime($_POST['end_time']));
    $room_id = intval($_POST['room_id']);
    $teacher_id = intval($_POST['teacher_id']);

    $start_time_12 = date('h:i A',strtotime($start_time));
    $end_time_12 = date('h:i A',strtotime($end_time));

    // ---------------------
    // Active term
    // ---------------------
    $term_id = $conn->query("SELECT term_id FROM academic_terms WHERE is_active=1 LIMIT 1")->fetch_assoc()['term_id'] ?? null;
    if(!$term_id) throw new Exception("No active term");

    // ---------------------
    // Fetch schedule_group_id and old term
    // ---------------------
    $row = $conn->query("SELECT schedule_group_id, term_id FROM sections_schedules WHERE ss_id=$schedule_id LIMIT 1")->fetch_assoc();
    $schedule_group_id = $row['schedule_group_id'] ?? 0;
    $old_term_id = $row['term_id'] ?? 0;

    // Determine if we need a new group ID (0 or from previous term)
    if($schedule_group_id == 0 || $old_term_id != $term_id){
        // Generate a unique numeric schedule_group_id
        $schedule_group_id = intval($conn->query("SELECT MAX(schedule_group_id) AS max_id FROM sections_schedules")->fetch_assoc()['max_id']) + 1;

        // Update all rows for this schedule to new group and active term
        $conn->query("UPDATE sections_schedules SET schedule_group_id=$schedule_group_id, term_id=$term_id WHERE ss_id=$schedule_id");
    } else {
        // Just ensure term_id is correct for this group
        $conn->query("UPDATE sections_schedules SET term_id=$term_id WHERE schedule_group_id=$schedule_group_id");
    }

    // ---------------------
    // Duration check based on selected days
    // ---------------------
    $day_count = count($days_of_week);
    if ($day_count === 1) {
        $required_duration = 3 * 3600; // 3 hrs
    } elseif ($day_count === 2) {
        $required_duration = 1.5 * 3600; // 1.5 hrs
    } elseif ($day_count === 3) {
        $required_duration = 1 * 3600; // 1 hr
    } else {
        throw new Exception("Invalid number of selected days: $day_count");
    }
    $duration = strtotime($end_time) - strtotime($start_time);
    if ($duration != $required_duration) {
        throw new Exception("Selected time ($start_time_12 - $end_time_12) must be exactly ".($required_duration/3600)." hour(s) for $day_count selected day(s).");
    }

    // ---------------------
    // Fetch subject
    // ---------------------
    $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code=? LIMIT 1");
    $stmt->bind_param('s',$subject_code); $stmt->execute();
    $subject_id = $stmt->get_result()->fetch_assoc()['subject_id'] ?? null;
    $stmt->close();
    if(!$subject_id) throw new Exception("Subject not found");

    // ---------------------
    // Fetch teacher
    // ---------------------
    $stmt = $conn->prepare("SELECT t_status, t_lname, t_fname, t_mname, CONCAT(t_lname, ', ', t_fname, ' ', COALESCE(LEFT(t_mname,1),''),'.') AS teacher_name FROM teachers WHERE t_id=?");
    $stmt->bind_param('i',$teacher_id); $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$teacher) throw new Exception("Teacher not found");
    $teacher_name = $teacher['teacher_name'];

    if($teacher['t_status']==='inactive'){
        $stmt2 = $conn->prepare("UPDATE teachers SET t_status='active' WHERE t_id=?");
        $stmt2->bind_param('i',$teacher_id); $stmt2->execute();
        $stmt2->close();
    }

    // ---------------------
    // Fetch existing days for this group
    // ---------------------
    $existing = $conn->query("SELECT ss_id, day_of_week FROM sections_schedules WHERE schedule_group_id=$schedule_group_id");
    $existing_days=[]; $existing_ids=[];
    while($row = $existing->fetch_assoc()){
        $existing_days[] = $row['day_of_week'];
        $existing_ids[$row['day_of_week']] = $row['ss_id'];
    }
    $existing->free();

    // ---------------------
    // Begin transaction
    // ---------------------
    $conn->begin_transaction();

    // ---------------------
    // Delete unselected days
    // ---------------------
    foreach(array_diff($existing_days,$days_of_week) as $del_day){
        $ss_id = $existing_ids[$del_day];
        $conn->query("DELETE FROM sections_schedules WHERE ss_id=$ss_id");
    }

   // ---------------------
// Fetch section_code for current section
// ---------------------
$stmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id=? LIMIT 1");
$stmt->bind_param('i', $section_id);
$stmt->execute();
$section_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$current_section_code = $section_row['section_code'] ?? 'Unknown';

// ---------------------
// Prepare conflict arrays
// ---------------------
$teacher_conflicts = [];
$room_conflicts = [];
$section_conflicts = [];
$subject_conflicts = [];

// ---------------------
// Insert/Update selected days
// ---------------------
foreach ($days_of_week as $day) {
    $day = trim($day);

    // ---- Teacher conflict
    $res = $conn->query("SELECT ss.*, s.section_code 
        FROM sections_schedules ss 
        JOIN sections s ON ss.section_id = s.section_id
        WHERE teacher_id=$teacher_id AND day_of_week='$day' 
        AND ((start_time<'$end_time' AND end_time>'$start_time'))
        AND schedule_group_id!=$schedule_group_id");
    while ($row = $res->fetch_assoc()) {
        $teacher_conflicts[] = "• $day ($start_time_12 - $end_time_12) [Subject: {$row['subject_code']}, Section: {$row['section_code']}]";
    }
    $res->free();

    // ---- Room conflict
    $res = $conn->query("SELECT * FROM sections_schedules 
        WHERE room_id=$room_id AND day_of_week='$day'
        AND ((start_time<'$end_time' AND end_time>'$start_time'))
        AND schedule_group_id!=$schedule_group_id");
    if ($res->num_rows > 0) $room_conflicts[] = "• $day ($start_time_12 - $end_time_12)";
    $res->free();

    // ---- Section conflict
    $res = $conn->query("SELECT ss.*, s.section_code 
        FROM sections_schedules ss 
        JOIN sections s ON ss.section_id = s.section_id
        WHERE ss.section_id=$section_id AND day_of_week='$day'
        AND ((start_time<'$end_time' AND end_time>'$start_time'))
        AND ss.schedule_group_id!=$schedule_group_id");
    while ($row = $res->fetch_assoc()) {
        $section_conflicts[] = "• $day ($start_time_12 - $end_time_12) in Section: {$row['section_code']}";
    }
    $res->free();

    // ---- Subject conflict in other sections
    $res = $conn->query("SELECT ss.*, s.section_code 
        FROM sections_schedules ss 
        JOIN sections s ON ss.section_id = s.section_id
        WHERE subject_id=$subject_id AND day_of_week='$day' AND ss.section_id!=$section_id
        AND ((start_time<'$end_time' AND end_time>'$start_time'))
        AND ss.schedule_group_id!=$schedule_group_id");
    while ($row = $res->fetch_assoc()) {
        $subject_conflicts[] = "• $day ($start_time_12 - $end_time_12) in Section: {$row['section_code']}";
    }
    $res->free();

    // ---- Insert/Update row
    if (in_array($day, $existing_days)) {
        $ss_id = $existing_ids[$day];
        $stmt = $conn->prepare("UPDATE sections_schedules 
            SET section_id=?, subject_id=?, subject_code=?, teacher_id=?, teacher_name=?, start_time=?, end_time=?, room_id=?, term_id=? 
            WHERE ss_id=?");
        $stmt->bind_param('iissssssii', $section_id, $subject_id, $subject_code, $teacher_id, $teacher_name, $start_time, $end_time, $room_id, $term_id, $ss_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $desc = "Ready for class!";
        $stmt = $conn->prepare("INSERT INTO sections_schedules 
            (schedule_group_id, section_id, subject_id, subject_code, teacher_id, teacher_name, day_of_week, start_time, end_time, room_id, term_id, description) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiisisssssis', $schedule_group_id, $section_id, $subject_id, $subject_code, $teacher_id, $teacher_name, $day, $start_time, $end_time, $room_id, $term_id, $desc);
        $stmt->execute();
        $stmt->close();
    }
}


    // ---------------------
    // Throw readable conflict errors
    // ---------------------
    $conflict_messages=[];
    if($teacher_conflicts) $conflict_messages[] = "Teacher conflicts:\n".implode("\n",$teacher_conflicts);
    if($room_conflicts) $conflict_messages[] = "Room conflicts:\n".implode("\n",$room_conflicts);
    if($section_conflicts) $conflict_messages[] = "Section conflicts:\n".implode("\n",$section_conflicts);
    if($subject_conflicts) $conflict_messages[] = "Subject conflicts in other sections:\n".implode("\n",$subject_conflicts);

    if($conflict_messages) throw new Exception(implode("\n\n",$conflict_messages));

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Schedule updated successfully']);

}catch(Exception $e){
    if(isset($conn) && $conn->errno) $conn->rollback();
    echo json_encode(['error'=>$e->getMessage()]);
}
break;


      case 'delete_schedule':
try {
    if (empty($_POST['schedule_id'])) {
        echo json_encode(['error' => 'Schedule ID is required']);
        exit;
    }

    $schedule_id = intval($_POST['schedule_id']);

    // Fetch the schedule_group_id for the given ss_id
    $stmt = $conn->prepare("SELECT schedule_group_id FROM sections_schedules WHERE ss_id = ? LIMIT 1");
    $stmt->bind_param('i', $schedule_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $schedule_group_id = intval($row['schedule_group_id']);
    } else {
        echo json_encode(['error' => 'Schedule not found']);
        exit;
    }

    // Delete all schedules with the same schedule_group_id
    $delete_query = "DELETE FROM sections_schedules WHERE schedule_group_id = ?";
    $stmt_delete = $conn->prepare($delete_query);
    $stmt_delete->bind_param('i', $schedule_group_id);

    if ($stmt_delete->execute()) {
        echo json_encode(['success' => true, 'message' => 'All schedules in the group deleted successfully']);
    } else {
        throw new Exception('Failed to delete schedules');
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
