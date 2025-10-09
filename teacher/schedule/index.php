<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../../includes/db.php';

// ✅ Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday','Sunday'];

// ✅ Get teacher's schedule from sections_schedules
// ✅ Get active term_id
$term_query = "SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1";
$term_result = $conn->query($term_query);

if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    die("No active term found.");
}

// ✅ Get teacher's schedule filtered by active term_id and aligned with ss_id
$schedule_query = "
    SELECT 
    ss.ss_id,
    ss.teacher_id,
    ss.section_id,
    ss.subject_code,
    ss.day_of_week,
    ss.start_time,
    ss.end_time,
    ss.room_id,
    ss.status,        -- ✅ Add this
    ss.description,   -- ✅ And this
    ss.created_at,
    ss.updated_at,
    r.room_number, 
    r.capacity AS room_capacity,
    s.subject_code, 
    s.subject_description, 
    s.units,
    sec.section_code AS section_name,
    d.degree_code, 
    d.degree_name
FROM sections_schedules ss
LEFT JOIN rooms r ON ss.room_id = r.room_id
LEFT JOIN subjects s ON ss.subject_id = s.subject_id
LEFT JOIN sections sec ON ss.section_id = sec.section_id
LEFT JOIN degrees d ON sec.degree_id = d.degree_id
WHERE ss.teacher_id = ?
  AND ss.term_id = ?
ORDER BY FIELD(ss.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), ss.start_time
";

// Prepare and bind
$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("ii", $t_id, $term_id);
$stmt->execute();
$schedules_result = $stmt->get_result();

// ✅ Organize schedules by day and compute stats
$schedules_by_day = [];
$total_hours = 0;
$total_subjects = 0;
$unique_subjects = [];

while ($schedule = $schedules_result->fetch_assoc()) {
    $day = $schedule['day_of_week'];
    $schedules_by_day[$day][] = $schedule;

    $start_time = new DateTime($schedule['start_time']);
    $end_time = new DateTime($schedule['end_time']);

    // Compute duration in hours
    $duration = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 3600;

    // Ensure positive duration
    if ($duration < 0) {
        // If end_time is earlier than start_time, assume overnight class
        $duration += 24;
    }

    // Optional: cap to max 12 hours just in case
    if ($duration > 12) {
        $duration = 12;
    }

    $total_hours += $duration;

    if (!isset($unique_subjects[$schedule['subject_code']])) {
        $unique_subjects[$schedule['subject_code']] = true;
        $total_subjects++;
    }
}

// ✅ Build plot_blocks from existing schedule
$plot_blocks = [];
$plot_query = "
    SELECT DISTINCT 
        ss.section_id, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time,
        s.subject_description, sec.section_code
    FROM sections_schedules ss
    JOIN sections sec ON ss.section_id = sec.section_id
    JOIN subjects s ON ss.subject_code = s.subject_code
    WHERE ss.teacher_id = ?
    ORDER BY FIELD(ss.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), ss.start_time
";

$plot_stmt = $conn->prepare($plot_query);
$plot_stmt->bind_param("i", $teacher_id);
$plot_stmt->execute();
$plot_result = $plot_stmt->get_result();

while ($row = $plot_result->fetch_assoc()) {
    $key = $row['section_id'] . '|' . $row['subject_code'] . '|' . $row['day_of_week'] . '|' . $row['start_time'] . '|' . $row['end_time'];
    $plot_blocks[$key] = $row;
}

// ✅ Fetch teacher availability directly from sections_schedules
$teacher_availability = [];
$avail_query = "
    SELECT section_id, subject_code, day_of_week, start_time, end_time,description
    FROM sections_schedules 
    WHERE teacher_id = ?
";
$avail_stmt = $conn->prepare($avail_query);
$avail_stmt->bind_param("i", $teacher_id);
$avail_stmt->execute();
$avail_result = $avail_stmt->get_result();

