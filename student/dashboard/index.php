<?php
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');
$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    die("No student session found.");
}

// ---------------------------
// Initialize arrays to prevent undefined variable warnings
// ---------------------------
$studentLabels = [];
$monthlyPresent = [];
$monthlyLate = [];
$monthlyAbsent = [];
$monthlyExcused = [];

$semesterPresent = [];
$semesterLate = [];
$semesterAbsent = [];
$semesterExcused = [];

// ---------------------------
// Get current active term
// ---------------------------
$term_query = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$term_row = $term_query->fetch_assoc();
$current_term_id = $term_row ? $term_row['term_id'] : null;

// ---------------------------
// Get student information (term-aware) & sections
// ---------------------------
$student_info = null;

$student_query = "
SELECT s.*,
       -- For regular students: get section from students_sections
       (SELECT sec.section_code 
        FROM students_sections ss
        JOIN sections sec ON ss.section_id = sec.section_id
        WHERE ss.s_id = s.s_id AND ss.term_id = ?) AS regular_section,
       -- For irregular students: get all enrolled sections as comma-separated
       (SELECT GROUP_CONCAT(DISTINCT se.section_code ORDER BY se.section_code SEPARATOR ', ')
        FROM subject_enrollments se
        WHERE se.s_id = s.s_id AND s.is_regular = 2 AND se.term_id = ?) AS irregular_sections
FROM students s
WHERE s.s_id = ?
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("iii", $current_term_id, $current_term_id, $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($student_info) {
    // Decide which section to show
    if ($student_info['is_regular'] == 1) {
        $student_info['section_code'] = $student_info['regular_section'];
    } else {
        $student_info['section_code'] = $student_info['irregular_sections'];
    }

    // Add student label
    $studentLabels[] = $student_info['s_fname'] . ' ' . $student_info['s_lname'];
}

// ---------------------------
// Get today's schedule (term-aware, aligned with enrolled sections)
// ---------------------------
$today = date('l'); // e.g. Monday, Tuesday, etc.

// Get student type
$status_query = "SELECT is_regular FROM students WHERE s_id = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$status_result = $stmt->get_result()->fetch_assoc();
$is_regular = $status_result['is_regular'] ?? 1;
$stmt->close();

// ---------------------------
// Query for Irregular Students
// ---------------------------
if ($is_regular == 2) {
    $schedule_query = "
    SELECT 
        e.subject_code,
        sub.subject_id,
        sub.subject_description,
        ss.day_of_week,
        ss.start_time,
        ss.end_time,
        r.room_number,
        ss.status,               -- ✅ Correct column name
        ss.description,          -- ✅ Remarks column
        ss.ss_id,                -- ✅ For attachments
        CONCAT(
            t.t_fname, ' ',
            IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),
            t.t_lname,
            IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
        ) AS teacher_name,
        e.section_code
    FROM subject_enrollments e
    JOIN subjects sub ON e.subject_code = sub.subject_code
    JOIN sections_schedules ss
        ON ss.subject_code = e.subject_code
        AND ss.section_code = e.section_code
        AND ss.term_id = ?
        AND ss.day_of_week = ?
        AND ss.is_active = 1
    LEFT JOIN teachers t ON t.t_id = ss.teacher_id
    LEFT JOIN rooms r ON r.room_id = ss.room_id
    WHERE e.s_id = ? 
      AND e.enrollment_status = 'Enrolled'
      AND e.term_id = ?
    ORDER BY sub.subject_code, ss.start_time
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("isii", $current_term_id, $today, $student_id, $current_term_id);
}
// ---------------------------
// Query for Regular Students
// ---------------------------
else {
    $schedule_query = "
    SELECT 
        ss.subject_code,
        sub.subject_id,
        sub.subject_description,
        COALESCE(ss.day_of_week, 'TBA') AS day_of_week,
        COALESCE(ss.start_time, 'TBA') AS start_time,
        COALESCE(ss.end_time, 'TBA') AS end_time,
        COALESCE(r.room_number, 'TBA') AS room_number,
        COALESCE(ss.status, 'TBA') AS status,           -- ✅ use correct column
        COALESCE(ss.description, 'Ready for class!') AS description,
        ss.ss_id,
        COALESCE(CONCAT(
            t.t_fname,' ',
            IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),
            t.t_lname,
            IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
        ), 'TBA') AS teacher_name
    FROM sections_schedules ss
    JOIN subjects sub ON ss.subject_code = sub.subject_code
    JOIN students_sections sts ON sts.section_id = ss.section_id
    LEFT JOIN teachers t ON t.t_id = ss.teacher_id
    LEFT JOIN rooms r ON r.room_id = ss.room_id
    WHERE sts.s_id = ? 
      AND ss.term_id = ? 
      AND ss.day_of_week = ?
      AND ss.is_active = 1
    ORDER BY sub.subject_code, ss.start_time
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("iis", $student_id, $current_term_id, $today);
}

$stmt->execute();
$today_schedule = $stmt->get_result();
$stmt->close();

