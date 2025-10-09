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
   background: var(--primary); 
   color: white;
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

$subjects_query = "SELECT DISTINCT 
                        s.subject_id,
                        s.subject_code,
                        s.subject_description,
                        s.units,
                        s.degree_id,   -- for filtering per department
                        t.t_id,
                        CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') AS teacher_name
                   FROM subjects s
                   LEFT JOIN teachers t ON s.subject_id = t.t_id  -- join with teachers table
                   ORDER BY s.subject_code";

$subjects_result = $conn->query($subjects_query);


?>

<div class="container-fluid">
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
                   
                  // Get the active term ID first
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$active_term = $term_result->fetch_assoc();
$active_term_id = $active_term['term_id'] ?? 2;

// Fetch schedules for this section
$schedules_query = "
    SELECT 
        ss.ss_id,
        ss.subject_code,
        s.subject_description,
        s.units,
        ss.teacher_id,
        ss.day_of_week,
        ss.start_time,
        ss.end_time,
        ss.room_id,
        r.room_number,
        r.capacity AS room_capacity,
        CASE 
            WHEN ss.term_id = $active_term_id AND ss.teacher_id IS NOT NULL THEN 
                CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname,1),''), '.')
            ELSE 'Not yet assigned'
        END AS teacher_name
    FROM sections_schedules ss
    LEFT JOIN subjects s ON ss.subject_id = s.subject_id
    LEFT JOIN rooms r ON ss.room_id = r.room_id
    LEFT JOIN teachers t ON ss.teacher_id = t.t_id
    WHERE ss.section_id = " . (int)$section['section_id'] . "
    AND ss.subject_id IS NOT NULL
    ORDER BY ss.day_of_week, ss.start_time
";

