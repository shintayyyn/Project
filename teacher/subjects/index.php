<?php
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    die("No active term found.");
}

// ✅ Get teacher's subjects with sections, schedules, and student counts filtered by active term
$subjects_query = "
SELECT 
    s.subject_id,
    s.subject_code,
    s.subject_description,
    s.units,
    sec.section_code,
    sec.section_id,
    d.degree_code,
    d.degree_name,
    ss.ss_id,  
    ss.schedule_group_id,
    ss.start_time,
    ss.end_time,
    ss.day_of_week,
    ss.status,
    ss.description,
    ss.teacher_name,
    r.room_number,

    -- ✅ Regular students (based on section_id and term)
    (
        SELECT COUNT(*) 
        FROM students_sections ss2
        INNER JOIN students st2 ON ss2.s_id = st2.s_id
        WHERE ss2.section_id = sec.section_id
          AND ss2.term_id = ?
          AND st2.is_regular = 1
    ) AS regular_count,

    -- ✅ Irregular students (filtered by section_code + subject)
    (
        SELECT COUNT(DISTINCT se.s_id)
        FROM subject_enrollments se
        INNER JOIN students st3 ON se.s_id = st3.s_id
        WHERE se.subject_id = s.subject_id
          AND se.term_id = ?
          AND se.section_code = sec.section_code    -- ✅ Ensure alignment with current section
          AND se.enrollment_status = 'Enrolled'
          AND st3.is_regular = 2
    ) AS irregular_count

FROM sections_schedules ss
INNER JOIN subjects s ON s.subject_id = ss.subject_id
INNER JOIN sections sec ON ss.section_id = sec.section_id
INNER JOIN degrees d ON sec.degree_id = d.degree_id
LEFT JOIN rooms r ON ss.room_id = r.room_id
WHERE ss.teacher_id = ?
  AND ss.term_id = ?
ORDER BY s.subject_code, sec.section_code, ss.day_of_week, ss.start_time;
";



$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("iiii", $term_id, $term_id, $teacher_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();


// ✅ Build the nested array structure
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subject_code = $row['subject_code'];
    if (!isset($subjects[$subject_code])) {
        $subjects[$subject_code] = [
            'code' => $subject_code,
            'description' => $row['subject_description'],
            'units' => $row['units'],
            'sections' => []
        ];
    }

    $section_code = $row['section_code'];
    if (!isset($subjects[$subject_code]['sections'][$section_code])) {
        $regular = (int)$row['regular_count'];
        $irregular = (int)$row['irregular_count'];

        $subjects[$subject_code]['sections'][$section_code] = [
            'section_id' => $row['section_id'],
            'section_code' => $section_code,
            'degree_code' => $row['degree_code'],
            'degree_name' => $row['degree_name'],
            'regular_count' => $regular,
            'irregular_count' => $irregular,
            'student_count' => $regular + $irregular, // ✅ always computed
            'schedules' => []
        ];
    }

    // ✅ Prevent duplicate schedules per ss_id
    if (!isset($subjects[$subject_code]['sections'][$section_code]['schedules'][$row['ss_id']])) {
        $subjects[$subject_code]['sections'][$section_code]['schedules'][$row['ss_id']] = [
            'ss_id' => $row['ss_id'],
            'schedule_group_id' => $row['schedule_group_id'],
            'day' => $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'room' => $row['room_number'] ?? 'TBA',
            'status' => $row['status'],
            'description' => $row['description'],
            'teacher_name' => $row['teacher_name']
        ];
    }
}

// ✅ Calculate subject totals
foreach ($subjects as $code => &$subject) {
    $total_regular = 0;
    $total_irregular = 0;

    foreach ($subject['sections'] as $section) {
        $total_regular += $section['regular_count'];
        $total_irregular += $section['irregular_count'];
    }

    $subject['total_regular'] = $total_regular;
    $subject['total_irregular'] = $total_irregular;
    $subject['total_students'] = $total_regular + $total_irregular;
}
unset($subject);



?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
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

*{
            font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
        }
.card {
    border-radius: var(--card-border-radius);
    box-shadow: 0 4px 6px rgba(61, 82, 160, 0.07);
    border: none;
    transition: transform var(--transition-speed);
    margin-bottom: 1.5rem;

}

.card:hover {
    transform: translateY(-2px);
}

.card-header {
    background: var(--primary);
    color: var(--tertiary);
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0 !important;
    padding: 1rem 1.5rem;
}


.subject-card {
    height: 100%;
}

.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

}

.subject-stats {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(61, 82, 160, 0.05);
    border-radius: 0.5rem;
}

