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
    SELECT s.*, CONCAT(s.s_fname,' ',IFNULL(s.s_mname,''),' ',s.s_lname,' ',IFNULL(s.s_suffix,'')) AS student_name
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
$term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Prepare attendance counts
$attendance = [];
foreach($students_result as $stu){
    $s_id = $stu['s_id'];
    $idcode = $stu['idcode'];

    $query = "
        SELECT ss.section_id, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time, sec.section_code
        FROM students_sections stus
        INNER JOIN sections_schedules ss ON stus.section_id = ss.section_id
        INNER JOIN sections sec ON sec.section_id = stus.section_id
        WHERE stus.s_id = ?
    ";
    $params = [$s_id];
    $types = "i";

    if ($term_id > 0) {
        $query .= " AND ss.term_id = ?";
        $params[] = $term_id;
        $types .= "i";
    }

    $stmt_subj = $conn->prepare($query);
    $stmt_subj->bind_param($types, ...$params);
    $stmt_subj->execute();
    $subj_result = $stmt_subj->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_subj->close();

    foreach($subj_result as $row){
        $subject_code = $row['subject_code'];
        $present = $late = $absent = 0;

        $stmt_att = $conn->prepare("
            SELECT status
            FROM attendance
            WHERE s_id = ? 
              AND subject_code = ? 
              AND DATE(time_in) BETWEEN ? AND ?
        ");
        $stmt_att->bind_param("isss", $s_id, $subject_code, $startDate, $endDate);
        $stmt_att->execute();
        $att_records = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_att->close();

        foreach($att_records as $rec){
            $status = ucfirst(strtolower(trim($rec['status'])));
            if($status === 'Present') $present++;
            elseif($status === 'Late') $late++;
            elseif($status === 'Absent') $absent++;
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
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="../../assets/css/content.css">
<link rel="stylesheet" href="../assets/css/content.css">
</head>
<style>
    #tableButtons .btn {
        margin-right: 5px;
    }
    .dtBtn.sendto{
    background: var(--primary) !important;
    color:white !important;
 }
/* Copy button */
.dtBtn.copy-btn {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15); /* subtle 3D shadow */
 }
.dtBtn.copy-btn:hover {
    background-color: #0a800aff !important; /* light blue */
    color: #c6e9d3ff !important; /* primary blue text */
    transform: translateY(-3px); /* lift effect */
    box-shadow: 0 6px 12px rgba(0,0,0,0.25); /* deeper shadow */
 }

/* PDF button */
.dtBtn.pdf-btn {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}
.dtBtn.pdf-btn:hover {
    background-color: #ac101dff !important; /* light red/pink */
    color: #f8c4c9ff !important; /* red text */
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.25);
}

/* Print button */
.dtBtn.print-btn {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}
.dtBtn.print-btn:hover {
    background-color: #072668ff !important; /* light yellow */
    color: #a5d7f2ff !important; /* yellow text */
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.25);
}
.tablehead th{
    background:var(--primary)!important;
    color: white !important;
}
    </style>
<body>
<center>
<div class="alert-container" id="alertContainer"></div>  
<div class="card shadow-sm text-start w-100 mt-5">
    <div class="card-header">
        <h1 class="h4 fw-bold"><i class="bi bi-bar-chart-line me-2"></i> Attendance Report</h1>
    </div>
    <div class="card-body">
    <div class="mb-3">
    <form method="get" class="row g-3" id="filterForm">
        <!-- Month -->
        <div class="col-12 col-md-6">
            <label class="form-label">Select Month</label>
            <input type="month" name="month" value="<?= htmlspecialchars($month); ?>" class="form-control" id="monthSelect">
        </div>

        <!-- Semester -->
        <div class="col-12 col-md-6">
            <label class="form-label">Semester</label>
