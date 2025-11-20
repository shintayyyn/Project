<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$student_id = $_SESSION['user_id'];

// 🧠 Get student status (regular or irregular)
$status_query = "SELECT is_regular FROM students WHERE s_id = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$status_result = $stmt->get_result()->fetch_assoc();
$is_regular = $status_result['is_regular'] ?? 1;

// 🧾 Get student's section info
$section_query = "
    SELECT sec.section_id, sec.section_code
    FROM students_sections ss
    JOIN sections sec ON sec.section_id = ss.section_id
    WHERE ss.s_id = ?
";
$stmt = $conn->prepare($section_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$section_info = $stmt->get_result()->fetch_assoc();
$section_id = $section_info['section_id'] ?? null;
$section_code = $section_info['section_code'] ?? null;

// 🎓 Get current active term info (with readable semester & A.Y.)
$term_query = "
    SELECT 
        at.term_id, 
        ay.year_start, 
        ay.year_end, 
        at.semester 
    FROM academic_terms at
    JOIN academic_years ay ON ay.ay_id = at.ay_id
    WHERE at.is_active = 1 
    LIMIT 1
";
$term_result = $conn->query($term_query)->fetch_assoc();
$term_id = $term_result['term_id'] ?? null;
$academic_year = isset($term_result['year_start'], $term_result['year_end'])
    ? "A.Y. {$term_result['year_start']}-{$term_result['year_end']}"
    : '';
$semester = $term_result['semester'] ?? '';

if (!$term_id) {
    echo json_encode(['error' => 'No active term found.']);
    exit;
}

// 🕒 Get latest update timestamp (for caching)
$timestamp_query = "
    SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS last_update, MAX(updated_at) AS formatted_time
    FROM (
        SELECT MAX(ss.updated_at) AS updated_at
        FROM sections_schedules ss
        JOIN students_sections sts ON ss.section_id = sts.section_id
        WHERE sts.s_id = ?
        AND ss.is_active = 1

        UNION ALL
        SELECT MAX(se.updated_at) AS updated_at
        FROM subject_enrollments se
        WHERE se.s_id = ?

        UNION ALL
        SELECT MAX(s.updated_at) AS updated_at
        FROM subjects s
    ) AS combined
";
$stmt = $conn->prepare($timestamp_query);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$timestamp_row = $stmt->get_result()->fetch_assoc();
$last_update = floatval($timestamp_row['last_update']);
if ($last_update <= 0) $last_update = time();

error_log("Load Schedule - Update Time: {$last_update} | Raw Time: {$timestamp_row['formatted_time']}");

// 🧩 Build the schedule query
if ($is_regular == 2) {
    // Irregular — subjects from enrollments + section link (same term)
    $schedule_query = "
        SELECT DISTINCT
            ss.subject_code,
            subj.subject_description,
            CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
            ss.day_of_week,
            TIME_FORMAT(ss.start_time, '%h:%i %p') AS start_time,
            TIME_FORMAT(ss.end_time, '%h:%i %p') AS end_time,
            r.room_number
        FROM sections_schedules ss
        JOIN subjects subj ON subj.subject_code = ss.subject_code
        JOIN subjects_teachers st ON st.subject_code = ss.subject_code AND st.term_id = ss.term_id
        JOIN teachers t ON t.t_id = st.t_id
        LEFT JOIN rooms r ON r.room_id = ss.room_id
        WHERE ss.term_id = ?
        AND ss.is_active = 1
        AND (
            ss.section_id = ? 
            OR ss.subject_code IN (SELECT subject_code FROM subject_enrollments WHERE s_id = ? AND term_id = ?)
        )
        GROUP BY ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time
       
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("iiii", $term_id, $section_id, $student_id, $term_id);
} else {
    // Regular — only from their section for active term
    $schedule_query = "
        SELECT DISTINCT
            ss.subject_code,
            subj.subject_description,
            CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
            ss.day_of_week,
            TIME_FORMAT(ss.start_time, '%h:%i %p') AS start_time,
            TIME_FORMAT(ss.end_time, '%h:%i %p') AS end_time,
            r.room_number
        FROM sections_schedules ss
        JOIN subjects subj ON subj.subject_code = ss.subject_code
        JOIN subjects_teachers st ON st.subject_code = ss.subject_code AND st.term_id = ss.term_id
        JOIN teachers t ON t.t_id = st.t_id
        LEFT JOIN rooms r ON r.room_id = ss.room_id
        WHERE ss.term_id = ?
        AND ss.section_id = ?
        AND ss.is_active = 1
        GROUP BY ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time
      
    ";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("ii", $term_id, $section_id);
}

$stmt->execute();
$result = $stmt->get_result();

// 🧩 Organize schedules per day
$schedules = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [],
    'Thursday' => [], 'Friday' => [], 'Saturday' => [],'Sunday' => []
];

while ($row = $result->fetch_assoc()) {
    $schedules[$row['day_of_week']][] = $row;
}

$today = date('l');

ob_start();
?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header position-relative p-0">
                <div class="schedule-header rounded-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title fw-semibold mb-2">Schedule Overview</h5>
                            <p class="text-white-50 mb-0 small">
                                <?= htmlspecialchars("$academic_year | $semester"); ?>
                            </p>
                        </div>
                        <div class="schedule-icon">
                            <i class="bi bi-calendar3-week fs-1 opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (array_sum(array_map('count', $schedules)) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="bg-light px-4 py-3" style="min-width: 200px">Day & Time</th>
                                    <th class="bg-light px-3 py-3" style="min-width: 250px">Subject</th>
                                    <th class="bg-light px-3 py-3" style="min-width: 200px">Teacher</th>
                                    <th class="bg-light px-3 py-3 text-center" style="min-width: 120px">Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $day => $day_schedules): 
                                    if (empty($day_schedules)) continue;
                                    foreach ($day_schedules as $schedule): ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="d-flex gap-3 align-items-center">
                                                <span class="badge <?= $day === $today ? 'bg-primary' : 'bg-secondary bg-opacity-10 text-secondary'; ?> px-3 py-2">
                                                    <?= $day; ?>
                                                </span>
                                                <div class="text-muted small">
                                                    <?= $schedule['start_time'] . ' - ' . $schedule['end_time']; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3">
                                            <div class="d-flex flex-column">
                                                <div class="fw-medium text-primary mb-1"><?= htmlspecialchars($schedule['subject_code']); ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($schedule['subject_description']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                                    <i class="bi bi-person text-primary small"></i>
                                                </div>
                                                <span class="text-body"><?= htmlspecialchars($schedule['teacher_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                                <?= $schedule['room_number'] ? htmlspecialchars($schedule['room_number']) : 'TBA'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <i class="bi bi-calendar-x text-secondary fs-4"></i>
                        </div>
                        <h6 class="text-secondary mb-2">No Classes Scheduled</h6>
                        <p class="text-muted small mb-0">You don't have any classes scheduled for the active term.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$html_content = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'html' => $html_content,
    'timestamp' => $last_update,
    'debug' => [
        'update_time' => $last_update,
        'formatted_time' => $timestamp_row['formatted_time'],
        'is_regular' => $is_regular,
        'section_id' => $section_id,
        'term_id' => $term_id,
        'academic_year' => $academic_year,
        'semester' => $semester
    ]
]);
