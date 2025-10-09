<?php
require_once __DIR__ . '/../../includes/db.php';
$student_id = $_SESSION['user_id'];

// Only process the query if it's not an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Get student information
    $student_id = $_SESSION['user_id'];

    // Get current semester and academic year from sections_schedules
    $period_query = "SELECT DISTINCT semester, academic_year 
                    FROM sections_schedules ss
                    JOIN students_sections sts ON ss.section_id = sts.section_id
                    WHERE sts.s_id = ?
                    ORDER BY academic_year DESC, FIELD(semester, 'First', 'Second', 'Summer') DESC
                    LIMIT 1";
    $period_stmt = $conn->prepare($period_query);
    $period_stmt->bind_param("i", $student_id);
    $period_stmt->execute();
    $period_result = $period_stmt->get_result();
    $period = $period_result->fetch_assoc();

    $current_semester = $period['semester'] ?? 'First';
    $current_academic_year = $period['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);

    // Get all schedules
 $schedule_query = "SELECT 
    ss.ss_id,
    ss.subject_code,
    s.subject_description,
    t.t_id AS teacher_id,
    CONCAT(t.t_fname, ' ', t.t_lname) as teacher_name,
    ss.day_of_week,
    TIME_FORMAT(ss.start_time, '%h:%i %p') as start_time,
    TIME_FORMAT(ss.end_time, '%h:%i %p') as end_time,
    r.room_number,
    ss.status,
    ss.description
FROM students_sections sts
JOIN sections_schedules ss ON sts.section_id = ss.section_id
JOIN subjects s ON ss.subject_code = s.subject_code
JOIN subjects_teachers st ON ss.subject_code = st.subject_code
JOIN teachers t ON st.t_id = t.t_id
LEFT JOIN rooms r ON ss.room_id = r.room_id
WHERE sts.s_id = ?
ORDER BY FIELD(ss.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
         ss.start_time ASC";



    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Organize schedules by day
    $schedules = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => []
    ];

    while ($row = $result->fetch_assoc()) {
        $schedules[$row['day_of_week']][] = $row;
    }

    $today = date('l');
}
?>


