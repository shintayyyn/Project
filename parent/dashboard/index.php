<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Parent info
$stmt = $conn->prepare("
    SELECT p_id, CONCAT(p_fname, ' ', IFNULL(p_mname, ''), ' ', p_lname, ' ', IFNULL(p_suffix, '')) AS full_name
    FROM parents
    WHERE p_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent_info = $stmt->get_result()->fetch_assoc();

// Children
$children_query = "
    SELECT s.s_id, CONCAT(s.s_fname, ' ', IFNULL(s.s_mname,''), ' ', s.s_lname, ' ', IFNULL(s.s_suffix,'')) AS full_name,
           sec.section_code
    FROM students s
    LEFT JOIN parent_student ps ON s.s_id = ps.s_id
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname ASC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result();

// Prepare chart data
$studentLabels = [];
$presentData = [];
$lateData = [];
$absentData = [];
$excusedData = [];
$totalPresent = $totalLate = $totalAbsent = 0;

while ($child = $children->fetch_assoc()) {
    $stmt = $conn->prepare("
        SELECT 
            SUM(status='Present') AS present_count,
            SUM(status='Late') AS late_count,
            SUM(status='Absent') AS absent_count,
            SUM(status='Excuse') AS excuse_count
        FROM attendance
        WHERE s_id = ?
    ");
    $stmt->bind_param("i", $child['s_id']);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();

    $studentLabels[] = $child['full_name'];
    $presentData[] = intval($counts['present_count']);
    $lateData[] = intval($counts['late_count']);
    $absentData[] = intval($counts['absent_count']);
    $excusedData[] = intval($counts['excuse_count']);

    $totalPresent += intval($counts['present_count']);
    $totalLate += intval($counts['late_count']);
    $totalAbsent += intval($counts['absent_count']);
}
$totalStudents = count($studentLabels);
$totalExcused = array_sum($excusedData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parent Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<main>
<div class="container py-5">
    <!-- Header -->
    <div class="parent-header-card">
    <!-- Left content: avatar circle and text -->
    <div class="header-content">
        <div class="avatar-circle">
            <?php
                $initials = strtoupper(substr($parent_info['full_name'], 0, 2)); 
                echo $initials;
            ?>
        </div>
        <div class="header-text">
            <h1>Hi! <?php echo htmlspecialchars($parent_info['full_name']); ?></h1>
            <h3 class="role">It's good to see you again.</h3>
        </div>
    </div>

    <!-- Profile Image on the Right (Overflowing) -->
    <img src="../assets/img/avatar.png" alt="Avatar" class="header-avatar">
    </div>
    <p class="text-center text-muted mb-4">Here’s an overview of your children’s attendance</p>

 <!-- Summary Cards -->
<div class="row mb-4 g-3 justify-content-center container-fluid">
    <!-- Total Present -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card-summary-two-tone">
            <div class="card-top">
                <h4 class="mb-0 fw-bold text-uppercase">Total Present</h4>
            </div>
            <div class="card-bottom" style="background: #033A70;">
                <h3 class="mb-0"><?php echo $totalPresent; ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Late -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card-summary-two-tone">
            <div class="card-top">
                <h4 class="mb-0 fw-bold text-uppercase">Total Late</h4>
            </div>
            <div class="card-bottom">
                <h3 class="mb-0"><?php echo $totalLate; ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Absent -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card-summary-two-tone">
            <div class="card-top">
                <h4 class="mb-0 fw-bold text-uppercase">Total Absent</h4>
            </div>
            <div class="card-bottom" style="background: #033A70;">
                <h3 class="mb-0"><?php echo $totalAbsent; ?></h3>
            </div>
        </div>
    </div>

    <!-- Total Excused -->
    <div class="col-12 col-sm-6 col-md-3">
        <div class="card-summary-two-tone">
            <div class="card-top">
                <h4 class="mb-0 fw-bold text-uppercase">Total Excused</h4>
            </div>
            <div class="card-bottom"> <!-- green -->
                <h3 class="mb-0"><?php echo $totalExcused; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 align-items-stretch">
    <!-- Bar Chart Card -->
    <div class="col-12 col-md-7 d-flex">
        <div class="chart-card w-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="mb-0">Student's Status</h2>
                <span class="badge badge-custom">
                   SUMMARY
                </span>
            </div>
            <canvas id="barChart" class="w-100"></canvas>
        </div>
    </div>

    <!-- Pie Chart Card -->
    <div class="col-12 col-md-5 d-flex">
        <div class="chart-card w-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="mb-0">Student's Performance</h2>
                <span class="badge badge-custom badge-secondary">
                    <?php echo $totalStudents; ?> Students
                </span>
            </div>
            <canvas id="pieChart"></canvas>
        </div>
    </div>
</div>



</div>
</main>

<script>
// Ensure data has default 0 values if empty
const presentDataSafe = <?php echo json_encode($presentData); ?>.map(v => v || 0);
const lateDataSafe    = <?php echo json_encode($lateData); ?>.map(v => v || 0);
const absentDataSafe  = <?php echo json_encode($absentData); ?>.map(v => v || 0);
const studentLabelsSafe = <?php echo json_encode($studentLabels); ?>;

// Bar Chart
new Chart(document.getElementById('barChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: studentLabelsSafe,
        datasets: [
            {
                label: 'Present',
                data: presentDataSafe,
                backgroundColor: '#033A70',
                borderRadius: 10,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
                minBarLength: 5 // show tiny bar even if value is 0
            },
            {
                label: 'Late',
                data: lateDataSafe,
                backgroundColor: '#FFC107',
                borderRadius: 10,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
                minBarLength: 5
            },
            {
                label: 'Absent',
                data: absentDataSafe,
                backgroundColor: '#1981C2',
                borderRadius: 10,
                barPercentage: 0.6,
                categoryPercentage: 0.7,
                minBarLength: 5
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    color: '#000',
                    padding: 15
                }
            },
            tooltip: {
                enabled: true,
                callbacks: {
                    title: function(tooltipItems) {
                        return studentLabelsSafe[tooltipItems[0].dataIndex] || 'No Name';
                    },
                    label: function(tooltipItem) {
                        return tooltipItem.dataset.label + ': ' + (tooltipItem.raw || 0);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 10, color: "#333", font: { size: 12 } },
                grid: { drawTicks: false, drawBorder: false }
            },
            x: {
                ticks: {
                    color: "#333",
                    font: { size: 12 },
                    callback: function(value, index) {
                        return window.innerWidth < 576 ? '' : this.getLabelForValue(value);
                    }
                },
                grid: { drawTicks: false, drawBorder: false }
            }
        }
    }
});
// Pie Chart
new Chart(document.getElementById('pieChart').getContext('2d'), {
    type: 'pie',
    data: {
        labels: ['Present', 'Late', 'Absent', 'Excused'],
        datasets: [{
            data: [
                <?php echo $totalPresent; ?>,
                <?php echo $totalLate; ?>,
                <?php echo $totalAbsent; ?>,
                <?php echo $totalExcused; ?>
            ],
            backgroundColor: ['#033A70', '#FFC107', '#1981C2', '#6FB2F7'],
            borderWidth: 0
        }]
    },
    options: {
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
                backgroundColor: '#fff',
                titleColor: '#000',
                bodyColor: '#000',
                borderColor: '#ddd',
                borderWidth: 1
            }
        }
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
</script>
</body>
</html>