// ---------------------------
// Get all subjects for the student
// ---------------------------

if ($is_regular == 2) {
    $subjects_query = "
        SELECT DISTINCT sub.subject_id,
               sub.subject_code,
               sub.subject_description,
               COALESCE(CONCAT(
                    t.t_fname,' ',
                    IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),
                    t.t_lname,
                    IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
               ), 'TBA') AS teacher_name,
               sub.units,
               GROUP_CONCAT(DISTINCT e.section_code ORDER BY e.section_code SEPARATOR ', ') AS sections_enrolled
        FROM subject_enrollments e
        JOIN subjects sub ON e.subject_id = sub.subject_id
        LEFT JOIN sections_schedules ss
          ON ss.subject_code = sub.subject_code
         AND ss.section_code = e.section_code
         AND ss.term_id = ?
         AND ss.is_active = 1
        LEFT JOIN teachers t ON t.t_id = ss.teacher_id
        WHERE e.s_id = ? 
          AND e.enrollment_status = 'Enrolled'
          AND e.term_id = ?
        GROUP BY sub.subject_code, sub.subject_description, sub.units
        ORDER BY sub.subject_code
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("iii", $current_term_id, $student_id, $current_term_id);
} else {
    $subjects_query = "
        SELECT DISTINCT sub.subject_id,
               sub.subject_code,
               sub.subject_description,
               COALESCE(CONCAT(
                    t.t_fname,' ',
                    IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),
                    t.t_lname,
                    IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
               ), 'TBA') AS teacher_name,
               sub.units
        FROM sections_schedules ss
        JOIN subjects sub ON ss.subject_code = sub.subject_code
        JOIN students_sections sts ON sts.section_id = ss.section_id
        LEFT JOIN teachers t ON t.t_id = ss.teacher_id
        WHERE sts.s_id = ? AND sts.term_id = ? AND ss.is_active = 1
        ORDER BY sub.subject_code
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("ii", $student_id, $current_term_id);
}

$stmt->execute();
$subjects = $stmt->get_result();
$stmt->close();

// ---------------------------
// Prepare Monthly & Semester Attendance Arrays
// ---------------------------
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

// 1️⃣ Monthly counts
$stmt = $conn->prepare("
    SELECT 
        SUM(status='Present') AS present_count,
        SUM(status='Late') AS late_count,
        SUM(status='Absent') AS absent_count,
        SUM(status='Excuse') AS excuse_count
    FROM attendance
    WHERE s_id = ? 
      AND DATE(time_in) BETWEEN ? AND ?
");
$stmt->bind_param("iss", $student_id, $month_start, $month_end);
$stmt->execute();
$monthCounts = $stmt->get_result()->fetch_assoc();
$monthlyPresent[] = intval($monthCounts['present_count']);
$monthlyLate[]    = intval($monthCounts['late_count']);
$monthlyAbsent[]  = intval($monthCounts['absent_count']);
$monthlyExcused[] = intval($monthCounts['excuse_count']);
$stmt->close();

// 2️⃣ Semester counts
$stmt = $conn->prepare("
    SELECT 
        SUM(status='Present') AS present_count,
        SUM(status='Late') AS late_count,
        SUM(status='Absent') AS absent_count,
        SUM(status='Excuse') AS excuse_count
    FROM attendance
    WHERE s_id = ?
      AND term_id = ?
");
$stmt->bind_param("ii", $student_id, $current_term_id);
$stmt->execute();
$semCounts = $stmt->get_result()->fetch_assoc();
$semesterPresent[] = intval($semCounts['present_count']);
$semesterLate[]    = intval($semCounts['late_count']);
$semesterAbsent[]  = intval($semCounts['absent_count']);
$semesterExcused[] = intval($semCounts['excuse_count']);
$stmt->close();

// ---------------------------
// Totals
// ---------------------------
$totalStudents = count($studentLabels);
$monthlyTotals = [
    'present' => array_sum($monthlyPresent),
    'late'    => array_sum($monthlyLate),
    'absent'  => array_sum($monthlyAbsent),
    'excused' => array_sum($monthlyExcused),
];
$semesterTotals = [
    'present' => array_sum($semesterPresent),
    'late'    => array_sum($semesterLate),
    'absent'  => array_sum($semesterAbsent),
    'excused' => array_sum($semesterExcused),
];

// ---------------------------
// Attendance Overview (Month)
// ---------------------------
$status_query = "SELECT is_regular FROM students WHERE s_id = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$status_result = $stmt->get_result()->fetch_assoc();
$is_regular = $status_result['is_regular'] ?? 1;
$stmt->close();

// ---------------------------
// Calculate Total Classes This Month (Recurring Weekly Schedule)
// ---------------------------

// Generate list of dates in this month
$month_start = new DateTime(date('Y-m-01'));
$month_end   = new DateTime(date('Y-m-t'));
$month_end->modify('+1 day');

$dates_in_month = [];
$period = new DatePeriod($month_start, new DateInterval('P1D'), $month_end);

foreach ($period as $date) {
    $dates_in_month[] = [
        'Y-m-d' => $date->format('Y-m-d'),
        'day'   => $date->format('l')
    ];
}

// ---------------------------
// Get all scheduled classes (regular or irregular)
// ---------------------------
if ($is_regular == 1) {
    // ✅ Regular student schedule
    $schedule_sql = "
        SELECT ss.day_of_week
        FROM sections_schedules ss
        JOIN students_sections sts ON sts.section_id = ss.section_id
        WHERE sts.s_id = ?
          AND ss.term_id = ?
          AND ss.is_active = 1
    ";
    $stmt = $conn->prepare($schedule_sql);
    $stmt->bind_param("ii", $student_id, $current_term_id);
} else {
    // ✅ Irregular student schedule
    $schedule_sql = "
        SELECT ss.day_of_week
        FROM sections_schedules ss
        JOIN subject_enrollments se 
            ON ss.subject_code = se.subject_code 
           AND ss.section_code = se.section_code
        WHERE se.s_id = ?
          AND se.term_id = ?
          AND se.enrollment_status = 'Enrolled'
          AND ss.is_active = 1
    ";
    $stmt = $conn->prepare($schedule_sql);
    $stmt->bind_param("ii", $student_id, $current_term_id);
}

$stmt->execute();
$sched_res = $stmt->get_result();
$stmt->close();

// Count how many times each weekly schedule occurs this month
$total_classes = 0;

while ($row = $sched_res->fetch_assoc()) {
    $class_day = $row['day_of_week'];

    foreach ($dates_in_month as $d) {
        if ($d['day'] === $class_day) {
            $total_classes++;
        }
    }
}

// ---------------------------
// Count attended classes (WEIGHTED)
// Present = 1
// Late = 0.5
// Excuse = 0.5
// ---------------------------

$attended_sql = "
    SELECT status
    FROM attendance
    WHERE s_id = ?
      AND term_id = ?
      AND DATE(time_in) BETWEEN ? AND ?
      AND status IN ('Present','Late','Excuse')
";

$stmt = $conn->prepare($attended_sql);
$month_start_string = $month_start->format('Y-m-d');
$month_end_string   = (clone $month_end)->modify('-1 day')->format('Y-m-d');

$stmt->bind_param("iiss", $student_id, $current_term_id, $month_start_string, $month_end_string);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ✅ Weighted attendance counter
$attended = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'Present') {
        $attended += 1;
    } else {
        $attended += 0.5;  // Late or Excuse
    }
}

