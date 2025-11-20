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
    <!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- (Optional) Flatpickr Bootstrap theme -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

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
    body{
        padding:0;
        overflow-x: hidden;
    }

    main{
        width: calc(100%);
        display: flex;
        justify-content: center;
        align-items: center;
    }
/* ===== Select2 Bootstrap Integration ===== */
.select2-container--bootstrap-5 .select2-selection {
    display: flex !important;
    align-items: center !important;
    height: calc(2.5rem + 2px) !important; /* match Bootstrap form-select height */
    padding: 0.375rem 0.75rem !important;
    font-size: 1rem !important;
    line-height: 1.5 !important;
    border-radius: 0.375rem !important;
    border: 1px solid #ced4da !important;
    background-color: #fff !important;
    box-shadow: none !important;
}

/* Text inside the selection */
.select2-container--bootstrap-5 .select2-selection__rendered {
    display: flex !important;
    align-items: center !important;
    height: 100% !important;
    color: #212529 !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1.5 !important;
}

/* Proper arrow alignment */
.select2-container--bootstrap-5 .select2-selection__arrow {
    height: 100% !important;
    top: 0 !important;
    right: 0.75rem !important;
    display: flex !important;
    align-items: center !important;
}

/* ===== Scrollable Dropdown Styling ===== */
.scrollable-dropdown .select2-results__options {
    max-height: 250px !important; /* Scroll limit */
    overflow-y: auto !important;
    overflow-x: hidden !important;
    scrollbar-width: thin;
    scrollbar-color: #ccc transparent;
    word-break: break-word !important;
      width: 500px !important;
    word-break: break-word !important;
}

/* Chrome/Safari Scrollbar */
.scrollable-dropdown .select2-results__options::-webkit-scrollbar {
    width: 6px;
}
.scrollable-dropdown .select2-results__options::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 3px;
}
.scrollable-dropdown .select2-results__options::-webkit-scrollbar-thumb:hover {
    background-color: #999;
}

/* ===== Wrapped & Readable Option Text ===== */
.scrollable-dropdown .select2-results__option {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: anywhere !important;
    line-height: 1.4 !important;
    padding: 6px 10px !important;
    font-size: 0.95rem !important;
}

/* ===== Hover & Highlight Effects ===== */
.scrollable-dropdown .select2-results__option--highlighted {
    background-color: #b6deff73 !important; /* Subtle Bootstrap primary tint */
    color: #0d6efd !important;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.scrollable-dropdown .select2-results__option:not(.select2-results__option--highlighted):hover {
    background-color: #f8f9fa !important;
    color: #212529 !important;
}
/* Make Flatpickr field clearly enabled */
#absent_date {
    background-color: #fff !important;
    cursor: pointer;
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

<body>
    <?php include(__DIR__ . '/../../includes/parent_header.php'); ?>
    <main>
     <div class="alert-container" id="alertContainer"></div>
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
                        <input 
                        type="text" 
                        class="form-control" 
                        id="absent_date" 
                        name="absent_date" 
                        placeholder="yyyy/mm/dd" 
                        required
                        >
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
    </main>
    
    <!-- JS Libraries -->
     <!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/showAlert.js"></script>
    <script src="../parent/assets/js/showAlert.js"></script>
    <script>
