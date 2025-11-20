<?php
require_once '../includes/db.php';
require_once 'dashboard.php'; 

$student_id = $_SESSION['user_id'];

// -----------------------------
// 1️⃣ Get student info + regularity
// -----------------------------
$student_query = "SELECT s.*, sec.section_code, s.is_regular 
                  FROM students s
                  LEFT JOIN students_sections ss ON s.s_id = ss.s_id
                  LEFT JOIN sections sec ON ss.section_id = sec.section_id
                  WHERE s.s_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();
$is_regular = $student_info['is_regular'] ?? 1;

// -----------------------------
// 2️⃣ Get ACTIVE academic term
// -----------------------------
$term_query = "SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1";
$term_result = $conn->query($term_query);
$current_term_id = ($term_result && $term_result->num_rows > 0)
    ? $term_result->fetch_assoc()['term_id']
    : null;

// -----------------------------
// 3️⃣ Get today's schedule (term-aware + direct teacher reference)
// -----------------------------
$today = date('l');

if ($is_regular == 1) {
    // Regular students (sections_schedules)
    $schedule_query = "
    SELECT ss.*, sub.subject_id, sub.subject_code, sub.subject_description,
           CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
           r.room_number
    FROM sections_schedules ss
    JOIN subjects sub ON ss.subject_id = sub.subject_id
    JOIN rooms r ON ss.room_id = r.room_id
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN students_sections sts ON sts.section_id = ss.section_id
    WHERE sts.s_id = ?
      AND ss.day_of_week = ?
      AND ss.term_id = ?
    ORDER BY ss.start_time";
} else {
    // Irregular students (subject_enrollments)
    $schedule_query = "
    SELECT ss.*, sub.subject_id, sub.subject_code, sub.subject_description,
           CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
           r.room_number
    FROM subject_enrollments se
    JOIN sections_schedules ss 
        ON ss.subject_id = se.subject_id 
       AND ss.section_code = se.section_code 
       AND ss.term_id = se.term_id
    JOIN subjects sub ON se.subject_id = sub.subject_id
    JOIN rooms r ON ss.room_id = r.room_id
    JOIN teachers t ON ss.teacher_id = t.t_id
    WHERE se.s_id = ?
      AND ss.day_of_week = ?
      AND se.term_id = ?
    ORDER BY ss.start_time";
}

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("isi", $student_id, $today, $current_term_id);
$stmt->execute();
$today_schedule = $stmt->get_result();


