<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';
date_default_timezone_set('Asia/Manila');

// ---------------------------
// Session check
// ---------------------------
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}
$parent_id = $_SESSION['parent_id'];

// ---------------------------
// Get children for parent
// ---------------------------
$children_query = "
    SELECT s.s_id, s.s_fname, s.s_lname, s.s_mname, s.s_suffix, s.is_regular,
         TRIM(
    CONCAT(
        s.s_fname,
        IF(s.s_mname IS NOT NULL AND s.s_mname != '', CONCAT(' ', LEFT(s.s_mname, 1), '.'), ''),
        ' ',
        s.s_lname,
        IF(s.s_suffix IS NOT NULL AND s.s_suffix != '', CONCAT(' ', s.s_suffix), '')
    )
) AS full_name

    FROM students s
    JOIN parent_student ps ON s.s_id = ps.s_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname ASC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result();
$stmt->close();

// ---------------------------
// Handle selected child
// ---------------------------
$selected_child_id = $_GET['child_id'] ?? null;

if (!$selected_child_id && $children->num_rows > 0) {
    $children->data_seek(0);
    $child = $children->fetch_assoc();
    $selected_child_id = $child['s_id'];
} else {
    $children->data_seek(0);
    while ($row = $children->fetch_assoc()) {
        if ($row['s_id'] == $selected_child_id) {
            $child = $row;
            break;
        }
    }
}
if (!$child) die("No child selected or found.");

// ---------------------------
// Current active term
// ---------------------------
$term_row = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_term_id = $term_row['term_id'] ?? null;

// ---------------------------
// Normalize day names
// ---------------------------
// $days = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat','Sunday'=>'Sun'];
$today = date('l');
$student_id = $selected_child_id;

// ---------------------------
// Fetch student type
// ---------------------------
$stmt = $conn->prepare("SELECT is_regular FROM students WHERE s_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$is_regular = $stmt->get_result()->fetch_assoc()['is_regular'] ?? 1;
$stmt->close();

// ---------------------------
// Fetch today's schedule
// ---------------------------
if ($is_regular == 1) {
    $schedule_query = "
        SELECT 
            ss.subject_code, sub.subject_id, sub.subject_description,
            COALESCE(ss.day_of_week,'TBA') AS day_of_week,
            COALESCE(ss.start_time,'TBA') AS start_time,
            COALESCE(ss.end_time,'TBA') AS end_time,
            COALESCE(r.room_number,'TBA') AS room_number,
            COALESCE(ss.status,'TBA') AS status,
            COALESCE(ss.description,'Ready for class!') AS description,
            ss.ss_id,
            COALESCE(CONCAT(t.t_fname,' ',
                IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),
                t.t_lname,
                IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
            ),'TBA') AS teacher_name
        FROM sections_schedules ss
        JOIN subjects sub ON ss.subject_code = sub.subject_code
        JOIN students_sections sts ON sts.section_id = ss.section_id
        LEFT JOIN teachers t ON t.t_id = ss.teacher_id
        LEFT JOIN rooms r ON r.room_id = ss.room_id
        WHERE sts.s_id = ? AND ss.term_id = ? AND ss.day_of_week = ?
        ORDER BY sub.subject_code, ss.start_time
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("iis", $student_id, $current_term_id, $today);
} else {
    // Irregular: Check enrollment then get schedule
    $schedule_query = "
        SELECT 
            e.subject_code, sub.subject_id, sub.subject_description,
            ss.day_of_week, ss.start_time, ss.end_time,
            r.room_number, ss.status, ss.description, ss.ss_id,
            CONCAT(t.t_fname,' ',IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1),'. '), ''),t.t_lname,IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')) AS teacher_name,
            e.section_code
        FROM subject_enrollments e
        JOIN subjects sub ON e.subject_code = sub.subject_code
        JOIN sections_schedules ss ON ss.subject_code = e.subject_code AND ss.section_code = e.section_code AND ss.term_id = ? AND ss.day_of_week = ?
        LEFT JOIN teachers t ON t.t_id = ss.teacher_id
        LEFT JOIN rooms r ON r.room_id = ss.room_id
        WHERE e.s_id = ? AND e.enrollment_status='Enrolled' AND e.term_id = ?
        ORDER BY sub.subject_code, ss.start_time
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("isii", $current_term_id, $today, $student_id, $current_term_id);
}

