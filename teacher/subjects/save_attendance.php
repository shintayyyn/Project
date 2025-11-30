<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once('../../includes/mailer.php');
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');


if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id   = $_SESSION['user_id'];
$currentDate  = date('Y-m-d');
$currentDay   = strtolower(date('l'));
$currentTime  = new DateTime();

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
    // get readable term/academic year label for responses
$termLabel = 'N/A';
$termInfoStmt = $conn->prepare("
    SELECT t.semester, ay.year_start, ay.year_end
    FROM academic_terms t
    JOIN academic_years ay ON t.ay_id = ay.ay_id
    WHERE t.term_id = ? LIMIT 1
");
$termInfoStmt->bind_param("i", $term_id);
$termInfoStmt->execute();
$termInfoRes = $termInfoStmt->get_result();
if ($termInfoRes && $termInfoRes->num_rows > 0) {
    $tr = $termInfoRes->fetch_assoc();
    $termLabel = "A.Y. {$tr['year_start']}-{$tr['year_end']} | {$tr['semester']}";
}
$termInfoStmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'No active term found.']);
    exit();
}



/* ======================================================
   CASE 1: Teacher Time-in
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['teacher_action'] ?? '') === 'time_in') {
    $subject_code = $_POST['subject_code'] ?? null;
    $section_id   = $_POST['section_id'] ?? null;

    if (!$subject_code || !$section_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    // ✅ Verify schedule and get subject_id
    $schedStmt = $conn->prepare("
        SELECT subject_id, start_time, end_time
        FROM sections_schedules
        WHERE teacher_id = ? AND subject_code = ? AND section_id = ? 
          AND term_id = ? AND LOWER(day_of_week) = ? AND is_active =1
        LIMIT 1
    ");
    $schedStmt->bind_param("isiis", $teacher_id, $subject_code, $section_id, $term_id, $currentDay);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();

    if ($schedRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No class schedule this time.']);
        exit();
    }

    $schedule    = $schedRes->fetch_assoc();
    $subject_id  = $schedule['subject_id'];
    $start_time  = new DateTime($schedule['start_time']);
    $end_time    = new DateTime($schedule['end_time']);

   $allowed_start = (clone $start_time)->modify('-15 minutes');

if ($currentTime < $allowed_start) {
    echo json_encode(['success' => false, 'message' => 'Attendance logging is allowed only 15 minutes before the class starts.']);
    exit();
}

if ($currentTime > $end_time) {
    echo json_encode(['success' => false, 'message' => 'Class has already ended.']);
    exit();
}


    // ✅ Get section code
    $secStmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
    $secStmt->bind_param("i", $section_id);
    $secStmt->execute();
    $section_code = $secStmt->get_result()->fetch_assoc()['section_code'];
    $secStmt->close();

    // ✅ Check if already dismissed
    $checkDismiss = $conn->prepare("
        SELECT dismissed FROM teacher_attendance
        WHERE t_id = ? AND subject_id = ? AND subject_code = ? AND section_code = ? 
          AND attendance_date = CURDATE() AND term_id = ?
    ");
    $checkDismiss->bind_param("iissi", $teacher_id, $subject_id, $subject_code, $section_code, $term_id);
    $checkDismiss->execute();
    $resDismiss = $checkDismiss->get_result();
    if ($resDismiss->num_rows > 0 && $resDismiss->fetch_assoc()['dismissed'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Class is already dismissed.']);
        exit();
    }
    $checkDismiss->close();

    // ✅ Check if already logged
    $checkStmt = $conn->prepare("
        SELECT 1 FROM teacher_attendance
        WHERE t_id = ? AND subject_id = ? AND subject_code = ? AND section_code = ? 
          AND attendance_date = CURDATE() AND term_id = ?
    ");
    $checkStmt->bind_param("iissi", $teacher_id, $subject_id, $subject_code, $section_code, $term_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows === 0) {
        $now = date('Y-m-d H:i:s');
        $insertStmt = $conn->prepare("
            INSERT INTO teacher_attendance (t_id, subject_id, subject_code, section_code, time_in, attendance_date, term_id)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        $insertStmt->bind_param("iisssi", $teacher_id, $subject_id, $subject_code, $section_code, $now, $term_id);
        $insertStmt->execute();
        $insertStmt->close();
        echo json_encode(['success' => true, 'message' => 'Teacher time-in logged.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Already timed in for this class today.']);
    }
    $checkStmt->close();
    exit();
}

/* ======================================================
   CASE 2: Closing Attendance (Scanner OFF)
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_code'], $_POST['section_id']) && empty($_POST['qr_code'])) {
    $subject_code = $_POST['subject_code'];
    $section_id   = $_POST['section_id'];

    // ✅ Verify schedule with subject_id
    $schedStmt = $conn->prepare("
        SELECT subject_id, start_time, end_time
        FROM sections_schedules
        WHERE teacher_id = ? AND subject_code = ? AND section_id = ? 
          AND term_id = ? AND LOWER(day_of_week) = ? AND is_active = 1
        LIMIT 1
    ");
    $schedStmt->bind_param("isiis", $teacher_id, $subject_code, $section_id, $term_id, $currentDay);
    $schedStmt->execute();
    $schedRes = $schedStmt->get_result();

    if ($schedRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No class schedule today.']);
        exit();
    }

    $schedule           = $schedRes->fetch_assoc();
    $subject_id         = $schedule['subject_id'];
    $end_time           = new DateTime($schedule['end_time']);
    $tenMinutesAfterEnd = (clone $end_time)->modify('+10 minutes');

    if ($currentTime > $tenMinutesAfterEnd) {
        echo json_encode(['success' => false, 'message' => 'Attendance closing time has passed.']);
        exit();
    }

    try {
        $conn->begin_transaction();

        $secStmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
        $secStmt->bind_param("i", $section_id);
        $secStmt->execute();
        $section_code = $secStmt->get_result()->fetch_assoc()['section_code'];
        $secStmt->close();

        // ✅ Teacher time-out
        $teacherNow = date('Y-m-d H:i:s');
        $updateTeacher = $conn->prepare("
            UPDATE teacher_attendance
            SET time_out = ?, dismissed = 1
            WHERE t_id = ? AND subject_id = ? AND subject_code = ? AND section_code = ? 
              AND attendance_date = ? AND term_id = ? AND time_out IS NULL
        ");
        $updateTeacher->bind_param("siisssi", $teacherNow, $teacher_id, $subject_id, $subject_code, $section_code, $currentDate, $term_id);
        $updateTeacher->execute();
        $updateTeacher->close();

        // ✅ Update students who timed-in but no timeout (active term only)
        $updateStudents = $conn->prepare("
            UPDATE attendance a
            JOIN students_sections ss ON a.s_id = ss.s_id AND ss.section_id = ? AND ss.term_id = ?
            LEFT JOIN subject_enrollments se ON a.s_id = se.s_id AND se.subject_code = ? AND se.section_code = ? AND se.term_id = ?
            SET a.time_out = NOW()
            WHERE a.subject_id = ? AND a.subject_code = ? AND a.section_code = ? 
              AND DATE(a.time_in) = ? AND a.term_id = ? AND a.time_out IS NULL
        ");
        $updateStudents->bind_param("iisiissssi", $section_id, $term_id, $subject_code, $section_code, $term_id, $subject_id, $subject_code, $section_code, $currentDate, $term_id);
        $updateStudents->execute();
        $updateStudents->close();

        // ✅ Insert absent students (active term only)
        $studentsStmt = $conn->prepare("
            SELECT DISTINCT s.s_id
            FROM students s
            LEFT JOIN students_sections ss ON s.s_id = ss.s_id AND ss.section_id = ? AND ss.term_id = ?
            LEFT JOIN subject_enrollments se ON s.s_id = se.s_id AND se.subject_code = ? AND se.section_code = ? AND se.term_id = ?
            WHERE (ss.s_id IS NOT NULL OR se.s_id IS NOT NULL)
        ");
        $studentsStmt->bind_param("iisis", $section_id, $term_id, $subject_code, $section_code, $term_id);
        $studentsStmt->execute();
        $studentsRes = $studentsStmt->get_result();

        while ($s = $studentsRes->fetch_assoc()) {
            $checkStmt = $conn->prepare("
                SELECT 1 FROM attendance
                WHERE s_id = ? AND subject_id = ? AND subject_code = ? AND section_code = ? 
                  AND DATE(COALESCE(time_in, time_out, NOW())) = ? AND term_id = ?
            ");
            $checkStmt->bind_param("iisssi", $s['s_id'], $subject_id, $subject_code, $section_code, $currentDate, $term_id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                $attendanceDate = $currentDate . " 00:00:00";
                $insertStmt = $conn->prepare("
                    INSERT INTO attendance (s_id, subject_id, subject_code, section_code, time_in, time_out, status, term_id)
                    VALUES (?, ?, ?, ?, ?, ?, 'Absent', ?)
                ");
                $insertStmt->bind_param("iissssi", $s['s_id'], $subject_id, $subject_code, $section_code, $attendanceDate, $attendanceDate, $term_id);
                $insertStmt->execute();
                 // Fetch room number by joining rooms table
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
$room = $roomRow['room_number'] ?? '';
$roomStmt->close();

// Send parent email
if ($parent_email) {
    $timeType = $attendanceRes->num_rows > 0 ? 'Time-out' : 'Time-in';
    $timestamp = date('M j, Y g:i A');

    sendEmail(
        $parent_email,
        $student['s_fname'] . ' ' . $student['s_mname'] . ' ' . $student['s_lname'] . ' ' . $student['s_suffix'], // Student Name
        $subject_code, // Subject Name
        $section_code, // Section
        $timeType,     // Time-in or Time-out
        $timestamp,    // Current timestamp
        $room          // Room number
    );
}
                $insertStmt->close();
                
            }

            $checkStmt->close();
        }
        $studentsStmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Attendance closed successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}


/* ======================================================
   CASE 3: Student QR Scan
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_code'], $_POST['subject_code'], $_POST['section_id'])) {
    $qr_code      = $_POST['qr_code'];
    $subject_code = $_POST['subject_code'];
    $section_id   = $_POST['section_id'];

    // ✅ Fetch section_code
    $secStmt = $conn->prepare("SELECT section_code FROM sections WHERE section_id = ?");
    $secStmt->bind_param("i", $section_id);
    $secStmt->execute();
    $section_code = $secStmt->get_result()->fetch_assoc()['section_code'];
    $secStmt->close();

    // ✅ Check if teacher dismissed
    $checkDismiss = $conn->prepare("
        SELECT dismissed 
        FROM teacher_attendance
        WHERE t_id = ? AND subject_id IS NOT NULL AND subject_code = ? AND section_code = ? 
          AND attendance_date = CURDATE() AND term_id = ?
    ");
    $checkDismiss->bind_param("issi", $teacher_id, $subject_code, $section_code, $term_id);
    $checkDismiss->execute();
    $resDismiss = $checkDismiss->get_result();
    if ($resDismiss->num_rows > 0 && $resDismiss->fetch_assoc()['dismissed'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Class is already dismissed. Scanner is turned off.']);
        exit();
    }
    $checkDismiss->close();

// ------------------- Validate QR (allow both regular + irregular students) -------------------

// 1) Get the subject_id for this schedule (required for se join)
$schedIdStmt = $conn->prepare("
    SELECT subject_id 
    FROM sections_schedules
    WHERE subject_code = ? AND section_id = ? AND term_id = ? AND is_active = 1
    LIMIT 1
");
$schedIdStmt->bind_param("sii", $subject_code, $section_id, $term_id);
$schedIdStmt->execute();
$schedIdStmt->bind_result($subject_id_from_schedule);
$schedIdStmt->fetch();
$schedIdStmt->close();

if (empty($subject_id_from_schedule)) {
    echo json_encode(['success' => false, 'message' => 'No class schedule found for this subject/section/term.']);
    exit();
}

// 2) Validate QR and resolve section_code fallback
$stmt = $conn->prepare("
    SELECT 
        s.s_id, 
        s.s_fname, 
        s.s_lname, 
        s.s_mname,
        s.s_suffix,
        g.expires_at,  -- ✅ Added
        COALESCE(sec.section_code, se.section_code) AS section_code,
        COALESCE(sec.section_id, sec_se.section_id) AS section_id,
        CASE 
            WHEN ss.s_id IS NOT NULL THEN 'Regular' 
            WHEN se.s_id IS NOT NULL THEN 'Irregular' 
            ELSE NULL 
        END AS student_type
    FROM generatedqrcode g
    JOIN students s ON g.id = s.s_id
    LEFT JOIN students_sections ss
        ON s.s_id = ss.s_id
       AND ss.section_id = ?
       AND ss.term_id = ?
    LEFT JOIN sections sec
        ON ss.section_id = sec.section_id
    LEFT JOIN subject_enrollments se
        ON s.s_id = se.s_id
       AND se.subject_id = ?
       AND se.term_id = ?
       AND se.is_status = 1
       AND se.enrollment_status = 'Enrolled'
    LEFT JOIN sections sec_se
        ON se.section_code = sec_se.section_code
    WHERE g.generated_qrcode = ?
      AND (ss.s_id IS NOT NULL OR se.s_id IS NOT NULL)
    LIMIT 1
");


$stmt->bind_param(
    "iiiis",
    $section_id, 
    $term_id, 
    $subject_id_from_schedule, 
    $term_id, 
    $qr_code
);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Student isn't enrolled in this class."]);
    exit();
}

$student = $result->fetch_assoc();
$student_id = $student['s_id'];
// Fetch parent email
$parentStmt = $conn->prepare("
    SELECT p.p_email 
    FROM parents p
    JOIN parent_student sp ON p.p_id = sp.p_id
    WHERE sp.s_id = ?
    LIMIT 1
");
$parentStmt->bind_param("i", $student_id);
$parentStmt->execute();
$parentRes  = $parentStmt->get_result();
$parentRow  = $parentRes->fetch_assoc();
$parent_email = $parentRow['p_email'] ?? null;
$parentStmt->close();


// ✅ Now check QR expiration properly
if (!empty($student['expires_at'])) {
    $expireTime = new DateTime($student['expires_at']);
    if ($currentTime > $expireTime) {
        echo json_encode([
            'success' => false,
            'message' => 'QR code has expired.'
        ]);
        exit();
    }
}



if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => "Student isn't enrolled in this class."]);
    exit();
}


    $student_id   = $student['s_id'];
    $section_code = $student['section_code'];
    $student_section_id = $student['section_id'];


    // ✅ Verify schedule + subject_id
   $stmt = $conn->prepare("
    SELECT ss.subject_id, ss.start_time, ss.end_time
    FROM sections_schedules ss
    WHERE ss.subject_code = ? 
      AND ss.teacher_id = ? 
      AND ss.term_id = ? 
      AND LOWER(ss.day_of_week) = ?
      AND ss.is_active = 1
    LIMIT 1
");
$stmt->bind_param("siis", $subject_code, $teacher_id, $term_id, $currentDay);
    $stmt->execute();
    $schedResult = $stmt->get_result();

    if ($schedResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => "No class schedule this time."]);
        exit();
    }

    $schedule    = $schedResult->fetch_assoc();
    $subject_id  = $schedule['subject_id'];
    $start_time  = new DateTime($schedule['start_time']);
    $end_time    = new DateTime($schedule['end_time']);
    $tenMinutesAfterEnd = (clone $end_time)->modify('+10 minutes');

   $allowed_start = (clone $start_time)->modify('-15 minutes');

if ($currentTime < $allowed_start) {
    echo json_encode(['success' => false, 'message' => 'Attendance can only be logged 15 minutes before the class starts.']);
    exit();
}

    if ($currentTime > $tenMinutesAfterEnd) {
        echo json_encode(['success' => false, 'message' => 'Attendance is closed.']);
        exit();
    }

    // ✅ Check if already logged
    $stmt = $conn->prepare("
        SELECT attendance_id, time_in, time_out, status
        FROM attendance
        WHERE s_id = ? AND subject_id = ? AND subject_code = ? AND section_code = ? 
          AND term_id = ? AND DATE(COALESCE(time_in, time_out, NOW())) = CURDATE()
        LIMIT 1
    ");
    $stmt->bind_param("iissi", $student_id, $subject_id, $subject_code, $section_code, $term_id);
    $stmt->execute();
    $attendanceRes = $stmt->get_result();

    if ($attendanceRes->num_rows > 0) {
        $attendance = $attendanceRes->fetch_assoc();

        if ($attendance['time_out']) {
            echo json_encode(['success' => false, 'message' => 'Time-out already recorded.']);
            exit();
        }

        if ($attendance['status'] === 'Absent') {
            echo json_encode(['success' => false, 'message' => 'Student is marked absent. Timeout not allowed.']);
            exit();
        }

        $minutesAfterEnd = ($currentTime->getTimestamp() - $end_time->getTimestamp()) / 60;
        if ($minutesAfterEnd < 1 || $minutesAfterEnd > 10) {
            echo json_encode(['success' => false, 'message' => 'Timeout only allowed 1–10 mins after class ends.']);
            exit();
        }

        // ✅ Record timeout
        $stmt = $conn->prepare("UPDATE attendance SET time_out = NOW() WHERE attendance_id = ? AND subject_id = ? AND time_out IS NULL");
        $stmt->bind_param("ii", $attendance['attendance_id'], $subject_id);
        $stmt->execute();

     echo json_encode([
    'success' => true,
    'message' => 'Time-out recorded.',
    'data' => [
        'id' => $attendance['attendance_id'],
       'name' => $student['s_fname'] . ' ' . $student['s_mname'] . ' ' . $student['s_lname'] . ' ' . $student['s_suffix'],
        's_fname' => $student['s_fname'],
        's_mname' => $student['s_mname'],
        's_lname' => $student['s_lname'],
        's_suffix' => $student['s_suffix'],
        'course_section' => $section_code,
        'time_in' => $attendance['time_in'] ? date('M j, Y g:i A', strtotime($attendance['time_in'])) : null,
        'time_out' => date('M j, Y g:i A'),
        'status' => $attendance['status'],
        'term' => $termLabel
    ]
]);

        exit();
    }

    // ✅ New time-in
    $minutesLate = ($currentTime->getTimestamp() - $start_time->getTimestamp()) / 60;
    $status = 'Present';
    if ($minutesLate >= 30) $status = 'Absent';
    elseif ($minutesLate >= 15) $status = 'Late';

    $stmt = $conn->prepare("
        INSERT INTO attendance (s_id, subject_id, subject_code, section_code, time_in, status, term_id)
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt->bind_param("iisssi", $student_id, $subject_id, $subject_code, $section_code, $status, $term_id);
    $stmt->execute();
  // Fetch room number by joining rooms table
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
$room = $roomRow['room_number'] ?? '';
$roomStmt->close();

// Send parent email
if ($parent_email) {
    $timeType = $attendanceRes->num_rows > 0 ? 'Time-out' : 'Time-in';
    $timestamp = date('M j, Y g:i A');

    sendEmail(
        $parent_email,
        $student['s_fname'] . ' ' . $student['s_mname'] . ' ' . $student['s_lname'] . ' ' . $student['s_suffix'], // Student Name
        $subject_code, // Subject Name
        $section_code, // Section
        $timeType,     // Time-in or Time-out
        $timestamp,    // Current timestamp
        $room          // Room number
    );
}



    echo json_encode([
    'success' => true,
    'message' => "Attendance logged as $status.",
    'data' => [
        'id' => $conn->insert_id,
        'name' => $student['s_fname'] . ' ' . $student['s_mname'] . ' ' . $student['s_lname'] . ' ' . $student['s_suffix'],
        's_fname' => $student['s_fname'],
        's_mname' => $student['s_mname'],
        's_lname' => $student['s_lname'],
        's_suffix' => $student['s_suffix'],
        'course_section' => $section_code,
        'time_in' => date('M j, Y g:i A'),
        'time_out' => null,
        'status' => $status,
        'term' => $termLabel
    ]
]);

    exit(); 
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
