<?php
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// ✅ Get filters
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : '';
$selected_term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : '';

// ✅ Fetch all academic terms dynamically
$term_dropdown_query = "
    SELECT 
        t.term_id,
        CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
    FROM academic_terms t
    INNER JOIN academic_years y ON t.ay_id = y.ay_id
    ORDER BY y.year_start DESC, t.semester ASC
";
$term_dropdown = $conn->query($term_dropdown_query);

// ✅ Fetch active academic term
$active_term_query = "
    SELECT 
        CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS active_term
    FROM academic_terms t
    INNER JOIN academic_years y ON t.ay_id = y.ay_id
    WHERE t.is_active = 1
    LIMIT 1
";
$active_term_result = $conn->query($active_term_query);
$active_term = ($active_term_result && $active_term_result->num_rows > 0)
    ? $active_term_result->fetch_assoc()['active_term']
    : 'A.Y. N/A | Term Not Set';

// ✅ Base Query (shows all if no filters)
$attendance_query = "
SELECT 
    a.s_id,
    s.idcode,
    a.subject_code,
    a.section_code,
    a.time_in,
    a.time_out,
    a.status,
    CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
FROM attendance a
INNER JOIN students s ON a.s_id = s.s_id
LEFT JOIN academic_terms t ON a.term_id = t.term_id
LEFT JOIN academic_years y ON t.ay_id = y.ay_id
WHERE a.s_id = ?
";

$params = [$student_id];
$types = "i";

// ✅ Apply filters dynamically
if (!empty($selected_month)) {
    $attendance_query .= " AND MONTH(a.time_in) = ?";
    $params[] = $selected_month;
    $types .= "i";
}
if (!empty($selected_term_id)) {
    $attendance_query .= " AND t.term_id = ?";
    $params[] = $selected_term_id;
    $types .= "i";
}

$attendance_query .= " ORDER BY a.time_in DESC";

// ✅ Execute query safely
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendance_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<style>
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

/* Status badge colors */
.badge {
    font-size: 0.85rem;
    padding: 0.4em 0.7em;
}

/* Empty state styling */
.text-center.py-4, .text-center.py-5 {
    padding: 2rem 1rem;
    color: #6c757d;
}

.text-center.py-4 i, .text-center.py-5 i {
    font-size: 2.5rem;
    color: #dee2e6;
    margin-bottom: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-title {
        font-size: 1rem;
    }

    .card-title i {
        font-size: 1.2rem;
    }
}

/* Button styles */
.btn{
    padding:0.75rem;
    border: none !important;
    font-weight: 500 !important;
    letter-spacing: 0.5px !important;
    border-radius: 8px !important;
    transition: none !important;
    transform: none !important;
    cursor: pointer !important;
    overflow: hidden !important;
}
/* 3D Hover Effect */
.btn:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

