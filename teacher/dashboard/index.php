<?php
// Get teacher's information
$t_id = $_SESSION['user_id'];
$teacher_query = "SELECT * FROM teachers WHERE t_id = ?";
$stmt = $conn->prepare($teacher_query);
$stmt->bind_param("i", $t_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// ✅ Get active term_id
$term_query = "SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1";
$term_result = $conn->query($term_query);
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    die("No active term found.");
}

// Get total subjects for active term
$subjects_query = "
    SELECT COUNT(DISTINCT st.subject_id) as total_subjects
    FROM subjects_teachers st
    WHERE st.t_id = ? 
      AND st.term_id = ?
";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("ii", $t_id, $term_id);
$stmt->execute();
$subjects_result = $stmt->get_result()->fetch_assoc();
$total_subjects = $subjects_result['total_subjects'];

// Get total sections (from schedules) for active term
$sections_query = "
    SELECT COUNT(DISTINCT section_id) as total_sections
    FROM sections_schedules
    WHERE teacher_id = ?
      AND term_id = ?
      AND is_active = 1
";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("ii", $t_id, $term_id);
$stmt->execute();
$sections_result = $stmt->get_result()->fetch_assoc();
$total_sections = $sections_result['total_sections'];

// Get advisory class (section_code) for active term
$advisor_query = "
    SELECT section_code
    FROM sections_advisors
    WHERE t_id = ?
      AND term_id = ?
";
$stmt = $conn->prepare($advisor_query);
$stmt->bind_param("ii", $t_id, $term_id);
$stmt->execute();
$advisor_result = $stmt->get_result();
$advisory_sections = [];
while ($row = $advisor_result->fetch_assoc()) {
    $advisory_sections[] = $row['section_code'];
}

// Get today's schedule for active term
$today = strtoupper(date('l')); // Convert to uppercase day name

$schedule_query = "
    SELECT 
        ss.ss_id,                  -- schedule row ID
        ss.subject_id,             -- subject ID from sections_schedules
        ss.subject_code,           -- subject code from sections_schedules
        s.subject_description,     -- description from subjects table
        sec.section_code, 
        ss.start_time, 
        ss.end_time, 
        r.room_number AS room,
        ss.status, 
        ss.description,
        ss.updated_at,
        ss.term_id
    FROM sections_schedules ss
    LEFT JOIN subjects s 
           ON ss.subject_id = s.subject_id
    JOIN sections sec 
         ON ss.section_id = sec.section_id
    LEFT JOIN rooms r 
          ON ss.room_id = r.room_id
    WHERE ss.teacher_id = ? 
      AND ss.day_of_week = ?
      AND ss.term_id = ?
      AND ss.is_active = 1
    ORDER BY ss.start_time
";


$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("isi", $t_id, $today, $term_id); // bind term_id as integer
$stmt->execute();
$schedule_result = $stmt->get_result();
?>


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

/* Main content area */
main {
    margin-left: 5px;
    width: calc(100% - 260px);
    padding: 0;
    overflow-x: hidden;
}

/* Container adjustments */
.container-fluid {
    width: 100%;
    padding: 0;
    margin: 0;
    overflow-x: hidden;
}

/* Card Styling */
.card {
    border-radius: var(--card-border-radius);
    box-shadow: 0 4px 6px rgba(61, 82, 160, 0.07);
    border: none;
    transition: transform var(--transition-speed);
    margin-bottom: 1.5rem;
}

.card:hover {
    transform: translateY(-2px);
}

.card-body {
    padding: 1.5rem;
}

/* Stats cards */
.stats-card {
    height: 100%;
    min-height: 140px;
}

.stats-card .card-body {
    display: flex;
    align-items: center;
    flex-direction: column;
    justify-content: center;
}

.stats-card .stats-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-card .stats-icon i {
    font-size: 2rem;
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.stats-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 1rem;
    text-align: center;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: var(--bs-body-color);
    padding: 1rem;
    transition: transform var(--transition-speed);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    color: var(--primary);
}

.quick-action-btn i {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    margin-bottom: 0.75rem;
    transition: all var(--transition-speed);
}

