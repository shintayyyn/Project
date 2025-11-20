<?php

require_once '../includes/db.php';

// session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$parent_id = $_SESSION['user_id'];

// Fetch children of this parent
$children_query = "
    SELECT s.s_id, s.s_fname, s.idcode
    FROM students s
    INNER JOIN parent_student ps ON ps.s_id = s.s_id
    WHERE ps.p_id = ?
";
$stmt_child = $conn->prepare($children_query);
$stmt_child->bind_param("i", $parent_id);
$stmt_child->execute();
$children = $stmt_child->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_child->close();

$student_id = isset($_GET['child']) ? intval($_GET['child']) : '';
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : '';
$selected_term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : '';


$term_dropdown_query = "
    SELECT t.term_id,
           CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
    FROM academic_terms t
    INNER JOIN academic_years y ON t.ay_id = y.ay_id
    ORDER BY y.year_start DESC, t.semester ASC
";
$term_dropdown = $conn->query($term_dropdown_query);


$attendance_history = [];

if (!empty($student_id)) {
    $attendance_query = "
        SELECT a.s_id, s.idcode, s.s_fname, a.subject_code, a.section_code,
               a.time_in, a.time_out, a.status,
               CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
        FROM attendance a
        INNER JOIN students s ON a.s_id = s.s_id
        LEFT JOIN academic_terms t ON a.term_id = t.term_id
        LEFT JOIN academic_years y ON t.ay_id = y.ay_id
        WHERE a.s_id = ?
    ";

    $params = [$student_id];
    $types = "i";

$selected_month = isset($_GET['month']) ? $_GET['month'] : '';

if (!empty($selected_month)) {
    list($year_number, $month_number) = explode('-', $selected_month);
    $month_number = intval($month_number);
    $year_number = intval($year_number);

    $attendance_query .= " AND MONTH(a.time_in) = ? AND YEAR(a.time_in) = ?";
    $params[] = $month_number;
    $params[] = $year_number;
    $types .= "ii";
}



    if (!empty($selected_term_id)) {
        $attendance_query .= " AND t.term_id = ?";
        $params[] = $selected_term_id;
        $types .= "i";
    }

    $attendance_query .= " ORDER BY a.time_in DESC";

    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $attendance_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

}

// Determine the semester for summary
$semester_for_export = $selected_term_id;
if (empty($semester_for_export)) {
    // Get latest semester (active semester) if none selected
    $latest_term = $conn->query("
        SELECT t.term_id
        FROM academic_terms t
        INNER JOIN academic_years y ON t.ay_id = y.ay_id
        ORDER BY y.year_start DESC, t.semester DESC
        LIMIT 1
    ")->fetch_assoc();
    $semester_for_export = $latest_term['term_id'] ?? '';
}

// Month should be empty for summary export
$month_for_export = '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Attendance Report</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<!-- <link rel="stylesheet" href="../../assets/css/content.css"> -->
<link rel="stylesheet" href="../assets/css/content.css">
</head>
<style>
     *{
            font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
        }
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
    .card-header{
        background:var(--primary);
        color:white;
    }
    td{
        border:0;
    }
  
    #tableButtons .btn {
        margin-right: 5px;
    }
    .dtBtn.sendto{
    background: var(--primary) !important;
    color:white !important;
 }
/* Excel button */
.dtBtn.excel-btn {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.15); /* subtle 3D shadow */
 }