$(document).ready(function(){

    // ===== Initialize Select2 =====
    $('#child_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Choose Child --',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        dropdownCssClass: 'scrollable-dropdown'
    });

    $('#schedule_id').select2({
        theme: 'bootstrap-5',
        placeholder: '-- Choose Schedule --',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        dropdownCssClass: 'scrollable-dropdown'
    });

    // ===== Load existing selections (if any) =====
    var selectedChild = "<?= $selected_child_id ?>";
    var selectedSchedule = "<?= $selected_schedule_id ?>";

    if (selectedChild) fetchSchedules(selectedChild, selectedSchedule);

    // ===== Fetch schedules when child changes =====
    $('#child_id').on('change', function(){
        var s_id = $(this).val();
        if(!s_id) {
            $('#schedule_id').empty().append('<option></option>').trigger('change');
            return;
        }
        fetchSchedules(s_id, null);
    });

    // ===== Fetch child schedules via AJAX =====
    function fetchSchedules(s_id, scheduleToSelect){
        $.ajax({
            url: 'absentform/get_child_schedules.php',
            type: 'GET',
            data: { s_id: s_id },
            dataType: 'json',
            success: function(schedules){
                console.log("Schedules returned:", schedules);
                console.log("Child:", $('#child_id').val());
            console.log("Schedule:", $('#schedule_id').val());

                var $sel = $('#schedule_id');
                $sel.empty().append('<option></option>');

                if (Array.isArray(schedules) && schedules.length) {
                    
                    schedules.forEach(function(s) {
                        let dayText = s.days || s.day_of_week || 'N/A';
                         // Convert 24-hour to 12-hour format with AM/PM
    function formatTime12h(timeStr) {
        if (!timeStr) return 'TBA';
        let [hour, minute] = timeStr.split(':').map(Number);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12; // Convert 0 -> 12
        return `${hour}:${minute.toString().padStart(2,'0')} ${ampm}`;
    }

    let timeText = (s.start_time && s.end_time)
        ? `${formatTime12h(s.start_time)} - ${formatTime12h(s.end_time)}`
        : 'TBA';
                        let roomText = s.room_number || 'TBA';
                        let teacherText = s.teacher_name || 'TBA';
                        let subjDesc = s.subject_description ? ` - ${s.subject_description}` : '';
                        let statusText = s.status || 'N/A';

                        let text = `${s.section_code ? s.section_code + ' - ' : ''}${s.subject_code}${subjDesc} `
                                 + `(${dayText} ${timeText}) | Teacher: ${teacherText} | Room: ${roomText} | Status: ${statusText}`;

                        // ✅ Ensure the option has a valid ss_id
                        $sel.append(
                            `<option value="${s.ss_id || s.id || s.schedule_id}}" data-days="${s.days || s.day_of_week || ''}">
                                ${text}
                            </option>`
                        );
                    });
                } else {
                    $sel.append('<option disabled>No schedules found</option>');
                }

                // ✅ Restore previously selected schedule if any
                $sel.val(scheduleToSelect).trigger('change');
            },
            error: function(xhr){
                showAlert('Failed to fetch schedules.', 'danger');
                console.error(xhr.responseText);
            }
        });
    }

    // ===== Flatpickr setup =====
    let absentPicker = flatpickr("#absent_date", {
        dateFormat: "Y-m-d",
        minDate: "today",
        disableMobile: true,
        placeholder: "Select available date...",
        locale: { firstDayOfWeek: 1 }
    });

    // ===== Adjust allowed days when schedule changes =====
    $('#schedule_id').on('change', function() {
        const selected = $(this).find(':selected');
        const daysText = selected.data('days') ? selected.data('days').toUpperCase().trim() : '';

        const dayMap = { 'SU': 0, 'M': 1, 'T': 2, 'W': 3, 'TH': 4, 'F': 5, 'S': 6 };
        const regex = /(TH|SU|M|T|W|F|S)/gi;  // longer tokens first

        const activeDays = [];

        let match;
        while ((match = regex.exec(daysText)) !== null) {
            const key = match[0].toUpperCase();
            if (dayMap[key] !== undefined) activeDays.push(dayMap[key]);
        }

        absentPicker.setDate(null); // clear selection
        absentPicker.set({
            minDate: "today",
            enable: activeDays.length
                ? [function(date) {
                    const today = new Date();
                    const isFuture = date >= new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    return isFuture && activeDays.includes(date.getDay());
                }]
                : [function(date) {
                    const today = new Date();
                    return date >= new Date(today.getFullYear(), today.getMonth(), today.getDate());
                }]
        });
    });

    // ===== Form submission =====
    $('#absentForm').on('submit', function(e){
        e.preventDefault();

        // ✅ Sync Select2 with native selects before sending
        $('#child_id, #schedule_id').trigger('change.select2');

        const child = $('#child_id').val();
        const schedule = $('#schedule_id').val();
        const date = $('#absent_date').val();
        const reason = $('#reason').val().trim();

        if(!child || !schedule || !date || !reason){
            showAlert('Please complete all required fields.', 'danger');
            return;
        }

        const formData = new FormData(this);
        console.log("Submitting schedule_id:", formData.get('schedule_id')); // 🔍 Debug check

        $.ajax({
            url: 'absentform/submit_absent.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res){
                if(res.status === 'success'){
                    showAlert(res.message, 'success');
                    $('#absentForm')[0].reset();
                    $('#child_id, #schedule_id').val(null).trigger('change');
                } else {
                    showAlert('Error: ' + res.message, 'danger');
                }
            },
            error: function(xhr){
                showAlert('Unexpected error occurred.', 'danger');
                console.error(xhr.responseText);
            }
        });
    });

    // ===== Bootstrap Alert Function =====
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

</body>
</html>

