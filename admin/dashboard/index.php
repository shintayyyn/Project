<?php
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

// =======================
// Get current active term
// =======================
$current_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$current_term_id = $current_term['term_id'] ?? null;

// =======================
// Fetch statistics
// =======================
$stats = [
    'students' => $conn->query("
        SELECT COUNT(*) as count 
        FROM students s
    ")->fetch_assoc()['count'],

    'teachers' => $conn->query("
        SELECT COUNT(DISTINCT t.t_id) as count 
        FROM teachers t
    ")->fetch_assoc()['count'],

    'sections' => $conn->query("
        SELECT COUNT(DISTINCT s.section_id) as count 
        FROM sections s
    ")->fetch_assoc()['count'],

    'subjects' => $conn->query("
        SELECT COUNT(DISTINCT subj.subject_id) as count 
        FROM subjects subj
    ")->fetch_assoc()['count']
];

$recent_activities_query = "
    (SELECT 
        s.s_id AS id,
        CONCAT(s.s_fname, ' ', s.s_lname) AS name,
        LEFT(s.s_fname, 1) AS first_initial,
        LEFT(s.s_lname, 1) AS last_initial,
        'Student' AS type,
        'Added new student' AS action,
        'fa-user-graduate' AS icon,
        s.s_created_at AS activity_time,
        sd.term_id
    FROM students s
    LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
    )

    UNION ALL

    (SELECT 
        s.s_id AS id,
        CONCAT(s.s_fname, ' ', s.s_lname) AS name,
        LEFT(s.s_fname, 1) AS first_initial,
        LEFT(s.s_lname, 1) AS last_initial,
        'Student' AS type,
        'Updated student record' AS action,
        'fa-user-graduate' AS icon,
        s.s_updated_at AS activity_time,
        sd.term_id
    FROM students s
    LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
    )

    UNION ALL

    (SELECT 
        t.t_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Teacher' AS type,
        'Added new teacher' AS action,
        'fa-chalkboard-teacher' AS icon,
        t.t_created_at AS activity_time,
        ss.term_id
    FROM teachers t
    LEFT JOIN sections_schedules ss ON t.t_id = ss.teacher_id
    )

    UNION ALL

    (SELECT 
        t.t_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Teacher' AS type,
        'Updated teacher profile' AS action,
        'fa-chalkboard-teacher' AS icon,
        t.t_updated_at AS activity_time,
        ss.term_id
    FROM teachers t
    LEFT JOIN sections_schedules ss ON t.t_id = ss.teacher_id
    )

    UNION ALL

    (SELECT 
        sec.section_id AS id,
        sec.section_code AS name,
        LEFT(sec.section_code, 1) AS first_initial,
        '' AS last_initial,
        'Section' AS type,
        'Added new section' AS action,
        'fa-layer-group' AS icon,
        sec.created_at AS activity_time,
        sec.term_id
    FROM sections sec
    )

    UNION ALL

    (SELECT 
        sec.section_id AS id,
        sec.section_code AS name,
        LEFT(sec.section_code, 1) AS first_initial,
        '' AS last_initial,
        'Section' AS type,
        'Updated section info' AS action,
        'fa-layer-group' AS icon,
        sec.updated_at AS activity_time,
        sec.term_id
    FROM sections sec
    )

    UNION ALL

    (SELECT 
        subj.subject_id AS id,
        subj.subject_code AS name,
        LEFT(subj.subject_code, 1) AS first_initial,
        '' AS last_initial,
        'Subject' AS type,
        'Added new subject' AS action,
        'fa-book' AS icon,
        subj.created_at AS activity_time,
        subj.term_id
    FROM subjects subj
    )

    UNION ALL

    (SELECT 
        subj.subject_id AS id,
        subj.subject_code AS name,
        LEFT(subj.subject_code, 1) AS first_initial,
        '' AS last_initial,
        'Subject' AS type,
        'Updated subject details' AS action,
        'fa-book' AS icon,
        subj.updated_at AS activity_time,
        subj.term_id
    FROM subjects subj
    )

    UNION ALL

    (SELECT 
        ss.ss_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Schedule' AS type,
        CONCAT('Assigned new schedule for ', sec.section_code) AS action,
        'fa-calendar-plus' AS icon,
        ss.created_at AS activity_time,
        ss.term_id
    FROM sections_schedules ss
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN sections sec ON ss.section_id = sec.section_id
    )

    UNION ALL

    (SELECT 
        ss.ss_id AS id,
        CONCAT(t.t_fname, ' ', t.t_lname) AS name,
        LEFT(t.t_fname, 1) AS first_initial,
        LEFT(t.t_lname, 1) AS last_initial,
        'Schedule' AS type,
        CONCAT('Updated schedule for ', sec.section_code) AS action,
        'fa-calendar-alt' AS icon,
        ss.updated_at AS activity_time,
        ss.term_id
    FROM sections_schedules ss
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN sections sec ON ss.section_id = sec.section_id
    )

    ORDER BY activity_time DESC
    LIMIT 30
";

$recent_activities = $conn->query($recent_activities_query);

// =======================
// Fetch section statistics
// =======================
$section_stats_query = "
    SELECT 
        s.section_code, 
        COUNT(ss.s_id) as student_count,
        s.max_students,
        ROUND((COUNT(ss.s_id) / s.max_students) * 100) as fill_percentage
    FROM sections s
    LEFT JOIN students_sections ss 
        ON s.section_id = ss.section_id 
        AND ss.term_id = $current_term_id
    GROUP BY s.section_id
    ORDER BY fill_percentage DESC
";
$section_stats = $conn->query($section_stats_query);

// =======================
// Fetch counts by term for chart
// =======================
$demographics_query = "
    SELECT 
        t.term_id,
        CONCAT(ay.year_start, '-', ay.year_end, ' ', t.semester) AS term_label,
        (SELECT COUNT(DISTINCT s.s_id) 
         FROM students_sections ss 
         JOIN students s ON ss.s_id = s.s_id 
         WHERE ss.term_id = t.term_id) AS students,
        (SELECT COUNT(DISTINCT ss.teacher_id) 
         FROM sections_schedules ss 
         WHERE ss.term_id = t.term_id) AS teachers,
        (SELECT COUNT(DISTINCT sec.section_id) 
         FROM sections sec 
         WHERE sec.term_id = t.term_id) AS sections,
        (SELECT COUNT(DISTINCT subj.subject_id) 
         FROM sections_schedules ss 
         JOIN subjects subj ON ss.subject_code = subj.subject_id 
         WHERE ss.term_id = t.term_id) AS subjects
    FROM academic_terms t
    JOIN academic_years ay ON t.ay_id = ay.ay_id
    ORDER BY t.term_id ASC
";

$demographics_result = $conn->query($demographics_query);

$terms = [];
$students_data = [];
$teachers_data = [];
$sections_data = [];
$subjects_data = [];

while ($row = $demographics_result->fetch_assoc()) {
    $terms[] = $row['term_label'];
    $students_data[] = (int)$row['students'];
    $teachers_data[] = (int)$row['teachers'];
    $sections_data[] = (int)$row['sections'];
    $subjects_data[] = (int)$row['subjects'];
}


// Regular vs Irregular
$type_sql = "
    SELECT 
        SUM(CASE WHEN is_regular = 1 THEN 1 ELSE 0 END) AS regular_count,
        SUM(CASE WHEN is_regular = 0 THEN 1 ELSE 0 END) AS irregular_count
    FROM students
    WHERE is_deleted = 0
";
$type_result = $conn->query($type_sql);
$type_data = $type_result->fetch_assoc();

$regular_count = (int)$type_data['regular_count'];
$irregular_count = (int)$type_data['irregular_count'];

// Living Situation
$living_sql = "
    SELECT 
        SUM(CASE WHEN is_solo = 1 THEN 1 ELSE 0 END) AS with_parents_count,
        SUM(CASE WHEN is_solo = 2 THEN 1 ELSE 0 END) AS solo_count
    FROM students
    WHERE is_deleted = 0
";
$living_result = $conn->query($living_sql);
$living_data = $living_result->fetch_assoc();

$with_parents_count = (int)$living_data['with_parents_count'];
$solo_count = (int)$living_data['solo_count'];

?>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Font Awesome CDN (version 5 or 6) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<style>
    .legend-color {
margin-left:10px;
  width: 20px;
  height: 20px;
  border-radius: 3px;
}

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
    height:20px;
}

.progress-bar {
    padding:20px;
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

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">Welcome, Admin!</h1>
        <p class="text-muted mb-0">Here's what's happening in your school today.</p>
            <?php
            $current_term = $conn->query("SELECT ay.year_start, ay.year_end, t.semester 
                FROM academic_terms t 
                JOIN academic_years ay ON t.ay_id = ay.ay_id 
                WHERE t.is_active = 1
                ORDER BY t.term_id DESC LIMIT 1")->fetch_assoc();
            if ($current_term) {
               echo '<span class="badge bg-primary">Current Term: ' 
    . htmlspecialchars($current_term['year_start']) . ' - ' 
    . htmlspecialchars($current_term['year_end']) . ' | ' 
    . htmlspecialchars($current_term['semester']) . 
'</span>';

            } else {
                echo "No academic term set.";
            }
            ?>
            </div>
<div class="d-flex gap-2">
    <!-- Download Report Button -->
    <a href="./reports/download_report.php" class="btn btn-primary">
        <span class="material-icons align-middle me-1" style="font-size: 1rem;">download</span> Download Report
    </a>

    <!-- End Term Button -->
    <button type="button" class="btn btn-primary" id="endTermBtn" data-term-id="1">
        <i class="bi bi-calendar-x me-1"></i> End Term
    </button>
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
                        <span class="material-icons text-info" style="font-size: 2.5rem;"><i class="fa fa-layer-group"></i></span>
                    </div>
                    <div class="flex-grow-1">
                        <p class="text-muted mb-1">Total Sections</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($stats['sections']); ?></h4>
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

<div class="row g-3 mb-4">
    <!-- Section Capacity -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="card-title mb-0 text-white">Section Capacity</h5>
                        <p class=" small mb-0 text-white">Current student distribution across sections</p>
                    </div>
                </div>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
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
                        <div class="progress">
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
                <h5 class="card-title mb-0 text-white">Recent Activities</h5>
            </div>
        </div>
        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
    <div class="list-group list-group-flush">
        <?php if ($recent_activities->num_rows > 0): ?>
            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                <?php
                    // Format the timestamp from SQL
                    $formatted_time = date("M d, Y h:i A", strtotime($activity['activity_time']));
                ?>
                <div class="list-group-item border-0 px-4 py-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center" style="background:var(--primary)">
                                 <span class="fw-bold" style="color: var(--tertiary);">
                                    <?php echo $activity['first_initial'] . $activity['last_initial']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="d-flex align-items-center">
                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['name']); ?></h6>
                                <div class="flex-shrink-0 ms-2">
                                    <span class="badge bg-<?php echo $activity['type'] === 'Student' ? 'primary' : 'success'; ?> bg-opacity-10 text-<?php echo $activity['type'] === 'Student' ? 'primary' : 'success'; ?>">
                                        <i class="fas <?php echo htmlspecialchars($activity['icon']); ?> me-1"></i>
                                        <?php echo htmlspecialchars($activity['type']); ?>
                                    </span>
                                </div>
                            </div>
                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($activity['action']); ?></p>
                            <p class="text-muted small mb-0"><i class="far fa-clock me-1"></i> <?php echo $formatted_time; ?></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p class="mb-0">No recent activities recorded yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
    </div>
</div>
</div>


 <div class="row">
  <!-- Regular vs Irregular -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0 text-white">Student Type</h5>
      </div>
      <div class="card-body d-flex align-items-center">
        <div class="col-6">
          <canvas id="studentTypeChart" height="150"></canvas>
        </div>
        <div class="col-6">
          <ul class="list-unstyled mb-0">
  <li class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <span class="legend-color me-2" style="background:rgba(23, 52, 132, 1)"></span>
      <span>Regular</span>
    </div>
    <strong id="regularCount"><?php echo $regular_count; ?></strong>
  </li>
  <li class="d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <span class="legend-color me-2" style="background:#ffc107"></span>
      <span>Irregular</span>
    </div>
    <strong id="irregularCount"><?php echo $irregular_count; ?></strong>
  </li>
</ul>

        </div>
      </div>
    </div>
  </div>

  <!-- Solo vs Parents -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0 text-white">Living Situation</h5>
      </div>
      <div class="card-body d-flex align-items-center">
        <div class="col-6">
          <canvas id="livingSituationChart" height="150"></canvas>
        </div>
        <div class="col-6">
         <ul class="list-unstyled mb-0">
  <li class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
      <span class="legend-color me-2" style="background:#007bff;"></span>
      <span>With Parents/Guardians</span>
    </div>
    <strong id="withParentsCount"><?php echo $with_parents_count; ?></strong>
  </li>
  <li class="d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <span class="legend-color me-2" style="background:#ffc107;"></span>
      <span>Solo</span>
    </div>
    <strong id="soloCount"><?php echo $solo_count; ?></strong>
  </li>
</ul>


        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const regularCount = parseInt(document.getElementById("regularCount").textContent);
    const irregularCount = parseInt(document.getElementById("irregularCount").textContent);
    const withParentsCount = parseInt(document.getElementById("withParentsCount").textContent);
    const soloCount = parseInt(document.getElementById("soloCount").textContent);

    // Regular vs Irregular
    new Chart(document.getElementById("studentTypeChart"), {
        type: "doughnut",
        data: {
            labels: ["Regular", "Irregular"],
            datasets: [{
                data: [regularCount, irregularCount],
                backgroundColor: ["rgba(23, 52, 132, 1)", "#ffc107"]
            }]
        },
        options: {
            plugins: { legend: { display: false } }
        }
    });

    // Living Situation
    new Chart(document.getElementById("livingSituationChart"), {
        type: "doughnut",
        data: {
            labels: ["With Parents/Guardians", "Solo"],
            datasets: [{
                data: [withParentsCount, soloCount],
                backgroundColor: ["#007bff", "#ffc107"]
            }]
        },
        options: {
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<!-- Demographics by Term -->
  <div class="col-lg-12">
            <div class="card border-0 shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0 text-white">Demographics by Term</h5>
    </div>
    <div class="card-body">
        <canvas id="demographicsChart" height="120"></canvas>
    </div>
</div>
    </div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<script>
const terms = <?php echo json_encode($terms); ?>;
// Keep raw data
const rawStudents = <?php echo json_encode($students_data); ?>;
const rawTeachers = <?php echo json_encode($teachers_data); ?>;
const rawSections = <?php echo json_encode($sections_data); ?>;
const rawSubjects = <?php echo json_encode($subjects_data); ?>;

// Processed for rendering
function processData(arr) {
    return arr.map(v => v === 0 ? 1 : v); // make zero visible with stub bar
}

const ctx = document.getElementById('demographicsChart').getContext('2d');
const demographicsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($terms); ?>,
        datasets: [
            {
                label: 'Students',
                data: processData(rawStudents),
                rawData: rawStudents, // keep real values
                backgroundColor: '#0d6efd'
            },
            {
                label: 'Teachers',
                data: processData(rawTeachers),
                rawData: rawTeachers,
                backgroundColor: '#198754'
            },
            {
                label: 'Sections',
                data: processData(rawSections),
                rawData: rawSections,
                backgroundColor: '#ffc107'
            },
            {
                label: 'Subjects',
                data: processData(rawSubjects),
                rawData: rawSubjects,
                backgroundColor: '#dc3545'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: {
                display: true,
                text: 'School Demographics by Term'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        // show rawData instead of processed
                        const dataset = context.dataset;
                        const rawVal = dataset.rawData[context.dataIndex];
                        return `${dataset.label}: ${rawVal}`;
                    }
                }
            }
        },
        scales: {
            x: { stacked: false },
            y: { beginAtZero: true }
        }
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const regularCount = parseInt(document.getElementById("regularCount").textContent);
    const irregularCount = parseInt(document.getElementById("irregularCount").textContent);
    const withParentsCount = parseInt(document.getElementById("withParentsCount").textContent);
    const soloCount = parseInt(document.getElementById("soloCount").textContent);

    // Regular vs Irregular
    new Chart(document.getElementById("studentTypeChart"), {
        type: "doughnut",
        data: {
            labels: ["Regular", "Irregular"],
            datasets: [{
                data: [regularCount, irregularCount],
                backgroundColor: ["#28a745", "#dc3545"]
            }]
        },
        options: {
            plugins: { legend: { display: false } }
        }
    });

    // Living Situation
    new Chart(document.getElementById("livingSituationChart"), {
        type: "doughnut",
        data: {
            labels: ["With Parents/Guardians", "Solo"],
            datasets: [{
                data: [withParentsCount, soloCount],
                backgroundColor: ["#007bff", "#ffc107"]
            }]
        },
        options: {
            plugins: { legend: { display: false } }
        }
    });
});

</script>


<script>
document.getElementById('endTermBtn').addEventListener('click', function() {
    const termId = this.getAttribute('data-term-id');

    Swal.fire({
        title: 'Are you sure?',
        text: "This will end the current term and set it as completed.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, end it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send request to end_term.php
            fetch('end_term.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'term_id=' + encodeURIComponent(termId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Term Ended!',
                        text: 'The term has been successfully ended.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Redirect to manage_terms.php (outside admin folder)
                        window.location.href = '../manage_terms.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'An error occurred while ending the term.',
                        icon: 'error',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    title: 'Server Error!',
                    text: 'Could not connect to the server.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
});

function showAlert(message, type = 'success') {
    // Ensure type is one of SweetAlert's supported icons
    const validTypes = ['success', 'error', 'warning', 'info', 'question'];
    if (!validTypes.includes(type)) type = 'info';

    Swal.fire({
        icon: type,
        title: type === 'success' ? 'Success!' :
               type === 'error' ? 'Error!' :
               type === 'warning' ? 'Warning!' : 'Notice',
        text: message,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

</script>


