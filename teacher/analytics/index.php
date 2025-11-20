<?php
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../../login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

// --- Active term ---
$term_query = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$term_id = ($term_query->num_rows > 0) ? $term_query->fetch_assoc()['term_id'] : 0;

// --- Fetch all subjects handled ---
$stmt = $conn->prepare("
    SELECT ss.subject_code, s.section_name, ss.section_id
    FROM sections_schedules ss
    JOIN sections s ON ss.section_id = s.section_id
    WHERE ss.teacher_id = ? AND ss.term_id = ? AND ss.is_active = 1
");
$stmt->bind_param("ii", $teacher_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[$row['subject_code']] = $row['section_name'];
}
$stmt->close();

// --- Compute analytics ---
$analytics = [];
$totalStudents = 0;
$totalPresent = 0;
$totalLate = 0;
$totalAbsent = 0;

foreach ($subjects as $subject_code => $section_name) {
    $query = $conn->prepare("
        SELECT a.s_id, s.s_fname, s.s_lname, a.status
        FROM attendance a
        JOIN students s ON a.s_id = s.s_id
        WHERE a.subject_code = ? AND a.term_id = ?
    ");
    $query->bind_param("si", $subject_code, $term_id);
    $query->execute();
    $rows = $query->get_result();

    $subjectStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
    $studentStats = [];

    while ($r = $rows->fetch_assoc()) {
        $status = strtoupper($r['status']);
        $subjectStats['total']++;
        $studentKey = $r['s_id'];
        if (!isset($studentStats[$studentKey])) {
            $studentStats[$studentKey] = ['name' => $r['s_fname'].' '.$r['s_lname'], 'present'=>0,'late'=>0,'absent'=>0];
        }

        if ($status === 'PRESENT') {
            $subjectStats['present']++;
            $studentStats[$studentKey]['present']++;
        } elseif ($status === 'LATE') {
            $subjectStats['late']++;
            $studentStats[$studentKey]['late']++;
        } elseif ($status === 'ABSENT') {
            $subjectStats['absent']++;
            $studentStats[$studentKey]['absent']++;
        }
    }

    $query->close();

    $analytics[$subject_code] = [
        'section' => $section_name,
        'present' => $subjectStats['present'],
        'late' => $subjectStats['late'],
        'absent' => $subjectStats['absent'],
        'total' => $subjectStats['total'],
        'attendance_rate' => $subjectStats['total'] > 0 ? 
            round(($subjectStats['present'] / $subjectStats['total']) * 100, 2) : 0,
        'students' => $studentStats
    ];

    $totalPresent += $subjectStats['present'];
    $totalLate += $subjectStats['late'];
    $totalAbsent += $subjectStats['absent'];
    $totalStudents += count($studentStats);
}

// --- Get top students ---
$studentScores = [];
foreach ($analytics as $subject => $data) {
    foreach ($data['students'] as $sid => $info) {
        $total = $info['present'] + $info['late'] + $info['absent'];
        if ($total === 0) continue;
        $rate = (($info['present'] + $info['late']) / $total) * 100;
        $studentScores[$sid] = ['name'=>$info['name'],'rate'=>round($rate,2)];
    }
}
usort($studentScores, fn($a, $b) => $b['rate'] <=> $a['rate']);
$topStudents = array_slice($studentScores, 0, 5);
$lowestStudents = array_slice(array_reverse($studentScores), 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Analytics Report</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    }
.card-header{
    background: var(--primary) !important;
    color:white;
    font-weight: bold;
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

</style>

</head>
<body>
    <main>
    <div class="container-fluid ">
    <h3 class="mb-2 fw-bold"><i class="bi bi-graph-up"></i> Teacher Analytics Report</h3>
     <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Analytics Report</li>
                </ol>
            </nav>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-header">Total Students</div>
                <div class="card-body"><h4><?= $totalStudents ?></h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-header">Total Presents</div>
                <div class="card-body"><h4><?= $totalPresent ?></h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-header">Total Lates</div>
                <div class="card-body"><h4><?= $totalLate ?></h4></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-header">Total Absents</div>
                <div class="card-body"><h4><?= $totalAbsent ?></h4></div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">Average Attendance per Subject</div>
        <div class="card-body">
            <canvas id="subjectChart" height="100"></canvas>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success">Top Performing Students</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($topStudents as $s): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= htmlspecialchars($s['name']) ?>
                            <span class="badge bg-warning text-dark"><?= $s['rate'] ?>%</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-danger">Most Absent Students</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($lowestStudents as $s): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= htmlspecialchars($s['name']) ?>
                            <span class="badge bg-danger"><?= $s['rate'] ?>%</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
    </main>
  

<script>
const ctx = document.getElementById('subjectChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($analytics)) ?>,
        datasets: [{
            label: 'Attendance Rate (%)',
            data: <?= json_encode(array_column($analytics, 'attendance_rate')) ?>,
            backgroundColor: '#033A70'
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
</script>
</body>
</html>
