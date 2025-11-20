<?php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access.');
}

$student_id = $_SESSION['user_id'];

// ✅ Initialize schedules per day
$schedules = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [],
    'Thursday' => [], 'Friday' => [], 'Saturday' => [], 'Sunday' => [],'TBA' => []
];

// 🧠 Get student's regularity status
$status_query = "SELECT is_regular FROM students WHERE s_id = ?";
$stmt = $conn->prepare($status_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$is_regular = ($stmt->get_result()->fetch_assoc()['is_regular'] ?? 1);
$stmt->close();

// 🧭 Get student's section info (for regulars)
$section_query = "
    SELECT sec.section_id, sec.section_code
    FROM students_sections ss
    JOIN sections sec ON sec.section_id = ss.section_id
    WHERE ss.s_id = ?
";
$stmt = $conn->prepare($section_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$section_info = $stmt->get_result()->fetch_assoc();
$section_id = $section_info['section_id'] ?? null;
$stmt->close();

// 🧠 Get active term
$period_query = "
    SELECT 
        at.term_id,
        at.semester,
        CONCAT(ay.year_start, '-', ay.year_end) AS academic_year
    FROM academic_terms at
    INNER JOIN academic_years ay ON ay.ay_id = at.ay_id
    WHERE at.is_active = 1
    ORDER BY ay.year_start DESC, FIELD(at.semester, 'First', 'Second', 'Summer') DESC
    LIMIT 1
";
$period_result = $conn->query($period_query);
$period = $period_result ? $period_result->fetch_assoc() : null;

$activeTermId = $period['term_id'] ?? null;
if (!$activeTermId) {
    echo json_encode(['error' => 'No active term found']);
    exit;
}

// 🔹 Irregular student (is_regular = 2)
if ($is_regular == 2) {
   $schedule_query = "
    SELECT DISTINCT
        ss.ss_id,
        t.t_id AS teacher_id,
        se.subject_id,
        se.subject_code,
        ss.start_time,
        FIELD(ss.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') AS day_order_field,
        COALESCE(s.subject_description, 'TBA') AS subject_description,
        COALESCE(CONCAT(
            t.t_fname, ' ',
            IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''),
            t.t_lname,
            IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
        ), 'TBA') AS teacher_name,
        COALESCE(ss.day_of_week, 'TBA') AS day_of_week,
        COALESCE(TIME_FORMAT(ss.start_time, '%h:%i %p'), 'TBA') AS start_time,
        COALESCE(TIME_FORMAT(ss.end_time, '%h:%i %p'), 'TBA') AS end_time,
        COALESCE(r.room_number, 'TBA') AS room_number,
        COALESCE(ss.status, 'TBA') AS status,
        COALESCE(ss.description, 'TBA') AS description
    FROM subject_enrollments se
    LEFT JOIN sections_schedules ss 
           ON ss.subject_id = se.subject_id 
          AND ss.subject_code = se.subject_code
          AND ss.section_code = se.section_code
          AND ss.term_id = se.term_id
          AND ss.is_active = 1
    LEFT JOIN subjects s ON s.subject_id = se.subject_id
    LEFT JOIN teachers t ON ss.teacher_id = t.t_id
    LEFT JOIN rooms r ON ss.room_id = r.room_id
        WHERE se.s_id = ?
          AND se.term_id = ?
          AND se.enrollment_status = 'Enrolled'
        ORDER BY day_order_field, ss.start_time ASC
    ";
    
        $stmt = $conn->prepare($schedule_query);    $stmt->bind_param("ii", $student_id, $activeTermId);

// 🔹 Regular student (is_regular = 1)
} else {
    if (!$section_id) {
        echo json_encode(['error' => 'Section not found for regular student']);
        exit;
    }

  $schedule_query = "
    SELECT DISTINCT
        ss.ss_id,
        t.t_id AS teacher_id,
        ss.subject_id,
        ss.subject_code,
        ss.start_time,
        FIELD(ss.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') AS day_order_field,
        COALESCE(s.subject_description, 'TBA') AS subject_description,
        COALESCE(CONCAT(
            t.t_fname, ' ',
            IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''),
            t.t_lname,
            IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
        ), 'TBA') AS teacher_name,
        COALESCE(ss.day_of_week, 'TBA') AS day_of_week,
        COALESCE(TIME_FORMAT(ss.start_time, '%h:%i %p'), 'TBA') AS start_time,
        COALESCE(TIME_FORMAT(ss.end_time, '%h:%i %p'), 'TBA') AS end_time,
        COALESCE(r.room_number, 'TBA') AS room_number,
        COALESCE(ss.status, 'TBA') AS status,
        COALESCE(ss.description, 'TBA') AS description
    FROM sections_schedules ss
    LEFT JOIN subjects s ON s.subject_id = ss.subject_id
    LEFT JOIN teachers t ON ss.teacher_id = t.t_id
    LEFT JOIN rooms r ON ss.room_id = r.room_id
    WHERE ss.term_id = ?
      AND ss.section_id = ?
      AND ss.is_active = 1
    ORDER BY day_order_field, ss.start_time ASC
";

    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("ii", $activeTermId, $section_id);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// 🗓️ Group schedules by day (with TBA fallback)
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $day = $row['day_of_week'] ?? 'TBA';
        if (!isset($schedules[$day])) $schedules[$day] = [];

        $schedules[$day][] = [
            'ss_id' => $row['ss_id'] ?? 0, // required for attachments
            'subject_id' => $row['subject_id'] ?? 'TBA',
            'subject_code' => $row['subject_code'] ?? 'TBA',
            'subject_description' => $row['subject_description'] ?? 'TBA',
            'teacher_name' => $row['teacher_name'] ?? 'TBA',
            'teacher_id' => $row['teacher_id'] ?? 0, // required for attachments
            'start_time' => $row['start_time'] ?? 'TBA',
            'end_time' => $row['end_time'] ?? 'TBA',
            'room_number' => $row['room_number'] ?? 'TBA',
            'status' => $row['status'] ?? 'Available',
            'description' => $row['description'] ?? ''
        ];
    }
}


$today = date('l');
?>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap" rel="stylesheet">

<main class="py-4">
    <div class="container-fluid px-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-sm-flex align-items-center justify-content-between">
                    <h1 class="h3 text-primary mb-2 mb-sm-0 fw-bold">
                        <i class="bi bi-calendar3-week me-2"></i>My Schedule
                    </h1>
                    <div class="d-flex align-items-center">
                       <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small d-flex align-items-center">
    <i class="bi bi-clock-history me-1"></i>
    Last Updated: <span id="last-updated" class="ms-1 fw-semibold text-dark">
        <?= htmlspecialchars($_SESSION['last_updated']); ?>
    </span>
</div>

                    </div>
                </div>
            </div>
        </div>
        <div id="schedule-container">
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header position-relative p-0">
                            <div class="schedule-header rounded-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                       <div class="d-flex align-items-center gap-3">
    <span><i class="bi bi-calendar3-week fs-1 opacity-25"></i></span>
    <h5 class="card-title fw-semibold mb-0 text-white">Schedule Overview</h5>
</div>

                                         <p class="text-white-50 mb-0 small">Your current class schedule</p>
                                      <div class="fst-italic text-white-50 small mt-2">
                                            <span class="fw-semibold text-white me-2">LEGEND:</span>
                                            <div class="d-flex flex-wrap align-items-center gap-3 mt-1">
                                                <div class="d-flex align-items-center gap-2">
                                                     <span class="btn border text-success bg-success rounded-pill px-3 py-2"></span>
                                                    <span>Available</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="btn border  text-primary bg-primary rounded-pill px-3 py-2"></span>
                                                    <span>Asynchronous</span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="btn border  text-danger bg-danger rounded-pill px-3 py-2"></span>
                                                    <span>Not Available / Unavailable (toggle the first column to see attachment)</span>
                                                </div>
                                            </div>
                                            NOTE : Click the first child row to toggle/ see more.
                                        </div>

                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body py-3 px-2 table-responsive">
                            <?php if (array_sum(array_map('count', $schedules)) > 0): ?>
                                <table id="scheduleTable" class="table display nowrap">
                                    <thead class="card-header">
                                        <tr>
                                            <th>Day & Time</th>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Room</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                 <tbody>
        <?php 
        foreach ($schedules as $day => $day_schedules):
            if (empty($day_schedules)) continue;
            foreach ($day_schedules as $schedule):

        // 🔹 Determine status and description
        $currentStatus = !empty($schedule['status']) ? trim($schedule['status']) : '';
        $statusDescription = !empty($schedule['description']) ? trim($schedule['description']) : '';

        // 🔹 Fetch attachment for this schedule
        $stmt = $conn->prepare("
            SELECT file_name, id, updated_at 
            FROM attachment_files 
            WHERE ss_id = ? 
            AND uploaded_by_id = ? 
            AND uploaded_by_type = 'teacher' 
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("ii", $schedule['ss_id'], $schedule['teacher_id']);
        $stmt->execute();
        $attachment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

      $daysAgo = 6;
$thresholdDate = strtotime("-$daysAgo days");
$latestUpdate = $attachment ? strtotime($attachment['updated_at']) : null;

// 🔹 Default status/description if empty or outdated
if (empty($currentStatus) || !$latestUpdate || $latestUpdate < $thresholdDate) {
    $currentStatus = 'Available';
    $statusDescription = 'Ready for class';
}



        $remarks = htmlspecialchars($currentStatus) . ' - ' . htmlspecialchars($statusDescription);

        // 🔹 Determine badge color for schedule day/time
        $statusColor = 'success';
        $badgeClass = 'bg-outline-success text-success';
        $textColor = 'text-success';

        if (stripos($currentStatus, 'not available') !== false) {
            $statusColor = 'danger';
            $badgeClass = 'bg-outline-danger text-danger';
            $textColor = 'text-danger';
        } elseif (stripos($currentStatus, 'async') !== false || stripos($currentStatus, 'asynchronous') !== false) {
            $statusColor = 'primary';
            $badgeClass = 'bg-outline-primary text-primary';
            $textColor = 'text-primary';
        } elseif (stripos($currentStatus, 'unavailable') !== false) {
            $statusColor = 'warning';
            $badgeClass = 'bg-outline-warning text-warning';
            $textColor = 'text-warning';
        }

        if ($day === $today) {
            $badgeClass = "bg-$statusColor text-white";
            $textColor = 'text-white';
        }
?>
    <tr>
        <td>
            <div class="d-flex gap-3 align-items-center">
                <span class="badge <?php echo $badgeClass; ?> px-3 py-2 d-inline-flex align-items-center">
                    <?php echo $day; ?>
                </span>
                <div class="small d-flex align-items-center gap-2 <?php echo $textColor !== 'text-white' ? 'text-muted' : ''; ?>">
                    <span class="status-dot bg-<?php echo $statusColor; ?>"></span>
                    <?php echo $schedule['start_time'] . ' - ' . $schedule['end_time']; ?>
                </div>
            </div>
        </td>
        <td class="px-3">
            <div class="d-flex flex-column">
                <div class="fw-medium text-primary mb-1"><?php echo htmlspecialchars($schedule['subject_code']); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($schedule['subject_description']); ?></div>
            </div>
        </td>
        <td class="px-3">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                    <i class="bi bi-person text-primary small"></i>
                </div>
                <span class="text-body"><?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
            </div>
        </td>
        <td class="px-3 text-center">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                <?php echo $schedule['room_number'] ? htmlspecialchars($schedule['room_number']) : 'TBA'; ?>
            </span>
        </td>
        <td class="px-3 text-center"><?php echo $remarks; ?></td>
        <td class="px-3 text-center">
           <?php if ($attachment && $latestUpdate >= $thresholdDate): ?>
    <button class="btn btn-sm btn-outline-primary view-attachment-btn"
            data-attachment-id="<?= $attachment['id'] ?>"
            data-uploaded-by="<?= $schedule['teacher_id'] ?>"
            data-bs-toggle="modal"
            data-bs-target="#attachmentModal">
        <i class="bi bi-eye"></i> View
    </button>
<?php else: ?>
    <span class="text-muted">No attachment provided</span>
<?php endif; ?>

        </td>
    </tr>
<?php endforeach; endforeach; ?>
</tbody>


                                </table>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="bg-light rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                        <i class="bi bi-calendar-x text-secondary fs-4"></i>
                                    </div>
                                    <h6 class="text-secondary mb-2">No Classes Scheduled</h6>
                                    <p class="text-muted small mb-0">You don't have any classes scheduled at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header card-header text-white">
        <h5 class="modal-title">Attachment Preview</h5>
        <button type="button" class="btn btn-warning" data-bs-dismiss="modal" aria-label="Close">Close</button>
      </div>
      <div class="modal-body" id="attachmentModalBody">Loading...</div>
    </div>
  </div>
</div>

<style>
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.bg-outline-primary {
    color: #0d6efd;
    border: 1px solid #0d6efd;
    background-color: transparent;
}
.bg-outline-success {
    color: #198754;
    border: 1px solid #198754;
    background-color: transparent;
}
.bg-outline-danger {
    color: #dc3545;
    border: 1px solid #dc3545;
    background-color: transparent;
}

     :root {
            --primary: #033A70;
            --secondary: #033A70;
            --tertiary: #FFCB05;
            --quaternary: #D0EEFC;
            --background: #EDF8FD;
            --sidebar-width: 250px;
            --card-border-radius: 0.75rem;
            --transition-speed: 0.3s;
        }
.table > :not(caption) > * > * {
    padding: 1.25rem 0.75rem;
    border-bottom-color: #f0f0f0;
}
.table > thead > tr > th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: white;
}
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}
.card {
    border-radius: 1rem;
    overflow: hidden;
    margin-bottom: 1rem;
}
.schedule-header {
    background: var(--primary);
    padding: 1.5rem;
    color: white;
}
.schedule-icon {
    position: absolute;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
}
tbody tr:hover {
    background-color: #f8f9fa;
}

