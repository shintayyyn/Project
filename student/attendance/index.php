<?php
require_once '../includes/db.php';
require_once 'dashboard.php'; 

// Get student information
$student_id = $_SESSION['user_id'];
$student_query = "SELECT s.*, sec.section_code 
                 FROM students s 
                 LEFT JOIN students_sections ss ON s.s_id = ss.s_id 
                 LEFT JOIN sections sec ON ss.section_id = sec.section_id 
                 WHERE s.s_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();

// Get today's schedule
$today = date('l');
$schedule_query = "SELECT ss.*, sub.subject_code, sub.subject_description, 
                         CONCAT(t.t_fname, ' ', t.t_lname) as teacher_name, 
                         r.room_number
                  FROM sections_schedules ss
                  JOIN subjects sub ON ss.subject_code = sub.subject_code
                  JOIN rooms r ON ss.room_id = r.room_id
                  JOIN students_sections sts ON sts.section_id = ss.section_id
                  JOIN subjects_teachers st ON st.subject_code = ss.subject_code 
                  JOIN teachers t ON t.t_id = st.t_id
                  WHERE sts.s_id = ? AND ss.day_of_week = ?
                  ORDER BY ss.start_time";
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("is", $student_id, $today);
$stmt->execute();
$today_schedule = $stmt->get_result();

// Get all subjects for the student
$subjects_query = "SELECT DISTINCT s.subject_code, s.subject_description, 
                         CONCAT(t.t_fname, ' ', t.t_lname) as teacher_name,
                         s.units
                  FROM sections_schedules ss
                  JOIN subjects s ON ss.subject_code = s.subject_code
                  JOIN students_sections sts ON ss.section_id = sts.section_id
                  JOIN subjects_teachers st ON st.subject_code = ss.subject_code
                  JOIN teachers t ON t.t_id = st.t_id
                  WHERE sts.s_id = ?
                  ORDER BY s.subject_code";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects = $stmt->get_result();

// Get all subjects grouped with their schedules for the student
$subject_cards_query = "
SELECT DISTINCT s.subject_code, s.subject_description, s.units,
       CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
       ss.day_of_week, ss.start_time, ss.end_time,
       r.room_number, g.generated_qrcode
FROM students_sections sts
JOIN sections_schedules ss ON ss.section_id = sts.section_id
JOIN subjects s ON s.subject_code = ss.subject_code
JOIN subjects_teachers st ON s.subject_code = st.subject_code
JOIN teachers t ON t.t_id = st.t_id
JOIN rooms r ON ss.room_id = r.room_id
LEFT JOIN generatedqrcode g ON g.id = sts.s_id
WHERE sts.s_id = ?
ORDER BY s.subject_code,
         FIELD(ss.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
         ss.start_time
";


$stmt = $conn->prepare($subject_cards_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$subject_cards = [];

while ($row = $result->fetch_assoc()) {
    $subjectKey = $row['subject_code']; // You may include section code if needed: $row['subject_code'] . '_' . $row['section_code']
    $scheduleKey = $row['day_of_week'] . $row['start_time'] . $row['end_time'] . $row['room_number'];

    if (!isset($subject_cards[$subjectKey])) {
        $subject_cards[$subjectKey] = [
            'subject_code' => $row['subject_code'],
            'subject_description' => $row['subject_description'],
            'units' => $row['units'],
            'teacher_name' => $row['teacher_name'],
            'generated_qrcode' => $row['generated_qrcode'] ?? null,
            'schedules' => [],
            '_dedupe' => [] // internal key to track duplicates
        ];
    }

    // Only add unique schedule
    if (!in_array($scheduleKey, $subject_cards[$subjectKey]['_dedupe'])) {
        $subject_cards[$subjectKey]['schedules'][] = [
            'day' => $row['day_of_week'],
            'start' => $row['start_time'],
            'end' => $row['end_time'],
            'room' => $row['room_number']
        ];
        $subject_cards[$subjectKey]['_dedupe'][] = $scheduleKey;
    }
}

// Remove internal keys before rendering
foreach ($subject_cards as &$subject) {
    unset($subject['_dedupe']);
}
unset($subject); // clear reference

// -----------------------------
// Fetch last 10 attendance records
// -----------------------------
$attendance_history = [];
$attendance_query = "
SELECT a.s_id, a.subject_code, a.section_code, a.time_in, a.time_out, a.status
FROM attendance a
WHERE a.s_id = ?
ORDER BY a.time_in DESC
LIMIT 5
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendance_history[] = $row;
    }
}

