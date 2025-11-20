<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../includes/db.php');

if(!isset($_SESSION['parent_id'])){
    echo json_encode(['status'=>'error','message'=>'Not logged in']);
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Get the active term_id
$term_query = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$active_term = $term_query->fetch_assoc();
$active_term_id = $active_term['term_id'] ?? null;

if ($active_term_id) {
  $status_stmt = $conn->prepare("
    SELECT 
        ar.id, 
        ar.absent_date, 
        ar.reason, 
        ar.attachment, 
        ar.status,
        s.s_id, 
        CONCAT(s.s_fname, ' ', s.s_lname) AS student_name,
        ss.ss_id,               -- Add this line
        ss.subject_code, 
        sec.section_code,
        ss.schedule_group_id,
        GROUP_CONCAT(DISTINCT 
            CASE 
                WHEN ss2.day_of_week='Sunday' THEN 'SU'
                WHEN ss2.day_of_week='Monday' THEN 'M'
                WHEN ss2.day_of_week='Tuesday' THEN 'T'
                WHEN ss2.day_of_week='Wednesday' THEN 'W'
                WHEN ss2.day_of_week='Thursday' THEN 'TH'
                WHEN ss2.day_of_week='Friday' THEN 'F'
                WHEN ss2.day_of_week='Saturday' THEN 'S'
            END
            ORDER BY FIELD(ss2.day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
            SEPARATOR ''
        ) AS days_abbr,
        DATE_FORMAT(MIN(ss2.start_time), '%h:%i %p') AS start_time,
        DATE_FORMAT(MAX(ss2.end_time), '%h:%i %p') AS end_time
    FROM absent_requests ar
    INNER JOIN students s ON ar.s_id = s.s_id
    INNER JOIN sections_schedules ss ON ar.ss_id = ss.ss_id
    INNER JOIN sections_schedules ss2 
        ON ss.schedule_group_id = ss2.schedule_group_id
        AND ss2.section_id = ss.section_id
        AND ss2.subject_code = ss.subject_code
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ar.p_id = ? 
      AND ss.term_id = ?
    GROUP BY ar.id
    ORDER BY ar.created_at DESC
");


    $status_stmt->bind_param("ii", $parent_id, $active_term_id);
    $status_stmt->execute();
    $requests_result = $status_stmt->get_result();
}
?>



<link rel="stylesheet" href="../../assets/css/content.css">
<link rel="stylesheet" href="../parent/assets/css/content.css">
 <!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- (Optional) Flatpickr Bootstrap theme -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

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
#requestsTable td:nth-child(3) { min-width: 100px; } /* Subject */
#requestsTable th:nth-child(4),
#requestsTable td:nth-child(4) { min-width: 130px; } /* Schedule */
#requestsTable th:nth-child(5),
#requestsTable td:nth-child(5) { min-width: 120px; } /* Date */
/* Keep Status and Attachment compact */
#requestsTable th:nth-child(7),
#requestsTable td:nth-child(7) { width: 80px; }

#requestsTable th:nth-child(8),
#requestsTable td:nth-child(8) { width: 100px; }

/* Ensure edit_absent_date always looks enabled */
#edit_absent_date,
#edit_absent_date:focus {
    background-color: #fff !important;  /* white background */
    color: #212529 !important;          /* normal text color */
    cursor: text !important;            /* normal cursor */
    pointer-events: auto !important;    /* make it clickable */
}

/* So disabled days are light grey, not dark or confusing */
.flatpickr-day.flatpickr-disabled {
    color: #ccc !important;
    opacity: 0.6 !important;
}

/* Highlight available days */
.flatpickr-day:not(.flatpickr-disabled):hover {
    background-color: #e9f5ff !important;
    color: #000 !important;
}
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
        <table id="requestsTable" class="table table-light">
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
    <td>
        <?= htmlspecialchars($row['days_abbr'] . ' ' . $row['start_time'] . '-' . $row['end_time']) ?>
    </td>
    <td><?= htmlspecialchars($row['absent_date']) ?></td>
    <td><?= htmlspecialchars($row['reason']) ?></td>
    <td class="text-center">
        <button class="btn btn-sm view-edit-request fw-bold" 
        style="background: var(--primary); color: var(--tertiary);"
        data-id="<?= $row['id'] ?>"
        data-child="<?= htmlspecialchars($row['student_name']) ?>"
        data-child_id="<?= $row['s_id'] ?>"              
        data-section="<?= htmlspecialchars($row['section_code']) ?>"
        data-subject="<?= htmlspecialchars($row['subject_code']) ?>"
        data-schedule="<?= htmlspecialchars($row['days_abbr'] . ' ' . $row['start_time'] . '-' . $row['end_time']) ?>"
        data-schedule_id="<?= $row['ss_id'] ?>"          
        data-absent_date="<?= htmlspecialchars($row['absent_date'] ?? '') ?>"
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
        <h5 class="modal-title fw-bold text-white">View/Update Absent Request</h5>
        <button type="button" class="btn btn-warning" data-bs-dismiss="modal" aria-label="Close">Close</button>
      </div>
      <div class="modal-body">
        <div class="row">
          <!-- Form Left Half -->
          <div id="formCol" class="col-12 col-lg-6">
            <form id="editAbsentForm" enctype="multipart/form-data">
              <input type="hidden" id="request_id" name="request_id">
              <input type="hidden" id="child_id" name="child_id">
                <input type="hidden" id="schedule_id" name="schedule_id">
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
    <div class="input-group">
        <input type="date" class="form-control" id="edit_absent_date" name="absent_date" required>
        <span class="input-group-text" id="calendarTrigger">
            <i class="bi bi-calendar-event"></i>
        </span>
    </div>
    <small class="form-text text-muted">
        For emergency absences, you can select any date, but an attachment is required.
    </small>
</div>

<script>
    // Make calendar icon open the datepicker
    document.getElementById('calendarTrigger').addEventListener('click', function() {
        document.getElementById('edit_absent_date').focus();
    });
</script>



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

     <!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
<script src="../../assets/js/showAlert.js"></script>
<script src="../assets/js/showAlert.js"></script>
<style>
/* Make all cells wrap text */
#requestsTable th, #requestsTable td {
    white-space: normal !important;
    word-wrap: break-word;
    vertical-align: top;
}

/* Scrollable Reason column */
#requestsTable td:nth-child(6) {
    max-height: 80px;
    overflow-y: auto;
    white-space: normal;
}
.dtBtn{
    background: var(--primary) !important;
    color:white !important;
}

