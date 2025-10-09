<?php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$t_id = $_SESSION['user_id'];

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE t_id = ?");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch the teacher's section from sections_advisors + sections
$stmt = $conn->prepare("
    SELECT sec.section_name, sec.section_code
    FROM sections_advisors sa
    JOIN sections sec ON sa.section_id = sec.section_id
    WHERE sa.t_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$sectionInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch advisory students (include ss_id for mayor assignment)
$query = "
    SELECT 
        ss.ss_id,
        ss.s_id, 
        ss.s_fname, 
        ss.s_lname,
        ss.s_mname,
        ss.s_suffix, 
        ss.is_Mayor,
        sec.section_code, 
        sec.section_name
    FROM students_sections ss
    JOIN sections sec ON ss.section_id = sec.section_id
    JOIN sections_advisors sa ON sec.section_id = sa.section_id
    WHERE sa.t_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $t_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>


<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

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

body, main {
    overflow-y: hidden; /* disable horizontal scroll */
}

/* Main content */
main {
    margin-left: 5px;
    width: calc(100% - 260px);
  
}

/* Container fluid */
.container-fluid {
    width: 100%;
    padding: 0.5rem;
    margin: 0 auto;
    overflow-x: hidden;
}

/* Card styling */
.card {
    border: none;
    border-radius: var(--card-border-radius);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04), 0 8px 16px rgba(0, 0, 0, 0.08);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    background: #fff;
    margin-bottom: 1rem;
    width:100%; /* fixed max size for large devices */
    margin-left: auto;
    margin-right: auto;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08), 0 12px 24px rgba(0, 0, 0, 0.12);
}

/* Profile header */
.profile-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1.5rem;
}

/* Avatar */
.profile-avatar {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.15);
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 1px;
}

/* Profile info */
.profile-info h1 {
    font-size: 1.25rem;
    margin: 0;
    font-weight: 600;
}

.profile-info p {
    margin: 0.25rem 0 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

/* Table responsiveness */
.table-responsive {
    overflow-x: auto;
}

.table thead {
    background: var(--primary);
    color: white;
}
#attendanceTable {
    width: 100% !important;
    table-layout: fixed;
    text-align: center;
}





/* Tablet adjustments */
@media (max-width: 992px) {
    body, main {
        overflow-y: auto; /* enable horizontal scroll if content is wider than screen */
    }
    .profile-header {
        justify-content: center;
        text-align: center;
    }

    .profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 1.25rem;
    }

    .profile-info h1 {
        font-size: 1.1rem;
    }

    .profile-info p {
        font-size: 0.85rem;
    }
}

/* Mobile adjustments */
@media (max-width: 576px) {
    main {
        margin-left: 0;
        width: 100%;
        padding: 0.25rem;
    }

    .card {
        margin: 0.5rem;
        max-width: 100%; /* full width for mobile */
    }

    .table thead {
        font-size: 0.85rem;
    }

    .table td,
    .table th {
        font-size: 0.8rem;
        padding: 0.35rem;
    }
}

</style>

