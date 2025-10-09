<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Fetch parent info
$parent_stmt = $conn->prepare("SELECT p_email, CONCAT(p_fname,' ',p_lname) AS parent_name FROM parents WHERE p_id = ?");
$parent_stmt->bind_param("i", $parent_id);
$parent_stmt->execute();
$parent_info = $parent_stmt->get_result()->fetch_assoc();
$parent_stmt->close();

// Fetch all children
$stmt = $conn->prepare("
    SELECT s.s_id, CONCAT(s.s_fname,' ',IFNULL(s.s_mname,''),' ',s.s_lname,' ',IFNULL(s.s_suffix,'')) AS student_name
    FROM parent_student ps
    INNER JOIN students s ON ps.s_id = s.s_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname, s.s_fname
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$students_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Filters
$month = $_GET['month'] ?? date('Y-m');
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Prepare attendance counts
$attendance = [];
foreach($students_result as $stu){
    $s_id = $stu['s_id'];

    $stmt = $conn->prepare("
        SELECT ss.section_id, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time, sec.section_code
        FROM students_sections stus
        INNER JOIN sections_schedules ss ON stus.section_id = ss.section_id
        INNER JOIN sections sec ON sec.section_id = stus.section_id
        WHERE stus.s_id = ?
        " . ($semester > 0 ? " AND ss.semester = ?" : "")
    );

    if($semester > 0){
        $stmt->bind_param("ii", $s_id, $semester);
    } else {
        $stmt->bind_param("i", $s_id);
    }

    $stmt->execute();
    $subj_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach($subj_result as $row){
        $subject_code = $row['subject_code'];
        $present = $late = $absent = 0;

        $stmt = $conn->prepare("
            SELECT status
            FROM attendance
            WHERE s_id = ? 
              AND subject_code = ? 
              AND DATE(time_in) BETWEEN ? AND ?
        ");
        $stmt->bind_param("isss", $s_id, $subject_code, $startDate, $endDate);
        $stmt->execute();
        $att_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach($att_records as $rec){
            $status = strtoupper(trim($rec['status']));
            if($status === 'PRESENT') $present++;
            elseif($status === 'LATE') $late++;
            elseif($status === 'ABSENT') $absent++;
        }

        $attendance[$s_id][$subject_code] = [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'days' => $row['day_of_week'] . " (" . date("g:i A", strtotime($row['start_time'])) . " - " . date("g:i A", strtotime($row['end_time'])) . ")",
            'section' => $row['section_code']
        ];
    }
}
if(isset($_GET['month']) && isset($_GET['semester'])){
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showAlert('Showing attendance for selected month and semester.', 'success');
        });
    </script>";
}

// Example: show error if no students found
if(empty($students_result)){
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showAlert('No students found for your account.', 'danger');
        });
    </script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Report</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="../../assets/css/content.css">
<link rel="stylesheet" href="../assets/css/content.css">
</head>
<body>

<center>
<div class="alert-container" id="alertContainer"></div>  
<div class="card shadow-sm text-start w-100 mt-5">
    <div class="card-header">
        <h1 class="h4 fw-bold"><i class="bi bi-bar-chart-line me-2"></i> Attendance Report</h1>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 mb-4" id="filterForm">
            <div class="col-md-3">
                <label class="form-label">Select Month</label>
                <input type="month" name="month" value="<?= htmlspecialchars($month); ?>" class="form-control" id="monthSelect">
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
                <select name="semester" class="form-select" id="semesterSelect">
                    <option value="0" <?= $semester==0?"selected":""; ?>>All</option>
                    <option value="1" <?= $semester==1?"selected":""; ?>>1st Semester</option>
                    <option value="2" <?= $semester==2?"selected":""; ?>>2nd Semester</option>
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table id="attendanceTable" class="table table-bordered table-striped">
                <thead>
                    <tr class="text-center">
                        <th></th>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Schedule</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach($students_result as $student){
                        $s_id = $student['s_id'];
                        $student_name = $student['student_name'];
                        if(isset($attendance[$s_id])){
                            foreach($attendance[$s_id] as $subject_code => $att){
                                echo '<tr>
                                    <td></td>
                                    <td>'.htmlspecialchars($s_id).'</td>
                                    <td>'.htmlspecialchars($student_name).'</td>
                                    <td>'.htmlspecialchars($subject_code).'</td>
                                    <td>'.htmlspecialchars($att['days']).'</td>
                                    <td>'.$att['present'].'</td>
                                    <td>'.$att['late'].'</td>
                                    <td>'.$att['absent'].'</td>
                                </tr>';
                            }
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <form action="reports/processes/export_attendance_reports.php" method="post" class="mt-4 text-end" id="exportForm">
            <input type="hidden" name="parent_id" value="<?= $parent_id; ?>">
            <input type="hidden" name="month" value="<?= $month; ?>">
            <input type="hidden" name="semester" value="<?= $semester; ?>">
            <button type="submit" class="btn btn-primary "><i class="bi bi-download me-2"></i> Generate / Export</button>
        </form>
    </div>
</div>
</center>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
<script src="../../assets/js/showAlert.js"></script>
<script src="../assets/js/showAlert.js"></script>
<script>
$(document).ready(function(){
    let table = $('#attendanceTable').DataTable({
        responsive:{ details:{ type:'column', target:'td:first-child'} },
        scrollY:'40vh', scrollX:true, scrollCollapse:true, paging:true, autoWidth:false,
        columnDefs:[
            { className:'control', orderable:false, targets:0 },
            { responsivePriority:2, targets:1 },
            { responsivePriority:3, targets:2 },
            { responsivePriority:4, targets:3 },
            { responsivePriority:5, targets:4 },
            { responsivePriority:6, targets:5 },
            { responsivePriority:7, targets:6 }
        ]
    });

    function loadAttendance(month, semester){
        $.ajax({
        url:'reports/processes/fetch_attendance.php',
        data:{month:month, semester:semester},
        success:function(html){
            // Destroy old DataTable
            table.destroy();

            // Replace tbody
            $('#attendanceTable tbody').html(html);

            // Reinitialize DataTable
            table = $('#attendanceTable').DataTable({
                responsive:{ details:{ type:'column', target:'td:first-child'} },
                scrollY:'50vh', scrollX:true, scrollCollapse:true, paging:true, autoWidth:false,
                columnDefs:[
                    { className:'control', orderable:false, targets:0 },
                    { responsivePriority:2, targets:1 },
                    { responsivePriority:3, targets:2 },
                    { responsivePriority:4, targets:3 },
                    { responsivePriority:5, targets:4 },
                    { responsivePriority:6, targets:5 },
                    { responsivePriority:7, targets:6 }
                ]
            });
        },
        error:function(){ showAlert('Failed to load attendance','danger',4000); }
    });
    }

    $('#monthSelect, #semesterSelect').on('change', function(){
        const month = $('#monthSelect').val();
        const semester = $('#semesterSelect').val();
        loadAttendance(month, semester);
    });

    // Keep export button AJAX for emailing
    $('#exportForm').on('submit', function(e){
        e.preventDefault();
        const formData = $(this).serialize();
        $.ajax({
            url:'reports/processes/export_attendance_reports.php',
            type:'POST',
            data:formData,
            dataType:'json',
            success:function(res){
                showAlert(res.message, res.success?'success':'danger',4000);
            },
            error:function(){ showAlert('Failed to send report','danger',4000); }
        });
    });
});

</script>


</body>
</html>