$stmt->execute();
$today_schedule = $stmt->get_result();
$stmt->close();

// ---------------------------
// Determine section / degree codes including year_level
// ---------------------------
$section_query = "
    SELECT 
        s.year_level,
        -- Regular student section
        (SELECT sec.section_code 
         FROM students_sections ss 
         JOIN sections sec ON ss.section_id = sec.section_id 
         WHERE ss.s_id = ? AND s.is_regular = 1 
         LIMIT 1) AS regular_section,
         
        -- Dominant section for irregular students (section with most subjects)
        (SELECT se.section_code
         FROM subject_enrollments se
         WHERE se.s_id = ? AND s.is_regular = 2 AND se.enrollment_status='Enrolled'
         GROUP BY se.section_code
         ORDER BY COUNT(*) DESC
         LIMIT 1) AS dominant_irregular_section,
         
        -- Degree code for irregular students
        (SELECT sd.degree_code 
         FROM students_degrees sd 
         WHERE sd.s_id = ? 
         LIMIT 1) AS irregular_degree
    FROM students s
    WHERE s.s_id = ?
";

$stmt = $conn->prepare($section_query);
$stmt->bind_param("iiii", $selected_child_id, $selected_child_id, $selected_child_id, $selected_child_id);
$stmt->execute();
$sec_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Assign correct code based on regular/irregular status
if ($child['is_regular'] == 1) {
    // Regular student: section only
    $child['section_code'] = $sec_row['regular_section'];
} else {
    // Irregular student: dominant section + degree + year_level
    $dominant_section = $sec_row['dominant_irregular_section'] ?? '-';
    $degree_code = $sec_row['irregular_degree'] ?? '-';
    $year_level = $sec_row['year_level'] ?? '-';
    
    $child['section_code'] = "{$degree_code}, Year {$year_level}";
}

// ---------------------------
// Attendance counts
// ---------------------------
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

function getAttendance($conn, $student_id, $term_id = null, $start = null, $end = null) {
    $sql = "SELECT 
                SUM(status='Present') AS present_count,
                SUM(status='Late') AS late_count,
                SUM(status='Absent') AS absent_count,
                SUM(status='Excuse') AS excuse_count
            FROM attendance
            WHERE s_id = ?";
    $types = "i";
    $params = [$student_id];

    if ($term_id !== null) {
        $sql .= " AND term_id = ?";
        $types .= "i";
        $params[] = $term_id;
    }
    if ($start && $end) {
        $sql .= " AND DATE(time_in) BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = $start;
        $params[] = $end;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'present' => intval($result['present_count']),
        'late'    => intval($result['late_count']),
        'absent'  => intval($result['absent_count']),
        'excused' => intval($result['excuse_count']),
    ];
}

$monthlyTotals  = getAttendance($conn, $selected_child_id, null, $month_start, $month_end);
$semesterTotals = getAttendance($conn, $selected_child_id, $current_term_id);

// ---------------------------
// Calculate total classes and weighted attendance for progress
// ---------------------------
$dates_in_month = [];
$period = new DatePeriod(new DateTime($month_start), new DateInterval('P1D'), (new DateTime($month_end))->modify('+1 day'));
foreach ($period as $d) $dates_in_month[] = ['Y-m-d' => $d->format('Y-m-d'), 'day' => $d->format('l')];

$total_classes = 0;
if ($child['is_regular'] == 1) {
    $sched_sql = "SELECT ss.day_of_week FROM sections_schedules ss JOIN students_sections sts ON sts.section_id = ss.section_id WHERE sts.s_id = ? AND ss.term_id = ?";
} else {
    $sched_sql = "SELECT ss.day_of_week FROM sections_schedules ss JOIN subject_enrollments se ON ss.subject_code = se.subject_code AND ss.section_code = se.section_code WHERE se.s_id = ? AND se.term_id = ? AND se.enrollment_status='Enrolled'";
}
$stmt = $conn->prepare($sched_sql);
$stmt->bind_param("ii", $selected_child_id, $current_term_id);
$stmt->execute();
$sched_res = $stmt->get_result();
$stmt->close();