<main class="py-4">
    <div class="container-fluid px-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-sm-flex align-items-center justify-content-between">
                    <h1 class="h3 text-primary mb-2 mb-sm-0 fw-bold">
                        <i class="bi bi-calendar3-week me-2"></i>My Schedule
                    </h1>
                    <div class="d-flex align-items-center">
                        <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small">
                            <i class="bi bi-clock-history me-1"></i>
                            Last Updated: <span id="last-updated">Just now</span>
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
                                        <h5 class="card-title fw-semibold mb-2 text-white">Schedule Overview</h5>
                                        <p class="text-white-50 mb-0 small">Your current class schedule</p>
                                    </div>
                                    <div class="schedule-icon">
                                        <i class="bi bi-calendar3-week fs-1 opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <?php if (array_sum(array_map('count', $schedules)) > 0): ?>
                                    <table id="scheduleTable" class="table table-striped table-bordered nowrap" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th class="bg-light px-4 py-3" style="min-width: 200px">Day & Time</th>
                                                <th class="bg-light px-3 py-3" style="min-width: 250px">Subject</th>
                                                <th class="bg-light px-3 py-3" style="min-width: 200px">Teacher</th>
                                                <th class="bg-light px-3 py-3 text-center" style="min-width: 120px">Room</th>
                                                <th class="bg-light px-3 py-3 text-center" style="min-width: 120px">Remarks</th>
                                                <th class="bg-light px-3 py-3 text-center" style="min-width: 120px">Actions</th>
                                            </tr>
                                        </thead>
                                       <tbody>
                                        <?php foreach ($schedules as $day => $day_schedules): 
                                            if (empty($day_schedules)) continue;
                                            foreach ($day_schedules as $schedule): 

                                                // Merge status and description for Remarks
                                                $remarks = '';
                                                if (!empty($schedule['status'])) $remarks .= htmlspecialchars($schedule['status']);
                                                if (!empty($schedule['description'])) $remarks .= ($remarks ? ' - ' : '') . htmlspecialchars($schedule['description']);

                                                // Default remarks if empty
                                                if (empty($remarks)) {
                                                    $remarks = '<span class="badge bg-success">Available</span> - Ready for Class';
                                                }

                                                $stmt = $conn->prepare("SELECT file_name, id 
                                    FROM attachment_files 
                                    WHERE ss_id = ? 
                                    AND uploaded_by_id = ? 
                                    AND uploaded_by_type = 'teacher' 
                                    LIMIT 1");
                                $stmt->bind_param("ii", $schedule['ss_id'], $schedule['teacher_id']);
                                $stmt->execute();
                                $attachment = $stmt->get_result()->fetch_assoc();

                                if (!$attachment) {
                                    // Debug output
                                    error_log("No attachment for SS_ID={$schedule['ss_id']} Teacher_ID={$schedule['teacher_id']}");
                                }
                                $stmt->close();

                                        ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="d-flex gap-3 align-items-center">
                                                    <span class="badge <?php echo $day === $today ? 'bg-primary' : 'bg-secondary bg-opacity-10 text-secondary'; ?> px-3 py-2">
                                                        <?php echo $day; ?>
                                                    </span>
                                                    <div class="text-muted small">
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
                                            <td class="px-3 text-center">
                                                <?php echo $remarks; ?>
                                            </td>
                                            <td class="px-3 text-center">
                                               <?php if ($attachment): ?>
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
                                </div>
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
      <div class="modal-header">
        <h5 class="modal-title">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="attachmentModalBody">
        Loading...
      </div>
    </div>
  </div>
</div>


<style>
.table > :not(caption) > * > * {
    padding: 1.25rem 0.75rem;
    border-bottom-color: #f0f0f0;
}
.table > thead > tr > th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #555;
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
.card-header {
    border: none;
}
.schedule-header {
    background: linear-gradient(145deg, #3d52a0 0%, #7091E6 100%);
    padding: 1.5rem;
    color: white;
}
.schedule-header .card-title {
    color: white !important;
}
.schedule-icon {
    position: absolute;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
}
tbody tr {
    transition: all 0.2s ease;
}
tbody tr:hover {
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .schedule-header {
        padding: 1.5rem;
    }
    .schedule-icon {
        right: 1.5rem;
    }
}
</style>

<script>// Initialize DataTable with full responsiveness and horizontal scrolling
    var table = $('#scheduleTable').DataTable({
        responsive: {
            details: {
                type: 'column',
                target: 'tr'
            }
        },
        scrollX: true, // enables horizontal scroll if needed
        columnDefs: [
            { className: 'dtr-control', targets: 0 } // First column triggers responsive child row
        ],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50],
        order: [[6, "desc"]] // Order by Date Added descending
    });

let lastUpdate = <?php echo time(); ?>;

function loadSchedule() {
    $.ajax({
        url: 'schedule/load_schedule.php',
        method: 'GET',
        data: { last_check: lastUpdate },
        dataType: 'json',
        cache: false,
        success: function(response) {
            console.log('Load response:', response);
            if (response.html) {
                $('#schedule-container').html(response.html);
            }
            if (response.timestamp) {
                lastUpdate = parseFloat(response.timestamp);
                $('#last-updated').text(new Date().toLocaleTimeString());
            }
        },
        error: function(xhr, status, error) {
            console.error('Load error:', error, xhr.status, xhr.responseText);
            console.log('Current URL:', window.location.href);
            console.log('Attempted URL:', new URL('schedule/load_schedule.php', window.location.href).href);
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
            console.log('Check response:', response);
            if (response.hasUpdates) {
                console.log('Updates found, reloading schedule');
                loadSchedule();
            } else if (response.timestamp) {
                lastUpdate = parseFloat(response.timestamp);
            }
        },
        error: function(xhr, status, error) {
            console.error('Check error:', error, xhr.status, xhr.responseText);
            console.log('Current URL:', window.location.href);
            console.log('Attempted URL:', new URL('schedule/check_updates.php', window.location.href).href);
        },
        complete: function() {
            setTimeout(checkForUpdates, 5000);
        }
    });
}

$(document).ready(function() {
    loadSchedule();
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
        console.log('Attachment data:', data); // <-- see what is returned
        $('#attachmentModalBody').html(data);
    },
    error: function(xhr, status, error) {
        console.error('Failed to load attachment:', error, xhr.responseText);
        $('#attachmentModalBody').html('Failed to load attachment.');
    }
});

});




</script>