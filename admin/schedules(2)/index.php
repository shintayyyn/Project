<?php
require_once __DIR__ . '/../../includes/db.php';

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
.title{
    color: var(--primary);
}
</style>


<div class="container-fluid">
    <div>
        <div class="d-flex align-items-center mb-3 title fw-bold">
    <i class="bi bi-calendar-week me-2 fs-3"></i> <!-- fs-3 makes icon bigger -->
    <h1 class="h2 mb-0">Schedule Management</h1> <!-- mb-0 removes extra bottom margin -->
</div>

         <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Schedules</li>
                </ol>
            </nav>
    </div>

  <!-- Sections with Schedules -->
<div class="d-flex flex-column flex-md-row gap-2 h-100">
            <!-- Degree Filter -->
            <div class="input-group mb-3">
    <span class="input-group-text" style="background: var(--primary); color: var(--tertiary);">
        <i class="fa-solid fa-filter"></i>
    </span>
    <select id="degreeFilter" class="form-select">
        <option value="">All Degrees</option>
        <?php foreach ($sections_by_degree as $degree_code => $degree_data): ?>
            <option value="<?= htmlspecialchars($degree_code); ?>">
                <?= htmlspecialchars($degree_code . ' - ' . $degree_data['degree_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

           <!-- Search Bar with Icon -->
<div class="input-group mb-3">
    <span class="input-group-text " id="search-icon" style="background: var(--primary); color: var(--tertiary);">
        <i class="fa-brands fa-searchengin fa-lg"></i>
                </span>
    <input 
        type="text" 
        id="searchSection" 
        class="form-control" 
        placeholder="Search section code..."
        aria-label="Search section"
        aria-describedby="search-icon"
    >
</div>

        </div>

<div class="row g-3" id="sectionsContainer">
    <?php foreach ($sections_by_degree as $degree_code => $degree_data) { ?>
        <?php foreach ($degree_data['sections'] as $section) { 
            // Fetch schedules
          // --------------------- Fetch Active Term ---------------------
$active_term = null;
$term_query = "SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1";
$term_result = $conn->query($term_query);
if ($term_result && $term_result->num_rows > 0) {
    $active_term = (int)$term_result->fetch_assoc()['term_id'];
}

// --------------------- Fetch Schedules for Section ---------------------
$schedules_query = "
    SELECT 
        ss.*,
        r.room_number,
        r.capacity AS room_capacity,
        s.subject_description,
        s.units,
        CONCAT(
            t.t_lname, ', ', t.t_fname, ' ',
            COALESCE(LEFT(t.t_mname, 1), ''), '.'
        ) AS teacher_name,
        sec.degree_id
    FROM sections_schedules ss
    LEFT JOIN rooms r ON ss.room_id = r.room_id
    LEFT JOIN subjects s ON ss.subject_code = s.subject_code
    LEFT JOIN teachers t ON ss.teacher_id = t.t_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.section_id = {$section['section_id']}
";

// Apply active term filter if available
if ($active_term !== null) {
    $schedules_query .= " AND ss.term_id = {$active_term}";
}

// Ensure alignment with the section’s degree_id
$schedules_query .= " AND sec.degree_id = {$section['degree_id']}";

// Order neatly
$schedules_query .= " ORDER BY ss.day_of_week, ss.start_time";

$schedules_result = $conn->query($schedules_query);

        ?>
            <div class="col-12 col-lg-6 section-card" 
                 data-degree="<?= htmlspecialchars($degree_code); ?>" 
                 data-section="<?= htmlspecialchars($section['section_code']); ?>">
                <div class="card h-100">
                    <!-- Section Header -->
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><?= htmlspecialchars($section['section_code']); ?></h5>
                                <small class="text-white"><?= htmlspecialchars($degree_data['degree_name']); ?></small>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-primary">
                                    <i class="bi bi-people-fill me-1"></i>
                                    <?= $section['student_count']; ?> / <?= $section['max_students']; ?>
                                </span>
                               <button 
                                    class="btn btn-sm btn-success add-schedule-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addScheduleModal"
                                    data-section-id="<?= $section['section_id']; ?>"
                                    data-section-code="<?= htmlspecialchars($section['section_code']); ?>"
                                    data-degree-id="<?= $section['degree_id']; ?>"
                                    data-term-id="<?= $active_term; ?>"
                                >
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                        </div>

                        <!-- Advisor Info -->
                        <div class="mt-2">
                            <?php if ($section['advisor_name']) { ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-person-badge me-1"></i>
                                    Advisor: <?= htmlspecialchars($section['advisor_name']); ?>
                                </span>
                            <?php } else { ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    No Advisor Assigned
                                </span>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Section Schedule Table -->
                    <div class="card-body">
                        <?php if ($schedules_result->num_rows > 0) { ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle display nowrap section-table">
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
                                        <?php while ($schedule = $schedules_result->fetch_assoc()) { ?>
                                            <tr>
                                                <td><?= $schedule['day_of_week']; ?></td>
                                                <td>
                                                    <?= date('h:i A', strtotime($schedule['start_time'])); ?> -
                                                    <?= date('h:i A', strtotime($schedule['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($schedule['subject_code']); ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($schedule['subject_description']); ?></small>
                                                    <small class="text-muted"><?= $schedule['units']; ?> units</small>
                                                </td>
                                               <td>
                                                <?php if ($schedule['term_id'] != $active_term) { ?>
                                                    <span class="text-muted">Not yet assigned</span>
                                                <?php } else { ?>
                                                    <?= !empty($schedule['teacher_name']) 
                                                        ? htmlspecialchars($schedule['teacher_name']) 
                                                        : '<span class="text-muted">No teacher assigned</span>'; ?>
                                                <?php } ?>
                                            </td>

                                                <td><?= htmlspecialchars($schedule['room_number']); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button 
                                                            class="btn btn-sm btn-primary edit-schedule-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editScheduleModal"
                                                            data-schedule-id="<?= $schedule['ss_id']; ?>"
                                                            data-section-id="<?= $section['section_id']; ?>"
                                                            data-subject-code="<?= htmlspecialchars($schedule['subject_code']); ?>"
                                                            data-teacher-id="<?= $schedule['teacher_id']; ?>"
                                                            data-day="<?= $schedule['day_of_week']; ?>"
                                                            data-start-time="<?= $schedule['start_time']; ?>"
                                                            data-end-time="<?= $schedule['end_time']; ?>"
                                                            data-room-id="<?= $schedule['room_id']; ?>"
                                                        >
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button 
                                                            class="btn btn-sm btn-danger delete-schedule-btn"
                                                            data-schedule-id="<?= $schedule['ss_id']; ?>"
                                                        >
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
    // Initialize DataTables for each section table individually
    $('.section-table').each(function() {
        $(this).DataTable({
            scrollY: '50vh',
            scrollCollapse: true,
            responsive: true,
            paging: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            columnDefs: [{ orderable: false, targets: -1 }],
            dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: { lengthMenu: "Show _MENU_ entries", search: "Search:", info: "Showing _START_ to _END_ of _TOTAL_ entries" }
        });

        // ---------------- Filter Sections ----------------
    const degreeFilter = $('#degreeFilter');
    const searchSection = $('#searchSection');

    function filterSections() {
        const degree = degreeFilter.val().toLowerCase();
        const search = searchSection.val().toLowerCase();

        $('.section-card').each(function() {
            const cardDegree = $(this).data('degree').toLowerCase();
            const sectionCode = $(this).data('section').toLowerCase();
            const visible = (!degree || cardDegree === degree) && (!search || sectionCode.includes(search));
            $(this).toggle(visible);
        });
    }

    degreeFilter.on('change', filterSections);
    searchSection.on('input', filterSections);
});


    // --------------------- Bootstrap Form Validation ---------------------
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

    // --------------------- Filter Sections ---------------------
    const degreeFilter = document.getElementById('degreeFilter');
    const searchSection = document.getElementById('searchSection');

    function filterSections() {
        const degree = degreeFilter.value.toLowerCase();
        const search = searchSection.value.toLowerCase();

        document.querySelectorAll('.section-card').forEach(card => {
            const cardDegree = card.getAttribute('data-degree').toLowerCase();
            const sectionCode = card.getAttribute('data-section').toLowerCase();

            const matchesDegree = !degree || cardDegree === degree;
            const matchesSearch = !search || sectionCode.includes(search);

            card.style.display = (matchesDegree && matchesSearch) ? '' : 'none';
        });
    }

    degreeFilter?.addEventListener('change', filterSections);
    searchSection?.addEventListener('input', filterSections);

    const addScheduleModal = document.getElementById('addScheduleModal');
if (addScheduleModal) {
    addScheduleModal.addEventListener('show.bs.modal', async function (event) {
        const button = event.relatedTarget;
        const sectionId = button.dataset.sectionId;
        const degreeId = button.dataset.degreeId;
        const termId = button.dataset.termId;

        // Pass section_id + term_id to form
        document.getElementById('add_section_id').value = sectionId;
        const form = addScheduleModal.querySelector('form');
        form.dataset.termId = termId;

        // Load subjects under this degree
        const subjectSelect = document.getElementById('add_subject_code');
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';

        try {
            const formData = new FormData();
            formData.append('action', 'get_degree_subjects');
            formData.append('degree_id', degreeId);

            const res = await fetch('/admin/ajax/schedules_ajax.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success && data.subjects.length > 0) {
                data.subjects.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.subject_code;
                    opt.textContent = `${s.subject_code} - ${s.subject_description} (${s.units} units)`;
                    subjectSelect.appendChild(opt);
                });
            } else {
                subjectSelect.innerHTML = '<option value="">No subjects available</option>';
            }
        } catch (err) {
            console.error(err);
            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
        }
    });

    // When subject changes, load teachers under active term
    document.getElementById('add_subject_code').addEventListener('change', function () {
        loadTeachersForSubject(this.value, 'add_teacher_id', addScheduleModal.querySelector('form').dataset.termId);
    });
}

editScheduleModal.addEventListener('show.bs.modal', async function(event) {
    const button = event.relatedTarget;
    const subjectCode = button.dataset.subjectCode;
    const teacherId = button.dataset.teacherId;
    const roomId = button.dataset.roomId;
    const day = button.dataset.day;

    // Assign values
    document.getElementById('edit_subject_code').value = subjectCode;
    await loadTeachersForSubject(subjectCode, 'edit_teacher_id', <?= $active_term ?? 'null' ?>);

    document.getElementById('edit_teacher_id').value = teacherId || '';
    document.getElementById('edit_day_of_week').value = day;
    document.getElementById('edit_room_id').value = roomId;
});


    // --------------------- Add Schedule Form ---------------------
    const addScheduleForm = document.getElementById('addScheduleForm');
    if (addScheduleForm) {
        addScheduleForm.addEventListener('submit', function(event) {
            event.preventDefault();
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'add_schedule');

            fetch('/admin/ajax/schedules_ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Schedule added successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('addScheduleModal')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', data.error || 'Error adding schedule');
                    }
                }).catch(err => {
                    console.error(err);
                    showAlert('danger', 'Error adding schedule');
                });
        });
    }

    // --------------------- Edit Schedule Modal ---------------------
    const editScheduleModal = document.getElementById('editScheduleModal');
    if (editScheduleModal) {
        editScheduleModal.addEventListener('show.bs.modal', async function(event) {
            const button = event.relatedTarget;
            const scheduleId = button.dataset.scheduleId;
            const sectionId = button.dataset.sectionId;
            const subjectCode = button.dataset.subjectCode;
            const teacherId = button.dataset.teacherId;
            const day = button.dataset.day;
            const startTime = button.dataset.startTime;
            const endTime = button.dataset.endTime;
            const roomId = button.dataset.roomId;

            const form = editScheduleModal.querySelector('form');
            form.classList.remove('was-validated');
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_section_id').value = sectionId;
            document.getElementById('edit_subject_code').value = subjectCode;
            await loadTeachersForSubject(subjectCode, 'edit_teacher_id');
            const teacherSelect = document.getElementById('edit_teacher_id');
            teacherSelect.value = teacherId || '';
            document.getElementById('edit_day_of_week').value = day;
            document.getElementById('edit_start_time').value = startTime;
            document.getElementById('edit_end_time').value = endTime;
            document.getElementById('edit_room_id').value = roomId;
        });

        const editForm = editScheduleModal.querySelector('form');
        editForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            if (!this.checkValidity()) {
                this.classList.add('was-validated');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'update_schedule');

            try {
                const response = await fetch('/admin/ajax/schedules_ajax.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(editScheduleModal).hide();
                    location.reload();
                } else {
                    showAlert('danger', data.error || 'Failed to update schedule');
                }
            } catch (error) {
                console.error(error);
                showAlert('danger', 'Error updating schedule');
            }
        });
    }

    // --------------------- Delete Schedule ---------------------
    document.querySelectorAll('.delete-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this schedule?')) return;
            const scheduleId = this.dataset.scheduleId;
            const formData = new FormData();
            formData.append('action', 'delete_schedule');
            formData.append('schedule_id', scheduleId);

            fetch('/admin/ajax/schedules_ajax.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Schedule deleted successfully!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', data.error || 'Error deleting schedule');
                    }
                }).catch(err => {
                    console.error(err);
                    showAlert('danger', 'Error deleting schedule');
                });
        });
    });

    // --------------------- Add / Edit Subject Teacher Loader ---------------------
   async function loadTeachersForSubject(subjectCode, teacherSelectId, termId) {
    const teacherSelect = document.getElementById(teacherSelectId);
    teacherSelect.innerHTML = '<option value="">Select Teacher</option>';
    if (!subjectCode) return;

    const formData = new FormData();
    formData.append('action', 'get_subject_teachers');
    formData.append('subject_code', subjectCode);
    formData.append('term_id', termId);

    try {
        const res = await fetch('/admin/ajax/schedules_ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.hasTeachers) {
            data.teachers.forEach(t => {
                const option = document.createElement('option');
                option.value = t.t_id;
                option.textContent = `${t.teacher_name} ${t.t_status === 'inactive' ? '(Inactive)' : ''}`;
                teacherSelect.appendChild(option);
            });
        } else {
            teacherSelect.innerHTML = '<option value="">No teacher assigned yet</option>';
        }
    } catch (err) {
        console.error(err);
        teacherSelect.innerHTML = '<option value="">Error loading teachers</option>';
    }
}

    
    // Remove required from teacher selects
    document.querySelectorAll('#add_teacher_id, #edit_teacher_id').forEach(sel => sel.removeAttribute('required'));
});
</script>
