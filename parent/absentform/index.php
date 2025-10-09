<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Fetch children for this parent
$stmt = $conn->prepare("
    SELECT s.s_id, CONCAT(s.s_fname, ' ', s.s_lname) AS full_name
    FROM students s
    INNER JOIN parent_student ps ON s.s_id = ps.s_id
    WHERE ps.p_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children_result = $stmt->get_result();

$selected_child_id = $_GET['child_id'] ?? null;
$selected_schedule_id = $_GET['schedule_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Absent Request</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.5.2/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/content.css">
    <link rel="stylesheet" href="../parent/assets/css/content.css">
</head>
<style>
    /* Force Select2 to look like Bootstrap form-control */
.select2-container--bootstrap-5 .select2-selection {
    display: flex !important;
    align-items: center !important;
    height: calc(2.5rem + 2px) !important; /* same height as .form-select */
    padding: 0.375rem 0.75rem !important;
    font-size: 1rem !important;
    line-height: 1.5 !important;
    border-radius: 0.375rem !important;
    border: 1px solid #ced4da !important;
    background-color: #fff !important;
    box-shadow: none !important;
}

/* Make sure text is inside like normal select */
.select2-container--bootstrap-5 .select2-selection__rendered {
    margin: 0 !important;
    padding: 0 !important;
    color: #212529 !important;
    line-height: 1.5 !important;
    display: flex !important;
    align-items: center !important;
    height: 100% !important;
}

/* Proper arrow alignment */
.select2-container--bootstrap-5 .select2-selection__arrow {
    height: 100% !important;
    top: 0 !important;
    right: 0.75rem !important;
    display: flex !important;
    align-items: center !important;
}

</style>
<body>
    <?php include(__DIR__ . '/../../includes/parent_header.php'); ?>
     <div class="alert-container" id="alertContainer"></div>

    <center>
        <div class="card shadow-sm mt-3 text-start w-100">
            <div class="card-header">
                <h4 class="mb-0 fw-bold">File Absent Request</h4>
            </div>
            <div class="card-body">
                <form id="absentForm" enctype="multipart/form-data">
                    <div class="row">
                    <!-- Select Child -->
                    <div class="col-6">
                        <div class="mb-3">
                            <label for="child_id" class="form-label">Select Child</label>
                            <select id="child_id" name="child_id" class="form-select" required>
                                <option value="">-- Choose Child --</option>
                                <?php while ($child = $children_result->fetch_assoc()): ?>
                                    <option value="<?= $child['s_id'] ?>" <?= ($selected_child_id == $child['s_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($child['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        </div>

                    <!-- Select Schedule -->
                    <div class="col-6">
                        <div class="mb-3">
                            <label for="schedule_id" class="form-label">Select Schedule</label>
                            <select id="schedule_id" name="schedule_id" class="form-select" required>
                                <option value="">-- Choose Schedule --</option>
                            </select>
                        </div>
                    </div>
                </div>

                    <!-- Date -->
                    <div class="mb-3">
                        <label for="absent_date" class="form-label">Date of Absence</label>
                        <input type="date" class="form-control" id="absent_date" name="absent_date" required>
                    </div>
                    <!-- Reason -->
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Absence</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Provide reason for absence..." required></textarea>
                    </div>
                    <!-- Attachment -->
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Allowed formats: PDF, JPG, PNG</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Absent Request</button>
                </form>
            </div>
        </div>
    </center>
    <!-- JS Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/showAlert.js"></script>
    <script src="../parent/assets/js/showAlert.js"></script>
    <script>
    $(document).ready(function(){

        // Initialize Select2 with placeholders
        $('#child_id').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Choose Child --',
            allowClear: true,
            width: '100%'
        });

        $('#schedule_id').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Choose Schedule --',
            allowClear: true,
            width: '100%'
        });

        var selectedChild = "<?= $selected_child_id ?>";
        var selectedSchedule = "<?= $selected_schedule_id ?>";

        if(selectedChild) fetchSchedules(selectedChild, selectedSchedule);

        $('#child_id').on('change', function(){
            var s_id = $(this).val();
            if(!s_id) {
                $('#schedule_id').empty().append('<option></option>').trigger('change');
                return;
            }
            fetchSchedules(s_id, null);
        });

        function fetchSchedules(s_id, scheduleToSelect){
            $.ajax({
                url:'absentform/get_child_schedules.php',
                type:'GET',
                data:{ s_id:s_id },
                dataType:'json',
                success:function(schedules){
                    var $sel = $('#schedule_id');
                    $sel.empty().append('<option></option>');
                    if(schedules.length){
                        schedules.forEach(function(s){
                            var text = (s.section_code?s.section_code+' - ':'')+
                                s.subject_code+' ('+s.day_of_week+' '+s.start_time.substring(0,5)+'-'+s.end_time.substring(0,5)+
                                ') | Teacher: '+(s.teacher_name||'TBA')+' | Room: '+(s.room_id||'TBA')+
                                ' | Status: '+(s.status||'N/A');
                            $sel.append('<option value="'+s.ss_id+'">'+text+'</option>');
                        });
                    } else {
                        $sel.append('<option disabled>No schedules found</option>');
                    }
                    $sel.val(scheduleToSelect).trigger('change');
                },
                error:function(xhr){ showAlert('Failed to fetch schedules.','danger'); console.error(xhr.responseText);}
            });
        }

        // Form submit
        $('#absentForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                url:'absentform/submit_absent.php',
                type:'POST',
                data:formData,
                contentType:false,
                processData:false,
                dataType:'json',
                success:function(res){
                    if(res.status==='success'){
                        showAlert(res.message,'success');
                        $('#absentForm')[0].reset();
                        $('#child_id, #schedule_id').val(null).trigger('change');
                    } else showAlert('Error: '+res.message,'danger');
                },
                error:function(xhr){ showAlert('Unexpected error occurred.','danger'); console.error(xhr.responseText);}
            });
        });

        function showAlert(msg,type='success'){
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible`;
            alert.style.opacity=0;
            alert.style.transform='translateY(-20px)';
            alert.innerHTML = `<i class="bi bi-${type==='success'?'check-circle-fill':'exclamation-circle-fill'} me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            container.appendChild(alert);
            setTimeout(()=>{ alert.style.transition='all 0.5s'; alert.style.opacity=1; alert.style.transform='translateY(0)'; },10);
            setTimeout(()=>{ alert.style.opacity=0; alert.style.transform='translateY(-20px)'; setTimeout(()=>alert.remove(),500); },3000);
        }

    });
    </script>
</body>
</html>

