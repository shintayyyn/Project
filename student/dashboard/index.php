<?php
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$student_id = $_SESSION['user_id'];

// Get student information
$student_query = "SELECT s.*, sec.section_code 
                 FROM students s 
                 LEFT JOIN students_sections ss ON s.s_id = ss.s_id 
                 LEFT JOIN sections sec ON ss.section_id = sec.section_id 
                 WHERE s.s_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
$stmt->close();

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
$stmt->close();

// ---------------------------
// Calculate attendance overview for current month
// ---------------------------
$month_start = date('Y-m-01'); // first day of current month
$month_end   = date('Y-m-t');  // last day of current month

// 1. Total scheduled classes for this student in current month
$total_classes_query = "
    SELECT COUNT(*) AS total_classes
    FROM sections_schedules ss
    JOIN students_sections sts ON sts.section_id = ss.section_id
    WHERE sts.s_id = ?
      AND DATE_FORMAT(ss.start_time, '%Y-%m-%d') BETWEEN ? AND ?
";
$stmt = $conn->prepare($total_classes_query);
$stmt->bind_param("iss", $student_id, $month_start, $month_end);
$stmt->execute();
$total_classes = $stmt->get_result()->fetch_assoc()['total_classes'];
$stmt->close();

// 2. Total attended classes (Present or Late) in current month
$attended_query = "
    SELECT COUNT(*) AS attended
    FROM attendance
    WHERE s_id = ?
      AND status IN ('Present','Late')
      AND DATE(time_in) BETWEEN ? AND ?
";
$stmt = $conn->prepare($attended_query);
$stmt->bind_param("iss", $student_id, $month_start, $month_end);
$stmt->execute();
$attended = $stmt->get_result()->fetch_assoc()['attended'];
$stmt->close();

// 3. Attendance percentage for current month
$attendance_progress = $total_classes > 0 ? round(($attended / $total_classes) * 100) : 0;

// 4. For display purposes
$present_count = $attended;
$absent_count  = $total_classes - $attended;
?>



<main class="py-4">
    <div class="container-fluid px-4">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-6 fw-bold text-primary mb-0">Welcome back, <?php echo htmlspecialchars($student_info['s_fname']); ?>!</h1>
                <p class="text-muted">Here's your academic overview for today.</p>
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
                                <p class="text-muted mb-0">Student</p>
                            </div>
                            <div class="profile-stats">
                                <div class="row text-center">
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-person-badge-fill"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">ID Number</span>
                                                <span class="stat-value"><?php echo htmlspecialchars($student_info['s_id']); ?></span>
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
            <!-- Today's Schedule -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-day me-2"></i>Today's Schedule (<?php echo $today; ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($today_schedule->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Room</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($class = $today_schedule->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($class['subject_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($class['subject_description']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($class['teacher_name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['room_number']); ?></td>
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

            <!-- Current Subjects -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book me-2"></i>My Subjects
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($subjects)): ?>
                            <div class="subjects-list">
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="subject-item">
                                        <div class="subject-icon">
                                            <i class="bi bi-book"></i>
                                        </div>
                                        <div class="subject-info">
                                            <div class="subject-header">
                                                <h6 class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></h6>
                                                <span class="subject-units"><?php echo htmlspecialchars($subject['units']); ?> Units</span>
                                            </div>
                                            <p class="subject-name"><?php echo htmlspecialchars($subject['subject_description']); ?></p>
                                            <div class="subject-meta">
                                                <span class="teacher-info">
                                                    <i class="bi bi-person"></i>
                                                    <?php echo htmlspecialchars($subject['teacher_name']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x"></i>
                                <p class="text-muted mb-0">No subjects found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Attendance Status -->
            <div class="col-lg-12">
                 <!-- Attendance Progress -->
                        <div class="card shadow-lg mb-3">
                            <div class="card-header py-3">
                               <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart-line me-2"></i>Attendance Progress  (<?php echo date('F'); ?>)
                            </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <h4>You have attended <?php echo $attendance_progress; ?>% of your classes.</h4>
                                    </div>
                                   <div class="col-lg-6 d-flex justify-content-center">
                                    <a class="col-lg-6 d-flex justify-content-center btn btn-primary attendancebtn" href="?page=attendance" class="<?php echo $page === 'attendance' ? 'active' : ''; ?>">
                                    <h4 >
                                        <i class="bi bi-qr-code me-2"></i>Log Attendance
                                    </h4>
                                    </a>
                                </div>

                                </div>
                                <div class="progress m-5" style="height: 40px;">
                                    <div class="progress-bar " role="progressbar" style="width: <?php echo $attendance_progress; ?>%;" aria-valuenow="<?php echo $attendance_progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <h4 class=" m-5"><?php echo $attendance_progress; ?>%</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Attendance Overview -->
                        <div class="card shadow-sm">
                            <div class="card-header py-3">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clipboard-data me-2"></i>Attendance Overview (<?php echo date('F'); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="mb-2 text-success">
                                        <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                                    </div>
                                    <div class="fw-bold" style="font-size: 2rem;"><?php echo $present_count; ?></div>
                                    <div class="small text-muted" style="font-size: 1.1rem;">Present</div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-2 text-danger">
                                        <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                                    </div>
                                    <div class="fw-bold" style="font-size: 2rem;"><?php echo $absent_count; ?></div>
                                    <div class="small text-muted" style="font-size: 1.1rem;">Absent</div>
                                </div>
                            </div>
                        </div>
                        </div>
            </div>
        </div>
    </div>
</main>

<style>
.attendancebtn{
    border-radius: 20px;
}
.progress-bar{
    color:#495057;
    background:gold;
    font-size: 25px;
    border-radius: 20px;
}
.progress{
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