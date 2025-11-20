<?php
require_once __DIR__ . '/../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Ensure user is a teacher
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ✅ Fetch active term
$term_query = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$active_term = $term_query->fetch_assoc();
$term_id = $active_term['term_id'] ?? null;

if (!$term_id) {
    // Fallback to latest term
    $term_query = $conn->query("SELECT term_id FROM academic_terms ORDER BY term_id DESC LIMIT 1");
    $term_id = $term_query->fetch_assoc()['term_id'] ?? 0;
}
if (!$term_id) die("No active term found.");

// ✅ Fetch teacher's sections (active term)
$stmt = $conn->prepare("
    SELECT s.section_id, s.section_name, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time
    FROM sections_schedules ss
    INNER JOIN sections s ON ss.section_id = s.section_id
    WHERE ss.teacher_id = ? AND ss.term_id = ?
");
$stmt->bind_param("ii", $teacher_id, $term_id);
$stmt->execute();
$sections_result = $stmt->get_result();
$stmt->close();

// ✅ Build mapping arrays
$section_subject_map = [];
$section_days_map = [];
$teacher_sections = [];
while ($row = $sections_result->fetch_assoc()) {
    $section_id = $row['section_id'];
    $subject_code = $row['subject_code'];

    $teacher_sections[$section_id] = $row['section_name'];
    $section_subject_map[$section_id][] = $subject_code;

    $day_full = ucfirst(strtolower($row['day_of_week']));
    $time_range = date("g:i A", strtotime($row['start_time'])) . ' - ' . date("g:i A", strtotime($row['end_time']));
    $section_days_map[$subject_code][] = $day_full . " ({$time_range})";
}

// Remove duplicates
foreach ($section_subject_map as $sid => $subs) {
    $section_subject_map[$sid] = array_unique($subs);
}

// ✅ Date filters
$month = $_GET['month'] ?? date('Y-m');
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// ✅ Fetch Regular Students
$all_section_ids = array_keys($teacher_sections);
$students_result = [];
if (!empty($all_section_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_section_ids), '?'));
    $types = str_repeat('i', count($all_section_ids));

    $stmt = $conn->prepare("
        SELECT ss.section_id, s.s_id,
               CONCAT(s.s_lname, IF(s.s_suffix!='', CONCAT(' ',s.s_suffix), ''), ', ',
                      s.s_fname, IF(s.s_mname!='', CONCAT(' ',LEFT(s.s_mname,1),'.'), '')) AS student_name,
               'Regular' AS student_type
        FROM students_sections ss
        JOIN students s ON ss.s_id = s.s_id
        WHERE ss.section_id IN ($placeholders)
        ORDER BY ss.section_id, s.s_lname, s.s_fname
    ");
    $stmt->bind_param($types, ...$all_section_ids);
    $stmt->execute();
    $students_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ✅ Fetch Irregular Students from subject_enrollments
$irregular_students = [];
foreach ($section_subject_map as $sec_id => $subjects) {
    foreach ($subjects as $subj) {
       $stmt = $conn->prepare("
    SELECT se.section_code AS section_id, se.s_id, se.subject_code,
           CONCAT(s.s_lname, IF(s.s_suffix!='', CONCAT(' ',s.s_suffix), ''), ', ',
                  s.s_fname, IF(s.s_mname!='', CONCAT(' ',LEFT(s.s_mname,1),'.'), '')) AS student_name,
           'Irregular' AS student_type
    FROM subject_enrollments se
    JOIN students s ON se.s_id = s.s_id
    JOIN sections_schedules ss 
      ON se.section_code = ss.section_id AND se.subject_code = ss.subject_code
    WHERE ss.teacher_id = ? 
      AND ss.term_id = ? 
      AND se.term_id = ? 
      AND se.section_code = ? 
      AND se.subject_code = ?
");

$stmt->bind_param("iiiis", $teacher_id, $term_id, $term_id, $sec_id, $subj);
$stmt->execute();
$irregular_students = array_merge($irregular_students, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt->close();

    }
}

// ✅ Merge students
$students = [];
foreach ($students_result as $r) {
    $students[$r['s_id'].'_'.$r['section_id'].'_'.$r['student_type']] = $r;
}
foreach ($irregular_students as $ir) {
    $key = $ir['s_id'].'_'.$ir['section_id'].'_'.$ir['student_type'];
    if (!isset($students[$key])) $students[$key] = $ir;
}

// ✅ Prepare attendance summary
$attendance = [];
foreach ($students as $stu) {
    $s_id = $stu['s_id'];
    $section_id = $stu['section_id'];
    $subjects = $section_subject_map[$section_id] ?? [];

    foreach ($subjects as $subj) {
        if ($stu['student_type']=='Irregular' && $subj != $stu['subject_code']) continue;

        $stmt = $conn->prepare("
            SELECT status
            FROM attendance
            WHERE s_id=? AND subject_code=? AND term_id=? AND DATE(time_in) BETWEEN ? AND ?
        ");
        $stmt->bind_param("isiss", $s_id, $subj, $term_id, $startDate, $endDate);
        $stmt->execute();
        $att_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $present = $late = $absent = 0;
        foreach ($att_records as $row) {
            $status = strtoupper(trim($row['status']));
            if ($status==='PRESENT') $present++;
            elseif ($status==='LATE') $late++;
            elseif ($status==='ABSENT') $absent++;
        }

        $attendance[$s_id][$subj] = [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'days' => implode(', ', $section_days_map[$subj] ?? [])
        ];
    }
}

// ✅ Day abbreviations
$day_abbrevs = [
    'Monday'=>'M','Tuesday'=>'T','Wednesday'=>'W','Thursday'=>'Th',
    'Friday'=>'F','Saturday'=>'Sa','Sunday'=>'Su'
];
$week_order = ['M','T','W','Th','F','Sa','Su'];
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance per Student</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="assets/css/content.css">
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
/* Prevent page scroll */
html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

body{
    overflow-x: hidden;
}

/* Main content area */
main {
    max-width: calc(100% - 240px); /* leave space for sidebar */
    box-sizing: border-box;
    overflow: hidden;
}

/* Card styling */
.card {
    border-radius: var(--card-border-radius);
    box-shadow: 0 4px 6px rgba(61, 82, 160, 0.07);
    border: none;
    background: white;
    max-width: calc(100% - 2rem); /* leave 1rem margin from viewport edges */
    overflow: hidden;
}

.card-header{
    background: var(--tertiary) !important;

}

/* Table container scroll only inside card */
.table-responsive {
    width: 100%;
    overflow-x: auto;  
    overflow-y: hidden;
}

/* Profile header */
.profile-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 1.2rem;
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
    background: var(--tertiary);
    border: 3px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: 1px;
    color:var(--primary)
}

/* Table adjustments */
table#attendanceTable {
    width: 100% !important;
    table-layout: auto;
}
th{
        background: var(--primary) !important;
        color: white;
    }

/* Responsive adjustments */
@media (max-width: 768px) {
    main {
        margin-left: 0;
        max-width: 100%;
        padding: 0.5rem;
    }
    .card {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    .profile-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .profile-avatar {
        margin-bottom: 0.5rem;
    }
    table#attendanceTable th,
    table#attendanceTable td {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
}


</style>
<body>
<main>
<div class="container-fluid">
    <div class="row g-0">
        <div class="col-md-12">
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= htmlspecialchars(strtoupper(substr($teacher['t_fname'],0,1).substr($teacher['t_lname'],0,1))) ?>
                    </div>
                    <div class="profile-info">
                        <h3>Generate Attendance Reports</h3>
                        <p>Attendance Reports for Section</p>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-primary" id="openExportModal">
                        <i class="bi bi-file-earmark-excel"></i> Export Attendance Report
                        </button>

                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3 gap-2 align-items-center">
                    <label class="fw-bold">Subject:</label>
                   <select id="sectionSelect" class="form-select w-auto">
    <option value="">All Subjects</option>
    <?php foreach ($section_subject_map as $section_id => $subjects): ?>
        <?php 
            $section_name = htmlspecialchars($teacher_sections[$section_id]);
            foreach ($subjects as $subject_code): 
                $schedule_entries = $section_days_map[$subject_code] ?? [];
                $time_day_map = []; // time_range => array of days

                foreach ($schedule_entries as $entry) {
                    preg_match('/\((.*?)\)/', $entry, $time_match);
                    $time_range = $time_match[1] ?? '';
                    $days_part = trim(preg_replace('/\(.*?\)/', '', $entry));
                    $days = array_map('trim', explode(',', $days_part));

                    foreach ($days as $day_full) {
                        $day_full = ucfirst(strtolower($day_full));
                        $short_day = $day_abbrevs[$day_full] ?? $day_full;

                        if (!isset($time_day_map[$time_range])) {
                            $time_day_map[$time_range] = [];
                        }
                        if (!in_array($short_day, $time_day_map[$time_range])) {
                            $time_day_map[$time_range][] = $short_day;
                        }
                    }
                }

                // Merge days with same time
                $merged_schedule = [];
                foreach ($time_day_map as $time_range => $days_array) {
                    $sorted_days = array_values(array_intersect($week_order, $days_array));
                    $merged_schedule[] = implode(',', $sorted_days) . " ({$time_range})";
                }

                $days_text = implode(' | ', $merged_schedule);
        ?>
        <option 
            value="<?= $section_id . '|' . htmlspecialchars($subject_code) ?>" 
            data-days="<?= htmlspecialchars($days_text) ?>"
        >
            <?= $section_name ?> - <?= htmlspecialchars($subject_code) ?>
        </option>
        <?php endforeach; ?>
    <?php endforeach; ?>
</select>

                    <label class="fw-bold">Month:</label>
                    <input type="month" class="form-control w-auto" id="monthInput" value="<?= $month ?>">
                     <label class="fw-bold">Semester:</label>
                        <select class="form-select w-auto" id="semesterSelect">
                            <option value="1">First</option>
                            <option value="2">Second</option>
                            <option value="3">Summer</option>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table id="attendanceTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Subject Code</th>
                            <th>Days of the Week</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>Absent</th>
                        </tr>
                    </thead>
                        <tbody>
                        <?php
                        $i = 1;
                        foreach($teacher_sections as $section_id => $section_name){
                            $students = $students_by_section[$section_id] ?? [];
                            $subjects = $section_subject_map[$section_id] ?? [];

                            foreach($students as $student){
                                foreach($subjects as $subject_code){

                                    $att = $attendance[$student['s_id']][$subject_code] ?? [];
                                    $present = (int)($att['present'] ?? 0);
                                    $late    = (int)($att['late'] ?? 0);
                                    $absent  = (int)($att['absent'] ?? 0);

                                    $schedule_entries = $section_days_map[$subject_code] ?? [];
                                    $time_day_map = []; // time_range => array of days

                                    foreach ($schedule_entries as $entry) {
                                        preg_match('/\((.*?)\)/', $entry, $time_match);
                                        $time_range = $time_match[1] ?? '';
                                        $days_part = trim(preg_replace('/\(.*?\)/', '', $entry));
                                        $days = array_map('trim', explode(',', $days_part));

                                        foreach ($days as $day_full) {
                                            $day_full = ucfirst(strtolower($day_full));
                                            $short_day = $day_abbrevs[$day_full] ?? $day_full;

                                            if (!isset($time_day_map[$time_range])) {
                                                $time_day_map[$time_range] = [];
                                            }
                                            if (!in_array($short_day, $time_day_map[$time_range])) {
                                                $time_day_map[$time_range][] = $short_day;
                                            }
                                        }
                                    }

                                    // Merge days by same time
                                    $schedule_parts = [];
                                    foreach ($time_day_map as $time_range => $days_array) {
                                        // Sort days according to week_order
                                        $sorted_days = array_values(array_intersect($week_order, $days_array));
                                        $schedule_parts[] = implode( $sorted_days) . " ({$time_range})";
                                    }

                                    $schedule_text = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '—';

                                    echo '<tr>
                                        <td>'.$i++.'</td>
                                        <td>'.htmlspecialchars($student['student_name']).'</td>
                                        <td>'.htmlspecialchars($subject_code).'</td>
                                        <td>'.htmlspecialchars($schedule_text).'</td>
                                        <td>'.$present.'</td>
                                        <td>'.$late.'</td>
                                        <td>'.$absent.'</td>
                                    </tr>';
                                }
                            }
                        }
                        ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Export Attendance Modal -->
<div class="modal fade" id="setDaysModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-excel"></i> Export Attendance Report
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Submit directly to export_attendance_reports.php -->
      <form id="attendanceDaysForm" method="POST" action="export_attendance_reports.php">
        <!-- Hidden inputs -->
        <input type="hidden" name="section_id" id="attendanceSectionId">
        <input type="hidden" name="section_name" id="attendanceSectionName">
        <input type="hidden" name="subject_code" id="attendanceSubjectCode">
        <input type="hidden" name="scheduled_days" id="classDaysInput">
        <input type="hidden" name="total_days" id="totalDaysHidden">

        <div class="modal-body row g-3">
          <!-- Semester -->
          <div class="col-md-6">
            <label class="fw-bold">Semester:</label>
            <select class="form-select" name="semester" id="attendanceSemester" required>
              <option value="">-- Select Semester --</option>
              <option value="First">First</option>
              <option value="Second">Second</option>
              <option value="Summer">Summer</option>
            </select>
          </div>

          <!-- Subject -->
          <div class="col-md-6">
            <label class="fw-bold">Subject:</label>
            <input type="text" class="form-control" name="subject_name" id="attendanceSubjectName" readonly>
          </div>

          <!-- Section -->
          <div class="col-md-6">
            <label class="fw-bold">Section:</label>
            <input type="text" class="form-control" id="attendanceSectionDisplay" readonly>
          </div>
            <!-- Days of the Week (Time) -->
            <div class="col-md-6">
            <label class="fw-bold">Days of the Week (Time):</label>
            <input type="text" class="form-control" id="attendanceDaysOfWeek" readonly>
            </div>

          <!-- Start Date -->
          <div class="col-md-6">
            <label class="fw-bold">Start Date:</label>
            <input type="date" class="form-control" name="start_date" id="semesterStartDate" required>
          </div>

          <!-- End Date -->
          <div class="col-md-6">
            <label class="fw-bold">End Date:</label>
            <input type="date" class="form-control" name="end_date" id="semesterEndDate" required>
          </div>

          <!-- Select Days of Week -->
          <div class="col-md-12">
            <label class="fw-bold">Class Days of the Week:</label>
            <div class="btn-group d-flex flex-wrap gap-2" role="group">
              <button type="button" class="btn btn-outline-primary day-pill" data-day="1">Mon</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="2">Tue</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="3">Wed</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="4">Thu</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="5">Fri</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="6">Sat</button>
              <button type="button" class="btn btn-outline-primary day-pill" data-day="7">Sun</button>
            </div>
          </div>

          <!-- Auto-Calculated Total Class Days -->
          <div class="col-md-6">
            <label class="fw-bold">Total Class Days:</label>
            <input type="text" class="form-control" id="totalClassDays" readonly>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-download"></i> Generate & Export
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
  <div id="exportToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="exportToastBody">
        Attendance report sent successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){

    // Initialize DataTable
    let table = $('#attendanceTable').DataTable({
        responsive: true,
        autoWidth: false,
        scrollX: true,
        scrollY: '380px',
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        ordering: true,
        columnDefs: [{ orderable: false, targets: -1 }],
        dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
        language: { lengthMenu: "Show _MENU_ entries" }
    });

   // 🔹 Update hidden section name input
function updateSectionName() {
    let selectedText = $("#sectionSelect option:selected").text();
    $('#attendanceSectionName').val(selectedText);
}
updateSectionName(); // Initial set

function loadAttendance(section_id = '', month = '', semester = '', subject_code = '') {
    if (!month || !semester) {
        console.warn("Missing required filters");
        table.clear().draw();
        return;
    }

    let requestData = { month: month, semester: semester };
    if (section_id) requestData.section_id = section_id;
    if (subject_code) requestData.subject_code = subject_code;

    $.getJSON('reports/processes/fetch_attendance.php', requestData, function(data) {
        table.clear().draw();
        let i = 1;

        if(data.length > 0){
            data.forEach(student => {
                let dayTime = student.days_of_week || '';
                table.row.add([
                    i++,
                    student.student_name || '',
                    student.subject_code || '',
                    dayTime || '',
                    student.present || 0,
                    student.late || 0,
                    student.absent || 0
                ]);
            });
        }
        table.draw();
    }).fail(function(xhr, status, error){
        console.error("AJAX Error:", status, error);
    });
}

$('#sectionSelect').on('change', function() {
    let val = $(this).val(); // "section_id|subject_code"
    let [section_id, subject_code] = val ? val.split('|') : ['', ''];

    loadAttendance(
        section_id,
        $('#monthInput').val(),
        $('#semesterSelect').val(),
        subject_code
    );
});


let initialVal = $('#sectionSelect').val();
let [initialSection, initialSubject] = initialVal ? initialVal.split('|') : ['', ''];

loadAttendance(
    initialSection,
    $('#monthInput').val(),
    $('#semesterSelect').val(),
    initialSubject
);

    

    // ----- Export Modal -----
    function calculateClassDays() {
        let startDate = $('#semesterStartDate').val();
        let endDate   = $('#semesterEndDate').val();
        let selectedDays = $('#classDaysInput').val().split(',').map(Number);

        if (!startDate || !endDate || selectedDays.length === 0) {
            $('#totalClassDays').val('');
            $('#totalDaysHidden').val('');
            return;
        }

        let start = new Date(startDate);
        let end   = new Date(endDate);
        let count = 0;

        while (start <= end) {
            let day = start.getDay(); // 0=Sun ... 6=Sat
            let normalized = day === 0 ? 7 : day; // match Mon=1 .. Sun=7
            if (selectedDays.includes(normalized)) {
                count++;
            }
            start.setDate(start.getDate() + 1);
        }

        $('#totalClassDays').val(count);
        $('#totalDaysHidden').val(count);
    }

    $('.day-pill').on('click', function(){
        $(this).toggleClass('active');
        let selected = [];
        $('.day-pill.active').each(function(){
            selected.push($(this).data('day'));
        });
        $('#classDaysInput').val(selected.join(','));
        calculateClassDays();
    });

    $('#semesterStartDate, #semesterEndDate').on('change', calculateClassDays);

    $('#openExportModal').on('click', function(){
    let selectedOption = $('#sectionSelect option:selected');
    let sectionText = selectedOption.text();
    let sectionCode = sectionText.split(' - ')[0];
    let subjectCode = selectedOption.data('subject');
    let days        = (selectedOption.data('days') || "").toString().split(',');
    let startTime   = selectedOption.data('start');
    let endTime     = selectedOption.data('end');

    $('#attendanceSectionId').val(selectedOption.val());
    $('#attendanceSectionName').val(sectionCode);
    $('#attendanceSubjectCode').val(subjectCode);
    $('#attendanceSubjectName').val(subjectCode);
    $('#attendanceSectionDisplay').val(sectionCode);

    // Reset pills
    $('.day-pill').removeClass('active');

    // Auto-select pills
   // Day name to number map
let dayMap = {
    "Mon": 1,
    "Tue": 2,
    "Wed": 3,
    "Thu": 4,
    "Fri": 5,
    "Sat": 6,
    "Sun": 7
};

// Auto-select pills by mapping names to numbers
days.forEach(function(day){
    let trimmed = day.trim();
    let dayNum = dayMap[trimmed];
    if(dayNum) {
        $('.day-pill[data-day="'+dayNum+'"]').addClass('active');
    }
});


    // Format time range string
    let dayTimeDisplay = '';
    if (days.length > 0 && startTime && endTime) {
        let dayNames = {
            1: "Mon", 2: "Tue", 3: "Wed", 
            4: "Thu", 5: "Fri", 6: "Sat", 7: "Sun"
        };
     let dayLabels = days.map(d => d.trim()).join(', ');

        dayTimeDisplay = dayLabels + " (" + startTime + " - " + endTime + ")";
    }
    $('#attendanceDaysOfWeek').val(dayTimeDisplay);

    // Reset hidden inputs
    let selected = [];
    $('.day-pill.active').each(function(){
        selected.push($(this).data('day'));
    });
    $('#classDaysInput').val(selected.join(','));
    $('#totalClassDays').val('');
    $('#totalDaysHidden').val('');

    $('#setDaysModal .modal-title').text('Export Attendance Report for ' + sectionCode + ' (' + subjectCode + ')');
    $('#setDaysModal').modal('show');
});


    $('#attendanceDaysForm').on('submit', function(e){
    e.preventDefault();

    let formData = $(this).serialize();

    $.ajax({
        url: 'reports/processes/export_attendance_reports.php',
        type: 'POST',
        data: formData,
        dataType: 'json', // expecting JSON response {success: true/false, message: "..."}
        success: function(resp){
            let toastEl = document.getElementById('exportToast');
            if(resp.success){
                $('#exportToastBody').text(resp.message || 'Attendance report sent successfully!');
                $(toastEl).removeClass('bg-danger').addClass('bg-success');
            } else {
                $('#exportToastBody').text(resp.message || 'Failed to send attendance report.');
                $(toastEl).removeClass('bg-success').addClass('bg-danger');
            }
            let toast = new bootstrap.Toast(toastEl);
            toast.show();

            $('#setDaysModal').modal('hide');
        },
        error: function(xhr, status, error){
            let toastEl = document.getElementById('exportToast');
            $('#exportToastBody').text('Error sending report.');
            $(toastEl).removeClass('bg-success').addClass('bg-danger');
            let toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    });
});

});
</script>

</body>
</html>