.quick-action-btn:nth-child(1) i {
    background-color: rgba(112, 145, 230, 0.15);
    color: var(--secondary);
}

.quick-action-btn:nth-child(2) i {
    background-color: rgba(25, 135, 84, 0.15);
    color: #198754;
}

.quick-action-btn:nth-child(3) i {
    background-color: rgba(61, 82, 160, 0.15);
    color: var(--primary);
}

.quick-action-btn:nth-child(4) i {
    background-color: rgba(255, 193, 7, 0.15);
    color: #ffc107;
}

.quick-action-btn:nth-child(5) i {
    background-color: rgba(220, 53, 69, 0.15);
    color: #dc3545;
}

.quick-action-btn span {
    font-size: 0.875rem;
    text-align: center;
}

/* Schedule Card */
.schedule-card {
    height: 100%;
}

.schedule-card .card-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.schedule-card .card-header i {
    color: rgba(255, 255, 255, 0.9);
}

.schedule-list {
    padding: 0;
    margin: 0;
}

.schedule-item {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: 1.5rem;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: all var(--transition-speed);
}

.schedule-item:last-child {
    border-bottom: none;
}

.schedule-item:hover {
    background: rgba(61, 82, 160, 0.03);
}

.schedule-time {
    display: flex;
    align-items: center;
    color: var(--primary);
    font-weight: 500;
    font-size: 0.95rem;
    white-space: nowrap;
    justify-content: flex-start;
    min-width: 140px;
}

.schedule-time i {
    font-size: 1rem;
    margin-right: 0.5rem;
    opacity: 0.8;
}

.schedule-subject {
    display: flex;
    flex-direction: column;
    padding: 0 1rem;
}

