<?php
require_once __DIR__ . '/../../includes/db.php';
?>
<link rel="stylesheet" href="../css/common.css">
<style>
/* Main content area fix */
body {
    overflow-x: hidden;
    background-color: #EDF8FD;
    margin: 0;
    padding: 0;
}

main {
    min-height: 100vh;
    margin: 0;
    padding: 1rem;
    background-color: #EDF8FD;
}

/* Container and row adjustments */
.container-fluid {
    padding: 0 15px;
    margin-right: auto;
    margin-left: auto;
}

.row {
    margin-right: -15px;
    margin-left: -15px;
    display: flex;
    flex-wrap: wrap;
}

.col-12, .col-lg-6 {
    padding: 10px;
}

.schedule-table {
    font-size: 0.875rem;
}

.schedule-table th,
.schedule-table td {
    padding: 0.5rem;
    white-space: normal;
    word-break: break-word;
}

.card-body {
    background: #D0EEFC;
    overflow-x: hidden;
}

.section-schedules {
    max-height: 500px;
    overflow-y: auto;
}

/* Table container */
.table-responsive {
    margin: 0;
    padding: 0;
}

.degree-card {
    margin-bottom: 1rem;
}

/* Reset and set proper button styles */
.btn-primary, 
.btn-group .btn-primary,
button.btn-primary {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: white !important;
}

.btn-secondary, 
.btn-group .btn-secondary,
button.btn-secondary {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    color: white !important;
}

.btn-danger, 
.btn-group .btn-danger,
button.btn-danger {
    background-color: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
}

.btn-success, 
.btn-group .btn-success,
button.btn-success {
    background-color: #198754 !important;
    border-color: #198754 !important;
    color: white !important;
}

.btn {
    padding: 0.375rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.section-code {
    font-weight: 600;
    font-size: 1.1rem;
}

.advisor-info {
    margin-top: 0.25rem;
}

.advisor-info .badge {
    font-size: 0.85rem;
    font-weight: 500;
    padding: 0.4rem 0.6rem;
}



.table th {
    font-weight: 600;
    background-color: rgba(0,0,0,0.02);
}

.degree-card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: none;
}

.header1{
    background:#033A70;
    color:#EDE8F5;
}


.degree-card .cbody1 {
    padding: 1.25rem;
    background-color: #EDF8FD;
}

.header2{
    background: #FFCB05;
}