while ($row = $sched_res->fetch_assoc()) {
    foreach ($dates_in_month as $d) {
        if ($d['day'] === $row['day_of_week']) $total_classes++;
    }
}

// Weighted attendance
$attended_sql = "SELECT status FROM attendance WHERE s_id = ? AND term_id = ? AND DATE(time_in) BETWEEN ? AND ? AND status IN ('Present','Late','Excuse')";
$stmt = $conn->prepare($attended_sql);
$stmt->bind_param("iiss", $selected_child_id, $current_term_id, $month_start, $month_end);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$attended = 0;
while ($row = $result->fetch_assoc()) $attended += ($row['status'] === 'Present') ? 1 : 0.5;

$attendance_progress = ($total_classes > 0) ? round(($attended / $total_classes) * 100, 2) : 0.00;
$present_count = $attended;
$absent_count  = max(0, $total_classes - $attended);
?>



<link rel="stylesheet" href="assets/css/schedule.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<style>
    :root {
            --primary: #033A70;
            --secondary: #033A70;
            --tertiary: #FFCB05;
            --quaternary: #D0EEFC;
            --background: #EDF8FD;
            --minimal: #3E7DCA;
            --sidebar-width: 250px;
            --card-border-radius: 0.75rem;
            --transition-speed: 0.3s;
        }
        