<main>
    <!-- Assign / Change Mayor Button -->
    <div class="d-flex justify-content-start mb-3">
        <?php
        // Check if a mayor already exists in this teacher’s advisory
        $checkMayor = $conn->prepare("
            SELECT ss.s_fname, ss.s_mname, ss.s_lname, ss.s_suffix
            FROM students_sections ss
            JOIN sections sec ON ss.section_id = sec.section_id
            JOIN sections_advisors sa ON sec.section_id = sa.section_id
            WHERE sa.t_id = ? AND ss.is_Mayor = 1
            LIMIT 1
        ");
        $checkMayor->bind_param("i", $t_id);
        $checkMayor->execute();
        $mayorResult = $checkMayor->get_result();
        $currentMayor = $mayorResult->fetch_assoc();
        $checkMayor->close();

        if ($currentMayor): ?>
            <button class="btn btn-warning" id="changeMayorBtn" data-bs-toggle="modal" data-bs-target="#assignMayorModal">
                <i class="bi bi-arrow-repeat"></i> Change Mayor (<?= htmlspecialchars($currentMayor['s_fname'] . ' ' . $currentMayor['s_mname'] . ' ' . $currentMayor['s_lname'] . ' ' . $currentMayor['s_suffix']) ?>)
            </button>
        <?php else: ?>
            <button class="btn btn-primary" id="assignMayorBtn" data-bs-toggle="modal" data-bs-target="#assignMayorModal">
                <i class="bi bi-person-plus"></i> Assign Mayor
            </button>
        <?php endif; ?>
    </div>

    <div class="container-fluid">
        <div class="row g-0">
            <div class="col-md-12">
                <div class="card">
                    <!-- Teacher Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php
                            $initials = strtoupper(substr($teacher['t_fname'] ?? '', 0, 1) . substr($teacher['t_lname'] ?? '', 0, 1));
                            echo htmlspecialchars($initials);
                            ?>
                        </div>
                        <div class="profile-info">
                            <h1><?= htmlspecialchars($teacher['t_fname'] . ' ' . $teacher['t_lname']) ?></h1>
                             <p><?= htmlspecialchars($sectionInfo['section_code']) ?> Department</p>
                        </div>
                    </div>

                    <!-- Advisory Table -->
                    <div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="bi bi-people-fill me-2"></i> My Advisory Class</h5>
                        <div class="table-responsive">
                            <table id="advisoryTable" class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Mayor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                $i = 1;
                                $result->data_seek(0); // reset pointer in case it was used before
                                while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?></td>
                                        <td><?= htmlspecialchars($row['section_code']) ?></td>
                                        <td>
                                            <?php if ($row['is_Mayor']): ?>
                                                <span class="badge bg-success">Mayor</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                           <button class="btn btn-sm btn-outline-success view-attendance" 
                                                data-id="<?= $row['s_id'] ?>" 
                                                data-name="<?= htmlspecialchars($row['s_fname'].' '.$row['s_lname']) ?>">
                                            <i class="bi bi-calendar-check"></i> View Attendance
                                        </button>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Assign/Change Mayor Modal -->
<div class="modal fade" id="assignMayorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-lg">
      <div class="modal-header profile-header">
        <h5 class="modal-title fw-bold">Select Student for Mayor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="list-group">
          <?php 
          $result->data_seek(0); // reset result pointer
          while ($row = $result->fetch_assoc()): ?>
            <label class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?>
                <small class="text-muted">(<?= htmlspecialchars($row['section_code']) ?>)</small>
              </div>
              <div>
                <?php if ($row['is_Mayor']): ?>
                  <span class="badge bg-success me-2">Current Mayor</span>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-primary assign-mayor" data-id="<?= $row['ss_id'] ?>" data-student="<?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?>" 
                 data-section="<?= htmlspecialchars($row['section_code']) ?>">Assign</button>
              </div>
            </label>
          <?php endwhile; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4">
      <div class="modal-header profile-header">
        <div class="profile-avatar">
          <i class="bi bi-calendar-check fs-3"></i>
        </div>
        <div class="profile-info">
          <h5 class="modal-title fw-bold" id="attendanceModalLabel">Attendance</h5>
          <p class="mb-0" id="attendanceModalStudentName">Student Attendance Records</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div class="table-responsive">
          <table class="table table-bordered text-center" id="attendanceTable">
        <thead class="table-dark">
        <tr class="text-center">
            <th>#</th>
            <th>Subject Code</th>
            <th>Section Code</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Status</th>
        </tr>
        </thead>
    <tbody id="attendanceListContainer">
      <!-- JS will populate rows here -->
      <!-- if no records: <tr><td colspan="6" class="text-center">No records found</td></tr> -->
    </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Assign Modal -->
<div class="modal fade" id="confirmAssignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header profile-header">
        <h5 class="modal-title fw-bold">Confirm Assignment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmAssignBody">
        Are you sure you want to assign this student as the <strong>Mayor</strong>?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmAssignBtn">Yes, Assign</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 2000">
  <div id="actionToast" class="toast align-items-center text-white bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastMessage">Success</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- ADD THIS: Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    let selectedSSId = null;
    const attendanceCache = new Map();

    // Initialize advisory table
    $('#advisoryTable').DataTable({
        responsive: true,
        scrollY: '650px',
        scrollCollapse: true,
        paging: true,
        autoWidth: false
    });

    // View Attendance button click
    $(document).on('click', '.view-attendance', function () {
        const s_id = $(this).data('id');
        const studentName = $(this).data('name');
        const container = $('#attendanceListContainer');
        const modalEl = document.getElementById('attendanceModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
         // Show modal
        
        modal.show();
        
        // Update modal title
        $('#attendanceModalStudentName').text(`Attendance of ${studentName}`);

       

        // Show loading spinner
        container.html(`<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`);

        // Use cached content if available
        if (attendanceCache.has(s_id)) {
            container.html(attendanceCache.get(s_id));
            initAttendanceTable();
            return;
        }

        // Fetch attendance rows from backend
        fetch(`advisory_class/view_attendance.php?s_id=${s_id}`)
            .then(res => res.text())
            .then(html => {
                container.html(html);
                attendanceCache.set(s_id, html);
                initAttendanceTable();
            })
            .catch(err => {
                container.html('<tr><td colspan="6" class="text-center">Failed to load attendance</td></tr>');
                console.error(err);
            });
    });

function initAttendanceTable() {
    // Destroy previous instance if exists
    if ($.fn.DataTable.isDataTable('#attendanceTable')) {
        $('#attendanceTable').DataTable().destroy();
    }
    

    // Only initialize if there is more than one row AND not just the "no records" message
    const rows = $('#attendanceListContainer tr');
    if (rows.length === 0) return;

    // Check if first row is the no-record message
    if (rows.length === 1 && $(rows[0]).find('td').length === 1) return;

    $('#attendanceTable').DataTable({
        responsive: true,
        scrollY: '400px',
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        autoWidth:true,
        columnDefs: [
            { orderable: false, targets: 0 } // first column (#) not sortable
        ]
    });
}
    // Cleanup attendance modal on close
    $('#attendanceModal').on('hidden.bs.modal', function(){
        if ($.fn.DataTable.isDataTable('#attendanceTable')) {
            $('#attendanceTable').DataTable().destroy();
        }
        $('#attendanceListContainer').html(''); // clear content
    });

    // Assign Mayor logic
    $(document).on('click', '.assign-mayor', function () {
        selectedSSId = $(this).data('id');
        const selectedStudentName = $(this).data('student');
        const section_name = $(this).data('section');

        $('#confirmAssignBody').html(
            `Are you sure you want to assign <strong>${selectedStudentName}</strong> as the new <strong>Mayor of ${section_name}</strong>?`
        );

        const assignMayorModalEl = document.getElementById('assignMayorModal');
        const assignMayorModal = bootstrap.Modal.getInstance(assignMayorModalEl);
        if (assignMayorModal) {
            $(assignMayorModalEl).fadeOut(200, function () { assignMayorModal.hide(); });
            assignMayorModalEl.addEventListener('hidden.bs.modal', function () {
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmAssignModal'));
                confirmModal.show();
            }, { once: true });
        } else {
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmAssignModal'));
            confirmModal.show();
        }
    });

    $('#confirmAssignBtn').on('click', function () {
        if (!selectedSSId) return;

        fetch(`advisory_class/assign_mayor.php`, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `ss_id=${selectedSSId}`
        })
        .then(res => res.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('confirmAssignModal')).hide();
            const toastEl = $('#actionToast');
            const toastBody = $('#toastMessage');
            toastBody.text(data.message);

            const toast = new bootstrap.Toast(document.getElementById('actionToast'));
            if (data.status === "success") {
                toastEl.removeClass("bg-danger").addClass("bg-success");
                toast.show();
                setTimeout(() => location.reload(), 1500);
            } else {
                toastEl.removeClass("bg-success").addClass("bg-danger");
                toast.show();
            }
        });
    });

});
</script>