$schedules_result = $conn->query($schedules_query);


                ?>
                       <div class="col-12 col-lg-6 section-card" 
                 data-degree="<?= htmlspecialchars($degree_code); ?>" 
                 data-section="<?= htmlspecialchars($section['section_code']); ?>"
                  data-section-id="<?= htmlspecialchars($section['section_id']); ?>">
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
                                                class="btn btn-sm btn-primary add-schedule-btn"
                                                data-section-id="<?= $section['section_id']; ?>"
                                                data-section-code="<?= htmlspecialchars($section['section_code']); ?>"
                                                data-degree-id="<?= $section['degree_id']; ?>">
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
            <table class="table table-hover align-middle schedule-table display nowrap">
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
                    <?php 
                    // Day abbreviation mapping
                    $dayMap = [
                        'Monday' => 'M',
                        'Tuesday' => 'T',
                        'Wednesday' => 'W',
                        'Thursday' => 'Th',
                        'Friday' => 'F',
                        'Saturday' => 'Sa',
                        'Sunday' => 'Sun'
                    ];

                    // Merge function
                    if (!function_exists('mergeDays')) {
                        function mergeDays($days, $dayMap) {
                            $merged = [];
                            foreach ($dayMap as $full => $abbr) {
                                if (in_array($full, $days)) {
                                    $merged[] = $abbr;
                                }
                            }
                            return implode('', $merged);
                        }
                    }

                    // Collect schedules and group by identical time/teacher/room/subject
                    $groupedSchedules = [];
                    while($schedule = $schedules_result->fetch_assoc()) {
                        $key = $schedule['subject_code'] . '|' . $schedule['start_time'] . '|' . $schedule['end_time'] . '|' . $schedule['teacher_id'] . '|' . $schedule['room_id'];
                        if (!isset($groupedSchedules[$key])) {
                            $groupedSchedules[$key] = [
                                'days' => [],
                                'schedule' => $schedule
                            ];
                        }
                        $groupedSchedules[$key]['days'][] = $schedule['day_of_week'];
                    }

                    // Display grouped schedules
                    foreach ($groupedSchedules as $group) {
                        $schedule = $group['schedule'];
                        $days = $group['days'];

                        $displayDays = mergeDays($days, $dayMap);
                    ?>
                        <tr>
                            <td><?php echo $displayDays; ?></td>
                            <td>
                                 <?php 
                                echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . 
                                     date('h:i A', strtotime($schedule['end_time']));
                                ?>
                            </td>
                           <td>
                            <div class="d-flex flex-column">
                                <span><?php echo htmlspecialchars($schedule['subject_code']); ?></span>
                                <small class="text-muted mb-1">(<?php echo htmlspecialchars($schedule['subject_description']); ?>)</small>
                                <small class="text-muted"><?php echo htmlspecialchars($schedule['units']); ?> units</small>
                            </div>
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
                                    data-schedule-id="<?= $schedule['ss_id'] ?? ''; ?>"
                                    data-section-id="<?= $section['section_id'] ?? ''; ?>"
                                    data-subject-code="<?= htmlspecialchars($schedule['subject_code'] ?? ''); ?>"
                                    data-teacher-id="<?= $schedule['teacher_id'] ?? ''; ?>"
                                    data-day="<?= isset($days) && is_array($days) ? implode(',', $days) : ''; ?>"
                                    data-start-time="<?= $schedule['start_time'] ?? ''; ?>"
                                    data-end-time="<?= $schedule['end_time'] ?? ''; ?>"
                                    data-room-id="<?= $schedule['room_id'] ?? ''; ?>"
                                    data-term-id="<?= $schedule['term_id'] ?? ''; ?>">
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
    <?php } ?>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addScheduleModalLabel">Add Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addScheduleForm" class="needs-validation" novalidate>
                <!-- <input type="hidden" id="add_section_id" name="section_id"> -->
                <input type="hidden" id="modal_section_code" name="section_code">
                <div class="modal-body">
                    <input type="hidden" name="section_id" id="add_section_id">
                
                    <div class="mb-3">
                        <label for="add_subject_code" class="form-label">Subject Code</label>
                        <select class="form-select" id="add_subject_code" name="subject_code" required>
                            <option value="" disabled selected>Select Subject</option>
                            <!-- Options will be loaded dynamically -->
                        </select>
                        <div class="invalid-feedback">Please select a subject.</div>
                    </div>

                    <div class="mb-3">
                        <label for="add_teacher_id" class="form-label">Teacher</label>
                        <select class="form-select" id="add_teacher_id" name="teacher_id" required>
                            <option value="" disabled selected>Select Teacher</option>
                        </select>
                        <div class="invalid-feedback">Please select a teacher.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Day(s) of the Week </label>
                        <div id="add_day_of_week" class="btn-group gap-2" role="group" aria-label="Select days">
                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="dayMon" value="Monday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="dayMon">Mon</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="dayTue" value="Tuesday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="dayTue">Tue</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="dayWed" value="Wednesday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="dayWed">Wed</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="dayThu" value="Thursday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="dayThu">Thu</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="dayFri" value="Friday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="dayFri">Fri</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="daySat" value="Saturday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="daySat">Sat</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="daySun" value="Sunday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="daySun">Sun</label>
                        </div>
                        <div class="invalid-feedback d-block">Please select at least one day.</div>
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