.dtBtn.excel-btn:hover {
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
th{
    background:var(--primary)!important;
    color: white !important;
}
    </style>
<body>
    <?php include(__DIR__ . '/../../includes/parent_header.php'); ?>

<div class="alert-container" id="alertContainer"></div>  
<center>
<div class="card shadow-sm text-start w-100 mt-2 p-2 d-flex justify-content-center">
     <h2 class="text-primary text-center fw-bold">Attendance Report</h2>
    <p class="text-muted text-center">Track and filter your child's attendance below.</p>

   <div class="card-header p-3 mb-3">
    <label class="fw-bold">Select Child:</label>
    <select id="childFilter" class="form-select w-auto">
        <?php
        // If no child is selected, pick the first child as default
        if (empty($student_id) && !empty($children)) {
            $student_id = $children[0]['s_id'];
        }
        ?>
        <?php foreach ($children as $child): ?>
            <option value="<?= $child['s_id'] ?>" <?= ($student_id == $child['s_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($child['s_fname']) ?> (<?= htmlspecialchars($child['idcode']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>

    <?php if (!empty($student_id)): ?>
     <div class="row mb-4 g-3">
    <!-- Academic Term Widget -->
   <div class="col-md-6 text-start">
    <label for="termFilter" class="fw-bold mb-0 me-2">Academic Term:</label>
    <select id="termFilter" class="form-select form-select-sm">
        <option value="all" <?= empty($selected_term_id) || $selected_term_id === 'all' ? 'selected' : '' ?>>All Terms</option>
        <?php while ($row = $term_dropdown->fetch_assoc()): ?>
            <option value="<?= $row['term_id'] ?>" <?= ($selected_term_id == $row['term_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['term_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>


    <!-- Month Widget -->
    <div class="col-md-6">
            <label for="monthFilter" class="fw-bold mb-0 me-2">Month:</label>
           <input 
    type="month" 
    id="monthFilter" 
    name="monthFilter" 
    class="form-control form-control-sm"
    value="<?= !empty($_GET['month']) ? htmlspecialchars($_GET['month']) : '' ?>"
    placeholder="---- --"
/>

    </div>

    <!-- Buttons Row -->
    <div class="col-12 mt-3">
        <div class="d-flex flex-column flex-md-row justify-content-center align-items-center gap-2">
            <!-- Send to Email -->
          <form action="reports/processes/export_attendance_reports.php" method="post" id="exportForm" class="mb-2 mb-md-0">
    <input type="hidden" name="parent_id" value="<?= $parent_id; ?>">
    <input type="hidden" name="month" value="<?= htmlspecialchars($month_for_export); ?>">
    <input type="hidden" name="semester" id="semesterInput" value="<?= htmlspecialchars($selected_term_id); ?>">
    <input type="hidden" name="child_id" id="childInput" value="<?= htmlspecialchars($student_id); ?>">

    <button type="submit" class="dtBtn btn sendto btn-primary">
        <i class="bi bi-envelope me-2"></i>Send to Email
    </button>
</form>

            <!-- Table Buttons Placeholder -->
            <div id="tableButtons" class="d-flex gap-2 flex-wrap">
                <!-- DataTables buttons will be injected here -->
            </div>
        </div>
    </div>
</div>


    <table id="attendanceTable" class="table table-light table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Subject</th>
                <th>Section</th>
                <th>Date</th>
                <th>Time</th>
                <th>Term</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($attendance_history as $a): ?>
<tr>
    <td><?= $a['idcode'] ?></td>
    <td><?= $a['subject_code'] ?></td>
    <td><?= $a['section_code'] ?></td>
    <td><?= date('M d, Y', strtotime($a['time_in'])) ?></td>
    <td><?= date('h:i A', strtotime($a['time_in'])) ?> - <?= $a['time_out'] ? date('h:i A', strtotime($a['time_out'])) : '-' ?></td>
    <td><?= $a['term_name'] ?></td>
    <td>
        <?php 
            $status = strtolower($a['status']);
            $badgeClass = '';
            $textColor = '';
            if($status == 'present') {
                $badgeClass = 'bg-success';
                $textColor = 'text-white';
            } elseif($status == 'late') {
                $badgeClass = 'bg-warning';
                $textColor = 'text-dark';
            } elseif($status == 'absent') {
                $badgeClass = 'bg-danger';
                $textColor = 'text-white';
            }
            elseif($status == 'excuse') {
                $badgeClass = 'bg-primary';
                $textColor = 'text-white';
            }else{
                 $badgeClass = 'bg-secondary';
                $textColor = 'text-dark';
            }
        ?>
        <span class="badge <?= $badgeClass ?> <?= $textColor ?>"><?= ucfirst($status) ?></span>
    </td>
</tr>
<?php endforeach; ?>

        </tbody>
    </table>

</div>

    <?php endif; ?>
</center>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- CSS -->
<!-- <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css"> -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

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
function initAttendanceTable() {
    return $('#attendanceTable').DataTable({
        responsive: {
            details: {
                type: 'column',
                target: 0 // first column for toggle
            }
        },
        columnDefs: [
            { className: 'dtr-control', orderable: false, targets: 0 } // toggle button
        ],
        order: [[3, "desc"]], // sort by Date column by default
        pageLength: 25,
        lengthChange: true,
        dom: `
            <'mt-2 d-flex flex-column flex-md-row justify-content-between align-items-start mb-2'
                <'me-0 me-md-2 mb-2 mb-md-0'l>
                <'d-flex flex-column flex-md-row'fB>
            >
            rt
            ip
        `,
         buttons: [
                {
                    extend: 'excel',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                    className: 'dtBtn btn excel-btn bg-transparent text-success me-1 border-0'
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
             scrollX: false,
        scrollY: '50vh',
        responsive: true,
        ordering: true,
        scrollCollapse: true,
        paging: true,
        searching: true,
        info: true,
        autoWidth: false
    });
}

let table = initAttendanceTable();
table.buttons().container().appendTo('#tableButtons');


function loadAttendanceData() {
    const child = $('#childFilter').val();
    const month = $('#monthFilter').val();
    const term = $('#termFilter').val();

    $.ajax({
        url: 'reports/processes/fetch_attendance.php', // create this PHP endpoint
        type: 'GET',
        data: {
            child: child,
            month: month,
            term_id: term === 'all' ? '' : term
        },
        dataType: 'json',
        success: function(res) {
            const table = $('#attendanceTable').DataTable();
            table.clear();

            res.forEach(a => {
                const status = a.status.toLowerCase();
                let badgeClass = '', textColor = '';
                if(status == 'present'){ badgeClass='bg-success'; textColor='text-white'; }
                else if(status == 'late'){ badgeClass='bg-warning'; textColor='text-dark'; }
                else if(status == 'absent'){ badgeClass='bg-danger'; textColor='text-white'; }
                else if(status == 'excuse'){ badgeClass='bg-primary'; textColor='text-white'; }
                else { badgeClass='bg-secondary'; textColor='text-dark'; }

                table.row.add([
                    a.idcode,
                    a.subject_code,
                    a.section_code,
                    a.date,
                    a.time,
                    a.term_name,
                    `<span class="badge ${badgeClass} ${textColor}">${a.status}</span>`
                ]);
            });

            table.draw();
        },
        error: function() {
            showAlert('Failed to load attendance data', 'danger', 4000);
        }
    });
}

// Bind change events
$('#childFilter, #monthFilter, #termFilter').on('change', loadAttendanceData);

// Load data initially
loadAttendanceData();


    function updateURL() {
        const url = new URL(window.location.href);
        const child = $('#childFilter').val();
        const month = $('#monthFilter').val();
        const term = $('#termFilter').val();

        child ? url.searchParams.set('child', child) : url.searchParams.delete('child');
        month ? url.searchParams.set('month', month) : url.searchParams.delete('month');
        term ? url.searchParams.set('term_id', term) : url.searchParams.delete('term_id');

        window.location.href = url.toString();
    }


function updateExportInputs() {
    $('#semesterInput').val($('#termFilter').val() === 'all' ? '' : $('#termFilter').val());
    $('#childInput').val($('#childFilter').val());
    $('input[name="month"]').val($('#monthFilter').val());
}

$('#childFilter, #monthFilter, #termFilter').on('change', updateExportInputs);
updateExportInputs(); // initialize on page load

     // Keep export button AJAX for emailing
   $('#exportForm').on('submit', function(e){
    e.preventDefault();

    // Get current selections
    let termVal = $('#termFilter').val();
    let childVal = $('#childFilter').val();

    // Update hidden inputs dynamically
    $('#semesterInput').val(termVal === "" ? "" : termVal);
    $('#childInput').val(childVal);

    // Submit via AJAX
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