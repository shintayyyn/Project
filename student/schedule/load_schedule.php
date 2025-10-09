<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$student_id = $_SESSION['user_id'];

// First get the latest update timestamp
$timestamp_query = "SELECT UNIX_TIMESTAMP(MAX(ss.updated_at)) as last_update,
                          MAX(ss.updated_at) as formatted_time
                   FROM sections_schedules ss
                   JOIN students_sections sts ON ss.section_id = sts.section_id
                   WHERE sts.s_id = ?";
$stmt = $conn->prepare($timestamp_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$timestamp_result = $stmt->get_result();
$timestamp_row = $timestamp_result->fetch_assoc();

$last_update = floatval($timestamp_row['last_update']);
if ($last_update <= 0) {
    $last_update = time();
}

// Add error logging
error_log("Load Schedule - Update Time: " . $last_update . ", Raw Time: " . $timestamp_row['formatted_time']);

// Get all schedules
$schedule_query = "SELECT 
    ss.subject_code,
    s.subject_description,
    CONCAT(t.t_fname, ' ', t.t_lname) as teacher_name,
    ss.day_of_week,
    TIME_FORMAT(ss.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(ss.end_time, '%h:%i %p') as end_time,
    r.room_number
FROM students_sections sts
JOIN sections_schedules ss ON sts.section_id = ss.section_id
JOIN subjects s ON ss.subject_code = s.subject_code
JOIN subjects_teachers st ON ss.subject_code = st.subject_code
JOIN teachers t ON st.t_id = t.t_id
LEFT JOIN rooms r ON ss.room_id = r.room_id
WHERE sts.s_id = ?
ORDER BY FIELD(ss.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
         ss.start_time ASC";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Organize schedules
$schedules = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => []
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
                            <p class="text-white-50 mb-0 small">Your current class schedule</p>
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
                                    foreach ($day_schedules as $schedule): 
                                ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="d-flex gap-3 align-items-center">
                                                <span class="badge <?php echo $day === $today ? 'bg-primary' : 'bg-secondary bg-opacity-10 text-secondary'; ?> px-3 py-2">
                                                    <?php echo $day; ?>
                                                </span>
                                                <div class="text-muted small">
                                                    <?php echo $schedule['start_time'] . ' - ' . $schedule['end_time']; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3">
                                            <div class="d-flex flex-column">
                                                <div class="fw-medium text-primary mb-1"><?php echo htmlspecialchars($schedule['subject_code']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($schedule['subject_description']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                                    <i class="bi bi-person text-primary small"></i>
                                                </div>
                                                <span class="text-body"><?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                                <?php echo $schedule['room_number'] ? htmlspecialchars($schedule['room_number']) : 'TBA'; ?>
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
                        <p class="text-muted small mb-0">You don't have any classes scheduled at the moment.</p>
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
        'formatted_time' => $timestamp_row['formatted_time']
    ]
]);