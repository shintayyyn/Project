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

// ---------------------------
// Get parent info
// ---------------------------
$stmt = $conn->prepare("
    SELECT p_id, CONCAT(p_fname, ' ', IFNULL(p_mname, ''), ' ', p_lname, ' ', IFNULL(p_suffix, '')) AS full_name 
    FROM parents 
    WHERE p_id = ?
");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$parent_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ---------------------------
// Get all children for this parent
// ---------------------------
$children_query = "
    SELECT s.s_id, s.s_fname, s.s_lname, s.s_mname, s.s_suffix,
           CONCAT(s.s_fname, ' ', IFNULL(s.s_mname, ''), ' ', s.s_lname, ' ', IFNULL(s.s_suffix, '')) AS full_name,
           sec.section_code, s.s_gender, s.s_bdate, s.s_age, s.s_cnum, s.s_email, s.s_status
    FROM students s
    LEFT JOIN parent_student ps ON s.s_id = ps.s_id
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname ASC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result();
$stmt->close();

// ---------------------------
// Handle selected child
// ---------------------------
$selected_child_id = $_GET['child_id'] ?? null;

if ($selected_child_id) {
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(s.s_fname, ' ', s.s_lname) AS full_name, sec.section_code
        FROM students s
        LEFT JOIN students_sections ss ON s.s_id = ss.s_id
        LEFT JOIN sections sec ON sec.section_id = ss.section_id
        WHERE s.s_id = ?
    ");
    $stmt->bind_param("i", $selected_child_id);
    $stmt->execute();
    $child = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // fallback: first child from list
    $children->data_seek(0);
    $child = $children->fetch_assoc();
    if ($child) {
        $selected_child_id = $child['s_id'];
    }
}

// ---------------------------
// Attendance calculations for selected child
// ---------------------------
$month_start = date('Y-m-01'); // first day of current month
$month_end   = date('Y-m-t');  // last day of current month

// Total scheduled classes
$total_classes_query = "
    SELECT COUNT(*) AS total_classes
    FROM sections_schedules ss
    JOIN students_sections sts ON sts.section_id = ss.section_id
    WHERE sts.s_id = ?
      AND DATE(ss.start_time) BETWEEN ? AND ?
";
$stmt = $conn->prepare($total_classes_query);
$stmt->bind_param("iss", $selected_child_id, $month_start, $month_end);
$stmt->execute();
$total_classes = $stmt->get_result()->fetch_assoc()['total_classes'];
$stmt->close();

// Total attended classes (Present or Late)
$attended_query = "
    SELECT COUNT(*) AS attended
    FROM attendance
    WHERE s_id = ?
      AND status IN ('Present','Late')
      AND DATE(time_in) BETWEEN ? AND ?
";
$stmt = $conn->prepare($attended_query);
$stmt->bind_param("iss", $selected_child_id, $month_start, $month_end);
$stmt->execute();
$attended = $stmt->get_result()->fetch_assoc()['attended'];
$stmt->close();

// Total late classes
$late_query = "
    SELECT COUNT(*) AS late_count
    FROM attendance
    WHERE s_id = ?
      AND status='Late'
      AND DATE(time_in) BETWEEN ? AND ?
";
$stmt = $conn->prepare($late_query);
$stmt->bind_param("iss", $selected_child_id, $month_start, $month_end);
$stmt->execute();
$late_count = $stmt->get_result()->fetch_assoc()['late_count'];
$stmt->close();

// Total absent classes
$absent_count = $total_classes - $attended;

// Attendance progress %
$attendance_progress = $total_classes > 0 ? round(($attended / $total_classes) * 100) : 0;

// ---------------------------
// Today variables for schedule
// ---------------------------
$today = date('l');           // Day of the week (e.g., Monday)
$today_date = date('F d, Y'); // Full date (e.g., September 10, 2025)

// ---------------------------
// Get today's schedule for selected child (strict by section_id)
// ---------------------------
$schedule_query = "
    SELECT ss.ss_id, ss.start_time, ss.end_time, ss.status, ss.description, ss.updated_at,
           sub.subject_code, sub.subject_description,
           CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
           r.room_number,
           sec.section_code
    FROM students_sections sts
    JOIN sections sec ON sec.section_id = sts.section_id
    JOIN sections_schedules ss ON ss.section_id = sts.section_id
    JOIN subjects sub ON ss.subject_code = sub.subject_code
    JOIN rooms r ON ss.room_id = r.room_id
    JOIN teachers t ON ss.teacher_id = t.t_id
    WHERE sts.s_id = ?
      AND ss.day_of_week = ?
    ORDER BY ss.start_time
";


$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("is", $selected_child_id, $today);
$stmt->execute();
$today_schedule = $stmt->get_result();
$stmt->close();

?>
<link rel="stylesheet" href="assets/css/schedule.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<main class="py-4">
     <?php include(__DIR__ . '/../../includes/parent_header.php'); ?>
    <div class="container-fluid px-4">
        <?php if ($child): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <!-- Child selection form -->
                    <form method="get" action="dashboard.php" class="mb-3">
                        <input type="hidden" name="page" value="schedule">
                        <label class="form-label fw-bold">Select Child:</label>

                        <div class="dropdown" style="max-width:400px">
                            <?php
                            // Get selected child name
                            $selected_child_name = "Select Child";
                            $children->data_seek(0);
                            while ($c = $children->fetch_assoc()) {
                                if ($c['s_id'] == $selected_child_id) {
                                    $selected_child_name = htmlspecialchars($c['full_name']) . " (" . ($c['section_code'] ?? 'N/A') . ")";
                                    break;
                                }
                            }
                            ?>
                            <button class="btn dropdown-toggle w-100 fw-bold" type="button" id="childDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:var(--primary); color:var(--tertiary);">
                                <?= $selected_child_name ?>
                            </button>
                            <ul class="dropdown-menu custom-scrollbar" aria-labelledby="childDropdown">
                                <!-- Search input inside dropdown -->
                                <li class="px-3 py-2">
                                    <input type="text" id="childSearch" class="form-control form-control-sm" placeholder="Search child...">
                                </li>
                                <li><hr class="dropdown-divider"></li>

                                <?php
                                $children->data_seek(0);
                                while ($c = $children->fetch_assoc()):
                                    $child_label = htmlspecialchars($c['full_name']) . " (" . ($c['section_code'] ?? 'N/A') . ")";
                                ?>
                                    <li class="dropdown-item-wrapper">
                                        <a class="dropdown-item <?= ($c['s_id'] == $selected_child_id) ? 'active' : '' ?>"
                                        href="dashboard.php?page=schedule&child_id=<?= urlencode($c['s_id']) ?>">
                                        <?= $child_label ?>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </form>
                    <!-- Profile card -->
                    <div class="card profile-card">
                        <div class="card-body p-0">
                            <div class="profile-header">
                                <div class="profile-cover"></div>
                                <div class="profile-avatar-wrapper">
                                    <div class="profile-image" style="background:var(--tertiary); color:var(--primary)">
                                        <?php
                                        $fname = $child['s_fname'];
                                        $initials = strtoupper(substr($fname, 0, 2));
                                        echo htmlspecialchars($initials);
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-content">
                                <div class="text-center mb-3">
                                    <h4 class="mb-1"><?php echo htmlspecialchars($child['full_name']); ?></h4>
                                    <p class="text-muted mb-0">Section: <?php echo htmlspecialchars($child['section_code'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Schedule, Absence Filing, Attendance -->
            <div class="row g-4 mb-4">
                <!-- Schedule -->
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-3" style="background: var(--tertiary)">
                            <h5 class="card-title head mb-0" style="color: var(--primary) !important">
                                <i class="bi bi-calendar-day me-2" style="color: var(--primary) !important"></i>
                                <?php echo htmlspecialchars($child['full_name']); ?>'s Schedule (<?php echo $today; ?>)
                            </h5>
                        </div>
                        <div class="card-body p-0" style="background: var(--quaternary);">
                            <?php
                            // ---------------------------
                            // Get today's schedule for selected child (strict by section_id)
                            // ---------------------------
                            $schedule_query = "
                            SELECT ss.ss_id, ss.start_time, ss.end_time, ss.status, ss.description, ss.updated_at,
                                sub.subject_code, sub.subject_description,
                                CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
                                r.room_number,
                                sec.section_code
                            FROM students_sections sts
                            JOIN sections sec ON sec.section_id = sts.section_id
                            JOIN sections_schedules ss ON ss.section_id = sts.section_id
                            JOIN subjects sub ON ss.subject_code = sub.subject_code
                            JOIN rooms r ON ss.room_id = r.room_id
                            JOIN teachers t ON ss.teacher_id = t.t_id
                            WHERE sts.s_id = ?
                            AND ss.day_of_week = ?
                            ORDER BY ss.start_time
                        ";
                            $stmt2 = $conn->prepare($schedule_query);
                            $stmt2->bind_param("is", $child['s_id'], $today);
                            $stmt2->execute();
                            $today_schedule = $stmt2->get_result();
                            ?>
                            <?php if ($today_schedule->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table id="childScheduleTable<?php echo $child['s_id']; ?>" class="table table-hover mb-0 table-striped">
                                        <thead style="color: var(--primary);">
    <tr>
        <th>Time</th>
        <th>Subject</th>
        <th>Teacher</th>
        <th>Room</th>
        <th>Status</th>
        <th>Attachment</th>
    </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($class = $today_schedule->fetch_assoc()): ?>
<?php
   $todayDate = date('Y-m-d');
$lastUpdated = !empty($class['updated_at']) ? date('Y-m-d', strtotime($class['updated_at'])) : null;

// Default
$currentStatus = $class['status'] ?? "Available";
$statusDescription = $class['description'] ?? "";

// ✅ If updated_at is not today, reset to defaults
if ($lastUpdated !== $todayDate) {
    $currentStatus = "Available";
    $statusDescription = "Ready for class!";
} elseif (strtolower($currentStatus) === "available" && empty(trim($statusDescription))) {
    $statusDescription = "Ready for class!";
}


    // pill color
    switch (strtolower($currentStatus)) {
        case "available":
            $statusClass = "bg-success";
            break;
        case "asynchronous":
            $statusClass = "bg-warning text-dark";
            break;
        case "not available":
            $statusClass = "bg-danger";
            break;
        default:
            $statusClass = "bg-secondary";
    }
?>
<tr>
    <td>
        <?php echo date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?>
    </td>
    <td>
        <strong><?php echo htmlspecialchars($class['subject_code']); ?></strong><br>
        <small class="text-muted"><?php echo htmlspecialchars($class['subject_description']); ?></small>
    </td>
    <td><?php echo htmlspecialchars($class['teacher_name']); ?></td>
    <td><?php echo htmlspecialchars($class['room_number']); ?></td>
    <td>
        <span class="badge rounded-pill <?php echo $statusClass; ?>">
            <?php echo htmlspecialchars($currentStatus); ?>
        </span>
        <br>
        <small class="text-muted">
            <?php echo htmlspecialchars($statusDescription); ?>
        </small>
    </td>
<td>
    <?php
    $attachments = $conn->prepare("
        SELECT id, file_name, updated_at
        FROM attachment_files
        WHERE ss_id = ?
        ORDER BY updated_at DESC
    ");
    $attachments->bind_param("i", $class['ss_id']); 
    $attachments->execute();
    $files = $attachments->get_result();
    $attachments->close();

    if ($files->num_rows > 0):
        while ($f = $files->fetch_assoc()):
            $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
            $filePath = "../uploads/remarks/" . $f['file_name']; // ✅ fixed
            ?>
            <button 
                class="btn btn-sm btn-primary view-attachment" 
                data-url="<?= $filePath ?>" 
                data-type="<?= htmlspecialchars($ext) ?>" 
                data-name="<?= htmlspecialchars($f['file_name']) ?>">
                <i class="bi bi-paperclip"></i> View
            </button><br>
        <?php endwhile;
    else: ?>
        <span class="badge rounded-pill bg-warning text-muted">No attachment provided.</span>
    <?php endif; ?>
</td>






</tr>
<?php endwhile; ?>


                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-calendar-x display-4 text-muted mb-3"></i>
                                    <p class="mb-0">No classes scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Absence + Attendance -->
                <div class="col-lg-4">
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header py-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clipboard-x me-2"></i>Absence Filing
                            </h5>
                        </div>
                        <div class="card-body text-center">
                                <p class="mb-3">Feel free to file an absence form for your child.</p>
                              <a href="dashboard.php?page=absentform&child_id=<?= urlencode($child['s_id']); ?>" 
                                class="btn rounded-pill px-4 fw-bold" style="background:var(--tertiary); color:var(--primary)">
                                Report Now
                                </a>

                        </div>
                    </div>
                   <?php $current_month = date('F Y'); // e.g., September 2025 ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header py-3" style="background: var(--tertiary);">
                        <h5 class="card-title mb-0" style="color:var(--primary) !important">
                            <i class="bi bi-bar-chart-line me-2" style="color:var(--primary) !important"></i>
                            Attendance Progress (<?php echo $current_month; ?>)
                        </h5>
                    </div>
                    <div class="card-body" style="background:var(--quaternary)">
                        <div class="progress mb-2">
                        <?php $attendance_progress = isset($attendance_progress) ? $attendance_progress : 0; ?>
                            <div class="progress-bar fw-bold" role="progressbar"
                                style="width: <?= $attendance_progress ?>%; min-width: 35px; background:var(--primary); color:var(--background)" 
                                aria-valuenow="<?= $attendance_progress ?>"
                                aria-valuemin="0" aria-valuemax="100">
                                <?= $attendance_progress ?>%
                            </div>
                        </div>
                    </div>
                </div>

<div class="card shadow-sm">
    <div class="card-header py-3">
        <h5 class="card-title mb-0">
            <i class="bi bi-clipboard-data me-2"></i>
            Attendance Overview (<?php echo $current_month; ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col">
                <div class="mb-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
                <div class="fw-bold"><?php echo $attended; ?></div>
                <div class="small text-muted">Present</div>
            </div>
            <div class="col">
                <div class="mb-1 text-danger"><i class="bi bi-x-circle-fill"></i></div>
                <div class="fw-bold"><?php echo $absent_count; ?></div>
                <div class="small text-muted">Absent</div>
            </div>
            <div class="col">
                <div class="mb-1 text-warning"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="fw-bold"><?php echo $late_count; ?></div>
                <div class="small text-muted">Late</div>
            </div>
        </div>
    </div>
</div>

                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No children found for your account.</div>
        <?php endif; ?>
    </div>
</main>

<!-- Toast container at bottom-right -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
  <div id="downloadToast" class="toast align-items-center text-white fw-bold bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        Your download has started!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>



<!--Attachment Modal-->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-paperclip"></i> Attachment Viewer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center" id="attachmentContent">
        <p class="text-muted">Loading attachment...</p>
      </div>
      <div class="modal-footer">
        <a id="downloadAttachment" class="btn btn-primary" download>
          <i class="bi bi-download me-2"> </i> Download
        </a>
      </div>
    </div>
  </div>
</div>


<script>
$(document).ready(function() {
    $('#childScheduleTable<?php echo $child['s_id']; ?>').DataTable({
        scrollX: false,            // no horizontal scroll
        scrollY: '40vh',           // vertical scroll height
        scrollCollapse: true,      // shrink table if less content
        responsive: true,
        paging: false,             // no pagination
        searching: false,          // hide search box
        info: false,               // hide "showing x of y entries"
        ordering: true,            // allow sorting
        autoWidth: false,
        columnDefs: [      
            { responsivePriority: 1, targets: 0 }, // Time - always
            { responsivePriority: 2, targets: 1 }, // Subject - always
            { responsivePriority: 3, targets: 4 }, // Status - always visible next
            { responsivePriority: 4, targets: 2 }, // Teacher
            { responsivePriority: 5, targets: 3 }  // Room
        ]
    });
});

$(document).on('click', '.view-attachment', function() {
    let url = $(this).data('url');
    let type = $(this).data('type').toLowerCase();
    let name = $(this).data('name');

    let content = "";

    if (['jpg','jpeg','png','gif','webp'].includes(type)) {
        // Image preview
        content = `<img src="${url}" alt="${name}" class="img-fluid rounded shadow">`;
    } 
    else if (['pdf','ppt','pptx'].includes(type)) {
        // Iframe preview with badge overlay
        content = `
            <div class="position-relative">
                <iframe src="${url}" style="width:100%; height:75vh;" frameborder="0"></iframe>
                <span class="badge bg-danger position-absolute top-0 start-0 m-2">${type.toUpperCase()}</span>
            </div>`;
    } 
    else {
        // Unsupported files
        content = `
            <div class="text-center py-5">
                <i class="bi bi-file-earmark-fill display-1 text-secondary"></i>
                <p class="mt-3">
                    <span class="badge bg-secondary">${type.toUpperCase()}</span> ${name}
                </p>
            </div>`;
    }

    $('#attachmentContent').html(content);
    $('#downloadAttachment').attr("href", url).attr("download", name);
    $('#attachmentModal').modal('show');
});

$(document).on('click', '#downloadAttachment', function(e){
    let toastEl = document.getElementById('downloadToast');
    let toast = new bootstrap.Toast(toastEl, { delay: 3000 }); // auto-hide after 3s
    toast.show();
});



document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('childSearch');
    const items = document.querySelectorAll('.dropdown-item-wrapper');

    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(filter) ? '' : 'none';
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.querySelector('main');
    if (mainContent) {
        setTimeout(() => {
            mainContent.classList.add('loaded');
        }, 100); // slight delay for smoother effect
    }
});
</script>