/* Optional: active press effect */
.btn:active {
    transform: translateY(0px) scale(0.98);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.btn-primary{
    background:var(--primary) !important;
}

.btn:disabled {
    background: #6c757d !important;
    cursor: not-allowed !important;
}

</style>

<body>
<!-- Attendance History -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center px-4 py-3">
    <div class="mb-2 mb-md-0">
        <h1 class="h3 text-primary mt-2 mb-1 fw-bold">
            <i class="bi bi-calendar3-week me-2"></i> Attendance History
        </h1>
        <small class="text-muted fw-semibold"><?= htmlspecialchars($active_term) ?></small>
    </div>

    <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small d-flex align-items-center">
        <i class="bi bi-clock-history me-1"></i> Last Updated:
        <span id="last-updated" class="ms-1 fw-semibold text-dark">
            <?= htmlspecialchars($_SESSION['last_updated']); ?>
        </span>
    </div>
</div>


<div class="container-fluid mt-3">
    <div class="card shadow-sm">
       <div class="card-header">
    <div class="row align-items-center">
        <!-- Card Title spans full width -->
        <div class="col-12 mb-2">
            <h4 class="card-title mb-0">
                <i class="bi bi-clock-history text-warning"></i> Attendance Records
            </h4>
        </div>

        <!-- Filters: stacked on mobile, side by side on md+ -->
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <label for="termFilter" class="text-white fw-semibold">Academic Term:</label>
            <select id="termFilter" class="form-select form-select-sm">
                <option value="">All Terms</option>
                <?php while ($row = $term_dropdown->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['term_id']) ?>"
                        <?= ($selected_term_id == $row['term_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['term_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <label for="monthFilter" class="text-white fw-semibold">Month:</label>
            <select id="monthFilter" class="form-select form-select-sm">
                <option value="">All Months</option>
                <?php 
                for ($m = 1; $m <= 12; $m++) {
                    $monthName = date('F', mktime(0,0,0,$m,1));
                    $selected = ($selected_month == $m) ? 'selected' : '';
                    echo "<option value='$m' $selected>$monthName</option>";
                }
                ?>
            </select>
        </div>
    </div>
</div>


        <div class="card-body">
              <!-- ✅ Generate Reports Button -->
<div class="d-flex flex-wrap justify-content-center gap-2 mt-1 ">
    <!-- Send to Email Button -->
    <button id="generateReportBtn" class="btn btn-sm btn-primary d-flex align-items-center">
        <i class="bi bi-envelope me-2"></i>Send to Email
    </button>

    <!-- Placeholder for Print & PDF Buttons -->
    <div id="exportButtons" class="d-flex flex-wrap gap-2"></div>
</div>

            <div class="table-responsive">
                <table id="attendanceTable" class="table table-hover table-bordered align-middle">
                    <thead class="card-header text-white">
                        <tr>
                            <th class="text-center dtr-control">ID</th>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Academic Term</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_history as $att): 
                            $status = $att['status'];
                            $badgeClass = match ($status) {
                                'Present' => 'bg-success',
                                'Absent' => 'bg-danger',
                                'Late' => 'bg-warning text-dark',
                                'Excuse' => 'bg-primary',
                                default => 'bg-secondary'
                            };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($att['idcode']) ?></td>
                            <td><?= htmlspecialchars($att['subject_code']) ?></td>
                            <td><?= htmlspecialchars($att['section_code']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($att['time_in']))) ?></td>
                            <td>
                                <?= htmlspecialchars(date('h:i A', strtotime($att['time_in']))) ?> -
                                <?= htmlspecialchars($att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-') ?>
                            </td>
                            <td><?= htmlspecialchars($att['term_name'] ?? $active_term) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ✅ JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>


<script>
$(document).ready(function () {
const table = $('#attendanceTable').DataTable({
  responsive: {
    details: {
        type: 'column',
        target: 0   // first column
    }
},
columnDefs: [
    { className: 'dtr-control', targets: 0 }, // expand icon
    { orderable: false, targets: 0 }
],

    order: [[3, "desc"]],
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
            extend: 'copy',
            text: 'Copy',
            className: 'btn btn-sm  btn-outline-primary'
        },
        {
            extend: 'excel',
            text: 'Excel',
            className: 'btn btn-sm btn-outline-success'
        },
        {
            extend: 'pdf',
            text: 'PDF',
            className: 'btn btn-sm btn-outline-danger'
        },
        {
            extend: 'print',
            text: 'Print',
            className: 'btn btn-sm btn-outline-secondary'
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

    // Move the buttons container into our placeholder
    table.buttons().container().appendTo('#exportButtons');

    // Filters
    $('#monthFilter, #termFilter').on('change', function() {
        const month = $('#monthFilter').val();
        const term = $('#termFilter').val();
        const url = new URL(window.location.href);
        if (month) url.searchParams.set('month', month); else url.searchParams.delete('month');
        if (term) url.searchParams.set('term_id', term); else url.searchParams.delete('term_id');
        window.location.href = url.toString();
    });
});

document.getElementById("generateReportBtn").addEventListener("click", function() {
    fetch("/student/attendance_reports/generate_report.php", {
        method: "POST",
        body: new URLSearchParams({ student_id: "<?= $student_id ?>" })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            showAlert(data.emailSent ? "Report generated! Email sent ✅" : "Report generated! Email failed ❌", "success");
        } else {
            showAlert(data.message || "Error generating report", "error");
        }
    })
    .catch(err => {
        console.error(err);
        showAlert("Something went wrong!", "error");
    });
});

</script>
</body>
</html>
