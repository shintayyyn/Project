<?php
// Fetch submitted absent requests for this parent
$status_stmt = $conn->prepare("
    SELECT ar.id, ar.absent_date, ar.reason, ar.attachment, ar.status,
           s.s_id, CONCAT(s.s_fname, ' ', s.s_lname) AS student_name,
           ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time,
           sec.section_code
    FROM absent_requests ar
    INNER JOIN students s ON ar.s_id = s.s_id
    INNER JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ar.p_id = ?
    ORDER BY ar.created_at DESC
");
$status_stmt->bind_param("i", $parent_id);
$status_stmt->execute();
$requests_result = $status_stmt->get_result();
?>
<link rel="stylesheet" href="../../assets/css/content.css">
<link rel="stylesheet" href="../parent/assets/css/content.css">
<!-- DataTables Responsive & Buttons CSS/JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.7/css/buttons.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>

<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.3.7/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.7/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.7/js/buttons.colVis.min.js"></script>
<style>
/* Make all cells wrap text */
#requestsTable th, #requestsTable td {
    white-space: normal !important;
    word-wrap: break-word;
    vertical-align: top;
}



/* Reason column scrollable but aligned */
#requestsTable td:nth-child(6) {
    max-height: 80px; /* adjust height */
    overflow-y: auto;
    white-space: normal;
}

/* Optional: adjust other columns */
#requestsTable th:nth-child(2),
#requestsTable td:nth-child(2) { min-width: 100px; } /* Section */
#requestsTable th:nth-child(3),
#requestsTable td:nth-child(3) { min-width: 120px; } /* Subject */
#requestsTable th:nth-child(4),
#requestsTable td:nth-child(4) { min-width: 150px; } /* Schedule */
#requestsTable th:nth-child(5),
#requestsTable td:nth-child(5) { min-width: 120px; } /* Date */
/* Keep Status and Attachment compact */
#requestsTable th:nth-child(7),
#requestsTable td:nth-child(7) { width: 80px; }

#requestsTable th:nth-child(8),
#requestsTable td:nth-child(8) { width: 100px; }