.stat-item {
    flex: 1;
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.section-list {
    margin-top: 1rem;
}

.section-item {
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.schedule-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    font-size: 0.875rem;
}

.schedule-item i {
    color: var(--primary);
}
/* Hide vertical scrollbar everywhere but keep scroll functionality */
html, body,.modal {
    overflow-y: auto;  /* allow vertical scroll */
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;     /* Firefox */
}

/* Webkit browsers: Chrome, Safari, Edge */
html::-webkit-scrollbar,
body::-webkit-scrollbar {
    width: 0px;
    background: transparent;  /* optional: hide background */
}

/* Optional: keep horizontal scroll if needed */
html, body {
    overflow-x: hidden;  /* hide horizontal scroll */
}


/* Main content area */
main {
    margin-left: 5px;
    width: calc(100% - 260px);
    padding: 0;
    overflow-x: hidden;
     position: relative; /* ensures z-index stacking context */
    z-index: 1;   
}
/* Ensure modal is on top */
.modal {
    z-index: 2000;
}

/* Optional: prevent body shifting when modal opens */
body.modal-open {
    overflow-x: hidden; 
     /* keeps horizontal scroll disabled */
    padding-right: 0 !important; /* prevent Bootstrap from adding extra space */
}




/* Container adjustments */
.container-fluid {
    width: 100%;
    padding: 0;
    margin: 0;
    overflow-x: hidden;
}

/* Add padding to content */
h2.mb-4 {
    padding: 1rem;
}

.card-body {
    padding: 1.5rem;
}
.btn.btn-primary:hover i {
  color: white !important;
}
span.input-group-text{
    background: var(--primary);
}.clear-3d {
    color: #012f58ff; /* base color */
    text-shadow: 
        1px 1px 0 #081b7933,
        2px 2px 2px #081b7933,
        3px 3px 3px #081b7933;
    transition: transform 0.2s, color 0.2s;
}

.clear-3d:hover {
    color: #dc3545;                 /* change color on hover */
    transform: translateY(-2px) scale(1.2); /* lift and scale for 3D effect */
    text-shadow: 
        2px 2px 1px #00000044,
        3px 3px 2px #00000033,
        4px 4px 3px #00000022;      /* stronger shadows for depth on hover */
}


</style>
<link rel="stylesheet" href="assets/css/content.css">
<main>
    <div class="container-fluid" id="subjectsContainer">
        <div class="row align-items-center mb-3 g-2 flex-wrap">
    <div class="col-md-8 col-12">
        <h2 class="mb-1 fw-bold">My Subjects</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Subjects</li>
            </ol>
        </nav>
    </div>

   <div class="col-md-4 col-12 d-flex justify-content-end mt-2 mt-md-0 text-end">
    <!-- Search widget -->
    <div class="input">
        <div class="input-group">
            <span class="input-group-text text-warning">
                <i class="bi bi-search"></i>
            </span>
            <div class="position-relative">
        <!-- Input field with padding for icons -->
        <input type="text" id="subjectSearch" class="form-control  pe-5" placeholder="Search...">
        <!-- Clear "x" button on the right inside input -->
       <span id="clearSearch" class="position-absolute top-50 end-0 translate-middle-y pe-3" style="cursor: pointer;">
    <i class="bi bi-x text-secondary clear-3d"></i>
</span>

    </div>
        </div>
    </div>
</div>
</div>

        <div class="row">
           <?php foreach ($subjects as $subject): ?>
    <div class="col-md-6 mb-4 subject-card-wrapper">
        <div class="card subject-card">
            <div class="card-header">
                <div class="subject-header">
                            <h5 class="mb-0 fw-bold">
                                <?= htmlspecialchars($subject['code']); ?>
                                <small class="d-block text-white-50">
                                    <?= htmlspecialchars($subject['description']); ?>
                                </small>
                            </h5>
                            <span class="badge bg-white text-primary">
                                <?= $subject['units']; ?> units
                            </span>
                    <!-- Removed Show button from here -->
                </div>
            </div>

            <div class="card-body">
                <div class="subject-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= count($subject['sections']); ?></div>
                        <div class="stat-label">Sections</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $subject['total_students']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>

                <div class="section-list">
                    <?php foreach ($subject['sections'] as $section): ?>
                        <div class="section-item">
                            <div class="section-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($section['section_code']); ?>
                                        <small class="text-muted d-block">
                                            <?= htmlspecialchars($section['degree_name']); ?>
                                        </small>
                                    </h6>
                                </div>

                                <!-- ✅ Show Students Button (per section) -->
                                <!-- ✅ Show Students Button (per section) -->
<button type="button" 
        class="w-auto btn-sm btn-warning"
        data-bs-toggle="modal"
        data-bs-target="#studentModal"
        data-section="<?= htmlspecialchars($section['section_code']); ?>"
        data-subject="<?= htmlspecialchars($subject['code']); ?>">
    <i class="bi bi-people-fill me-2"></i>Show
</button>


                            </div>

                            <!-- Section stats badges -->
                            <span class="badge bg-primary"><?= $section['student_count']; ?> students</span>
                            <span class="badge bg-success ms-1"><?= $section['regular_count']; ?> Regular</span>
                            <span class="badge bg-warning text-dark ms-1"><?= $section['irregular_count']; ?> Irregular</span>

                            <!-- ✅ Schedule list with Log Attendance per ss_id -->
                            <ul class="schedule-list">
                                <?php foreach ($section['schedules'] as $schedule): ?>
                                    <li class="schedule-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-clock"></i>
                                            <?= htmlspecialchars($schedule['day']); ?> 
                                            <?= date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?>
                                            <i class="bi bi-building ms-2"></i> 
                                            <?= htmlspecialchars($schedule['room']); ?>
                                        </div>

                                        <!-- ✅ Log Attendance button -->
                                       <?php
$schedule_date = $schedule['day']; 
$start_time = $schedule['start_time']; 
$end_time = $schedule['end_time'];     

$current_day = date('l'); 
$current_time = date('H:i:s');

// ✅ Convert times to timestamps for accurate comparison
$start_timestamp = strtotime($start_time);
$end_timestamp = strtotime($end_time);
$current_timestamp = strtotime($current_time);

// ✅ Allow button 15 minutes before class starts
$early_start = $start_timestamp - (15 * 60); // 15 minutes earlier

$is_today = strtolower($schedule_date) === strtolower($current_day);
$is_within_time = ($current_timestamp >= $early_start && $current_timestamp <= $end_timestamp);
$is_disabled = !$is_today || !$is_within_time;
?>


                                        <a href="<?= $is_disabled 
                                                    ? '#' 
                                                    : 'subjects/log_attendance.php?subject_code=' . urlencode($subject['code']) . 
                                                      '&section_id=' . $section['section_id'] . 
                                                      '&ss_id=' . $schedule['ss_id']; ?>" 
                                           class="btn btn-sm btn-primary text-nowrap <?= $is_disabled ? 'disabled' : '' ?>" 
                                           <?= $is_disabled ? 'aria-disabled="true" tabindex="-1" title="Not available at this time"' : '' ?>>
                                            <i class="bi bi-qr-code-scan me-1"></i>Log Attendance
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>


            
            <?php if (empty($subjects)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted"></i>
                        <h4 class="mt-3">No Subjects Assigned</h4>
                        <p class="text-muted">You don't have any subjects assigned to you at the moment.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Single modal -->
<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header card-header text-white">
                <h5 class="modal-title ">Students in <span id="modalSectionCode"></span></h5>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
       </div>
            <div class="modal-body">
                <div class="text-end mb-3">
                    <span id="modalCounts"></span>
                </div>
                <div id="studentList">
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-hourglass-split"></i> Loading students...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#studentModal').on('shown.bs.modal', function (e) {
        var button = $(e.relatedTarget); // get the button that triggered the modal
        var sectionCode = button.data('section'); 
        var subjectCode = button.data('subject');
        var termId = <?= $term_id ?>; // PHP variable

        // Update modal title
        $('#modalSectionCode').text(sectionCode + ' (' + subjectCode + ')');

        // Show loading placeholder
        $('#studentList').html('<div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Loading students...</div>');

        // Fetch students via AJAX
        $.ajax({
            url: 'subjects/fetch_students.php',
            type: 'GET',
            data: {
                section_id: sectionCode,
                subject_code: subjectCode,
                term_id: termId
            },
            success: function(data) {
                // Inject table into responsive wrapper
                $('#studentList').html('<div class="table-responsive">' + data + '</div>');

                // Destroy any existing DataTable first to prevent duplication
                if ($.fn.DataTable.isDataTable('#studentsTable')) {
                    $('#studentsTable').DataTable().destroy();
                }

                // Initialize DataTable on the newly loaded table
                $('#studentsTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    responsive: true,
                    scrollY: '50vh',
                    scrollCollapse: true,
                    scrollX: true,
                    scroller: true,
                    columnDefs: [
                        { orderable: false, targets: 3 } // Status column
                    ]
                });
            },
            error: function() {
                $('#studentList').html('<div class="text-danger text-center py-3">Failed to load students.</div>');
            }
        });
    });

    // Optional: fix column alignment on modal resize
    $('#studentModal').on('shown.bs.modal', function() {
        if ($.fn.DataTable.isDataTable('#studentsTable')) {
            $('#studentsTable').DataTable().columns.adjust().draw();
        }
    });

  $(document).ready(function() {
    // Add placeholder alert inside the container
    if ($('#noSearchResult').length === 0) {
        $('#subjectsContainer').append('<div id="noSearchResult" class="alert alert-info text-center mt-3" style="display:none;">No subjects or sections found.</div>');
    }

    $('#subjectSearch').on('input', function() {
        var query = $(this).val().toLowerCase();
        var visibleCount = 0;

        $('.subject-card-wrapper').each(function() {
            var subjectText = $(this).text().toLowerCase();
            if (subjectText.indexOf(query) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        // Show alert inside the container if no cards are visible
        if (visibleCount === 0) {
            $('#noSearchResult').show();
        } else {
            $('#noSearchResult').hide();
        }
    });
});

 // Clear button functionality
    $('#clearSearch').on('click', function() {
        $('#subjectSearch').val('').focus();  // Clear input and focus
        $('.subject-card-wrapper').show();       // Show all sections
        $('#noSearchResult').show(); // Hide alert
    });
});

</script>
</body>
</html>