<select name="term_id" class="form-select" id="termSelect">
    <option value="0" <?= $term_id==0 ? "selected" : ""; ?>>All</option>
    <?php
    $term_result = $conn->query("
        SELECT at.term_id, at.semester, ay.year_start, ay.year_end
        FROM academic_terms at
        INNER JOIN academic_years ay ON at.ay_id = ay.ay_id
        ORDER BY ay.year_start DESC, at.semester ASC
    ");
    while($row = $term_result->fetch_assoc()){
        $id = $row['term_id'];
        $display = 'A.Y. '.$row['year_start'].'-'.$row['year_end'].' | '.$row['semester'].' Semester';
        $selected = ($term_id == $id) ? "selected" : "";
        echo '<option value="'.htmlspecialchars($id).'" '.$selected.'>'.htmlspecialchars($display).'</option>';
    }
    ?>
</select>

        </div>
    </form>
     <!-- Buttons -->
    <div class="row  mt-3">
    <div class="col d-flex flex-column flex-md-row justify-content-center align-items-center gap-2">
        <!-- Send to Email form -->
        <form action="reports/processes/export_attendance_reports.php" method="post" id="exportForm" class="mb-2 mb-md-0">
            <input type="hidden" name="parent_id" value="<?= $parent_id; ?>">
            <input type="hidden" name="month" value="<?= $month; ?>">
            <input type="hidden" name="semester" value="<?= $semester; ?>">
            <button type="submit" class="dtBtn btn sendto btn-primary">
                <i class="bi bi-envelope me-2"></i>Send to Email
            </button>
        </form>

        <!-- Table buttons -->
        <div id="tableButtons" class="d-flex gap-2 flex-wrap">
            <!-- Buttons will be injected here -->
        </div>
    </div>
</div>


</div>


        <div class="table-responsive">
            <table id="attendanceTable" class="table">
                <thead>
                    <tr class="tablehead">
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
$hasData = false;
foreach ($students_result as $student) {
    $s_id = $student['s_id'];
    $idcode = $student['idcode'];
    $student_name = $student['student_name'];

    if (isset($attendance[$s_id])) {
        foreach ($attendance[$s_id] as $subject_code => $att) {
            $hasData = true;
            echo '<tr>
                <td></td>
                <td>' . htmlspecialchars($idcode) . '</td>
                <td>' . htmlspecialchars($student_name) . '</td>
                <td>' . htmlspecialchars($subject_code) . '</td>
                <td>' . htmlspecialchars($att['days']) . '</td>
                <td>' . (int)$att['present'] . '</td>
                <td>' . (int)$att['late'] . '</td>
                <td>' . (int)$att['absent'] . '</td>
            </tr>';
        }
    }
}

if (!$hasData) {
    echo '<tr><td colspan="8" class="text-center">No data available</td></tr>';
}
?>
</tbody>

            </table>
        </div>
    </div>
</div>
</center>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<!-- JSZip (required for Excel export, optional for PDF) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- pdfmake (required for PDF export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="../../assets/js/showAlert.js"></script>
<script src="../assets/js/showAlert.js"></script>
<script>
$(document).ready(function(){
    // Initialize DataTable
    function initAttendanceTable() {
        return $('#attendanceTable').DataTable({
            responsive: { details: { type: 'column', target: 'td:first-child' } },
            scrollY: '40vh',
            scrollX: true,
            scrollCollapse: true,
            paging: true,
            autoWidth: false,
            lengthChange: true,
            dom: `
                <'row mb-2'
                    <'col-12 text-end'B>
                >
                <'row mb-3'
                    <'col-sm-6'l>
                    <'col-sm-6'f>
                >
                <'row'<'col-12'tr>>
                <'row mt-2'<'col-sm-5'i><'col-sm-7'p>>
            `,
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="bi bi-clipboard me-1"></i> Copy',
                    className: 'dtBtn btn copy-btn bg-transparent text-success me-1 border-0'
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                    className: 'dtBtn btn pdf-btn bg-transparent text-danger me-1 border-0',
                    title: 'Attendance Report',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend: 'print',
                    text: '<i class="bi bi-printer me-1"></i> Print',
                    className: 'dtBtn btn print-btn bg-transparent text-primary me-1 border-0'
                }
            ],
            columnDefs: [
                { className: 'control', orderable: false, targets: 0 }
            ]
        });
    }

    let table = initAttendanceTable();
    table.buttons().container().appendTo('#tableButtons');

    // Load attendance via AJAX
    function loadAttendance(month, term_id){
        $.ajax({
            url:'reports/processes/fetch_attendance.php',
            data:{ month: month, semester: term_id },
          success:function(res){
    if(res.status !== 'success') return;

    let rows = '';

    if (res.data.length === 0) {
        rows = `
            <tr>
                <td colspan="8" class="text-center">No data available</td>
            </tr>
        `;
    } else {
        res.data.forEach(row => {
            rows += `
                <tr>
                    <td></td>
                    <td>${row.idcode}</td>
                    <td>${row.student_name}</td>
                    <td>${row.subject_code}</td>
                    <td>${row.schedule}</td>
                    <td>${row.present}</td>
                    <td>${row.late}</td>
                    <td>${row.absent}</td>
                </tr>
            `;
        });
    }

    table.destroy();
    $('#attendanceTable tbody').html(rows);
    table = initAttendanceTable();
    table.buttons().container().appendTo('#tableButtons');
},

            error:function(){
                showAlert('Failed to load attendance','danger',4000);
            }
        });
        
    }

    // ✅ Trigger reload when filters change
    $('#monthSelect, #termSelect').on('change', function(){
        const month = $('#monthSelect').val();
        const term_id = $('#termSelect').val();
        loadAttendance(month, term_id);
    });

    // ✅ Load correct data as soon as the page loads
    loadAttendance($('#monthSelect').val(), $('#termSelect').val());


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
