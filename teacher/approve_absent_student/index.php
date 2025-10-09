<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

// Only allow teacher/admin
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['teacher', 'admin'])) {
    header('Location: ../../login.php');
    exit();
}

// Handle approve/reject actions via GET
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action']; // 'approve' or 'reject'
    $id = intval($_GET['id']);

    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE absent_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        header("Location: approve_absent.php");
        exit();
    }
}

$t_id = $_SESSION['user_id'];

// Fetch only absent requests for sections handled by this teacher
$stmt = $conn->prepare("
    SELECT ar.id, ar.absent_date, ar.reason, ar.attachment, ar.status,
           s.s_id, CONCAT(s.s_fname,' ',s.s_lname) AS student_name,
           ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time,
           sec.section_code,  -- human-readable like BSIT 1A
           p.p_id, CONCAT(p.p_fname,' ',p.p_lname) AS parent_name
    FROM absent_requests ar
    INNER JOIN students s ON ar.s_id = s.s_id
    INNER JOIN parents p ON ar.p_id = p.p_id
    INNER JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.teacher_id = ?
    ORDER BY ar.created_at DESC
");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$results = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Absent Requests</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<style>
    body{
        overflow: hidden;
    }
    /* Card styling */
.card {
    max-width: calc(100% - 230px);
    top: -10px;
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Card body should NOT scroll */
.card-body {
    overflow: visible; /* allow table scroll independently */

}

th{
    background: var(--primary) !important;
    color: white;
}

/* Card header */
.card-header {
    background: var(--primary) !important;
    color: var(--tertiary) !important;
    border-bottom: none;
    padding: 2rem 1.25rem;
    border-radius: 10px 10px 0 0 !important;
}

.btn-primary{
    padding: 0.5rem !important;
}
</style>
<link rel="stylesheet" href="assets/css/content.css">
<body>
<div class="container-fluid mt-5">
    <div class="card shadow-sm w-100">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Absent Requests</h5>
        </div>
        <div class="card-body">
            <table id="absentTable" class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Parent</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Schedule</th>
                        <th>Date of Absence</th>
                        <th>Reason</th>
                        <th>Attachment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $results->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['parent_name']) ?></td>
                        <td><?= htmlspecialchars($row['section_code']) ?></td>
                        <td><?= htmlspecialchars($row['subject_code']) ?></td>
                        <td><?= htmlspecialchars($row['day_of_week'].' '.date("H:i", strtotime($row['start_time'])).'-'.date("H:i", strtotime($row['end_time']))) ?></td>
                        <td><?= htmlspecialchars($row['absent_date']) ?></td>
                        <td><?= htmlspecialchars($row['reason']) ?></td>
                        <td class="text-center">
                        <?php if($row['attachment']): ?>
                           <a href="#" class="btn btn-primary view-attachment" 
                            data-file="../parent/uploads/absent_attachments/<?= urlencode(basename($row['attachment'])) ?>">
                            <i class="bi bi-eye"></i>
                            </a>
                        <?php else: ?>
                           <small text class="alert alert-info p-1">No attachment provided</small>
                        <?php endif; ?>
                    </td>

                        <td>
                            <?php if($row['status'] === 'Pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif($row['status'] === 'Approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td>
                        <?php if($row['status'] === 'Pending'): ?>
                            <button class="btn btn-success btn-sm action-btn" data-action="approve" data-id="<?= $row['id'] ?>">
                                <i class="bi bi-check-circle"></i> OK
                            </button>
                            <button class="btn btn-danger btn-sm action-btn" data-action="reject" data-id="<?= $row['id'] ?>">
                                <i class="bi bi-x-circle"></i> NO
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>Done</button>
                        <?php endif; ?>
                    </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Attachment Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <iframe id="attachmentFrame" src="" style="width:100%;height:500px; display:none;" frameborder="0"></iframe>
        <img id="attachmentImage" src="" style="max-width:100%; max-height:500px; display:none;" />
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toast-message"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>





<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function(){
    $('#absentTable').DataTable({
    scrollY: '50vh',        // Set vertical scroll height
    scrollCollapse: true,   // Collapse if rows are less than scrollY
    paging: true,           // Enable pagination
    responsive: true        // Optional: make it responsive
});


   const toastEl = new bootstrap.Toast(document.getElementById('toast'));

    $('.action-btn').click(function() {
        const btn = $(this);
        const action = btn.data('action');
        const id = btn.data('id');

        $.ajax({
            url: 'approve_absent_student/process_request.php',
            type: 'POST',
            data: { action, id },
            success: function(res) {
                if(res.status === 'success'){
                    // Update table row
                    const row = btn.closest('tr');
                    row.find('td:nth-child(9)').html(action === 'approve' 
                        ? '<span class="badge bg-success">Approved</span>' 
                        : '<span class="badge bg-danger">Rejected</span>');
                    row.find('td:nth-child(10)').html('<button class="btn btn-secondary btn-sm" disabled>Done</button>');

                    // Show toast
                    $('#toast-message').text(res.message);
                    if(action === 'approve') {
                        $('#toast').removeClass('bg-danger').addClass('bg-success');
                    } else {
                        $('#toast').removeClass('bg-success').addClass('bg-danger');
                    }
                    toastEl.show();
                } else {
                    alert(res.message);
                }
            },
            error: function() {
                alert('Something went wrong');
            }
        });
    });

  // Show attachment in modal
    $('.view-attachment').click(function(e){
        e.preventDefault();
        var file = $(this).data('file');
        var ext = file.split('.').pop().toLowerCase();

        if(['jpg','jpeg','png','gif'].includes(ext)){
            $('#attachmentFrame').hide();
            $('#attachmentImage').attr('src', file).show();
        } else if(ext === 'pdf'){
            $('#attachmentImage').hide();
            $('#attachmentFrame').attr('src', file).show();
        } else {
            alert('Unsupported file type.');
            return;
        }

        $('#attachmentModal').modal('show');
    });

    // Clear modal on close
    $('#attachmentModal').on('hidden.bs.modal', function () {
        $('#attachmentFrame').attr('src','').hide();
        $('#attachmentImage').attr('src','').hide();
    });
});


</script>
</body>
</html>