<script>
const allSubjects = [];
<?php
$subjects_result->data_seek(0);
while ($subject = $subjects_result->fetch_assoc()) {
    $jsSubject = json_encode($subject);
    echo "allSubjects.push($jsSubject);\n";
}
?>
</script>


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
                    <input type="hidden" id="edit_term_id" name="term_id" value="">
                    
                    <div class="mb-3">
                        <label for="edit_subject_code" class="form-label">Subject</label>
                        <select class="form-select" id="edit_subject_code" name="subject_code" required>
                            <option value="">Select Subject</option>
                            <?php
                            $subjects_result->data_seek(0);
                            while ($subject = $subjects_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($subject['subject_code']) . '">' . 
                                     htmlspecialchars($schedule['subject_code'] . ' - ' . $schedule['subject_description']) . 
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
                        <label class="form-label">Day(s) of the Week</label>
                        <div id="edit_day_of_week" class="btn-group gap-2" role="group" aria-label="Select days">
                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_dayMon" value="Monday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_dayMon">Mon</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_dayTue" value="Tuesday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_dayTue">Tue</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_dayWed" value="Wednesday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_dayWed">Wed</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_dayThu" value="Thursday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_dayThu">Thu</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_dayFri" value="Friday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_dayFri">Fri</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_daySat" value="Saturday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_daySat">Sat</label>

                            <input type="checkbox" class="btn-check" name="day_of_week[]" id="edit_daySun" value="Sunday" autocomplete="off">
                            <label class="btn btn-outline-primary rounded-pill" for="edit_daySun">Sun</label>
                        </div>
                        <div class="invalid-feedback d-block">Please select at least one day.</div>
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


<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
  $(document).ready(function () {
    $('table').DataTable({
        scrollY: '50vh',
        scrollCollapse: true,
        responsive: {
            details: {
                type: 'inline',  // adds the toggle button
                target: -1,      // last column
                renderer: function(api, rowIdx, columns) {
                    return columns
                        .filter(col => col.hidden)
                        .map(col => `<div><strong>${col.title}:</strong> ${col.data}</div>`)
                        .join('');
                }
            }
        },
        autoWidth: false,
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1,  responsivePriority: 0 } // Make last column unsortable (e.g., action buttons)
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

 // --------------------- Filter Sections ---------------------
    const degreeFilter = document.getElementById('degreeFilter');
    const searchSection = document.getElementById('searchSection');

    function filterSections() {
    const degree = degreeFilter.value.toLowerCase();
    const search = searchSection.value.toLowerCase();
    let visibleCount = 0; // count visible cards

    document.querySelectorAll('.section-card').forEach(card => {
        const cardDegree = card.getAttribute('data-degree').toLowerCase();
        const sectionCode = card.getAttribute('data-section').toLowerCase();

        const matchesDegree = !degree || cardDegree === degree;
        const matchesSearch = !search || sectionCode.includes(search);

        const isVisible = matchesDegree && matchesSearch;
        card.style.display = isVisible ? '' : 'none';

        if (isVisible) visibleCount++;
    });

    // Show alert if no cards are visible
    const alertBox = document.getElementById('noSectionsAlert');
    if (!alertBox) {
        // Create alert dynamically if not exist
        const container = document.getElementById('sectionsContainer') || document.body;
        const div = document.createElement('div');
        div.id = 'noSectionsAlert';
        div.className = 'alert alert-info mt-3';
        div.textContent = 'No sections found for the selected filter.';
        div.style.display = visibleCount === 0 ? '' : 'none';
        container.prepend(div);
    } else {
        alertBox.style.display = visibleCount === 0 ? '' : 'none';
    }
}


    degreeFilter?.addEventListener('change', filterSections);
    searchSection?.addEventListener('input', filterSections);



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

    document.querySelectorAll('.add-schedule-btn').forEach(button => {
    button.addEventListener('click', function() {
        const sectionId = this.getAttribute('data-section-id');
        const sectionCode = this.getAttribute('data-section-code');
        const degreeId = this.getAttribute('data-degree-id');

        // Filter subjects for this section's degree
        const subjectsForDegree = allSubjects.filter(sub => sub.degree_id == degreeId);

        if (subjectsForDegree.length === 0) {
            showAlert('info', `No subjects found for section ${sectionCode}`);
            return;
        }

        // Populate the subjects dropdown
        const subjectSelect = document.getElementById('add_subject_code');
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        subjectsForDegree.forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.subject_code;
            option.textContent = `${sub.subject_code} - ${sub.subject_description} (${sub.units} units)`;
            subjectSelect.appendChild(option);
        });

        // Set hidden inputs
        document.getElementById('add_section_id').value = sectionId;
        document.getElementById('modal_section_code').value = sectionCode;

        // Update modal header
        document.getElementById('addScheduleModalLabel').textContent = `Add Schedule (${sectionCode})`;

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('addScheduleModal'));
        modal.show();
    });
    
});

    const addScheduleModal = document.getElementById('addScheduleModal');
addScheduleModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const sectionId = button.getAttribute('data-section-id');
    const sectionCode = button.getAttribute('data-section-code');

    // Set hidden inputs
    document.getElementById('add_section_id').value = sectionId;
    document.getElementById('modal_section_code').value = sectionCode;

    // Update modal header
    const modalTitle = addScheduleModal.querySelector('#addScheduleModalLabel');
    modalTitle.textContent = `Add Schedule (${sectionCode})`;
});