.schedule-subject h6 {
    color: var(--bs-body-color);
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.schedule-subject small {
    color: #6c757d;
    font-size: 0.85rem;
}

.schedule-room {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size:1.20rem;
    background: rgba(108, 117, 125, 0.1);
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    white-space: nowrap;
    justify-content: center;
}

.schedule-room i {
    font-size: 0.9rem;
    opacity: 0.8;
}

.no-schedule {
    padding: 3rem 1.5rem;
    text-align: center;
    color: #6c757d;
}

.no-schedule i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-schedule p {
    font-size: 1rem;
    margin: 0;
}

@media (max-width: 768px) {
    .schedule-item {
        grid-template-columns: 1fr;
        gap: 0.75rem;
        padding: 1rem;
    }

    .schedule-time {
        font-size: 0.9rem;
        justify-content: flex-start;
    }

    .schedule-subject {
        padding: 0;
    }

    .schedule-room {
        justify-content: flex-start;
        padding: 0.4rem 0.6rem;
        font-size: 0.85rem;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    main {
        margin-left: 0;
        width: 100%;
    }
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
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary);
}

.stat-value.advisory_sections {
    font-size: 16px;
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
</style>

<main>
    <div class="container-fluid">
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-6 fw-bold" style="color:var(--primary);">Welcome back, <?php echo htmlspecialchars($teacher['t_fname']); ?>!</h1>
                <p class="text-muted">Here's what's happening with your classes today.</p>
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
                                <div class="profile-image" style="background:var(--tertiary); color:var(--primary)">
                                    <?php 
                                    $fname = $teacher['t_fname'];
                                    $lname = $teacher['t_lname'];
                                    $initials = strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
                                    echo htmlspecialchars($initials);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="profile-content">
                            <div class="text-center mb-3">
                                <h4 class="mb-1"><?php echo htmlspecialchars($teacher['t_fname'] . ' ' . $teacher['t_lname']); ?></h4>
                                <p class="text-muted mb-0">Teacher</p>
                            </div>
                            <div class="profile-stats">
                                <div class="row text-center">
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-person-badge-fill"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">ID Number</span>
                                                <span class="stat-value"><?php echo htmlspecialchars($teacher['idcode']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-book"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">Subjects</span>
                                                <span class="stat-value"><?php echo $total_subjects; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="stat-item">
                                            <i class="bi bi-people"></i>
                                            <div class="stat-text">
                                                <span class="stat-label">Sections</span>
                                                <span class="stat-value"><?php echo $total_sections; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                     <div class="col">
                                    <div class="stat-item">
                                        <i class="bi bi-people"></i>
                                        <div class="stat-text">
                                            <span class="stat-label">Advisory Class</span>
                                            <span class="stat-value advisory_sections text-center">
                                                <?php 
                                                if (!empty($advisory_sections)) {
                                                    echo implode(", ", $advisory_sections); // ex: BSIT-1A, BSIT-2B
                                                } else {
                                                    echo "None";
                                                }
                                                ?>
                                            </span>
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

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">

            <!-- Quick Actions -->
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-4 text-muted text-center">Quick Actions</h6>
                        <div class="quick-actions">
                            <a href="?page=schedule" class="quick-action-btn">
                                <i class="bi bi-calendar3"></i>
                                <span>Schedule</span>
                            </a>
                            <a href="?page=subjects" class="quick-action-btn">
                                <i class="bi bi-book"></i>
                                <span>Subjects</span>
                            </a>
                            <a href="?page=sections" class="quick-action-btn">
                                <i class="bi bi-people"></i>
                                <span>Sections</span>
                            </a>
                            <a href="?page=profile" class="quick-action-btn">
                                <i class="bi bi-person"></i>
                                <span>Profile</span>
                            </a>
                            <a href="../../../logout.php" class="quick-action-btn">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

     <!-- Today's Schedule -->
<div class="row">
    <div class="col-12">
        <div class="card schedule-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Today's Schedule (<?= date('l, F j, Y'); ?>)
                </h5>
            </div>
        <div class="card-body p-0">
    <?php if ($schedule_result->num_rows > 0): ?>
        <div class="schedule-list">
            <?php while ($schedule = $schedule_result->fetch_assoc()): ?>
                <?php
                    // Default values
                    $status = trim($schedule['status'] ?? '');
                    $description = trim($schedule['description'] ?? '');
                    $updated_at = $schedule['updated_at'] ?? null;

                    // Today's date
                    $today = date('Y-m-d');
                    $updated_date = $updated_at ? date('Y-m-d', strtotime($updated_at)) : null;

                    // ✅ If not updated today OR values are empty → fallback
                    if ($updated_date !== $today || ($status === '' && $description === '')) {
                        $status = 'Available';
                        $description = 'Ready for class';
                    }

                    // Status badge colors
                    $badge_class = match (strtolower($status)) {
                        'available' => 'bg-success',
                        'asynchronous' => 'bg-info text-dark',
                        'not available' => 'bg-danger',
                        default => 'bg-success',
                    };
                ?>
                <div class="card-body border-bottom">
                    <div class="row">
                        <!-- Time -->
                        <div class="col mb-2">
                            <div class="schedule-time">
                                <i class="bi bi-clock me-2"></i>
                                <?= !empty($schedule['start_time']) ? date('h:i A', strtotime($schedule['start_time'])) : '' ?>
                                -
                                <?= !empty($schedule['end_time']) ? date('h:i A', strtotime($schedule['end_time'])) : '' ?>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="col mb-2 schedule-subject">
                            <h6 class="mb-0"><?= htmlspecialchars($schedule['subject_code'] ?? '') ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($schedule['subject_description'] ?? '') ?></small>
                        </div>

                        <!-- Room -->
                        <div class="col mb-2 schedule-room">
                            <span class="badge text-dark px-3 py-2">
                                <i class="bi bi-building me-1"></i>
                                <?= htmlspecialchars($schedule['room'] ?? 'TBA') ?>
                            </span>
                        </div>

                        <!-- Status + Description -->
                        <div class="col mb-2 schedule-status">
                            <?php if ($status !== ''): ?>
                                <span class="badge <?= $badge_class ?> mb-1">
                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($description !== ''): ?>
                                <div class="text-muted fst-italic small">
                                    <?= htmlspecialchars($description) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-schedule text-center p-4">
            <i class="bi bi-calendar-x display-5 d-block text-muted"></i>
            <p class="text-muted mt-2">No classes scheduled for today</p>
        </div>
    <?php endif; ?>
</div>





        </div>
    </div>
</div>


                
            </div>
        </div>
    </div>
</main>