$stmt->close();

?>

<style>
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
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

.card-title1 {
    font-weight: 600;
    display: flex;
    align-items: center;
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary);
    font-weight: 600;
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

.table td, .table th {
    padding: 1rem 1.25rem;
    vertical-align: middle;
    border-color: rgba(0, 0, 0, 0.05);
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

<main class="py-4">
    <div class="container-fluid px-4">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-6 fw-bold text-primary mb-0">
                    <i class="bi bi-qr-code me-2"></i>
                    Log Attendance</h1>
                <p class="text-muted">Scan your QR-CODE now for today's schedule.</p>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="row g-4 mb-4">
          
             <!-- Current Subjects -->
            <div class="row row-cols-1 row-cols-md-2 g-4 mb-4 row-cols-lg-12 h-100">
                <?php if (!empty($subject_cards)): ?>
                    <?php 
                    $modalIndex = 0;
                    foreach ($subject_cards as $subject): 
                        $modalIndex++;
                        $safeSubjectCode = preg_replace('/[^A-Za-z0-9_]/', '_', $subject['subject_code']);
                        $modalId = 'qrModal_' . $safeSubjectCode . '_' . $modalIndex;
                    ?>

                  <div class="col">
                    <div class="card shadow-sm h-100 d-flex flex-column">
                        <!-- Header -->
                        <div class="card-header py-3">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($subject['subject_code']); ?> - <?php echo htmlspecialchars($subject['subject_description']); ?>
                            </h5>
                        </div>

                    <!-- Body -->
                    <div class="card-body flex-grow-1">
                        <p class="mb-1"><strong>Units:</strong> <?php echo $subject['units']; ?></p>
                        <p class="mb-2"><strong>Teacher:</strong> <?php echo htmlspecialchars($subject['teacher_name']); ?></p>
                        <h6 class="text-muted">Schedules:</h6>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($subject['schedules'] as $sched): ?>
                                <li>
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo $sched['day']; ?>, 
                                    <?php echo date('h:i A', strtotime($sched['start'])) . " - " . date('h:i A', strtotime($sched['end'])); ?>
                                    @ Room <?php echo htmlspecialchars($sched['room']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                        <!-- Full-width clickable footer button -->
                        <button class="btn w-100 m-0 rounded-5 text-align-center" 
                                style="background-color: gold; color: #495057;" 
                                data-bs-toggle="modal" 
                                data-bs-target="#<?php echo $modalId; ?>"><h4>  <i class="bi bi-qr-code"></i> Log Attendance</h4>
                        </button>
                    </div>
                    </div>
                    <!-- QR Modal -->
                    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="<?php echo $modalId; ?>Label">QR Code - <?php echo htmlspecialchars($subject['subject_code']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                               <div class="modal-body text-center">
                                <?php $qrText = $subject['generated_qrcode'] ?? null; ?>
                            <?php if (!empty($qrText)): ?>
                                <img 
                                    src="https://quickchart.io/qr?text=<?= urlencode($qrText) ?>" 
                                    alt="QR Code" 
                                    style="width: 100%; max-width: 300px;"
                                >
                                <p class="mt-2 text-muted">Show this QR Code to scan your attendance.</p>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    QR Code not available. Please contact the registrar or your department.
                                </div>
                            <?php endif; ?>

                            </div>

                            </div>
                        </div>
                    </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-journal-x"></i> No subjects or schedules found for your account.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

      <!-- Attendance History -->
    <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history me-2"></i>Attendance History
            </h5>
           <a href="?page=attendance_report" class="btn btn-primary btn-sm">
            View All
        </a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($attendance_history)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_history as $att): ?>
                            <?php
                                // Determine badge color
                                $status = $att['status'];
                                $badgeClass = '';
                                if ($status === 'Present') {
                                    $badgeClass = 'bg-success';
                                } elseif ($status === 'Absent') {
                                    $badgeClass = 'bg-danger';
                                } else {
                                    $badgeClass = 'bg-secondary';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($att['s_id']) ?></td>
                                <td><?= htmlspecialchars($att['subject_code']) ?></td>
                                <td>
                                    <?= htmlspecialchars($att['time_in'] ? date('M d, Y', strtotime($att['time_in'])) : 'N/A') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-') ?>
                                    -
                                    <?= htmlspecialchars($att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-clock-history display-4 text-muted mb-3"></i>
                    <p class="mb-0">No attendance records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>


    </div>
</main>

