<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

$s_id = $_SESSION['user_id'];

// Fetch student info including is_Mayor from students_sections
$student_query = "
    SELECT s.s_id, s.s_fname, s.s_lname, ss.is_Mayor, ss.section_id
    FROM students s
    JOIN students_sections ss ON s.s_id = ss.s_id
    WHERE s.s_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $s_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student = $student_result->fetch_assoc();
$full_name = $student['s_fname'] . ' ' . $student['s_lname'];

// Fetch approved devices for this student or for classmates if Mayor
if ($student['is_Mayor']) {
    $device_query = "
        SELECT ad.id, ad.student_id, ad.device_name, ad.device_type, ad.mac_address, ad.status, ad.created_at,
               CONCAT(s.s_fname, ' ', s.s_lname) AS student_name
        FROM approved_devices ad
        JOIN students s ON ad.student_id = s.s_id
        WHERE ad.student_id IN (
            SELECT s_id FROM students_sections WHERE section_id = ?
        )
        ORDER BY ad.created_at DESC
    ";
    $stmt2 = $conn->prepare($device_query);
    $stmt2->bind_param("i", $student['section_id']);
} else {
    $device_query = "
        SELECT id, student_id, device_name, device_type, mac_address, status, created_at,
               CONCAT(s_fname, ' ', s_lname) AS student_name
        FROM approved_devices
        WHERE student_id = ?
        ORDER BY created_at DESC
    ";
    $stmt2 = $conn->prepare($device_query);
    $stmt2->bind_param("i", $s_id);
}

$stmt2->execute();
$devices = $stmt2->get_result();

// Fetch classmates if student is Mayor
$classmates = [];
if ($student['is_Mayor']) {
    $class_query = "
        SELECT s_id, CONCAT(s_fname, ' ', s_lname) as full_name
        FROM students_sections
        WHERE section_id = ? AND s_id != ?
    ";
    $stmt3 = $conn->prepare($class_query);
    $stmt3->bind_param("ii", $student['section_id'], $s_id);
    $stmt3->execute();
    $classmates = $stmt3->get_result();
}
?>

<!-- DataTables + Responsive CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<!-- jQuery + DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

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
<div class="container-fluid py-4">
    <div class="row mb-4">
            <div class="col-12">
                <div class="d-sm-flex align-items-center justify-content-between">
                    <h1 class="h3 text-primary mb-2 mb-sm-0 fw-bold">
                        <i class="bi bi-phone me-2"></i> Approve Devices
                    </h1>
                    <div class="d-flex align-items-center">
                        <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small">
                            <i class="bi bi-clock-history me-1"></i>
                            Last Updated: <span id="last-updated">Just now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"> List of Devices</h5>
        </div>
        <div class="card-body table-responsive">
            <table id="devicesTable" class="table table-striped table-bordered nowrap" style="width:100%">
               <thead>
                   <tr>
                       <th>Student ID</th>
                       <th>Student Name</th>
                       <th>Device Type</th>
                       <th>Device Token</th>
                       <th>Status</th>
                       <?php if($student['is_Mayor']): ?>
                       <th>Action</th>
                       <?php endif; ?>
                       <th>Date Added</th>
                   </tr>
               </thead>
               <tbody>
                   <?php while($row = $devices->fetch_assoc()): ?>
                   <tr>
                       <td><?= htmlspecialchars($row['student_id']); ?></td>
                       <td><?= htmlspecialchars($row['student_name']); ?></td>
                       <td><?= htmlspecialchars($row['device_type']); ?></td>
                       <td><code><?= htmlspecialchars($row['mac_address']); ?></code></td>
                       <td id="status-<?= $row['id']; ?>">
                        <?php if($row['status'] === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif($row['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                        </td>
                       <?php if($student['is_Mayor']): ?>
                       <td>
                            <button class="btn btn-sm btn-primary edit-device-btn" 
                                    data-id="<?= $row['id']; ?>" 
                                    data-name="<?= htmlspecialchars($row['student_name']); ?>" 
                                    data-status="<?= $row['status']; ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editDeviceModal">
                                Edit
                            </button>
                        </td>
                        <?php endif; ?>
                       <td><?= date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
                   </tr>
                   <?php endwhile; ?>
               </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="editDeviceForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editDeviceModalLabel"><i class="bi bi-pencil-square"></i> Edit Device Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" id="edit_device_id" name="id">
          <p>Update device status for <strong id="edit_device_name"></strong></p>
          <div class="d-flex justify-content-around mt-3">
              <button type="button" class="btn btn-success" id="approveBtn">Approve</button>
              <button type="button" class="btn btn-warning text-dark" id="pendingBtn">Pending</button>
              <button type="button" class="btn btn-danger" id="rejectBtn">Reject</button>
          </div>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with full responsiveness and horizontal scrolling
    var table = $('#devicesTable').DataTable({
        responsive: {
            details: {
                type: 'column',
                target: 'tr'
            }
        },
        scrollX: true, // enables horizontal scroll if needed
        columnDefs: [
            { className: 'dtr-control', targets: 0 } // First column triggers responsive child row
        ],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        order: [[6, "desc"]] // Order by Date Added descending
    });

    var deviceId;

    $('.edit-device-btn').click(function() {
        deviceId = $(this).data('id');
        $('#edit_device_id').val(deviceId);
        $('#edit_device_name').text($(this).data('name'));
    });

    function updateStatus(newStatus) {
        $.post('approve_devices/update_device_status.php', {id: deviceId, status: newStatus}, function(response){
            if(response.success){
                $('#editDeviceModal').modal('hide');
                var badgeHtml;
                if(newStatus === 'approved'){
                    badgeHtml = '<span class="badge bg-success">Approved</span>';
                } else if(newStatus === 'pending'){
                    badgeHtml = '<span class="badge bg-warning text-dark">Pending</span>';
                } else {
                    badgeHtml = '<span class="badge bg-danger">Rejected</span>';
                }
                $('#status-' + deviceId).html(badgeHtml);
            } else {
                alert('Failed to update status.');
            }
        }, 'json');
    }

    $('#approveBtn').click(function(){ updateStatus('approved'); });
    $('#pendingBtn').click(function(){ updateStatus('pending'); });
    $('#rejectBtn').click(function(){ updateStatus('rejected'); });
});
</script>

