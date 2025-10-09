<?php
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dean_id = $_SESSION['t_id'] ?? null;
if (!$dean_id || $_SESSION['user_type'] !== 'dean') {
    // die("Unauthorized access");
}

// Get all degrees assigned to this dean
$degree_ids_query = $conn->prepare("SELECT degree_id FROM degrees WHERE dean_id = ?");
$degree_ids_query->bind_param("i", $dean_id);
$degree_ids_query->execute();
$degree_ids_result = $degree_ids_query->get_result();

$degree_ids = [];
while ($row = $degree_ids_result->fetch_assoc()) {
    $degree_ids[] = $row['degree_id'];
}
if (empty($degree_ids)) {
    die("No degree assigned to this dean.");
}
$degree_ids_str = implode(',', $degree_ids);

// === STATISTICS SECTION ===
$stats = [
    'students' => $conn->query("
        SELECT COUNT(*) as count 
        FROM students s
        JOIN students_degrees sd ON s.s_id = sd.s_id
        WHERE sd.degree_id IN ($degree_ids_str)
    ")->fetch_assoc()['count'],

    'teachers' => $conn->query("
        SELECT COUNT(*) as count
        FROM teachers
        WHERE t_department IN ($degree_ids_str)
    ")->fetch_assoc()['count'],

    'sections' => $conn->query("
        SELECT COUNT(*) as count
        FROM sections
        WHERE degree_id IN ($degree_ids_str)
    ")->fetch_assoc()['count'],

    'subjects' => $conn->query("
        SELECT COUNT(*) as count
        FROM subjects
        WHERE degree_id IN ($degree_ids_str)
    ")->fetch_assoc()['count']
];

// === RECENT ACTIVITIES WITH TIMESTAMP ===
// === RECENT ACTIVITIES SECTION ===
$recent_activities_query = "
    (SELECT 
        s.s_id AS id,
        CONCAT(s.s_fname, ' ', s.s_lname) AS name,
        LEFT(s.s_fname, 1) AS first_initial,
        LEFT(s.s_lname, 1) AS last_initial,
        'Student' AS type,
        'Added new student' AS action,
        'fa-user-graduate' AS icon,
        s.s_created_at AS activity_time
    FROM students s
    JOIN students_degrees sd ON s.s_id = sd.s_id
    WHERE sd.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        s.s_id AS id,
        CONCAT(s.s_fname, ' ', s.s_lname) AS name,
        LEFT(s.s_fname, 1) AS first_initial,
        LEFT(s.s_lname, 1) AS last_initial,
        'Student' AS type,
        'Updated student record' AS action,
        'fa-user-graduate' AS icon,
        s.s_updated_at AS activity_time
    FROM students s
    JOIN students_degrees sd ON s.s_id = sd.s_id
    WHERE sd.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        t.t_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Teacher' AS type,
        'Added new teacher' AS action,
        'fa-chalkboard-teacher' AS icon,
        t.t_created_at AS activity_time
    FROM teachers t
    WHERE t.t_department IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        t.t_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Teacher' AS type,
        'Updated teacher profile' AS action,
        'fa-chalkboard-teacher' AS icon,
        t.t_updated_at AS activity_time
    FROM teachers t
    WHERE t.t_department IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        sec.section_id AS id,
        sec.section_code AS name,
        LEFT(sec.section_code, 1) AS first_initial,
        '' AS last_initial,
        'Section' AS type,
        'Added new section' AS action,
        'fa-layer-group' AS icon,
        sec.created_at AS activity_time
    FROM sections sec
    WHERE sec.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        sec.section_id AS id,
        sec.section_code AS name,
        LEFT(sec.section_code, 1) AS first_initial,
        '' AS last_initial,
        'Section' AS type,
        'Updated section info' AS action,
        'fa-layer-group' AS icon,
        sec.updated_at AS activity_time
    FROM sections sec
    WHERE sec.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        subj.subject_id AS id,
        subj.subject_code AS name,
        LEFT(subj.subject_code, 1) AS first_initial,
        '' AS last_initial,
        'Subject' AS type,
        'Added new subject' AS action,
        'fa-book' AS icon,
        subj.created_at AS activity_time
    FROM subjects subj
    WHERE subj.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        subj.subject_id AS id,
        subj.subject_code AS name,
        LEFT(subj.subject_code, 1) AS first_initial,
        '' AS last_initial,
        'Subject' AS type,
        'Updated subject details' AS action,
        'fa-book' AS icon,
        subj.updated_at AS activity_time
    FROM subjects subj
    WHERE subj.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        ss.ss_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Schedule' AS type,
        CONCAT('Assigned new schedule for ', sec.section_code) AS action,
        'fa-calendar-plus' AS icon,
        ss.created_at AS activity_time
    FROM sections_schedules ss
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN sections sec ON ss.section_id = sec.section_id
    WHERE sec.degree_id IN ($degree_ids_str))

    UNION ALL

    (SELECT 
        ss.ss_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Schedule' AS type,
        CONCAT('Updated schedule for ', sec.section_code) AS action,
        'fa-calendar-alt' AS icon,
        ss.updated_at AS activity_time
    FROM sections_schedules ss
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN sections sec ON ss.section_id = sec.section_id
    WHERE sec.degree_id IN ($degree_ids_str))

    ORDER BY activity_time DESC
    LIMIT 30
";

$recent_activities = $conn->query($recent_activities_query);

// === SECTION FILL STATS ===
$section_stats_query = "
    SELECT 
        sec.section_code, 
        COUNT(ss.s_id) as student_count,
        sec.max_students,
        ROUND((COUNT(ss.s_id) / sec.max_students) * 100) as fill_percentage
    FROM sections sec
    LEFT JOIN students_sections ss ON sec.section_id = ss.section_id
    LEFT JOIN students_degrees sd ON ss.s_id = sd.s_id
    WHERE sec.degree_id IN ($degree_ids_str)
    GROUP BY sec.section_id
    ORDER BY fill_percentage DESC
";
$section_stats = $conn->query($section_stats_query);
$total_sections = $section_stats->num_rows;

// === SUBJECTS FOR THIS DEAN'S DEGREE ===
$subjects = $conn->query("
    SELECT * FROM subjects
    WHERE degree_id IN ($degree_ids_str)
");
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

/* Custom Scrollbar Styling */
.card-body::-webkit-scrollbar {
    width: 8px;
}

.card-body::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 4px;
}

.card-body::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 4px;
}

.card-body::-webkit-scrollbar-thumb:hover {
    background: #adb5bd;
}
.card-body,.list-group-item{
    color: #033A70;
}

/* For Firefox */
.card-body {
    overflow-x:hidden;
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 #f8f9fa;
}
</style>
<!-- Font Awesome CDN (version 5 or 6) -->
<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Welcome, dean!</h1>
        <p class="text-muted mb-0">Here's what's happening in your school today.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/Project/dean/reports/download_report.php" class="btn btn-primary">
            <span class="material-icons align-middle" style="font-size: 1rem;">download</span> Download Report
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="material-icons text-primary" style="font-size: 2.5rem;"><i class="fa fa-user-graduate"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-1">Total Students</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($stats['students']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="material-icons text-success" style="font-size: 2.5rem;"><i class="fa fa-chalkboard-teacher"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-1">Total Teachers</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($stats['teachers']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="material-icons text-info" style="font-size: 2.5rem;"> <span class="material-icons text-info" style="font-size: 2.5rem;"><i class="fa fa-layer-group"></i></span></span>
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-1">Total Sections</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($total_sections); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <span class="material-icons text-warning" style="font-size: 2.5rem;"><i class="fa fa-book"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-1">Total Subjects</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($stats['subjects']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Section Capacity -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Section Capacity</h5>
                        <p class="small mb-0 text-white">Current student distribution across sections</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php while ($section = $section_stats->fetch_assoc()): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($section['section_code']); ?></h6>
                                <small class="text-muted">
                                    <?php echo $section['student_count']; ?> of <?php echo $section['max_students']; ?> students
                                </small>
                            </div>
                            <div class="text-end">
                                <h6 class="mb-0"><?php echo $section['fill_percentage']; ?>%</h6>
                            </div>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar <?php 
                                echo $section['fill_percentage'] >= 90 ? 'bg-danger' : 
                                    ($section['fill_percentage'] >= 75 ? 'bg-warning' : 'bg-success'); 
                            ?>" style="width: <?php echo $section['fill_percentage']; ?>%"></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar-sm rounded-circle bg-primary bg-opacity-10">
                            <i class="fas fa-history text-primary"></i>
                        </div>
                    </div>
                    <h5 class="card-title mb-0">Recent Activities</h5>
                </div>
            </div>
            <div class="card-body">
    <div class="list-group list-group-flush">
        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
            <div class="list-group-item border-0 px-4 py-3">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded-circle bg-light d-flex align-items-center justify-content-center">
                            <span class="fw-bold text-<?php 
                                echo $activity['type'] === 'Student' ? 'primary' : 'success'; 
                            ?>"><?php echo $activity['first_initial'] . $activity['last_initial']; ?></span>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="d-flex align-items-center">
                            <h6 class="mb-0"><?php echo htmlspecialchars($activity['name']); ?></h6>
                            <div class="flex-shrink-0 ms-2">
                                <span class="badge bg-<?php 
                                    echo $activity['type'] === 'Student' ? 'primary' : 'success'; 
                                ?> bg-opacity-10 text-<?php 
                                    echo $activity['type'] === 'Student' ? 'primary' : 'success'; 
                                ?>">
                                    <i class="fas <?php echo $activity['icon']; ?> me-1"></i>
                                    <?php echo $activity['type']; ?>
                                </span>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">
                            <?php echo $activity['action']; ?>
                            <br>
                            <small>
                                <?= date('F j, Y \a\t g:i A', strtotime($activity['activity_time'])) ?>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

        </div>
    </div>
</div>