</style>

<script>
$(document).ready(function() {
   $('#requestsTable').each(function() {
    if (!$.fn.DataTable.isDataTable(this)) { // Prevent multiple initialization
        $(this).DataTable({
            responsive: true,
            autoWidth: false,
            scrollY: '50vh',
            scrollCollapse: true,
            paging: true,
            lengthChange: true,
            buttons: [
                { extend: 'print', text: '<i class="bi text-white bi-printer me-1 w-auto"></i> Print', className: 'dtBtn btn btn-sm btn-primary' }
            ],
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
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: 2 },
                { responsivePriority: 3, targets: 7 },
                { responsivePriority: 4, targets: 1 },
                { responsivePriority: 5, targets: 3 },
                { responsivePriority: 6, targets: 4 },
                { responsivePriority: 7, targets: 5 },
                { responsivePriority: 8, targets: 6 }
            ]
        });
    }
});

    // Existing absent dates from PHP
    const unavailableDates = <?= json_encode($existing_dates ?? []) ?>;

    // Map schedule text to weekdays
function getAllowedDays(scheduleText) {
    const dayMap = { 'SU':0,'M':1,'T':2,'W':3,'TH':4,'F':5,'S':6 };
    const activeDays = [];

    // Put longer patterns first so "TH" isn't matched as "T"
    const regex = /(TH|SU|M|T|W|F|S)/gi;

    let match;
    while ((match = regex.exec(scheduleText)) !== null) {
        const key = match[0].toUpperCase();
        if (dayMap[key] !== undefined) activeDays.push(dayMap[key]);
    }

    return activeDays;
}


    // Flatpickr for edit modal
 const editPicker = flatpickr("#edit_absent_date", {
    dateFormat: "Y-m-d",
    disableMobile: true,
    locale: { firstDayOfWeek: 1 },
    minDate: "today",
    disable: unavailableDates, // disables already requested dates
});


    // Modal
    var editAbsentModal = new bootstrap.Modal(document.getElementById('editAbsentModal'));
    let pendingAlert = null;
    

  $(document).on('click', '.view-edit-request', function() {
    var btn = $(this);

    // Fill hidden inputs for submission
    $('#request_id').val(btn.data('id'));
    $('#child_id').val(btn.data('child_id'));       
    $('#schedule_id').val(btn.data('schedule_id'));

    // Fill visible modal fields
    $('#child_name').val(btn.data('child'));
    $('#section_subject').val(btn.data('section') + ' | ' + btn.data('subject'));
    $('#schedule').val(btn.data('schedule'));
    $('#edit_absent_date').val(btn.data('absent_date'));
    $('#edit_reason').val(btn.data('reason'));

    // Handle attachment preview
    var filename = btn.data('attachment') || '';
    const container = $('#current_attachment');
    const formCol = $('#formCol');
    const previewCol = $('#previewCol');
    previewCol.hide();
    formCol.removeClass('col-lg-6').addClass('col-12');
    container.empty();
    $('#attachmentPreviewImg, #attachmentPreviewFrame').hide();

    if(filename.trim() !== '') {
        formCol.removeClass('col-12').addClass('col-lg-6');
        previewCol.show();
        var file = 'uploads/absent_attachments/' + filename;
        var ext = filename.split('.').pop().toLowerCase();
        if(['jpg','jpeg','png','gif'].includes(ext)) {
            $('#attachmentPreviewImg').attr('src', file).show();
        } else if(ext === 'pdf') {
            $('#attachmentPreviewFrame').attr('src', file).show();
        } else {
            container.html('<span class="badge rounded-pill bg-secondary">No preview available</span>');
        }
    } else {
        container.html('<span class="badge rounded-pill bg-success">No attachment provided</span>');
    }

    // Set allowed weekdays in Flatpickr
    const activeDays = getAllowedDays(btn.data('schedule') || '');
    editPicker.setDate(btn.data('absent_date') || null);
    editPicker.set('enable', [
        function(date) {
            const today = new Date();
            const isFuture = date >= new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const ymd = date.toISOString().split('T')[0];
            return isFuture && activeDays.includes(date.getDay()) && !unavailableDates.includes(ymd);
        }
    ]);

    editAbsentModal.show();
});

    // Form submission
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
                    pendingAlert = { message: response.message, type: response.status==='success' ? 'success' : 'primary' };
                    // Update row
                    let rowId = response.updated_row_id;
                    let rowData = response.updated_row_data;
                    if(rowId && rowData){
                        let row = table.row('#row-' + rowId);
                        if(row.node()){
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

    // Bootstrap alert helper
    function showAlert(msg, type='success'){
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.style.opacity = 0;
        alert.style.transform = 'translateY(-20px)';
        alert.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'} me-2"></i>${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(alert);
        setTimeout(()=>{ alert.style.transition='all 0.5s'; alert.style.opacity=1; alert.style.transform='translateY(0)'; },10);
        setTimeout(()=>{ alert.style.opacity=0; alert.style.transform='translateY(-20px)'; setTimeout(()=>alert.remove(),500); },3000);
    }

});
</script>