</style>
<?php include(__DIR__ . '/../../includes/parent_header.php'); ?>
<div class="alert-container" id="alertContainer"></div>  
<center>
<div class="card shadow-sm mt-3 text-start w-100">
    <div class="card-header">
        <h4 class=" fw-bold mb-0">Absent Request Status</h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table id="requestsTable" class="table table-bordered table-striped">
            <thead>
                <tr class="text-center">
                    <th>Child</th>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Schedule</th>
                    <th>Date of Absence</th>
                    <th>Reason</th>
                    <th>Attachment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $requests_result->fetch_assoc()): ?>
                <tr id="row-<?= $row['id'] ?>">
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['section_code']) ?></td>
                    <td><?= htmlspecialchars($row['subject_code']) ?></td>
                    <td><?= htmlspecialchars($row['day_of_week'].' '.date("H:i", strtotime($row['start_time'])).'-'.date("H:i", strtotime($row['end_time']))) ?></td>
                    <td><?= htmlspecialchars($row['absent_date']) ?></td>
                    <td><?= htmlspecialchars($row['reason']) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm view-edit-request fw-bold" 
                                style="background: var(--primary); color: var(--tertiary);"
                                data-id="<?= $row['id'] ?>"
                                data-child="<?= htmlspecialchars($row['student_name']) ?>"
                                data-section="<?= htmlspecialchars($row['section_code']) ?>"
                                data-subject="<?= htmlspecialchars($row['subject_code']) ?>"
                                data-schedule="<?= $row['day_of_week'].' '.date("H:i", strtotime($row['start_time'])).'-'.date("H:i", strtotime($row['end_time'])) ?>"
                                data-absent_date="<?= $row['absent_date'] ?>"
                                data-reason="<?= htmlspecialchars($row['reason']) ?>"
                                data-attachment="<?= basename($row['attachment']) ?>">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </td>
                    <td class="text-center">
                        <?php if($row['status'] === 'Pending'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php elseif($row['status'] === 'Approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</center>

<div class="modal fade" id="editAbsentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">View/Update Absent Request</h5>
        <button type="button" class="btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <!-- Form Left Half -->
          <div id="formCol" class="col-12 col-lg-6">
            <form id="editAbsentForm" enctype="multipart/form-data">
              <input type="hidden" id="request_id" name="request_id">
              <div class="mb-3">
                  <label class="form-label">Child</label>
                  <input type="text" id="child_name" class="form-control" disabled>
              </div>
              <div class="mb-3">
                  <label class="form-label">Section & Subject</label>
                  <input type="text" id="section_subject" class="form-control" disabled>
              </div>
              <div class="mb-3">
                  <label class="form-label">Schedule</label>
                  <input type="text" id="schedule" class="form-control" disabled>
              </div>
              <div class="mb-3">
                  <label for="edit_absent_date" class="form-label">Date of Absence</label>
                  <input type="date" class="form-control" id="edit_absent_date" name="absent_date" required>
              </div>
              <div class="mb-3">
                  <label for="edit_reason" class="form-label">Reason</label>
                  <textarea class="form-control" id="edit_reason" name="reason" rows="3" required></textarea>
              </div>
              <div class="mb-3">
                  <label class="form-label">Attachment</label>
                  <div id="current_attachment" class="mb-2"></div>
                  <input type="file" class="form-control" id="edit_attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                  <small class="text-muted">Uploading a new file will replace the current one.</small>
              </div>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
          </div>

          <!-- Preview Right Half -->
          <div id="previewCol" class="col-12 col-lg-6 text-center">
            <h5>Current Attachment</h5>
            <iframe id="attachmentPreviewFrame" style="width:100%; height:500px;" frameborder="0"></iframe>
            <img id="attachmentPreviewImg" src="" style="max-width:100%; max-height:500px; display:none;">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
<script src="../../assets/js/showAlert.js"></script>
<script src="../parent/assets/js/showAlert.js"></script>
<script>
$(document).ready(function() {
var table = $('#requestsTable').DataTable({
    responsive: true,
    scrollY: '40vh',
    scrollCollapse: true,
    paging: true,
    autoWidth: false,
    columnDefs: [
        { responsivePriority: 1, targets: 0 }, // Child
        { responsivePriority: 2, targets: 2 }, // Subject
        { responsivePriority: 3, targets: 7 }, // Status
        { responsivePriority: 4, targets: 1 }, // Section
        { responsivePriority: 5, targets: 3 }, // Schedule
        { responsivePriority: 6, targets: 4 }, // Date
        { responsivePriority: 7, targets: 5 }, // Reason
        { responsivePriority: 8, targets: 6 }  // Attachment
    ]
});


    var editAbsentModal = new bootstrap.Modal(document.getElementById('editAbsentModal'));
    let pendingAlert = null;

    $(document).on('click', '.view-edit-request', function() {
    var btn = $(this);

    $('#request_id').val(btn.data('id'));
    $('#child_name').val(btn.data('child'));
    $('#section_subject').val(btn.data('section') + ' | ' + btn.data('subject'));
    $('#schedule').val(btn.data('schedule'));
    $('#edit_absent_date').val(btn.data('absent_date'));
    $('#edit_reason').val(btn.data('reason'));

    var filename = btn.data('attachment') || '';
    const container = $('#current_attachment');
    const formCol = $('#formCol');
    const previewCol = $('#previewCol');

    // Reset
    previewCol.hide();
    formCol.removeClass('col-lg-6').addClass('col-12');
    container.empty();
    $('#attachmentPreviewImg, #attachmentPreviewFrame').hide();

    if (filename.trim() !== '') {
        // Show preview
        formCol.removeClass('col-12').addClass('col-lg-6');
        previewCol.show();

        var file = 'uploads/absent_attachments/' + filename;
        var ext = filename.split('.').pop().toLowerCase();

        if (['jpg','jpeg','png','gif'].includes(ext)) {
            $('#attachmentPreviewImg').attr('src', file).show();
            $('#attachmentPreviewFrame').hide();
        } else if (ext === 'pdf') {
            $('#attachmentPreviewFrame').attr('src', file).show();
            $('#attachmentPreviewImg').hide();
        } else {
            container.html('<span class="badge rounded-pill bg-secondary">No preview available for this file type</span>');
        }
    } else {
        // No attachment
        container.html('<span class="badge rounded-pill bg-success">No attachment provided</span>');
    }

    editAbsentModal.show();
});


    $('#editAbsentForm').submit(function(e){
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: 'request_status/update_absent.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response){
                if(response.status === 'success' || response.status === 'primary'){
                    pendingAlert = { message: response.message, type: response.status === 'success' ? 'success' : 'primary' };

                    // Update the row in the table without reload
                    let rowId = response.updated_row_id; // make sure PHP returns this
                    let rowData = response.updated_row_data; // PHP should return updated row as an array
                    if(rowId && rowData){
                        let row = table.row('#row-' + rowId);
                        if(row.node()){ // make sure row exists
                            row.data(rowData).invalidate().draw(false);
                        }
                    }
                    $('#editAbsentModal').modal('hide');
                } else {
                    showAlert('Error: ' + response.message, 'danger');
                }
            },
            error: function(){
                showAlert('Unexpected error occurred.', 'danger');
            }
        });
    });

    $('#editAbsentModal').on('hidden.bs.modal', function () {
        if(pendingAlert){
            showAlert(pendingAlert.message, pendingAlert.type);
            pendingAlert = null;
        }
    });
});

</script>