// -----------------------------
// 4️⃣ Subject Cards (grouped + aligned + QR)
// -----------------------------
if ($is_regular == 1) {
    $subject_cards_query = "
    SELECT DISTINCT s.subject_id, s.subject_code, s.subject_description, s.units,
           CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
           ss.ss_id, ss.day_of_week, ss.start_time, ss.end_time,
           r.room_number, g.generated_qrcode, sec.section_code, ss.teacher_id, ss.schedule_group_id
    FROM students_sections sts
    JOIN sections sec ON sec.section_id = sts.section_id
    JOIN sections_schedules ss ON ss.section_id = sts.section_id
    JOIN subjects s ON s.subject_id = ss.subject_id
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN rooms r ON ss.room_id = r.room_id
    LEFT JOIN generatedqrcode g ON g.id = sts.s_id
    WHERE sts.s_id = ? AND ss.term_id = ?
    ORDER BY s.subject_code,
             FIELD(ss.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
             ss.start_time";
} else {
    $subject_cards_query = "
    SELECT DISTINCT s.subject_id, s.subject_code, s.subject_description, s.units,
           CONCAT(t.t_fname, ' ', t.t_lname) AS teacher_name,
           ss.ss_id, ss.day_of_week, ss.start_time, ss.end_time,
           r.room_number, g.generated_qrcode, ss.section_code, ss.teacher_id, ss.schedule_group_id
    FROM subject_enrollments se
    JOIN sections_schedules ss 
        ON ss.subject_id = se.subject_id 
       AND ss.section_code = se.section_code 
       AND ss.term_id = se.term_id
    JOIN subjects s ON s.subject_id = se.subject_id
    JOIN teachers t ON ss.teacher_id = t.t_id
    JOIN rooms r ON ss.room_id = r.room_id
    LEFT JOIN generatedqrcode g ON g.id = se.s_id
    WHERE se.s_id = ? AND se.term_id = ?
    ORDER BY s.subject_code,
             FIELD(ss.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
             ss.start_time";
}

$stmt = $conn->prepare($subject_cards_query);
$stmt->bind_param("ii", $student_id, $current_term_id);
$stmt->execute();
$result = $stmt->get_result();

$subject_cards = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['subject_id']; // unique key by subject_id
    $schedKey = "{$row['day_of_week']}{$row['start_time']}{$row['end_time']}{$row['room_number']}";

    if (!isset($subject_cards[$key])) {
        $subject_cards[$key] = [
            'subject_id' => $row['subject_id'],
            'subject_code' => $row['subject_code'],
            'subject_description' => $row['subject_description'],
            'units' => $row['units'],
            'teacher_name' => $row['teacher_name'],
            'section_code' => $row['section_code'],
            'ss_id' => $row['ss_id'],                   // ✅ include ss_id
            'teacher_id' => $row['teacher_id'],
            'schedule_group_id' => $row['schedule_group_id'],
            'generated_qrcode' => $row['generated_qrcode'] ?? null,
            'schedules' => [],
            '_dedupe' => []
        ];
    }

    if (!in_array($schedKey, $subject_cards[$key]['_dedupe'])) {
        $subject_cards[$key]['schedules'][] = [
            'day' => $row['day_of_week'],
            'start' => $row['start_time'],
            'end' => $row['end_time'],
            'room' => $row['room_number']
        ];
        $subject_cards[$key]['_dedupe'][] = $schedKey;
    }
}

foreach ($subject_cards as &$sub) unset($sub['_dedupe']);
unset($sub);



// -----------------------------
// Fetch last 5 attendance records
// -----------------------------
$attendance_history = [];
$attendance_query = "
SELECT a.s_id, s.idcode, a.subject_code, a.section_code, a.time_in, a.time_out, a.status
FROM attendance a
INNER JOIN students s ON a.s_id = s.s_id
WHERE a.s_id = ?
ORDER BY a.time_in DESC
LIMIT 5
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendance_history[] = $row;
    }
}

$stmt->close();
?>

<main class="py-4">
    <div class="container-fluid px-4 ">

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center justify-content-sm-between text-center text-sm-start">
            
            <!-- Stack heading and paragraph -->
            <div class="d-flex flex-column mb-3 mb-sm-0">
                <h3 class="display-6 fw-bold text-primary mb-1">
                    <i class="bi bi-qr-code me-2"></i> Log Attendance
                </h3>
                <p class="text-muted mb-0">Scan your QR-CODE now for today's schedule.</p>
            </div>

            <!-- Last Updated box -->
            <div class="d-flex align-items-center">
                <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small d-flex align-items-center">
                    <i class="bi bi-clock-history me-1"></i>
                    Last Updated: 
                    <span id="last-updated" class="ms-1 fw-semibold text-dark">
                        <?= htmlspecialchars($_SESSION['last_updated']); ?>
                    </span>
                </div>
            </div>

        </div>
    </div>
</div>



<?php
$today = date('l'); // e.g., Monday, Tuesday, etc.
$today_subjects = [];

// 🔹 Filter only subjects that have schedules for today
foreach ($subject_cards as $subject) {
    $today_schedules = array_filter($subject['schedules'], function($sched) use ($today) {
        return strtolower($sched['day']) === strtolower($today);
    });

    if (!empty($today_schedules)) {
        // 🔹 Merge same time slots (like MWF with same start/end)
        $merged = [];
        foreach ($today_schedules as $sched) {
            $key = $sched['start'] . '|' . $sched['end'] . '|' . $sched['room'];
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'days' => [$sched['day']],
                    'start' => $sched['start'],
                    'end' => $sched['end'],
                    'room' => $sched['room']
                ];
            } else {
                $merged[$key]['days'][] = $sched['day'];
            }
        }
        $subject['schedules'] = array_values($merged);
        $today_subjects[] = $subject;
    }
}
?>