// ---------------------------
// Final Progress Calculation
// ---------------------------
$attendance_progress = ($total_classes > 0)
    ? round(($attended / $total_classes) * 100, 2)
    : 0.00;

$present_count = $attended;          // weighted count
$absent_count  = max(0, $total_classes - $attended);

?>





<main class="py-4">
    <div class="container-fluid px-4">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <h1 class="display-6 fw-bold text-primary mb-0">Welcome back, <?= htmlspecialchars($student_info['s_fname']); ?>!</h1>
                    <p class="text-muted mb-0">Here's your academic overview for today.</p>
                </div>
                <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small d-flex align-items-center">
                    <i class="bi bi-clock-history me-1"></i>
                    Last Updated: <span id="last-updated" class="ms-1 fw-semibold text-dark">
                        <?= htmlspecialchars($_SESSION['last_updated']); ?>
                    </span>
                </div>


            </div>

        </div>


        <!-- Profile Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card profile-card">
                    <div class="card-body p-0">
                        <div class="profile-header">
                            <div class="profile-cover"></div>
                            <div class="profile-avatar-wrapper">
                                <div class="profile-image">
                                    <?php
                                    $fname = $student_info['s_fname'];
                                    $lname = $student_info['s_lname'];
                                    $initials = strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
                                    echo htmlspecialchars($initials);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="profile-content">
                            <div class="text-center mb-3">
                                <h4 class="mb-1"><?php echo htmlspecialchars($student_info['s_fname'] . ' ' . $student_info['s_lname']); ?></h4>
                                <p class="text-muted mb-0">Student | <span class="badge bg-success text-wrap"><?php echo htmlspecialchars($role); ?></span> </p>

                            </div>
                            <div class="profile-stats">
                                <div class="row text-center">
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-person-badge-fill"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">ID Number</span>
                                                <span class="stat-value"><?php echo htmlspecialchars($student_info['idcode']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-people"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">Section</span>
                                                <span class="stat-value"><?php echo htmlspecialchars($student_info['section_code'] ?? 'Not Assigned'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-book"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">Course</span>
                                                <span class="stat-value">BSIT</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Schedule -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3">
                        <div class="row">
                            <div class="col">
                                <h5 class="card-title head mb-0 d-flex justify-content-between align-items-center">
                                    <i class="bi bi-calendar me-2 text-white"></i>Today's Schedule (<?php echo $today; ?>)
                                </h5>
                            </div>
                            <div class="col text-end">
                                <button class="btn btn-warning fw-semibold btn-sm" data-bs-toggle="modal" data-bs-target="#studyLoadModal<?= $student_info['s_id']; ?>">
                                    <i class="bi bi-book me-1"></i> View Study Load
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <?php if ($today_schedule->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table id="childScheduleTable<?php echo $student_info['s_id']; ?>" class="table table-hover mb-0 table-striped">
                                    <thead style="color: var(--primary);">
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Room</th>
                                            <th>Status</th>
                                            <th>Attachment</th>
                                        </tr>
                                    </thead>
                                   <tbody>
<?php
$seen = []; // Prevent duplicates

while ($class = $today_schedule->fetch_assoc()):
    $key = $class['subject_code'] . '|' . $class['start_time'] . '|' . $class['end_time'] . '|' . $class['room_number'];
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    // 🔹 Determine class status and description
    $currentStatus = !empty($class['status']) ? trim($class['status']) : '';
    $statusDescription = !empty($class['description']) ? trim($class['description']) : '';

    // 🔹 Fetch latest attachment info if exists
    $attachments = $conn->prepare("
        SELECT id, file_name, updated_at
        FROM attachment_files
        WHERE ss_id = ?
        ORDER BY updated_at DESC
    ");
    $attachments->bind_param("i", $class['ss_id']);
    $attachments->execute();
    $files = $attachments->get_result();
    $attachments->close();

    $latestUpdate = null;
    $fileList = [];
    if ($files->num_rows > 0) {
        while ($f = $files->fetch_assoc()) {
            $fileList[] = $f;
        }
        $latestUpdate = strtotime($fileList[0]['updated_at']); // latest file
    }

    // 🔹 Check if status is empty or outdated
    $oneWeekAgo = strtotime('-1 week');
    if (empty($currentStatus) || ($latestUpdate && $latestUpdate < $oneWeekAgo)) {
        $currentStatus = 'Available';
        $statusDescription = 'Ready for class';
    }

    // 🔹 Determine badge class
    $cleanStatus = strtolower($currentStatus);
    if (strpos($cleanStatus, 'not available') !== false) {
        $statusClass = "bg-danger text-white";
    } elseif (strpos($cleanStatus, 'async') !== false) {
        $statusClass = "bg-primary text-white";
    } elseif (strpos($cleanStatus, 'available') !== false) {
        $statusClass = "bg-success text-white";
    } else {
        $statusClass = "bg-secondary";
    }
?>
    <tr>
        <td><?= date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?></td>
        <td>
            <strong><?= htmlspecialchars($class['subject_code']); ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($class['subject_description']); ?></small>
        </td>
        <td><?= htmlspecialchars($class['teacher_name']); ?></td>
        <td><?= htmlspecialchars($class['room_number']); ?></td>
        <td>
            <span class="badge rounded-pill <?= $statusClass; ?>"><?= htmlspecialchars($currentStatus); ?></span><br>
            <?php if (!empty($statusDescription)): ?>
                <small class="text-muted"><?= htmlspecialchars($statusDescription); ?></small>
            <?php endif; ?>
        </td>
        <td>
            <?php
            // 🔹 Show attachments only if within 1 week
            if (!empty($fileList) && $latestUpdate >= $oneWeekAgo):
                foreach ($fileList as $file) {
                    $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                    $filePath = "../uploads/remarks/" . $file['file_name'];
            ?>
                    <button class="btn btn-sm btn-primary view-attachment"
                        data-url="<?= $filePath ?>"
                        data-type="<?= htmlspecialchars($ext) ?>"
                        data-name="<?= htmlspecialchars($file['file_name']) ?>">
                        <i class="bi bi-paperclip"></i> View
                    </button><br>
                <?php
                }
            else: ?>
                <span class="badge rounded-pill bg-warning text-muted">No attachment provided</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endwhile; ?>
</tbody>

                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
                                <p class="mb-0">No classes scheduled for today</p>
                            </div>
                        <?php endif; ?>
                    </div>


                </div>
            </div>


            <?php
            // Convert $subjects result object to array and remove duplicates by subject_code
            $subjects_array = [];

            if ($subjects && $subjects->num_rows > 0) {
                while ($row = $subjects->fetch_assoc()) {
                    $subject_code = trim($row['subject_code']);

                    // If not yet added, store it
                    if (!isset($subjects_array[$subject_code])) {
                        $subjects_array[$subject_code] = $row;
                    } else {
                        // If teacher_name was 'TBA' but a newer one has a real teacher, update it
                        if (
                            $subjects_array[$subject_code]['teacher_name'] === 'TBA' &&
                            $row['teacher_name'] !== 'TBA'
                        ) {
                            $subjects_array[$subject_code]['teacher_name'] = $row['teacher_name'];
                        }
                    }
                }
            }

            // Reindex numerically for clean foreach loop
            $subjects_array = array_values($subjects_array);
            ?>


            <!-- Current Subjects -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book me-2"></i>My Subjects
                        </h5>
                    </div>

                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                        <?php if (!empty($subjects_array)): ?>
                            <!-- Scrollable subject list -->
                            <div class="subjects-list">
                                <?php foreach ($subjects_array as $subject): ?>
                                    <div class="subject-item d-flex p-3 border-bottom align-items-start">
                                        <div class="subject-icon me-3 bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                            <i class="bi bi-book text-white fs-5"></i>
                                        </div>
                                        <div class="subject-info flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="subject-code mb-0 fw-bold text-primary">
                                                    <?= htmlspecialchars($subject['subject_code']); ?>
                                                </h6>
                                                <span class="badge bg-primary"><?= htmlspecialchars($subject['units']); ?> Units</span>
                                            </div>
                                            <p class="subject-name mb-1 text-muted small">
                                                <?= htmlspecialchars($subject['subject_description']); ?>
                                            </p>
                                            <div class="subject-meta small text-secondary">
                                                <i class="bi bi-person me-1"></i>
                                                <?= htmlspecialchars($subject['teacher_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 mb-2"></i>
                                <p class="mb-0">No subjects found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Status -->
            <div class="d-flex justify-content-center align-items-center vh-10">
                <div class="col-12">

                    <!-- Attendance Progress -->
                    <div class="card shadow-lg mb-3">
                        <div class="card-header py-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart-line me-2"></i>
                                Attendance Progress (<?php echo date('F'); ?>)
                            </h5>
                        </div>

                        <div class="card-body">
                            <div class="row align-items-center">

                                <!-- Text Summary -->
                                <?php
                                // fallback
                                $attendance_progress = $attendance_progress ?? 0;

                                // Color for text Percentage (lighter version)
                                if ($attendance_progress >= 100) {
                                    $percent_color = 'text-success';
                                } elseif ($attendance_progress >= 80) {
                                    $percent_color = 'text-primary';
                                } elseif ($attendance_progress >= 50) {
                                    $percent_color = 'text-warning';
                                } else {
                                    $percent_color = 'text-danger';
                                }
                                ?>

                                <div class="col-lg-6">
                                    <h5>
                                        You have attended
                                        <span class="<?php echo $percent_color; ?>">
                                            <?php echo $attendance_progress; ?>%
                                        </span>
                                        of your classes.
                                    </h5>
                                </div>

                                <!-- Log Attendance Button -->
                                <div class="col-lg-6 col-md-4 col-sm-12 text-center text-md-end mb-3 mb-md-0">
                                    <a href="?page=attendance"
                                        class="btn btn-warning text-dark fw-semibold btn-lg">
                                        <i class="bi bi-qr-code me-2"></i> Log Attendance
                                    </a>
                                </div>

                            </div>

                            <!-- Progress Bar Area -->
                            <?php
                            // Progress Bar Color
                            if ($attendance_progress >= 100) {
                                $bar_color = 'bg-success text-white';
                            } elseif ($attendance_progress >= 80) {
                                $bar_color = 'bg-primary text-white';
                            } elseif ($attendance_progress >= 50) {
                                $bar_color = 'bg-warning text-dark';
                            } else {
                                $bar_color = 'bg-danger text-white';
                            }

                            // Ensure visibility (min width)
                            $progress_width = ($attendance_progress > 0) ? $attendance_progress : 5;
                            ?>

                            <div class="progress m-5" style="height: 40px;">
                                <div
                                    class="progress-bar <?php echo $bar_color; ?> d-flex align-items-center justify-content-center"
                                    role="progressbar"
                                    style="width: <?php echo $progress_width; ?>%; min-width: 80px;"
                                    aria-valuenow="<?php echo $attendance_progress; ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                    <?php echo $attendance_progress; ?>%
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- Toast container at bottom-right -->
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
            <div id="downloadToast" class="toast align-items-center text-white fw-bold bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Your download has started!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <!-- Study Load Modal -->
        <div class="modal fade" id="studyLoadModal<?= $student_info['s_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header card-header">
                        <h5 class="modal-title text-white">
                            <i class="bi bi-book me-2"></i>
                            My Study Load
                        </h5>
                        <button type="button" class="btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                    <div class="modal-body">
                        <div id="studyLoadContainer<?= $student_info['s_id']; ?>" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!--Attachment Modal-->
        <div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header card-header">
                        <h5 class="modal-title text-white"><i class="bi bi-paperclip"></i> Attachment Viewer</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center" id="attachmentContent">
                        <p class="text-muted">Loading attachment...</p>
                    </div>
                    <div class="modal-footer">
                        <a id="downloadAttachment" class="btn btn-primary" download>
                            <i class="bi bi-download me-2"> </i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 align-items-stretch">
            <!-- Bar Chart Card -->
            <div class="col-12 col-md-7 d-flex">
                <div class="chart-card w-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="mb-0">Student's Status</h2>
                        <span class="badge badge-custom">
                            SUMMARY
                        </span>
                    </div>
                    <canvas id="barChart" class="w-100"></canvas>
                    <footer class="text-center">
                        <h5><?php
                            // Example: Fetch active academic year and term
                            $term_query = $conn->query("
                                SELECT 
                                    CONCAT('A.Y. ', ay.year_start, '-', ay.year_end, ' | ', 
                                        CASE 
                                            WHEN at.semester = '1st' THEN '1st Semester'
                                            WHEN at.semester = '2nd' THEN '2nd Semester'
                                            ELSE at.semester
                                        END
                                    ) AS term_display
                                FROM academic_terms at
                                JOIN academic_years ay ON ay.ay_id = at.ay_id
                                WHERE at.is_active = 1
                                LIMIT 1
                            ");
                            $active_term = $term_query->fetch_assoc();
                            $term_display = $active_term ? $active_term['term_display'] : 'No Active Term';
                            ?>


                            <span class="badge badge-custom fw-semibold">
                                <?php echo htmlspecialchars($term_display); ?>
                            </span>

                        </h5>
                    </footer>
                </div>
            </div>
            <!-- Pie Chart Card -->
            <div class="col-12 col-md-5 d-flex">
                <div class="chart-card w-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="mb-0">Student's Performance</h2>
                        <span class="badge badge-custom badge-secondary">
                            <?php echo date('F Y'); ?> <!-- This outputs: October 2025 -->
                        </span>

                    </div>
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>

</main>


<style>
    /* CUSTOM SCROLLBAR */
    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-thumb {
        background: rgba(78, 78, 78, 0.2);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* ============================
   CHART CARDS
   ============================ */
    .chart-card {
        background: #fff;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        min-height: 350px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }

    .chart-card h2 {
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        color: #000;
        margin: 0;
    }

    /* ============================
   BADGES
   ============================ */
    .badge-custom {
        background: linear-gradient(90deg, var(--primary), #1981C2);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 12px;
        padding: 5px 12px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .badge-secondary {
        background: linear-gradient(90deg, #FFC107, #FFD500);
        color: var(--primary);
    }

    /* ============================
   CHART LEGEND
   ============================ */
    .chart-legend {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .chart-legend span {
        display: flex;
        align-items: center;
        font-size: 0.9rem;
        color: #333;
    }

    .chart-legend .legend-box {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        margin-right: 6px;
    }

    /* ============================
   CANVAS
   ============================ */
    canvas {
        flex: 1;
        max-height: 250px;
    }

    .attendancebtn {
        border-radius: 20px;
    }

    .progress-bar {
        color: #495057;
        background: gold;
        font-size: 25px;
        border-radius: 20px;
    }

    .progress {
        border-radius: 20px;
    }

    /* Profile Card Styling */
    .profile-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .profile-header {
        position: relative;
        height: 80px;
    }

    .profile-cover {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 100%;
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .profile-avatar-wrapper {
        position: absolute;
        left: 50%;
        bottom: -40px;
        transform: translateX(-50%);
    }

    .profile-image {
        width: 85px;
        height: 85px;
        border: 4px solid white;
        background: linear-gradient(145deg, var(--secondary) 0%, var(--primary) 100%);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        font-weight: 600;
    }

    .profile-content {
        padding-top: 55px;
        padding-bottom: 25px;
        background-color: white;
    }

    .profile-stats {
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.02);
        margin: 0 30px;
        border-radius: 12px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px;
    }

    .stat-item i {
        font-size: 2rem;
        color: var(--primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
        padding: 15px;
        border-radius: 12px;
    }

    .stat-text {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }

    .stat-label {
        font-size: 1rem;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .stat-value {
        font-weight: 600;
        color: var(--primary);
        font-size: 1.25rem;
    }

    @media (max-width: 768px) {
        .profile-stats {
            margin: 0 15px;
        }

        .stat-item {
            flex-direction: column;
            text-align: center;
            padding: 15px;
        }

        .stat-text {
            align-items: center;
        }
    }

    /* Card styling */
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        background: white;
        margin-bottom: 1rem;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(240, 231, 231, 0.1);
    }

    .card-header {
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
        border-bottom: none;
        padding: 1rem 1.25rem;
        border-radius: 10px 10px 0 0 !important;
    }

    .card-title {
        color: white !important;
        font-weight: 600;
        display: flex;
        align-items: center;
        margin: 0;
        font-size: 1.1rem;
    }

    .card-title i {
        font-size: 1.3rem;
        margin-right: 0.75rem;
    }

    /* Table styling */
    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: rgba(0, 0, 0, 0.02);
        font-weight: 600;
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
    }

    .table td,
    .table th {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-color: rgba(205, 203, 203, 0.05);
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* List group styling */
    .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem 1.25rem;
        transition: all 0.2s ease;
        border-color: rgba(0, 0, 0, 0.05);
    }

    .list-group-item:hover {
        background-color: rgba(0, 0, 0, 0.02);
        transform: translateX(5px);
    }

    .list-group-item:first-child {
        border-top: none;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .list-group-item h6 {
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    /* Empty state styling */
    .text-center.py-5 {
        padding: 3rem !important;
    }

    .text-center.py-5 i {
        font-size: 3rem;
        color: var(--secondary);
        opacity: 0.5;
        margin-bottom: 1rem;
    }

    .text-center.py-5 p {
        color: #6c757d;
        font-size: 0.95rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .card {
            margin-bottom: 1rem;
        }

        .profile-image {
            width: 60px;
            height: 60px;
        }

        .profile-image i {
            font-size: 2rem;
        }

        .card-title {
            font-size: 1rem;
        }

        .card-title i {
            font-size: 1.2rem;
        }
    }

    /* Subjects Card Styling */
    .subjects-list {
        padding: 0;
    }

    .subject-item {
        display: flex;
        align-items: flex-start;
        padding: 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .subject-item:last-child {
        border-bottom: none;
    }

    .subject-item:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.03);
        transform: translateX(5px);
    }

    .subject-icon {
        width: 45px;
        height: 45px;
        min-width: 45px;
        border-radius: 10px;
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
    }

    .subject-icon i {
        font-size: 1.25rem;
        color: white;
    }

    .subject-info {
        flex: 1;
    }

    .subject-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.35rem;
    }

    .subject-code {
        color: var(--primary);
        font-weight: 600;
        margin: 0;
        font-size: 1rem;
    }

    .subject-units {
        font-size: 0.8rem;
        color: #6c757d;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 500;
    }

    .subject-name {
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .subject-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .teacher-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .teacher-info i {
        font-size: 0.9rem;
        color: var(--primary);
    }

    /* Empty state styling */
    .text-center.py-5 i {
        font-size: 2.5rem;
        color: #dee2e6;
        margin-bottom: 0.5rem;
    }

    .text-center.py-5 p {
        font-size: 0.9rem;
    }

    @media (max-width: 576px) {
        .subject-item {
            padding: 1rem;
        }

        .subject-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
        }

        .subject-icon i {
            font-size: 1.1rem;
        }

        .subject-code {
            font-size: 0.95rem;
        }

        .subject-name {
            font-size: 0.85rem;
        }
    }
</style>

<!-- Bootstrap JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-VO4l2JxgKzpP1g7MvhDblZjzL5EE8v/IuT63rPxkFq0Zrj/6DqOvlR+TE1U8W6lQ" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    $(document).ready(function() {
        $('#childScheduleTable<?php echo $student_info['s_id']; ?>').DataTable({
            scrollX: false, // no horizontal scroll
            scrollY: '50vh', // vertical scroll height
            scrollCollapse: true, // shrink table if less content
            responsive: true,
            paging: false, // no pagination
            searching: false, // hide search box
            info: false, // hide "showing x of y entries"
            ordering: true, // allow sorting
            autoWidth: false,
            columnDefs: [{
                    responsivePriority: 1,
                    targets: 0
                }, // Time - always
                {
                    responsivePriority: 2,
                    targets: 1
                }, // Subject - always
                {
                    responsivePriority: 3,
                    targets: 4
                }, // Status - always visible next
                {
                    responsivePriority: 4,
                    targets: 2
                }, // Teacher
                {
                    responsivePriority: 5,
                    targets: 3
                } // Room
            ]
        });
    });

    $(document).on('click', '.view-attachment', function() {
        let url = $(this).data('url');
        let type = $(this).data('type').toLowerCase();
        let name = $(this).data('name');

        let content = "";

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(type)) {
            // Image preview
            content = `<img src="${url}" alt="${name}" class="img-fluid rounded shadow">`;
        } else if (['pdf', 'ppt', 'pptx'].includes(type)) {
            // Iframe preview with badge overlay
            content = `
            <div class="position-relative">
                <iframe src="${url}" style="width:100%; height:75vh;" frameborder="0"></iframe>
                <span class="badge bg-danger position-absolute top-0 start-0 m-2">${type.toUpperCase()}</span>
            </div>`;
        } else {
            // Unsupported files
            content = `
            <div class="text-center py-5">
                <i class="bi bi-file-earmark-fill display-1 text-secondary"></i>
                <p class="mt-3">
                    <span class="badge bg-secondary">${type.toUpperCase()}</span> ${name}
                </p>
            </div>`;
        }

        $('#attachmentContent').html(content);
        $('#downloadAttachment').attr("href", url).attr("download", name);
        $('#attachmentModal').modal('show');
    });

    $(document).on('click', '#downloadAttachment', function(e) {
        let toastEl = document.getElementById('downloadToast');
        let toast = new bootstrap.Toast(toastEl, {
            delay: 3000
        }); // auto-hide after 3s
        toast.show();
    });



    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('childSearch');
        const items = document.querySelectorAll('.dropdown-item-wrapper');

        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
        const mainContent = document.querySelector('main');
        if (mainContent) {
            setTimeout(() => {
                mainContent.classList.add('loaded');
            }, 100); // slight delay for smoother effect
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="studyLoadModal"]').forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                const modalId = this.id;
                const childId = modalId.replace('studyLoadModal', '');
                const container = document.getElementById('studyLoadContainer' + childId);

                container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;

                fetch('dashboard/fetch_studyload.php?child_id=' + childId)
                    .then(response => response.text())
                    .then(data => container.innerHTML = data)
                    .catch(err => container.innerHTML = `<div class="alert alert-danger">Failed to load study load.</div>`);
            });
        });
    });
    // ✅ Ensure data has default 0 values if empty
    const semesterPresentSafe = <?php echo json_encode($semesterPresent); ?>.map(v => v || 0);
    const semesterLateSafe = <?php echo json_encode($semesterLate); ?>.map(v => v || 0);
    const semesterAbsentSafe = <?php echo json_encode($semesterAbsent); ?>.map(v => v || 0);
    const semesterExcusedSafe = <?php echo json_encode($semesterExcused); ?>.map(v => v || 0);
    const studentLabelsSafe = <?php echo json_encode($studentLabels); ?>;

    // ----------------------------------------------------
    // 📊 BAR CHART (Whole Semester Summary per Student)
    // ----------------------------------------------------
    new Chart(document.getElementById('barChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: studentLabelsSafe.length ? studentLabelsSafe : ['No Students'],
            datasets: [{
                    label: 'Present',
                    data: semesterPresentSafe.length ? semesterPresentSafe : [0],
                    backgroundColor: '#033A70',
                    borderRadius: 10,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    minBarLength: 5 // ensures bar still shows if zero
                },
                {
                    label: 'Late',
                    data: semesterLateSafe.length ? semesterLateSafe : [0],
                    backgroundColor: '#FFC107',
                    borderRadius: 10,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    minBarLength: 5
                },
                {
                    label: 'Absent',
                    data: semesterAbsentSafe.length ? semesterAbsentSafe : [0],
                    backgroundColor: '#1981C2',
                    borderRadius: 10,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    minBarLength: 5
                },
                {
                    label: 'Excused',
                    data: semesterExcusedSafe.length ? semesterExcusedSafe : [0],
                    backgroundColor: '#6FB2F7',
                    borderRadius: 10,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                    minBarLength: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        color: '#000',
                        padding: 15
                    }
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        title: function(tooltipItems) {
                            return studentLabelsSafe[tooltipItems[0].dataIndex] || 'No Name';
                        },
                        label: function(tooltipItem) {
                            return tooltipItem.dataset.label + ': ' + (tooltipItem.raw || 0);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 10,
                        color: "#333",
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        drawTicks: false,
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: "#333",
                        font: {
                            size: 12
                        },
                        callback: function(value, index) {
                            return window.innerWidth < 576 ? '' : this.getLabelForValue(value);
                        }
                    },
                    grid: {
                        drawTicks: false,
                        drawBorder: false
                    }
                }
            }
        }
    });

    // ---------------------------------------------
    // 🟢 Pie Chart (Monthly Summary with Zero Visual)
    // ---------------------------------------------
    const monthlyData = [
        <?php echo $monthlyTotals['present']; ?>,
        <?php echo $monthlyTotals['late']; ?>,
        <?php echo $monthlyTotals['absent']; ?>,
        <?php echo $monthlyTotals['excused']; ?>
    ];

    // Check if all values are zero
    const isAllZero = monthlyData.every(v => v === 0);

    const pieData = {
        labels: isAllZero ? ['No Data Recorded'] : ['Present', 'Late', 'Absent', 'Excused'],
        datasets: [{
            data: isAllZero ? [1] : monthlyData,
            backgroundColor: isAllZero ?
                ['#E0E0E0'] // gray placeholder if no data
                :
                ['#033A70', '#FFC107', '#1981C2', '#6FB2F7'],
            borderWidth: 0
        }]
    };

    const pieOptions = {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 15,
                    color: '#000'
                }
            },
            tooltip: {
                enabled: !isAllZero, // disable tooltips if no data
                backgroundColor: '#fff',
                titleColor: '#000',
                bodyColor: '#000',
                borderColor: '#ddd',
                borderWidth: 1
            },
            // 🟡 Optional: Custom "No Data" label overlay
            beforeDraw: (chart) => {
                if (isAllZero) {
                    const {
                        width,
                        height,
                        ctx
                    } = chart;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.font = 'bold 16px Roboto';
                    ctx.fillStyle = '#999';
                    ctx.fillText('No Data Recorded', width / 2, height / 2);
                    ctx.restore();
                }
            }
        }
    };

    new Chart(document.getElementById('pieChart').getContext('2d'), {
        type: 'pie',
        data: pieData,
        options: pieOptions
    });

    document.addEventListener('DOMContentLoaded', function() {
        const mainContent = document.querySelector('main');
        if (mainContent) {
            setTimeout(() => {
                mainContent.classList.add('loaded');
            }, 100); // slight delay for smoother effect
        }
    });
</script>