// Add Schedule Form Handler
const addScheduleForm = document.getElementById('addScheduleForm');
if (addScheduleForm) {
    addScheduleForm.addEventListener('submit', function(event) {
        event.preventDefault();

        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        // Collect all checked days
        const checkedDays = [];
        document.querySelectorAll('#add_day_of_week input[type="checkbox"]:checked').forEach(cb => {
            checkedDays.push(cb.value);
        });

        if (checkedDays.length === 0) {
            showAlert('warning', 'Please select at least one day.');
            return;
        }

        const sectionId = document.getElementById('add_section_id').value;
        const subjectCode = document.getElementById('add_subject_code').value;
        const teacherId = document.getElementById('add_teacher_id').value;
        const startTime = document.getElementById('add_start_time').value;
        const endTime = document.getElementById('add_end_time').value;
        const roomId = document.getElementById('add_room').value;

        if (!sectionId || !subjectCode || !teacherId || !startTime || !endTime || !roomId) {
            showAlert('warning', 'Please fill all required fields.');
            return;
        }

        // Loop through each day and submit a separate request
        const promises = checkedDays.map(day => {
            const formData = new FormData();
            formData.append('action', 'add_schedule');
            formData.append('section_id', sectionId);
            formData.append('subject_code', subjectCode);
            formData.append('teacher_id', teacherId);
            formData.append('day_of_week', day);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            formData.append('room_id', roomId);

            return fetch('/admin/ajax/schedules_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json());
        });

        // Wait for all requests to finish
        Promise.all(promises)
            .then(results => {
                let hasError = false;
                results.forEach(res => {
                    if (!res.success) {
                        hasError = true;
                        showAlert('danger', res.error || 'Error adding schedule');
                    }
                });

                if (!hasError) {
                    showAlert('success', 'Schedule added successfully!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addScheduleModal'));
                    modal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error adding schedule');
            });

        this.classList.add('was-validated');
    });
}

  
  const editScheduleModal = document.getElementById('editScheduleModal');
if (editScheduleModal) {
    editScheduleModal.addEventListener('show.bs.modal', async function(event) {
        const button = event.relatedTarget;
        const scheduleId = button.getAttribute('data-schedule-id');
        const sectionId = button.getAttribute('data-section-id');
        const subjectCode = button.getAttribute('data-subject-code');
        const teacherId = button.getAttribute('data-teacher-id');
        const day = button.getAttribute('data-day'); // comma-separated
        const startTime = button.getAttribute('data-start-time');
        const endTime = button.getAttribute('data-end-time');
        const roomId = button.getAttribute('data-room-id');
        const termId = button.getAttribute('data-term-id'); // NEW

        // Reset form validation
        const form = editScheduleModal.querySelector('form');
        form.classList.remove('was-validated');

        // Set schedule ID, section ID, term ID
        document.getElementById('edit_schedule_id').value = scheduleId;
        document.getElementById('edit_section_id').value = sectionId;
        document.getElementById('edit_term_id').value = termId; // NEW

        // Set subject code
        const editSubjectCode = document.getElementById('edit_subject_code');
        editSubjectCode.value = subjectCode;

        // Load teachers for the subject
        await loadTeachersForSubject(subjectCode, 'edit_teacher_id');

        // Set selected teacher
        const teacherSelect = document.getElementById('edit_teacher_id');
        teacherSelect.value = teacherId && teacherId !== 'null' ? teacherId : '';

        // Pre-check the day checkboxes
        const daysArray = day.split(',').map(d => d.trim());
        const dayCheckboxes = editScheduleModal.querySelectorAll('input[name="day_of_week[]"]');
        dayCheckboxes.forEach(cb => {
            cb.checked = daysArray.includes(cb.value);
        });

        // Set times
        document.getElementById('edit_start_time').value = startTime;
        document.getElementById('edit_end_time').value = endTime;

        // Set room
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
            const response = await fetch('/admin/ajax/schedules_ajax.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showAlert('success', data.message);
                const modal = bootstrap.Modal.getInstance(editScheduleModal);
                modal.hide();
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

// Handle subject selection change for Edit Schedule
document.getElementById('edit_subject_code').addEventListener('change', function() {
    loadTeachersForSubject(this.value, 'edit_teacher_id');
});

// Function to update schedule displays when teachers change
function updateScheduleTeachers(subjectCode, teacherName) {
    const scheduleTeachers = document.querySelectorAll(`.schedule-teacher[data-subject-code="${subjectCode}"]`);
    scheduleTeachers.forEach(element => {
        element.textContent = teacherName || 'No teacher assigned';
        element.className = teacherName ? '' : 'text-muted schedule-teacher';
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
                
                fetch(' /admin/ajax/schedules_ajax.php', {
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


    // Handle subject selection change for Add Schedule
    document.getElementById('add_subject_code').addEventListener('change', function() {
        loadTeachersForSubject(this.value, 'add_teacher_id');
    });


   // Function to load all teachers (not filtered by subject code)
 async function loadTeachersForSubject(subjectCode, teacherSelectId) {
    const teacherSelect = document.getElementById(teacherSelectId);

    // Clear existing options
    teacherSelect.innerHTML = '<option value="">Select Teacher</option>';

    try {
        // Always fetch all teachers, ignore subjectCode
        const formData = new FormData();
        formData.append('action', 'get_subject_teachers');

        const response = await fetch(' /admin/ajax/schedules_ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            if (data.teachers && data.teachers.length > 0) {
                data.teachers.forEach(teacher => {
                    const option = document.createElement('option');
                    option.value = teacher.t_id;
                     option.textContent = `${teacher.teacher_name} (${teacher.t_status.toUpperCase() || '-'} - ${teacher.degree_code|| '-'})`;
                    teacherSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No teachers found';
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


    // Function to check for teacher updates
    function checkForTeacherUpdates() {
        const subjectElements = document.querySelectorAll('.schedule-teacher');
        const subjectCodes = [...new Set([...subjectElements].map(el => el.dataset.subjectCode))];
        
        if (subjectCodes.length === 0) return;

        const formData = new FormData();
        formData.append('action', 'check_teacher_updates');
        formData.append('subject_codes', JSON.stringify(subjectCodes));

        fetch('/admin/ajax/schedules_ajax.php', {
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
    document.querySelectorAll('#edit_teacher_id').forEach(select => {
        select.removeAttribute('required');
    });
});

  // Helper function to show alerts
   function showAlert(type, message) {
    // Ensure type is lowercase string
    type = (type || 'info').toLowerCase();

    let icon;
    let title;

    switch(type) {
        case 'success':
            icon = 'success';
            title = 'Success!';
            break;
        case 'error':
        case 'danger':
            icon = 'error';
            title = 'Error!';
            break;
        case 'info':
            icon = 'info';
            title = 'Notice';
            break;
        case 'warning':
            icon = 'warning';
            title = 'Warning!';
            break;
        default:
            icon = 'info';
            title = 'Notice';
    }

    Swal.fire({
        icon: icon,
        title: title,
        text: message || '',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

</script>