<!-- Today's Schedule -->
<div class="row g-4 mb-4">
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4 row-cols-lg-12 h-100">
        <?php if (!empty($today_subjects)): ?>
            <?php 
            $modalIndex = 0;
            foreach ($today_subjects as $subject): 
                $modalIndex++;
                $safeSubjectCode = preg_replace('/[^A-Za-z0-9_]/', '_', $subject['subject_code']);
                $modalId = 'qrModal_' . $safeSubjectCode . '_' . $modalIndex;
            ?>

            <div class="col-12 mx-auto mb-4">
            <div class="card shadow-sm h-100 d-flex flex-column text-center text-sm-start">        <!-- Header -->
                    <div class="card-header py-3">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($subject['subject_code']); ?> - 
                            <?php echo htmlspecialchars($subject['subject_description']); ?>
                        </h5>
                    </div>

                    <!-- Body -->
                <div class="card-body flex-grow-1">
                        <p class="mb-1"><strong>Units:</strong> <?php echo $subject['units']; ?></p>
                        <p class="mb-2"><strong>Teacher:</strong> <?php echo htmlspecialchars($subject['teacher_name']); ?></p>
                        <h6 class="text-muted">Today's Schedule:</h6>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($subject['schedules'] as $sched): ?>
                                <li>
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo implode('', array_map(function($d) { return substr($d, 0, 3); }, $sched['days'])); ?>
                                    <?php echo ", " . date('h:i A', strtotime($sched['start'])) . " - " . date('h:i A', strtotime($sched['end'])); ?>
                                    @ Room <?php echo htmlspecialchars($sched['room']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

<?php

// Sanitize modal ID
$modal_safe_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $subject['subject_code'] . '_' . $subject['subject_id']);

$todayDay = date('l');
$now = time();

$buttonDisabled = true;

foreach ($subject['schedules'] as $sched) {
    $schedDays = $sched['days'];
    $startTime = strtotime($sched['start']);
    $endTime   = strtotime($sched['end']);

    // Time windows
    $fifteenBeforeStart = $startTime - (15 * 60);
    $fortyAfterStart    = $startTime + (40 * 60);

    $tenBeforeEnd       = $endTime - (10 * 60);
    $fifteenAfterEnd    = $endTime + (15 * 60);

    if (in_array($todayDay, $schedDays)) {

        // Window A: 15 minutes before start up to 40 minutes after start
        $windowA = ($now >= $fifteenBeforeStart && $now <= $fortyAfterStart);

        // Window B: 10 minutes before end up to 15 minutes after end
        $windowB = ($now >= $tenBeforeEnd && $now <= $fifteenAfterEnd);

        if ($windowA || $windowB) {
            $buttonDisabled = false;
            break;
        }
    }
}

?>


<!-- Button to trigger modal -->
<button 
    class="btn w-100 m-0 rounded-5 text-align-center generateQRBtn"
    style="background-color: gold; color: #495057;"
    data-ss-id="<?= htmlspecialchars($subject['ss_id']); ?>"
    data-subject="<?= htmlspecialchars($subject['subject_code']); ?>"
    data-subject-id="<?= htmlspecialchars($subject['subject_id']); ?>"
    data-section-id="<?= htmlspecialchars($subject['section_code']); ?>"
    data-schedule-group-id="<?= htmlspecialchars($subject['schedule_group_id']); ?>"
    data-teacher-id="<?= htmlspecialchars($subject['teacher_id']); ?>"
    data-term-id="<?= htmlspecialchars($current_term_id); ?>" 
    data-modal="qrModal_<?= $modal_safe_id; ?>"
    data-bs-toggle="modal"
    data-bs-target="#qrModal_<?= $modal_safe_id; ?>"
     <?php echo $buttonDisabled ? 'disabled' : ''; ?>
>
    <h4><i class="bi bi-qr-code"></i> Log Attendancde</h4>
