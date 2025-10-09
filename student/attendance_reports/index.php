<?php
require_once '../includes/db.php'; // Adjust path if necessary

// Ensure student is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch all attendance for this student
$attendance_query = "
SELECT a.s_id, a.subject_code, a.section_code, a.time_in, a.time_out, a.status
FROM attendance a
WHERE a.s_id = ?
ORDER BY a.time_in DESC
";

$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
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

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
.btn-primary {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
    border: none !important;
    padding: 0.875rem 2rem !important;
    font-weight: 500 !important;
    letter-spacing: 0.5px !important;
    border-radius: 8px !important;
    transition: none !important;
    transform: none !important;
    cursor: pointer !important;
    /* position: relative !important; */
    overflow: hidden !important;
}

.btn-primary:disabled {
    background: #6c757d !important;
    cursor: not-allowed !important;
}

</style>
<body>
<!-- Attendance History -->
<div class="d-flex justify-content-between align-items-center px-4 py-3 ">
                    <h1 class="h3 text-primary mt-2 mb-sm-0 fw-bold">
                        <i class="bi bi-calendar3-week me-2"></i>Attendance History
                    </h1>
                    <div class="d-flex align-items-center">
                        <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small">
                            <i class="bi bi-clock-history me-1"></i>
                            Last Updated: <span id="last-updated">Just now</span>
                        </div>
                    </div>
</div>
<div class="container-fluid mt-3 d-flex justify-content-center">
    <div class="card shadow-sm w-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history me-2"></i>All Attendance Records
            </h5>
            <div class="d-flex justify-content-start mb-3 align-items-center">
    <label for="monthFilter" class="text-white me-2 fw-semibold">Filter by Month:</label>
    <select id="monthFilter" class="form-select w-auto">
        <option value="">All Months</option>
        <?php 
        // Generate month options
        for ($m=1; $m<=12; $m++) {
            $monthName = date('F', mktime(0,0,0,$m,1));
            echo "<option value='$m'>$monthName</option>";
        }
        ?>
    </select>
</div>

        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="attendanceTable" class="table table-hover table-bordered">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Subject</th>
                            <th>Section</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_history as $att): ?>
                        <?php
                            $status = $att['status'];
                            $badgeClass = ($status === 'Present') ? 'bg-success' : (($status === 'Absent') ? 'bg-danger' : 'bg-secondary');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($att['s_id']) ?></td>
                            <td><?= htmlspecialchars($att['subject_code']) ?></td>
                            <td><?= htmlspecialchars($att['section_code']) ?></td>
                            <td><?= htmlspecialchars($att['time_in'] ? date('M d, Y', strtotime($att['time_in'])) : 'N/A') ?></td>
                            <td>
                                <?= htmlspecialchars($att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-') ?>
                                -
                                <?= htmlspecialchars($att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-') ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ✅ Generate Reports Button -->
            <div class="mt-5 text-start">
                <button id="generateReportBtn" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Generate Reports
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ✅ Bootstrap Toast (Top End) -->
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="toastMsg" class="toast text-bg-success" role="alert" data-bs-delay="3000">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
document.getElementById("generateReportBtn").addEventListener("click", function() {
    fetch("/student/attendance_reports/generate_report.php", {
        method: "POST",
        body: new URLSearchParams({ student_id: "<?= $student['s_id'] ?? '' ?>" })
    })
    .then(res => res.json())
    .then(data => {
        let toastEl = document.getElementById("toastMsg");
        let toast = new bootstrap.Toast(toastEl);

        if (data.status === "success") {
            toastEl.classList.remove("text-bg-danger");
            toastEl.classList.add("text-bg-success");
            toastEl.querySelector(".toast-body").textContent =
                "Report generated! " + (data.emailSent ? "Email sent ✅" : "Email failed ❌");
            toast.show();

        } else {
            toastEl.classList.remove("text-bg-success");
            toastEl.classList.add("text-bg-danger");
            toastEl.querySelector(".toast-body").textContent = data.message || "Error generating report";
            toast.show();
        }
    })
    .catch(err => console.error(err));
});
</script>



<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        "order": [[3, "desc"]], // Order by date descending
        "pageLength": 25,
        "lengthMenu": [10, 25, 50, 100],
        "responsive": true
    });
    $('#monthFilter').on('change', function() {
    const selectedMonth = $(this).val();
    const url = new URL(window.location.href);
    if (selectedMonth) {
        url.searchParams.set('month', selectedMonth);
    } else {
        url.searchParams.delete('month');
    }
    window.location.href = url.toString();
});

});
</script>
</body>
</html>