while ($row = $avail_result->fetch_assoc()) {
    $key = $row['section_id'] . '|' . $row['subject_code'] . '|' . $row['day_of_week'] . '|' . $row['start_time'] . '|' . $row['end_time'];

    $remarks = $row['description'] ?? '';
    $status = 'Available'; // default

    if (stripos($remarks, 'async') !== false) {
        $status = 'Asynchronous';
    } elseif (stripos($remarks, 'unavailable') !== false) {
        $status = 'Not Available';
    }

    $teacher_availability[$key] = [
        'status' => $status,
        'description' => $remarks
    ];

    // ✅ Fetch all attachment files uploaded by this teacher
    $attachments = [];
    $attachment_stmt = $conn->prepare("
    SELECT * FROM attachment_files 
    WHERE uploaded_by_type = 'teacher' AND uploaded_by_id = ?
");
    $attachment_stmt->bind_param("i", $teacher_id);
    $attachment_stmt->execute();
    $attachment_result = $attachment_stmt->get_result();

    while ($row = $attachment_result->fetch_assoc()) {
        $key = $row['ss_id']; // Assume `ss_id` from sections_schedules
        $attachments[$key] = $row;
    }
}
?>


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

    .btn-plot-sched {
        color: white !important;
        border: none;
    }


    .btn-plot-sched .btn:hover {
        transform: none !important;
    }

    .btn-plot-sched .btn:active {
        transform: none !important;
    }

    /* Update edit button hover styles */
    .btn-plot-sched:hover {
        background: linear-gradient(145deg, #2E4190 0%, #6180C8 100%) !important;
        color: white !important;
        border: none;
    }

    /* Prevent horizontal scroll */
    html,
    body {
        max-width: 100%;
        overflow-x: hidden;
    }

    body.modal-open {
        overflow-x: hidden !important;
        padding-right: 0 !important;
    }

    .modal {
        overflow-x: hidden;
    }

    .modal-open {
        overflow-x: hidden !important;
    }

    .modal-dialog {
        justify-content: center;
    }

    .container,
    .modal-dialog,
    .toast-container {
        overflow-x: hidden;
    }


    /* Main content area */
    main {
        margin-left: 5px;
        width: calc(100% - 260px);
        padding: 0;
        overflow-x: hidden;
    }

    /* Container adjustments */
    .container-fluid {
        width: 100%;
        padding: 0;
        margin: 0;
        overflow-x: hidden;
    }

    /* Table container adjustments */
    .table-responsive {
        margin: 0;
        padding: 0;
        border: none;
        overflow-x: hidden;
    }

    .table {
        width: 100%;
        margin-bottom: 0;
        table-layout: fixed;
    }

    .card {
        border-radius: var(--card-border-radius);
        box-shadow: 0 4px 6px rgba(61, 82, 160, 0.07);
        border: none;
        transition: transform var(--transition-speed);
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .card-header {
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
        color: white;
        border-radius: 0 !important;
        padding: 1rem 1.5rem;
    }

    /* Add padding to content */
    h2.mb-4 {
        padding: 1rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Table styles */
    .table thead th {
        font-size: 14px;
        font-weight: 600;
        padding: 12px;
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    /* Set fixed widths and prevent overflow */
    .table th:nth-child(1),
    .table td:nth-child(1) {
        width: 150px;
        max-width: 150px;
    }

    .table th:nth-child(2),
    .table td:nth-child(2) {
        width: 250px;
        max-width: 250px;
    }

    .table th:nth-child(3),
    .table td:nth-child(3) {
        width: 120px;
        max-width: 120px;
    }

    .table th:nth-child(4),
    .table td:nth-child(4) {
        width: 180px;
        max-width: 180px;
    }

    .table th:nth-child(5),
    .table td:nth-child(5) {
        width: 250px;
        max-width: 250px;
    }

    .table th:nth-child(6),
    .table td:nth-child(6) {
        text-wrap: wrap;
        width: 100px;
        max-width: 100px;
    }

    .table th:nth-child(7),
    .table td:nth-child(7) {
        width: 100px;
        max-width: 100px;
    }

    /* Prevent horizontal scrollbar */
    .table td,
    .table th {
        white-space: normal !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        word-wrap: break-word !important;
    }

    .table tr {
        transition: all var(--transition-speed);
    }

    .table tbody tr:hover {
        background-color: rgba(61, 82, 160, 0.05);
        transform: scale(1.002);
    }

    /* Stats cards */
    .stats-card {
        background-color: #fff;
        border-radius: var(--card-border-radius);
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .stats-card .stats-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .stats-card .stats-icon i {
        font-size: 1.5rem;
        color: white;
    }

    .stats-card .stats-info h3 {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .stats-card .stats-info p {
        color: #6c757d;
        margin: 0;
    }

    .modal-header {
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
        color: white;
        border-bottom: none;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    /* No schedule message */
    .no-schedule {
        text-align: center;
        padding: 2rem;
        color: #6c757d;
        background-color: #f8f9fa;
        border-radius: var(--card-border-radius);
        margin-bottom: 1rem;
    }

    .no-schedule i {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: #adb5bd;
    }
</style>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/content.css">
</head>

<body>
    <main>
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h2 class="mb-0 fw-bold">My Teaching Schedule</h2>
                 <nav aria-label="breadcrumb">
                <ol class="breadcrumb m-2">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Schedule</li>
                </ol>
            </nav>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary btn-plot-sched" data-bs-toggle="modal" data-bs-target="#plotScheduleModal">
                    <i class="bi bi-plus-circle me-1"></i> Plot Class Availability
                </button>
            </div>
        </div>
        <div class="container-fluid" data-page="schedule">
            <!-- Stats Row -->
        <div class="row mb-4">
    <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="stats-card h-100">
            <div class="stats-icon" style="background-color: var(--primary);">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stats-info">
                <h3><?php echo number_format($total_hours, 1); ?></h3>
                <p>Total Teaching Hours/Week</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="stats-card h-100">
            <div class="stats-icon" style="background-color: var(--secondary);">
                <i class="bi bi-book"></i>
            </div>
            <div class="stats-info">
                <h3><?php echo $total_subjects; ?></h3>
                <p>Unique Subjects</p>
            </div>
                    </div>
                </div>
                  <div class="col-12 col-md-6 col-lg-4 mb-3">
        <div class="stats-card h-100">
            <div class="stats-icon" style="background-color: #198754;">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div class="stats-info">
                            <h3><?php echo count(array_filter($days, function ($day) use ($schedules_by_day) {
                                    return isset($schedules_by_day[$day]) && !empty($schedules_by_day[$day]);
                                })); ?></h3>
                            <p>Teaching Days</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Cards -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-calendar3 me-2"></i>
                                Schedule Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($days as $day) { ?>
                                <h6 class="fw-bold mb-3"><?php echo $day; ?></h6>
                                <?php if (isset($schedules_by_day[$day]) && !empty($schedules_by_day[$day])) { ?>
                                   <div class="table-responsive mb-4">
    <table class="table table-hover align-middle table-sm" style="table-layout: fixed; width: 100%;">
        <thead class="table-light">
            <tr>
                <th style="width: 12%;">Time</th>
                <th style="width: 20%;">Subject</th>
                <th style="width: 12%;">Section</th>
                <th style="width: 12%;">Room</th>
                <th style="width: 20%;">Degree</th>
                <th style="width: 15%;">Remarks</th>
                <th style="width: 12%;" class="text-center">Actions</th>
            </tr>
        </thead>
                                            <tbody>
                                                <?php foreach ($schedules_by_day[$day] as $schedule): ?>
                                                    <?php
                                                    $start = new DateTime($schedule['start_time']);
                                                    $end = new DateTime($schedule['end_time']);

                                                    if ($end->format('H') > 12 && ($end->getTimestamp() - $start->getTimestamp()) > 10800) {
                                                        $end->modify('-12 hours');
                                                    }

                                                    $timeRange = $start->format('h:i A') . ' - ' . $end->format('h:i A');
                                                    $key = $schedule['section_id'] . '|' . $schedule['subject_code'] . '|' . $schedule['day_of_week'] . '|' . $schedule['start_time'] . '|' . $schedule['end_time'];

                                                    // Determine default status / description
                                                    $status = 'Available';
                                                    $description = 'Ready for class';

                                                    // Only show teacher-plotted remarks if this schedule falls in the current week
                                                    $currentWeek = date('W'); // current week number
                                                    $scheduleWeek = date('W', strtotime($schedule['updated_at'] ?? $schedule['created_at']));

                                                    if ($currentWeek === $scheduleWeek) {
                                                        // Use stored status / description if teacher plotted
                                                        if (!empty($schedule['status'])) {
                                                            $status = $schedule['status'];
                                                        }
                                                        if (!empty($schedule['description'])) {
                                                            $description = $schedule['description'];
                                                        }
                                                    }

                                                    // Badge class
                                                    $badgeClass = match ($status) {
                                                        'Available' => 'bg-success',
                                                        'Asynchronous' => 'bg-info text-dark',
                                                        'Not Available' => 'bg-danger',
                                                        default => 'bg-success'
                                                    };

                                                    // File handling
                                                    $ss_id = $schedule['ss_id'];
                                                    $file = $attachments[$ss_id]['file_name'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td class="px-3"><?= $timeRange ?></td>
                                                        <td class="px-3">
                                                            <div class="fw-bold"><?= htmlspecialchars($schedule['subject_code']) ?></div>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars($schedule['subject_description']) ?> (<?= $schedule['units'] ?> units)
                                                            </small>
                                                        </td>
                                                        <td class="px-3"><?= htmlspecialchars($schedule['section_name']) ?></td>
                                                        <td class="px-3">
                                                            <?= htmlspecialchars($schedule['room_number']) ?>
                                                            <small class="text-muted d-block">Capacity: <?= $schedule['room_capacity'] ?></small>
                                                        </td>
                                                        <td class="px-3"><?= htmlspecialchars($schedule['degree_name']) ?></td>
                                                        <td class="px-3">
                                                            <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                                            <?php if (!empty($description)): ?>
                                                                <div class="text-muted small"><?= htmlspecialchars($description) ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                        <!-- View Button -->
                                                        <button class="btn btn-sm btn-primary view-remarks-btn fw-bold d-inline-flex align-items-center justify-content-center" 
                                                            style="padding: 0.5rem !important; width: 36px; height: 36px;"
                                                            data-status="<?= htmlspecialchars($status) ?>"
                                                            data-description="<?= htmlspecialchars($description) ?>"
                                                            data-file="<?= htmlspecialchars($file) ?>"
                                                            data-ss-id="<?= $ss_id ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewRemarksModal">
                                                            <i class="bi bi-eye"></i>
                                                        </button>

                                                        <!-- Edit Button -->
                                                        <button class="btn btn-sm btn-primary edit-remarks-btn d-inline-flex align-items-center justify-content-center" 
                                                            style="padding: 0.5rem !important; width: 36px; height: 36px;"
                                                            data-block="<?= htmlspecialchars($key) ?>"
                                                            data-status="<?= htmlspecialchars($status) ?>"
                                                            data-description="<?= htmlspecialchars($description) ?>"
                                                            data-file="<?= htmlspecialchars($file) ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#plotScheduleModal">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                    </td>

                                                    </tr>
                                                <?php endforeach; ?>

                                            </tbody>

                                        </table>
                                    </div>
                                <?php } else { ?>
                                    <div class="no-schedule mb-4">
                                        <i class="bi bi-calendar-x d-block"></i>
                                        <p class="mb-0">No classes scheduled for <?php echo $day; ?></p>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="viewRemarksModal" tabindex="-1" aria-labelledby="viewRemarksLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content bg-white">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewRemarksLabel">View Class Availability Info</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Remarks:</strong> <span id="modalRemarks"></span></p>
                            <div id="modalFilePreview" class="text-center mt-3">
                                <!-- File preview will be inserted here -->

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

     <!-- Plot Schedule Modal -->
<div class="modal fade" id="plotScheduleModal" tabindex="-1" aria-labelledby="plotScheduleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="plotForm" action="schedule/save_plot_schedule.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="plotScheduleLabel">Plot Class Availability</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Select block -->
                    <div class="mb-3">
                        <label for="plot_block" class="form-label">Choose Section, Subject, Day & Time</label>
                        <select name="block_key" id="plot_block" class="form-select" required>
                            <option value="">-- Choose --</option>
                            <?php foreach ($plot_blocks as $row): ?>
                                <option value="<?= $row['section_id'] ?>|<?= $row['subject_code'] ?>|<?= $row['day_of_week'] ?>|<?= $row['start_time'] ?>|<?= $row['end_time'] ?>">
                                    <?= $row['day_of_week'] ?> | 
                                    <?= date('h:i A', strtotime($row['start_time'])) ?> - <?= date('h:i A', strtotime($row['end_time'])) ?> 
                                    | <?= $row['section_code'] ?> - <?= $row['subject_description'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="">Select Status</option>
                            <option value="Available">Available</option>
                            <option value="Asynchronous">Asynchronous</option>
                            <option value="Not Available">Not Available</option>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Description/s</label>
                        <textarea id="remarks" name="remarks" class="form-control" placeholder="Enter remarks or reason here..." rows="1" style="overflow:hidden; resize:none;"></textarea>
                        <div class="form-text text-danger d-none" id="remarksRequiredMsg">Remarks are required for async or unavailable.</div>
                    </div>

                    <!-- File Upload -->
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attach File (optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
                        <small id="existingFileName" class="text-muted d-none"></small>
                        <button type="button" id="viewAttachmentBtn" class="btn btn-outline-secondary mt-2 d-none">
                            <i class="bi bi-eye"></i> View File
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
        <!-- File Preview Modal -->
        <div class="modal fade" id="viewAttachmentModal" tabindex="-1" aria-labelledby="viewAttachmentLabel" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content bg-white">
                    <div class="modal-header">
                        <h5 class="modal-title">File Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center" id="previewArea">
                        <p class="text-muted">No preview available.</p>
                    </div>
                </div>
            </div>
        </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#plotScheduleModal form');
            const submitBtn = form.querySelector('button[type="submit"]');
            const statusSelect = document.getElementById('status');
            const remarksInput = document.getElementById('remarks');
            const remarksMsg = document.getElementById('remarksRequiredMsg');
            const attachmentInput = document.getElementById('attachment');
            const previewArea = document.getElementById('previewArea');
            const viewBtn = document.getElementById('viewAttachmentBtn');
            const fileLabel = document.getElementById('existingFileName');

            // Submit form
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                submitBtn.disabled = true;

                const formData = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const autoResizeTextarea = (textarea) => {
                    textarea.style.height = 'auto'; // Reset height
                    textarea.style.height = textarea.scrollHeight + 'px'; // Set to content height
                };

                const remarksInput = document.getElementById('remarks');

                // Initial auto-size
                autoResizeTextarea(remarksInput);

                // Adjust on input
                remarksInput.addEventListener('input', () => autoResizeTextarea(remarksInput));

                const data = await res.json();

                if (data.success) {
                    // Close modal
                    const modalEl = document.getElementById('plotScheduleModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    // Show toast after short delay
                    setTimeout(() => {
                        showToast('Success', 'Schedule saved successfully!', 'bg-success');
                        setTimeout(() => location.reload(), 2000);
                    }, 300);
                } else {
                    showToast('Error', data.error || 'Something went wrong.', 'bg-danger');
                    submitBtn.disabled = false;
                }
            });

            // Toast display
            function showToast(title, message, toastClass = 'bg-primary') {
                const toastContainer = document.getElementById('toastContainer') || (() => {
                    const container = document.createElement('div');
                    container.id = 'toastContainer';
                    container.className = 'toast-container position-fixed top-0 end-0 p-3';
                    document.body.appendChild(container);
                    return container;
                })();

                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white ${toastClass}`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');

                toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
                `;

                toastContainer.appendChild(toast);
                new bootstrap.Toast(toast).show();
                setTimeout(() => toast.remove(), 5000);
            }

            // Toggle remarks + auto-fill + file field behavior
                statusSelect.addEventListener('change', () => {
                    const value = statusSelect.value;

                    if (value === 'Available') {
                        // ✅ Auto-fill remarks
                        remarksInput.value = "Ready for class.";
                        remarksInput.setAttribute('readonly', 'readonly'); // prevent editing
                        remarksMsg.classList.add('d-none');

                        // ✅ Hide file input when Available
                        attachmentInput.closest('.mb-3').classList.add('d-none');
                        fileLabel.classList.remove('d-none');
                        fileLabel.textContent = "No file needed for 'Available' status.";
                        viewBtn.classList.add('d-none');
                        previewArea.innerHTML = '';

                    } else {
                        // ✅ Clear remarks when switching away from Available
                        remarksInput.value = "";
                        remarksInput.removeAttribute('readonly');

                        if (value === 'Asynchronous' || value === 'Not Available') {
                            remarksInput.setAttribute('required', 'required');
                            remarksMsg.classList.remove('d-none');

                            // ✅ Show file upload again
                            attachmentInput.closest('.mb-3').classList.remove('d-none');

                        } else {
                            remarksInput.removeAttribute('required');
                            remarksMsg.classList.add('d-none');
                            attachmentInput.closest('.mb-3').classList.remove('d-none');
                        }
                    }
                });



            // File preview logic
            attachmentInput.addEventListener('change', () => {
                const file = attachmentInput.files[0];
                if (file) {
                    viewBtn.classList.remove('d-none');
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        const fileURL = e.target.result;
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                            previewArea.innerHTML = `<img src="${fileURL}" class="img-fluid rounded shadow-sm" style="max-height: 600px;">`;
                        } else if (ext === 'pdf') {
                            previewArea.innerHTML = `<embed src="${fileURL}" type="application/pdf" width="100%" height="600px" />`;
                        } else {
                            previewArea.innerHTML = `<p class="text-muted">Preview not available for this file type.</p>`;
                        }
                    };

                    reader.readAsDataURL(file);
                } else {
                    viewBtn.classList.add('d-none');
                    previewArea.innerHTML = '';
                }
            });

            document.getElementById('viewAttachmentBtn').addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('viewAttachmentModal'), {
                    backdrop: 'static', // Prevent accidental closure
                    keyboard: false
                });
                modal.show();
            });

            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('hidden.bs.modal', () => {
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
            });



           // View Remarks modal
const viewButtons = document.querySelectorAll('.view-remarks-btn');
viewButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        const status = this.dataset.status;
        const description = this.dataset.description;
        const file = this.dataset.file;

        const badgeClass = getBadgeClass(status);
        document.getElementById('modalStatus').innerHTML = `<span class="badge ${badgeClass}">${status}</span>`;
        document.getElementById('modalRemarks').textContent = description;

        const preview = document.getElementById('modalFilePreview');
        preview.innerHTML = '';

        // ✅ Hide file if status = Available
        if (status === 'Available') {
            preview.innerHTML = ``;
            return;
        }

        // ✅ Show file only if not Available
        if (file) {
            const ext = file.split('.').pop().toLowerCase();
            const path = `../uploads/remarks/${file}`;

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                preview.innerHTML = `<img src="${path}" class="img-fluid rounded shadow-sm" style="max-height: 400px;">`;
            } else if (ext === 'pdf') {
                preview.innerHTML = `<embed src="${path}" type="application/pdf" width="100%" height="500px" />`;
            } else {
                preview.innerHTML = `<p class="text-muted">File format not previewable</p>`;
            }
        } else {
            preview.innerHTML = `<p class="text-muted">No attachment provided.</p>`;
        }
    });
});


            // Edit Remarks modal
            const editButtons = document.querySelectorAll('.edit-remarks-btn');
            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const block = this.dataset.block;
                    const status = this.dataset.status;
                    const description = this.dataset.description;
                    const file = this.dataset.file;

                    document.getElementById('plot_block').value = block;
                    document.getElementById('status').value = status;
                    document.getElementById('remarks').value = description;

                    if (file) {
                        fileLabel.textContent = `Attached File: ${file}`;
                        fileLabel.classList.remove('d-none');
                        viewBtn.classList.remove('d-none');

                        const ext = file.split('.').pop().toLowerCase();
                        const path = `/uploads/remarks/${file}`;

                        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                            previewArea.innerHTML = `<img src="${path}" class="img-fluid rounded shadow-sm" style="max-height: 600px;">`;
                        } else if (ext === 'pdf') {
                            previewArea.innerHTML = `<embed src="${path}" type="application/pdf" width="100%" height="600px" />`;
                        } else {
                            previewArea.innerHTML = `<p class="text-muted">Preview not available for this file type.</p>`;
                        }
                    } else {
                        fileLabel.textContent = `No file currently attached.`;
                        fileLabel.classList.remove('d-none');
                        viewBtn.classList.add('d-none');
                        previewArea.innerHTML = '';
                    }

                    const changeEvent = new Event('change');
                    document.getElementById('status').dispatchEvent(changeEvent);
                });
            });

            function getBadgeClass(status) {
                switch (status) {
                    case 'Available':
                        return 'bg-success';
                    case 'Asynchronous':
                        return 'bg-info text-dark';
                    case 'Not Available':
                        return 'bg-danger';
                    default:
                        return 'bg-secondary';
                }
            }
        });
    </script>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.6/umd/popper.min.js"></script>
</body>

</html>