</button>



 






                </div>
            </div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal_<?php echo $modal_safe_id; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code - <?php echo htmlspecialchars($subject['subject_code']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="qrContent_qrModal_<?php echo $modal_safe_id; ?>">
                <!-- QR Code will be injected here -->
                <p class="text-muted">Click "Log Attendance" to generate QR code.</p>
            </div>
        </div>
    </div>
</div>


            <?php endforeach; ?>
        <?php else: ?>
            <!-- Centered message when no schedules found today -->
            <div class="d-flex justify-content-center align-items-center" style="min-height: 300px;">
                <div class="alert alert-info text-center w-75">
                    <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                    <h5 class="mb-1">No schedules found for <?php echo date('l'); ?></h5>
                    <p class="mb-0">You don’t have any subjects or classes scheduled for today.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>



      <!-- Attendance History -->
    <div class="card shadow-sm mt-4 p-1">

        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title  mb-0">
                <i class="bi bi-clock-history me-2 text-warning"></i>Attendance History
            </h5>
           <a href="?page=attendance_report" class="btn btn-warning btn-sm">
            View All
        </a>
        </div>
       
      <div class="card-body">
    <?php if (!empty($attendance_history)): ?>
        <div class="table-responsive">
            <table id="attendanceTable" class="table table-bordered table-hover">
                <thead>
                    <tr>
                       
                        <th>Student ID</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_history as $att): ?>
                    <?php
                        $status = $att['status'];
                        $badgeClass =
                            $status === 'Present' ? 'bg-success text-white' :
                            ($status === 'Absent' ? 'bg-danger text-white' :
                            ($status === 'Late' ? 'bg-warning text-dark' :
                            ($status === 'Excuse' ? 'bg-primary text-white' : 'bg-secondary text-white')));
                    ?>
                        <tr>
                            
                            <td><?= htmlspecialchars($att['idcode']) ?></td>
                            <td><?= htmlspecialchars($att['subject_code']) ?></td>
                            <td><?= $att['time_in'] ? date('M d, Y', strtotime($att['time_in'])) : 'N/A' ?></td>
                            <td>
                                <?= $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-' ?>
                                -
                                <?= $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-' ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="bi bi-clock-history display-4 text-muted mb-3"></i>
            <p class="mb-0">No attendance records found.</p>
        </div>
    <?php endif; ?>
</div>

    </div>


    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.generateQRBtn').forEach(button => {
        button.addEventListener('click', async function() {
            const ssId            = this.dataset.ssId;
            const termId = this.dataset.termId;
            const subjectCode     = this.dataset.subject;
            const subjectId       = this.dataset.subjectId;
            const sectionId       = this.dataset.sectionId;
            const scheduleGroupId = this.dataset.scheduleGroupId;
            const teacherId       = this.dataset.teacherId;
            const modalId         = this.dataset.modal;
            const qrContainer     = document.getElementById('qrContent_' + modalId);

            qrContainer.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"></div>
                    <p class="mt-2 text-muted">Generating QR Code...</p>
                </div>
            `;

            try {
                const response = await fetch('generate_temp_qr.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 
                          'ss_id=' + encodeURIComponent(ssId) +
                           '&term_id=' + encodeURIComponent(termId) +
                    '&subject_code=' + encodeURIComponent(subjectCode) +
                          '&subject_id=' + encodeURIComponent(subjectId) +
                          '&section_id=' + encodeURIComponent(sectionId) +
                          '&schedule_group_id=' + encodeURIComponent(scheduleGroupId) +
                          '&teacher_id=' + encodeURIComponent(teacherId) 
                });

                const data = await response.json();

                if (data.status === 'success') {
                    const expires = new Date(data.expires_at);
                    const formattedTime = expires.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    qrContainer.innerHTML = `
                        <img src="https://quickchart.io/qr?text=${encodeURIComponent(data.qr_code)}"
                             alt="QR Code"
                             style="width: 100%; max-width: 300px;">
                        <p class="mt-3 text-muted">
                            This QR code expires at <strong>${formattedTime}</strong>.
                        </p>
                    `;
                } else {
                    qrContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            } catch (error) {
                qrContainer.innerHTML = `<div class="alert alert-danger">Error generating QR code. Please try again.</div>`;
                console.error(error);
            }
        });
    });
});


var table = $('#attendanceTable').DataTable({
    responsive: {
        details: {
            type: 'column',
            target: 0
        }
    },
    columnDefs: [
        { className: 'dtr-control custom-plus', targets: 0 }
    ],
    scrollX: false,            // no horizontal scroll
        scrollY: '50vh',           // vertical scroll height
        responsive: true,
        ordering: true,            // allow sorting
    scrollCollapse: true,
    paging: true,
    searching: true,
    info: true,
    autoWidth: false
});

  $('#attendanceTable_length').addClass('mb-2 mt-2');
    $('#attendanceTable_filter').addClass('mb-2');
</script>