</style>
<main class="py-4">
     <?php include(__DIR__ . '/../../includes/parent_header.php'); ?>
    <div class="container-fluid px-4">
        <?php if ($child): ?>
            <div class="row mb-4">
                <div class="col-12">
    <!-- Child selection form -->
    <form method="get" action="dashboard.php" class="mb-3">
        <input type="hidden" name="page" value="schedule">
        <label class="form-label fw-bold">Select Child:</label>

        <div class="dropdown w-auto">
            <?php
            // Pre-fetch section codes for all children to avoid missing "-"
            $children->data_seek(0);
            $child_sections = [];
            while ($c = $children->fetch_assoc()) {
                if ($c['is_regular'] == 1) {
                    // Regular student: get section
                    $stmt = $conn->prepare("
                        SELECT sec.section_code
                        FROM students_sections ss
                        JOIN sections sec ON ss.section_id = sec.section_id
                        WHERE ss.s_id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param("i", $c['s_id']);
                    $stmt->execute();
                    $sec_row = $stmt->get_result()->fetch_assoc();
                    $section_code = $sec_row['section_code'] ?? '-';
                    $stmt->close();
                } else {
                    // Irregular student: dominant section + degree + year level
                    $stmt = $conn->prepare("
                        SELECT se.section_code, sd.degree_code, s.year_level
                        FROM subject_enrollments se
                        LEFT JOIN students_degrees sd ON se.s_id = sd.s_id
                        JOIN students s ON s.s_id = se.s_id
                        WHERE se.s_id = ? AND se.enrollment_status='Enrolled'
                        GROUP BY se.section_code
                        ORDER BY COUNT(*) DESC
                        LIMIT 1
                    ");
                    $stmt->bind_param("i", $c['s_id']);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $dominant_section = $row['section_code'] ?? '-';
                    $degree_code = $row['degree_code'] ?? '-';
                    $year_level = $row['year_level'] ?? '-';
                    $section_code = ($degree_code != '-' ? $degree_code . ', ' : '') . "Year {$year_level}";
                    $stmt->close();
                }
                $child_sections[$c['s_id']] = $section_code;

                // Determine selected child display name
                if ($c['s_id'] == $selected_child_id) {
                    $selected_child_name = htmlspecialchars($c['full_name']) . " (" . $section_code . ")";
                }
            }
            ?>
            
            <button class="btn dropdown-toggle w-100 fw-bold" type="button" id="childDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:var(--primary); color:var(--tertiary);">
                <?= $selected_child_name ?>
            </button>

            <ul class="dropdown-menu custom-scrollbar" aria-labelledby="childDropdown">
                <!-- Search input inside dropdown -->
                <li class="px-3 py-2">
                    <input type="text" id="childSearch" class="form-control form-control-sm" placeholder="Search child...">
                </li>
                <li><hr class="dropdown-divider"></li>

                <?php
                $children->data_seek(0);
                while ($c = $children->fetch_assoc()):
                    $child_label = htmlspecialchars($c['full_name']) . " (" . ($child_sections[$c['s_id']] ?? '-') . ")";
                ?>
                    <li class="dropdown-item-wrapper">
                        <a class="dropdown-item <?= ($c['s_id'] == $selected_child_id) ? 'active' : '' ?>"
                           href="dashboard.php?page=schedule&child_id=<?= urlencode($c['s_id']) ?>">
                            <?= $child_label ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </form>

    <!-- Profile card -->
    <div class="card profile-card">
        <div class="card-body p-0">
            <div class="profile-header">
                <div class="profile-cover"></div>
                <div class="profile-avatar-wrapper">
                    <div class="profile-image" style="background:var(--tertiary); color:var(--primary)">
                        <?php
                        $fname = $child['s_fname'];
                        $initials = strtoupper(substr($fname, 0, 2));
                        echo htmlspecialchars($initials);
                        ?>
                    </div>
                </div>
            </div>
            <div class="profile-content">
                <div class="text-center mb-3">
                    <h4 class="mb-1"><?php echo htmlspecialchars($child['full_name']); ?></h4>
                    <p class="text-muted mb-0">Section: <?php echo htmlspecialchars($child_sections[$child['s_id']] ?? '-'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

            </div>

            <!-- Today's Schedule, Absence Filing, Attendance -->
        <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-3">
                            <div class="row">
                                <div class="col">
                                <h5 class="card-title head mb-0 d-flex justify-content-between align-items-center" style="color: var(--primary) !important">
                                <div class="text-white">
                                    <i class="bi bi-calendar-day me-2"></i>
                                    <?= htmlspecialchars($child['full_name']); ?>'s Schedule (<?= $today; ?>)
                                </div>
                            </h5>
                                </div>
                                <div class="col text-end">
                                     <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#studyLoadModal<?= $child['s_id']; ?>">
                               <i class="bi bi-book me-1"></i> View Study Load
                                </button>
                        </div>
                                </div>
                            </div>
                  <div class="card-body p-0">
    <?php if ($today_schedule->num_rows > 0): ?>
        <div class="table-responsive p-1">
            <table id="childScheduleTable<?= $child['s_id']; ?>" class="table table-hover mb-0 table-striped">
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

                        $currentStatus = !empty($class['status']) ? trim($class['status']) : '';
                        $statusDescription = !empty($class['description']) ? trim($class['description']) : '';

                        // Attachments
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
                            $latestUpdate = strtotime($fileList[0]['updated_at']);
                        }

                        $oneWeekAgo = strtotime('-1 week');
                        if (empty($currentStatus) || ($latestUpdate && $latestUpdate < $oneWeekAgo)) {
                            $currentStatus = 'Available';
                            $statusDescription = 'Ready for class';
                        }

                        $cleanStatus = strtolower($currentStatus);
                        $statusClass = 'bg-secondary';
                        if (strpos($cleanStatus, 'not available') !== false) $statusClass = 'bg-danger text-white';
                        elseif (strpos($cleanStatus, 'async') !== false) $statusClass = 'bg-primary text-white';
                        elseif (strpos($cleanStatus, 'available') !== false) $statusClass = 'bg-success text-white';
                    ?>
                    <tr>
                        <td>
                            <?= date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?>
                        </td>
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
                            <?php if (!empty($fileList) && $latestUpdate >= $oneWeekAgo): ?>
                                <?php foreach ($fileList as $file): 
                                    $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                    $filePath = "../uploads/remarks/" . $file['file_name'];
                                ?>
                                    <button class="btn btn-sm btn-primary view-attachment"
                                        data-url="<?= $filePath ?>"
                                        data-type="<?= htmlspecialchars($ext) ?>"
                                        data-name="<?= htmlspecialchars($file['file_name']) ?>">
                                        <i class="bi bi-paperclip"></i> View
                                    </button><br>
                                <?php endforeach; ?>
                            <?php else: ?>
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

                <!-- Absence + Attendance -->
                <div class="col-lg-4">
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header py-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clipboard-x me-2"></i>Absence Filing
                            </h5>
                        </div>
                        <div class="card-body text-center">
                                <p class="mb-3">Feel free to file an absence form for your child.</p>
                              <a href="dashboard.php?page=absentform&child_id=<?= urlencode($child['s_id']); ?>" 
                                class="btn rounded-pill px-4 fw-bold" style="background:var(--tertiary); color:var(--primary)">
                                Report Now
                                </a>

                        </div>
                    </div>
                   <?php $current_month = date('F Y'); // e.g., September 2025 ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header py-3" style="background: var(--tertiary);">
                        <h5 class="card-title mb-0" style="color:var(--primary) !important">
                            <i class="bi bi-bar-chart-line me-2" style="color:var(--primary) !important"></i>
                            Attendance Progress (<?php echo $current_month; ?>)
                        </h5>
                    </div>
                    <div class="card-body" style="background:var(--quaternary)">
                        <div class="progress mb-2">
                        <?php $attendance_progress = isset($attendance_progress) ? $attendance_progress : 0; ?>
                            <div class="progress-bar fw-bold" role="progressbar"
                                style="width: <?= $attendance_progress ?>%; min-width: 35px; background:var(--primary); color:var(--background)" 
                                aria-valuenow="<?= $attendance_progress ?>"
                                aria-valuemin="0" aria-valuemax="100">
                                <?= $attendance_progress ?>%
                            </div>
                        </div>
                    </div>
                </div>

   <div class="card shadow-sm mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-clipboard-data me-2"></i>
            Attendance Overview
        </h5>
        <span class="badge bg-secondary">
            <?php echo date('F Y'); ?>
        </span>
    </div>
    <div class="card-body d-flex flex-column align-items-center">
        <!-- Pie Chart -->
        <div class="chart-card w-100 d-flex justify-content-center mb-3" style="max-width: 500px;">
            <canvas id="pieChart"></canvas>
        </div>
    </div>
</div>

                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No children found for your account.</div>
        <?php endif; ?>
    </div>
</main>

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
<div class="modal fade" id="studyLoadModal<?= $child['s_id']; ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-book me-2"></i>
          <?= htmlspecialchars($child['full_name']); ?>'s Study Load
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="studyLoadContainer<?= $child['s_id']; ?>" class="text-center py-4">
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
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-paperclip"></i> Attachment Viewer</h5>
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



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
$(document).ready(function() {
 $('#childScheduleTable<?php echo $child['s_id']; ?>').DataTable({
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
                    responsivePriority: -1,
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
    const mainContent = document.querySelector('main');
    if (mainContent) {
        setTimeout(() => {
            mainContent.classList.add('loaded');
        }, 100); // slight delay for smoother effect
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="studyLoadModal"]').forEach(modal => {
        modal.addEventListener('show.bs.modal', function () {
            const modalId = this.id;
            const childId = modalId.replace('studyLoadModal', '');
            const container = document.getElementById('studyLoadContainer' + childId);

            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;

            fetch('schedule/fetch_studyload.php?child_id=' + childId)
                .then(response => response.text())
                .then(data => container.innerHTML = data)
                .catch(err => container.innerHTML = `<div class="alert alert-danger">Failed to load study load.</div>`);
        });
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
            backgroundColor: isAllZero ? ['#E0E0E0'] : ['#28a745', '#ffc107', '#dc3545', '#6fb2f7'],
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
                enabled: !isAllZero,
                backgroundColor: '#fff',
                titleColor: '#000',
                bodyColor: '#000',
                borderColor: '#ddd',
                borderWidth: 1
            },
            // Optional "No Data" overlay
            beforeDraw: (chart) => {
                if (isAllZero) {
                    const { width, height, ctx } = chart;
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
});
</script>