/* Responsive Tweaks */
@media (max-width: 768px) {
    .schedule-header { padding: 1.5rem; }
    .schedule-icon { right: 1.5rem; }
    .dataTables_wrapper .dataTables_filter {
        float: none !important;
        text-align: left !important;
        margin-bottom: 1rem;
    }
}

/* Expand icon styling */
table.dataTable.dtr-inline.collapsed > tbody > tr > td:first-child::before {
    background-color: #3d52a0;
    border: none;
}
</style>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
function initScheduleTable() {

    const table = $('#scheduleTable').DataTable({
       responsive: { details: { type: 'column', 
        target: 0 } }, 
        columnDefs: [ { 
            className: 'dtr-control custom-plus', 
            targets: 0 } ], 
            scrollX: false, // no horizontal scroll 
       scrollY: '50vh', // vertical scroll height 
       responsive: true, 
       ordering: true, // allow sorting 
       scrollCollapse: true, 
       paging: true, 
       searching: true, 
       info: true, 
       autoWidth: false

    }
    )}

let lastUpdate = <?php echo time(); ?>;

function loadSchedule() {
    $.ajax({
        url: 'schedule/load_schedule.php',
        method: 'GET',
        data: { last_check: lastUpdate },
        dataType: 'json',
        cache: false,
        success: function(response) {
            if (response.html) {
                if ($.fn.DataTable.isDataTable('#scheduleTable')) {
                    $('#scheduleTable').DataTable().destroy();
                }
                $('#schedule-container').html(response.html);
                initScheduleTable();
            }
            if (response.timestamp) {
                lastUpdate = parseFloat(response.timestamp);
                $('#last-updated').text(new Date().toLocaleTimeString());
            }
        },
        complete: function() {
            setTimeout(checkForUpdates, 5000);
        }
    });
}

function checkForUpdates() {
    $.ajax({
        url: 'schedule/check_updates.php',
        method: 'GET',
        data: { last_check: lastUpdate },
        dataType: 'json',
        cache: false,
        success: function(response) {
            if (response.hasUpdates) {
                loadSchedule();
            } else if (response.timestamp) {
                lastUpdate = parseFloat(response.timestamp);
            }
        },
        complete: function() {
            setTimeout(checkForUpdates, 10);
        }
    });
}

$(document).ready(function() {
    initScheduleTable();
    setTimeout(checkForUpdates, 5000);
});

$(document).on('click', '.view-attachment-btn', function() {
    var attachmentId = $(this).data('attachment-id');
    var uploadedBy = $(this).data('uploaded-by');
    $('#attachmentModalBody').html('Loading...');
    $.ajax({
        url: 'schedule/view_attachment.php',
        method: 'GET',
        data: { id: attachmentId, uploaded_by: uploadedBy },
        success: function(data) {
            $('#attachmentModalBody').html(data);
        },
        error: function(xhr, status, error) {
            $('#attachmentModalBody').html('Failed to load attachment.');
        }
    });
});
</script>