.degree-card .card-body .card {
    border: 1px solid rgba(0,0,0,0.1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.btn-group .btn.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 4px;
    margin: 0 2px;
}

.btn-group .btn.btn-sm:hover {
    opacity: 0.85;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
<?php

// Fetch all sections with their advisors, schedules, and degrees
$query = "SELECT DISTINCT 
            s.section_id,
            s.section_code,
            s.year_level,
            s.degree_id,
            d.degree_code,
            d.degree_name,
            sa.t_id,
            CONCAT(sa.t_lname, ', ', sa.t_fname, ' ', COALESCE(LEFT(sa.t_mname, 1), ''), '.') as advisor_name,
            (SELECT COUNT(*) FROM students_sections ss WHERE ss.section_id = s.section_id) as student_count,
            s.max_students
          FROM sections s
          LEFT JOIN sections_advisors sa ON s.section_id = sa.section_id
          LEFT JOIN degrees d ON s.degree_id = d.degree_id
          ORDER BY d.degree_code, s.year_level, s.section_code";

$sections_result = $conn->query($query);

// Group sections by degree
$sections_by_degree = [];
while ($section = $sections_result->fetch_assoc()) {
    $degree_code = $section['degree_code'];
    if (!isset($sections_by_degree[$degree_code])) {
        $sections_by_degree[$degree_code] = [
            'degree_name' => $section['degree_name'],
            'sections' => []
        ];
    }
    $sections_by_degree[$degree_code]['sections'][] = $section;
}

// Fetch all rooms
$rooms_query = "SELECT * FROM rooms ORDER BY room_number";
$rooms_result = $conn->query($rooms_query);

// Store rooms data for JavaScript
$rooms_data = [];
while ($room = $rooms_result->fetch_assoc()) {
    $rooms_data[] = $room;
}
$rooms_json = json_encode($rooms_data);

// Get available subjects and their assigned teachers
$subjects_query = "SELECT DISTINCT 
                    s.subject_id,
                    s.subject_code,
                    s.subject_description,
                    s.units,
                    st.t_id,
                    CONCAT(st.t_lname, ', ', st.t_fname, ' ', COALESCE(LEFT(st.t_mname, 1), ''), '.') as teacher_name
                  FROM subjects s
                  LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id
                  ORDER BY s.subject_code";
$subjects_result = $conn->query($subjects_query);
?>

<div class="container-fluid">
    <!-- Add message containers -->
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>

    <div>
        <h1 class="h2 mb-2">Schedule Management</h1>
         <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Schedules</li>
                </ol>
            </nav>
    </div>

    <!-- Sections with Schedules -->
    <?php foreach ($sections_by_degree as $degree_code => $degree_data) { ?>
    <div class="card mb-4 degree-card">
        <div class="card-header header1">
            <h4 class="mb-0">
                <i class="bi bi-mortarboard-fill me-2"></i>
                <?php echo htmlspecialchars($degree_code . ' - ' . $degree_data['degree_name']); ?>
            </h4>
        </div>
        <div class="card-body">
            <div class="row g-0">
                <?php foreach ($degree_data['sections'] as $section) { 
                    // Get schedules for this section with subject and teacher details
                    $schedules_query = "SELECT 
                                        ss.*,
                                        r.room_number,
                                        r.capacity as room_capacity,
                                        s.subject_description,
                                        s.units,
                                        CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') as teacher_name
                                      FROM sections_schedules ss
                                      LEFT JOIN rooms r ON ss.room_id = r.room_id
                                      LEFT JOIN subjects s ON ss.subject_code = s.subject_code
                                      LEFT JOIN teachers t ON ss.teacher_id = t.t_id
                                      WHERE ss.section_id = " . $section['section_id'] . "
                                      ORDER BY ss.day_of_week, ss.start_time";
                    $schedules_result = $conn->query($schedules_query);
                ?>
                    <div class="col-12 col-lg-6">
                        <div class="card h-100">
                            <div class=" card-header header2">
                                <div class="d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="card-title mb-0 d-flex align-items-center">
                                            <span class="section-code"><?php echo htmlspecialchars($section['section_code']); ?></span>
                                            <span class="badge bg-info ms-2">
                                                <i class="bi bi-people-fill me-1"></i>
                                                <?php echo $section['student_count']; ?> / <?php echo $section['max_students']; ?>
                                            </span>
                                        </h5>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#addScheduleModal"
                                                    data-section-id="<?php echo $section['section_id']; ?>"
                                                    data-section-code="<?php echo htmlspecialchars($section['section_code']); ?>">
                                                <i class="bi bi-plus-lg me-1"></i>Add
                                            </button>
                                        </div>
                                    </div>
                                    <?php if ($section['advisor_name']) { ?>
                                    <div class="advisor-info">
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-person-badge me-1"></i>
                                            Advisor: <?php echo htmlspecialchars($section['advisor_name']); ?>
                                        </span>
                                    </div>
                                    <?php } else { ?>
                                    <div class="advisor-info">
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            No Advisor Assigned
                                        </span>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="card-body cbody1">
                                <?php if($schedules_result->num_rows > 0) { ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle schedule-table">
                                            <thead>
                                                <tr>
                                                    <th>Day</th>
                                                    <th>Time</th>
                                                    <th>Subject</th>
                                                    <th>Teacher</th>
                                                    <th>Room</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($schedule = $schedules_result->fetch_assoc()) { ?>
                                                    <tr>
                                                        <td><?php echo $schedule['day_of_week']; ?></td>
                                                        <td>
                                                            <?php 
                                                            echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                                                 date('h:i A', strtotime($schedule['end_time']));
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <div><?php echo htmlspecialchars($schedule['subject_code']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($schedule['subject_description']); ?></small>
                                                            <small class="text-muted"><?php echo $schedule['units']; ?> units</small>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                                if (!empty($schedule['teacher_name'])) {
                                                                    echo htmlspecialchars($schedule['teacher_name']);
                                                                } else {
                                                                    echo '<span class="text-muted schedule-teacher" data-subject-code="' . htmlspecialchars($schedule['subject_code']) . '">No teacher assigned</span>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($schedule['room_number']); ?></td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-primary edit-schedule-btn" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#editScheduleModal" 
                                                                        data-schedule-id="<?php echo $schedule['ss_id']; ?>" 
                                                                        data-section-id="<?php echo $section['section_id']; ?>" 
                                                                        data-subject-code="<?php echo htmlspecialchars($schedule['subject_code']); ?>"
                                                                        data-teacher-id="<?php echo $schedule['teacher_id']; ?>"
                                                                        data-day="<?php echo $schedule['day_of_week']; ?>"
                                                                        data-start-time="<?php echo $schedule['start_time']; ?>"
                                                                        data-end-time="<?php echo $schedule['end_time']; ?>"
                                                                        data-room-id="<?php echo $schedule['room_id']; ?>">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </button>
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-danger delete-schedule-btn" 
                                                                        data-schedule-id="<?php echo $schedule['ss_id']; ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted mb-0">No schedules assigned to this section.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addScheduleForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="section_id" id="add_section_id">
                    
                    <div class="mb-3">
                        <label for="add_subject_code" class="form-label">Subject Code</label>
                        <select class="form-select" id="add_subject_code" name="subject_code" required>
                            <option value="">Select Subject</option>
                            <?php
                            $subjects_result->data_seek(0); // Reset pointer to beginning
                            while ($subject = $subjects_result->fetch_assoc()) {
                                echo "<option value='{$subject['subject_code']}'>{$subject['subject_code']} - {$subject['subject_description']} ({$subject['units']} units)</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a subject.</div>
                    </div>

                    <div class="mb-3">
                        <label for="add_teacher_id" class="form-label">Teacher</label>
                        <select class="form-select" id="add_teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                        </select>
                        <div class="invalid-feedback">Please select a teacher.</div>
                    </div>

                    <div class="mb-3">
                        <label for="add_day_of_week" class="form-label">Day</label>
                        <select class="form-select" id="add_day_of_week" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                        <div class="invalid-feedback">Please select a day.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="add_start_time" name="start_time" required>
                            <div class="invalid-feedback">Please select a start time.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="add_end_time" name="end_time" required>
                            <div class="invalid-feedback">Please select an end time.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_room" class="form-label">Room</label>
                        <select class="form-select" id="add_room" name="room_id" required>
                            <option value="">Select Room</option>
                            <?php
                            $rooms_result->data_seek(0); // Reset pointer to beginning
                            while ($room = $rooms_result->fetch_assoc()) {
                                echo "<option value='{$room['room_id']}' data-capacity='{$room['capacity']}'>" .
                                     htmlspecialchars($room['room_number'] . " (Capacity: {$room['capacity']})") .
                                     "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a room.</div>
                        <small class="form-text text-muted room-info"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editScheduleForm" class="needs-validation" novalidate>
                    <input type="hidden" id="edit_schedule_id" name="schedule_id">
                    <input type="hidden" id="edit_section_id" name="section_id">
                    
                    <div class="mb-3">
                        <label for="edit_subject_code" class="form-label">Subject</label>
                        <select class="form-select" id="edit_subject_code" name="subject_code" required>
                            <option value="">Select Subject</option>
                            <?php
                            $subjects_result->data_seek(0);
                            while ($subject = $subjects_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($subject['subject_code']) . '">' . 
                                     htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_description']) . 
                                     '</option>';
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a subject.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_teacher_id" class="form-label">Teacher</label>
                        <select class="form-select" id="edit_teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_day_of_week" class="form-label">Day</label>
                        <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                        <div class="invalid-feedback">Please select a day.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label for="edit_start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                            <div class="invalid-feedback">Please select a start time.</div>
                        </div>
                        <div class="col">
                            <label for="edit_end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
                            <div class="invalid-feedback">Please select an end time.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_room_id" class="form-label">Room</label>
                        <select class="form-select" id="edit_room_id" name="room_id" required>
                            <option value="">Select Room</option>
                            <?php
                            $rooms_result->data_seek(0);
                            while ($room = $rooms_result->fetch_assoc()) {
                                echo '<option value="' . $room['room_id'] . '">' . 
                                     htmlspecialchars($room['room_number']) . ' (Capacity: ' . $room['capacity'] . ')' .
                                     '</option>';
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Please select a room.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editScheduleForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
    $('table').DataTable({
        scrollY: '650px',
        scrollCollapse: true,
        responsive: true,
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1 } // Make last column unsortable (e.g., action buttons)
        ],
        dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Add Schedule Form Handler
    const addScheduleForm = document.getElementById('addScheduleForm');
    if (addScheduleForm) {
        addScheduleForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            if (this.checkValidity()) {
                const formData = new FormData(this);
                formData.append('action', 'add_schedule');
                
                fetch('/Project/admin/ajax/schedules_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showAlert('success', 'Schedule added successfully!');
                        // Close the modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
                        modal.hide();
                        // Reload the page to show the new schedule
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showAlert('danger', data.error || 'Error adding schedule');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Error adding schedule');
                });
            }
            
            this.classList.add('was-validated');
        });
    }

    // Edit Schedule Form Handler
    const editScheduleModal = document.getElementById('editScheduleModal');
    if (editScheduleModal) {
        editScheduleModal.addEventListener('show.bs.modal', async function(event) {
            const button = event.relatedTarget;
            const scheduleId = button.getAttribute('data-schedule-id');
            const sectionId = button.getAttribute('data-section-id');
            const subjectCode = button.getAttribute('data-subject-code');
            const teacherId = button.getAttribute('data-teacher-id');
            const day = button.getAttribute('data-day');
            const startTime = button.getAttribute('data-start-time');
            const endTime = button.getAttribute('data-end-time');
            const roomId = button.getAttribute('data-room-id');
            
            // Reset form validation
            const form = editScheduleModal.querySelector('form');
            form.classList.remove('was-validated');
            
            // Set the schedule ID and section ID in the form
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_section_id').value = sectionId;
            
            // Set subject code
            const editSubjectCode = document.getElementById('edit_subject_code');
            editSubjectCode.value = subjectCode;
            
            // Load teachers for the subject
            await loadTeachersForSubject(subjectCode, 'edit_teacher_id');
            
            // Set the selected teacher after teachers are loaded
            const teacherSelect = document.getElementById('edit_teacher_id');
            if (teacherId && teacherId !== 'null') {
                teacherSelect.value = teacherId;
            } else {
                teacherSelect.value = '';
            }
            
            // Set other fields
            document.getElementById('edit_day_of_week').value = day;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_room_id').value = roomId;
        });

        // Handle form submission
        const editForm = editScheduleModal.querySelector('form');
        editForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            if (!this.checkValidity()) {
                event.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'update_schedule');
            
            // Debug log
            console.log('Submitting form with data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            try {
                const response = await fetch('/Project/admin/ajax/schedules_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Server response:', data);  // Debug log
                
                if (data.success) {
                    showAlert('success', data.message);
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(editScheduleModal);
                    modal.hide();
                    // Reload the page to show updated schedule
                    location.reload();
                } else {
                    showAlert('danger', data.error || 'Failed to update schedule');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while updating the schedule');
            }
        });
    }

    // Delete Schedule Handler
    document.querySelectorAll('.delete-schedule-btn').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const scheduleId = this.dataset.scheduleId;
                const formData = new FormData();
                formData.append('action', 'delete_schedule');
                formData.append('schedule_id', scheduleId);
                
                fetch('/Project/admin/ajax/schedules_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Schedule deleted successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showAlert('danger', data.error || 'Error deleting schedule');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Error deleting schedule');
                });
            }
        });
    });

    // Add Schedule Modal Handler
    const addScheduleModal = document.getElementById('addScheduleModal');
    if (addScheduleModal) {
        addScheduleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const sectionId = button.getAttribute('data-section-id');
            const sectionCode = button.getAttribute('data-section-code');
            
            // Set the section ID in the form
            const sectionIdInput = document.querySelector('#addScheduleModal input[name="section_id"]');
            if (sectionIdInput) {
                sectionIdInput.value = sectionId;
            }
            
            // Update modal title to include section code
            const modalTitle = addScheduleModal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Add Schedule - Section ${sectionCode}`;
            }
        });
    }

    // Handle subject selection change for Add Schedule
    document.getElementById('add_subject_code').addEventListener('change', function() {
        loadTeachersForSubject(this.value, 'add_teacher_id');
    });

    // Handle subject selection change for Edit Schedule
    document.getElementById('edit_subject_code').addEventListener('change', function() {
        loadTeachersForSubject(this.value, 'edit_teacher_id');
    });

    // Function to load teachers for a subject
    async function loadTeachersForSubject(subjectCode, teacherSelectId) {
        const teacherSelect = document.getElementById(teacherSelectId);
        
        // Clear existing options
        teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
        
        if (subjectCode) {
            try {
                // Fetch teachers for the selected subject
                const formData = new FormData();
                formData.append('action', 'get_subject_teachers');
                formData.append('subject_code', subjectCode);
                
                const response = await fetch('/Project/admin/ajax/schedules_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.hasTeachers) {
                        data.teachers.forEach(teacher => {
                            const option = document.createElement('option');
                            option.value = teacher.t_id;
                            option.textContent = teacher.teacher_name;
                            teacherSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'No teacher assigned to this subject yet';
                        option.selected = true;
                        teacherSelect.appendChild(option);
                    }
                } else {
                    throw new Error(data.error || 'Error loading teachers');
                }
            } catch (error) {
                console.error('Error:', error);
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Error loading teachers';
                option.selected = true;
                teacherSelect.appendChild(option);
            }
        }
    }

    // Function to update schedule displays when teachers change
    function updateScheduleTeachers(subjectCode, teacherName) {
        const scheduleTeachers = document.querySelectorAll(`.schedule-teacher[data-subject-code="${subjectCode}"]`);
        scheduleTeachers.forEach(element => {
            element.textContent = teacherName || 'No teacher assigned';
            element.className = teacherName ? '' : 'text-muted schedule-teacher';
        });
    }

    // Function to check for teacher updates
    function checkForTeacherUpdates() {
        const subjectElements = document.querySelectorAll('.schedule-teacher');
        const subjectCodes = [...new Set([...subjectElements].map(el => el.dataset.subjectCode))];
        
        if (subjectCodes.length === 0) return;

        const formData = new FormData();
        formData.append('action', 'check_teacher_updates');
        formData.append('subject_codes', JSON.stringify(subjectCodes));

        fetch('/Project/admin/ajax/schedules_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.updates) {
                Object.entries(data.updates).forEach(([subjectCode, teacherName]) => {
                    updateScheduleTeachers(subjectCode, teacherName);
                });
            }
        })
        .catch(error => console.error('Error checking for teacher updates:', error));
    }

    // Check for updates periodically (every 30 seconds)
    setInterval(checkForTeacherUpdates, 30000);
    // Also check immediately when the page loads
    checkForTeacherUpdates();

    // Remove required attribute from teacher select in add/edit forms
    document.querySelectorAll('#add_teacher_id, #edit_teacher_id').forEach(select => {
        select.removeAttribute('required');
    });

    // Helper function to show alerts
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.getElementById('alertContainer').appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
</script>