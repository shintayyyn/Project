<?php
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    die("No active term found.");
}

// ✅ Get teacher's subjects with sections, schedules, and student counts filtered by active term
$subjects_query = "
SELECT 
    s.subject_id,
    s.subject_code,
    s.subject_description,
    s.units,
    sec.section_code,
    sec.section_id,
    d.degree_code,
    d.degree_name,
    ss.ss_id,  
    ss.schedule_group_id,
    ss.start_time,
    ss.end_time,
    ss.day_of_week,
    ss.status,
    ss.description,
    ss.teacher_name,
    r.room_number,

    -- Regular students
    (
        SELECT COUNT(*) 
        FROM students_sections ss2
        INNER JOIN students st2 ON ss2.s_id = st2.s_id
        WHERE ss2.section_id = sec.section_id
          AND ss2.term_id = ?
          AND st2.is_regular = 1
    ) AS regular_count,

    -- Irregular students
    (
    SELECT COUNT(DISTINCT se.s_id)
    FROM subject_enrollments se
    INNER JOIN students st3 ON se.s_id = st3.s_id
    WHERE se.subject_id = s.subject_id
      AND se.term_id = ?
      AND se.enrollment_status = 'Enrolled'
      AND st3.is_regular = 2
      AND (se.section_code = sec.section_code OR se.section_code IS NULL)
) AS irregular_count


FROM sections_schedules ss
INNER JOIN subjects s ON s.subject_id = ss.subject_id
INNER JOIN sections sec ON ss.section_id = sec.section_id
INNER JOIN degrees d ON sec.degree_id = d.degree_id
LEFT JOIN rooms r ON ss.room_id = r.room_id
WHERE ss.teacher_id = ?
  AND ss.term_id = ?
ORDER BY s.subject_code, sec.section_code, ss.day_of_week, ss.start_time;
";


$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("iiii", $term_id, $term_id, $teacher_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();


// ✅ Build the nested array structure
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subject_code = $row['subject_code'];
    if (!isset($subjects[$subject_code])) {
        $subjects[$subject_code] = [
            'code' => $subject_code,
            'description' => $row['subject_description'],
            'units' => $row['units'],
            'sections' => []
        ];
    }

    $section_code = $row['section_code'];
    if (!isset($subjects[$subject_code]['sections'][$section_code])) {
        $regular = (int)$row['regular_count'];
        $irregular = (int)$row['irregular_count'];

        $subjects[$subject_code]['sections'][$section_code] = [
            'section_id' => $row['section_id'],
            'section_code' => $section_code,
            'degree_code' => $row['degree_code'],
            'degree_name' => $row['degree_name'],
            'regular_count' => $regular,
            'irregular_count' => $irregular,
            'student_count' => $regular + $irregular, // ✅ always computed
            'schedules' => []
        ];
    }

    // ✅ Prevent duplicate schedules per ss_id
    if (!isset($subjects[$subject_code]['sections'][$section_code]['schedules'][$row['ss_id']])) {
        $subjects[$subject_code]['sections'][$section_code]['schedules'][$row['ss_id']] = [
            'ss_id' => $row['ss_id'],
            'schedule_group_id' => $row['schedule_group_id'],
            'day' => $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'room' => $row['room_number'] ?? 'TBA',
            'status' => $row['status'],
            'description' => $row['description'],
            'teacher_name' => $row['teacher_name']
        ];
    }
}

// ✅ Calculate subject totals
foreach ($subjects as $code => &$subject) {
    $total_regular = 0;
    $total_irregular = 0;

    foreach ($subject['sections'] as $section) {
        $total_regular += $section['regular_count'];
        $total_irregular += $section['irregular_count'];
    }

    $subject['total_regular'] = $total_regular;
    $subject['total_irregular'] = $total_irregular;
    $subject['total_students'] = $total_regular + $total_irregular;
}
unset($subject);



?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../assets/css/attendance.css">
</head>
<body>
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

*{
            font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
        }
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

.card-header {
    background: var(--primary);
    color: var(--tertiary);
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0 !important;
    padding: 1rem 1.5rem;
}


.subject-card {
    height: 100%;
}

.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

}

.subject-stats {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(61, 82, 160, 0.05);
    border-radius: 0.5rem;
}

.stat-item {
    flex: 1;
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.section-list {
    margin-top: 1rem;
}

.section-item {
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.schedule-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    font-size: 0.875rem;
}

.schedule-item i {
    color: var(--primary);
}

/* Prevent horizontal scroll */
html, body {
    max-width: 100%;
    overflow-x: hidden;
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

/* Add padding to content */
h2.mb-4 {
    padding: 1rem;
}

.card-body {
    padding: 1.5rem;
}
</style>
<link rel="stylesheet" href="assets/css/content.css">
<main>
    <h2 class="mb-2 fw-bold">My Subjects</h2>
     <nav aria-label="breadcrumb">
                <ol class="breadcrumb ">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Subjects</li>
                </ol>
            </nav>
    <div class="container-fluid">
        <div class="row">
            <?php foreach ($subjects as $subject): ?>
            <div class="col-md-6 mb-4">
                <div class="card subject-card">
                    <div class="card-header">
                        <div class="subject-header">
                            <h5 class="mb-0 fw-bold">
                                <?php echo htmlspecialchars($subject['code']); ?>
                                <small class="d-block text-white-50">
                                    <?php echo htmlspecialchars($subject['description']); ?>
                                </small>
                            </h5>
                            <span class="badge bg-white text-primary">
                                <?php echo $subject['units']; ?> units
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="subject-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo count($subject['sections']); ?></div>
                                <div class="stat-label">Sections</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $subject['total_students']; ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                        </div>

                        <div class="section-list">
                            <?php foreach ($subject['sections'] as $section): ?>
                            <div class="section-item">
  <div class="section-header">
    <h6 class="mb-0"><?= $section['section_code'] ?>
        <small class="text-muted d-block"><?= $section['degree_name'] ?></small>
    </h6>
    <span class="badge bg-primary">
        <?= $section['student_count'] ?> students
    </span>
    <span class="badge bg-success ms-1">
        <?= $section['regular_count'] ?> Regular
    </span>
    <span class="badge bg-warning text-dark ms-1">
        <?= $section['irregular_count'] ?> Irregular
    </span>
</div>

    <ul class="schedule-list">
        <?php foreach ($section['schedules'] as $schedule): ?>
        <li class="schedule-item">
            <i class="bi bi-clock"></i>
            <?= $schedule['day'] ?> 
            <?= date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])) ?>
            <i class="bi bi-building ms-2"></i> <?= $schedule['room'] ?>
        </li>
        <?php endforeach; ?>
    </ul>

            <a href="subjects/log_attendance.php?subject_code=<?= urlencode($subject['code']) ?>&section_id=<?= $section['section_id'] ?>" 
            class="btn btn-sm btn-primary mt-2">
                <i class="bi bi-qr-code-scan me-1"></i>Log Attendance
            </a>
        </div>

                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($subjects)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted"></i>
                        <h4 class="mt-3">No Subjects Assigned</h4>
                        <p class="text-muted">You don't have any subjects assigned to you at the moment.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>

