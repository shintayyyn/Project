<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id   = $_SESSION['user_id'];
$subject_code = $_GET['subject_code'] ?? null;
$section_id   = $_GET['section_id'] ?? null;
$ss_id        = $_GET['ss_id'] ?? null;

if (!$subject_code || !$section_id || !$ss_id) {
    echo "<script>showToast('Missing subject, section, or schedule ID.'); window.location.href='../dashboard.php';</script>";
    exit();
}

date_default_timezone_set('Asia/Manila');
$currentDay = date('l');
$today      = date('Y-m-d');

// ✅ Fetch subject schedule info (using ss_id instead of subject_code)
$subjectStmt = $conn->prepare("
    SELECT ss.subject_id, ss.day_of_week, ss.start_time, ss.end_time, ss.description
    FROM sections_schedules ss
    INNER JOIN subjects s ON ss.subject_id = s.subject_id
    WHERE ss.ss_id = ?
      AND ss.teacher_id = ?
    LIMIT 1
");
$subjectStmt->bind_param("ii", $ss_id, $teacher_id);
$subjectStmt->execute();
$subjectRes = $subjectStmt->get_result();

$day_of_week = $start_time = $end_time = $description = 'N/A';
$subject_id = null;
if ($subjectRes->num_rows > 0) {
    $sched       = $subjectRes->fetch_assoc();
    $subject_id  = $sched['subject_id'];
    $day_of_week = $sched['day_of_week'];
    $start_time  = date('g:i A', strtotime($sched['start_time']));
    $end_time    = date('g:i A', strtotime($sched['end_time']));
    $description = $sched['description'];
} else {
    die("<script>alert('Schedule not found.'); window.history.back();</script>");
}
$subjectStmt->close();

// ✅ Get active term_id
$termStmt = $conn->prepare("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$termStmt->execute();
$termRes = $termStmt->get_result();
$currentTermId = ($termRes->num_rows > 0) ? $termRes->fetch_assoc()['term_id'] : null;
$termStmt->close();

// ✅ Fetch subject description via subject_id
$descStmt = $conn->prepare("SELECT subject_description FROM subjects WHERE subject_id = ? LIMIT 1");
$descStmt->bind_param("i", $subject_id);
$descStmt->execute();
$descRes = $descStmt->get_result();
$subject_description = ($descRes->num_rows > 0) ? $descRes->fetch_assoc()['subject_description'] : '';
$descStmt->close();

// ✅ Fetch attendance — now filtered by ss_id
$stmt = $conn->prepare("
    SELECT a.attendance_id, s.s_fname, s.s_lname, sd.degree_code, sec.section_code,
           subj.subject_code, subj.subject_description,
           a.time_in, a.time_out, a.status,
           ay.year_start, ay.year_end, t.semester
    FROM attendance a
    INNER JOIN students s ON a.s_id = s.s_id
    INNER JOIN students_degrees sd ON s.s_id = sd.s_id
    INNER JOIN sections sec ON a.section_code = sec.section_code
    INNER JOIN sections_schedules ss ON a.subject_id = ss.subject_id
    INNER JOIN subjects subj ON ss.subject_id = subj.subject_id
    INNER JOIN academic_terms t ON a.term_id = t.term_id
    INNER JOIN academic_years ay ON t.ay_id = ay.ay_id
    WHERE ss.ss_id = ?
      AND ss.teacher_id = ?
      AND subj.subject_code = ?
      AND sec.section_id = ?
    ORDER BY COALESCE(a.time_in, a.time_out) DESC
");
$stmt->bind_param("iisi", $ss_id, $teacher_id, $subject_code, $section_id);
$stmt->execute();
$res = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Attendance System</title>
      <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
     <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/attendance.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
      --shadow-dark: rgba(0, 0, 0, 0.2);
    }

    * {
      margin: 0;
      padding: 0;
      font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
    }
    
body {
      background: var(--background);
  
}



    .navbar {
      padding: 1rem 2rem;
      background: var(--primary);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .navbar a{
      color: var(--tertiary) !important;
      font-size: 1.25rem;
      font-weight: bold;
    }
    h3,h2,.fa-user-plus{
      font-weight: bold;
    }
    .main {
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: calc(100vh - 80px);
      padding: 20px;
    }



    /* Left QR scanner */
    .qr-container {
      flex: 1 1;
      width:100%;
    }

   

/* QR Scanner Frame */
.qr-frame {
  width: 100%;
  max-width: 350px;
  height: 250px;               /* fixed square scanner box */
  border: 4px solid #0d6efd;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  margin: auto;
  background: var(--primary);
}


/* Force the video to fill the frame without squishing */
.qr-video-wrapper video {
  position: absolute;
  width: 100% !important;
  height:auto !important;
  object-fit: cover;           /* 👈 makes it "cover" fully */
  border-radius: 12px;
}


    /* Laser Animation */
    .laser {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: rgba(255, 0, 0, 0.7);
      animation: laser-scan 3s linear infinite;
      box-shadow: 0 0 8px red;
      z-index: 1000;
    }

    @keyframes laser-scan {
      0%   { top: 0; }
      50%  { top: calc(100% - 3px); }
      100% { top: 0; }
    }

    .clock {
      font-size: 1.1rem;
    }


    .table-container::-webkit-scrollbar {
      width: 6px;
    }
    .table-container::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 3px;
    }

    #attendanceTable th, 
  #attendanceTable td {
    word-wrap: break-word;
}


table.dataTable{
    table-layout: fixed !important;
    th{
       background:var(--primary) !important;
       color: white;
    }
}


    .attendance-card:hover {
      transform: scale(1.05);
    }

    .attendance-card .count {
      font-size: 2.5rem;
    }

    .attendance-card .label {
      font-size: 1.2rem;
      margin-top: 5px;
      text-transform: uppercase;
    }

    .gap-3 {
      gap: 1rem;
    }

    .badge-success { background-color: #28a745 !important; }
    .badge-warning { background-color: #ffc107 !important; color: #212529 !important; }
    .badge-danger  { background-color: #dc3545 !important; }
    .badge {
      color: #fff;
      padding: 0.5em 0.6em;
      font-size: 0.85rem;
      border-radius: 0.25rem;
    }

   .attendanceStats .card {
    margin: 10px;
    height: 20px;
    width: 100px;       /* compact widget size */
    min-height: 90px;   /* keeps consistency */
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* card header styling */
.attendanceStats .card .card-header {
    background-color: var(--primary);
    color: var(--tertiary);
    padding: 6px;
    font-size: 0.9rem;
}

  </style>

</head>

<body>

  <!-- Navbar -->
<nav class="navbar navbar-expand-lg shadow-sm">
  <div class="container-fluid">
    <!-- Logo + Brand -->
    <a class="navbar-brand d-flex align-items-center" href="#">
      <i class="fa-solid fa-qrcode"></i>
      <span class="fw-bold ms-2">Attendify</span>
    </a>

    <!-- Responsive Toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Nav Links -->
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link active" href="/teacher/dashboard.php">Dashboard</a>
        </li>
      </ul>
    </div>

    <!-- Button always far right -->
    <div class="d-flex">
      <button class="btn btn-warning enroll-btn ms-2"
        data-ss-id="<?= $ss_id?>"   
        data-subject-id="<?= $subject_id ?>"                          
        data-subject-code="<?= htmlspecialchars($subject_code) ?>"                      
        data-subject-description="<?= htmlspecialchars($subject_description) ?>"
        data-term-id="<?= $currentTermId ?>"
        data-bs-toggle="modal" data-bs-target="#enrollModal">
        <i class="fa-solid fa-user-plus me-2"></i> Enroll an Irregular Student
      </button>
    </div>
  </div>
</nav>




  <!-- Main Content -->
  <div class="main">
    <audio id="scan-sound" src="../../assets/audio/qrbeeptone.mp3" preload="auto"></audio>

    <div class="attendance-container row">
      <!-- QR Scanner -->
      <div class="qr-container col-12 col-lg-4 d-flex flex-column justify-content-center align-items-center mb-4 mb-lg-0">
        <div class="subject-info text-center mb-4">
          <h3 class="fw-bold">
            <?= htmlspecialchars($subject_code) ?> - <?= htmlspecialchars($subject_description)?>
            </h3>

        </div>

        <div class="scanner-con w-100 text-center">
          <div class="scanner-controls mb-2">
            <button id="btnTurnOn" class="btn btn-primary me-2">Turn On Scanner</button>
            <button id="btnTurnOff" class="btn btn-danger" disabled>Turn Off Scanner</button>
          </div>

          <p class="mb-3">Scan your QR Code here for your attendance</p>
          
          <!-- QR Frame with Laser -->
          <div class="qr-frame corner-border">
            <div id="reader" class="qr-video-wrapper"></div>
            <div class="laser"></div>
          </div>

          <div class="clock mt-3 fw-bold text-primary" id="liveClock">Loading time...</div>
        </div>

        <!-- QR Detected Message -->
        <div class="qr-detected-container mt-3">
          <form id="attendance-form" method="POST">
            <input type="hidden" name="qr_code" id="detected-qr-code">
          </form>
        </div>
      </div>

      <!-- Attendance List -->
      <div class="attendance-list col-12 col-lg-8">
      <div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="mb-0">List of Present Students</h2>
  
  <!-- Attendance Stats -->
  <div class="d-flex gap-2 attendanceStats">
    <div class="card text-center shadow rounded stat-card">
      <div class="card-header py-1">
        <h6 class="fw-bold mb-0">Present</h6>
      </div>
      <div class="card-body py-2">
        <h4 id="presentCount" class="mb-0">0</h4>
      </div>
    </div>
    <div class="card text-center shadow rounded stat-card">
      <div class="card-header py-1">
        <h6 class="fw-bold mb-0">Late</h6>
      </div>
      <div class="card-body py-2">
        <h4 id="lateCount" class="mb-0">0</h4>
      </div>
    </div>
    <div class="card text-center shadow rounded stat-card">
      <div class="card-header py-1">
        <h6 class="fw-bold mb-0">Absent</h6>
      </div>
      <div class="card-body py-2">
        <h4 id="absentCount" class="mb-0">0</h4>
      </div>
    </div>
  </div>
      </div>
      <div class="card-body">
          <div class="table-responsive shadow rounded p-3">
  <table class="table" id="attendanceTable">
      <thead class="ttext-center">
          <tr>
            <th class="d-lg-none"></th>
              <th >No.</th>
              <th >Name</th>
              <th>Course & Section</th>
              <th >Time In</th>
              <th>Time Out</th>
              <th >Term</th>
              <th >Status</th>
          </tr>
      </thead>
              
            <tbody>
              <?php
                $teacher_id   = $_SESSION['user_id'];
                $subject_code = $_GET['subject_code'] ?? '';
                $section_id   = $_GET['section_id'] ?? '';

                if (!$subject_code || !$section_id) {
                    echo '<tr><td colspan="8" class="text-center text-danger">No subject or section selected.</td></tr>';
                } else {
                    $stmt = $conn->prepare("
                                        SELECT a.attendance_id, s.s_id, s.s_fname, s.s_lname, 
                        MAX(sd.degree_code) AS degree_code, sec.section_code, 
                        a.subject_code, a.time_in, a.time_out, a.status,
                        ay.year_start, ay.year_end, t.semester
                    FROM attendance a
                    INNER JOIN students s ON a.s_id = s.s_id
                    INNER JOIN students_degrees sd ON s.s_id = sd.s_id
                    INNER JOIN sections sec ON a.section_code = sec.section_code
                    INNER JOIN sections_schedules ss 
                        ON ss.subject_code = a.subject_code 
                        AND ss.section_id = sec.section_id
                    INNER JOIN academic_terms t ON a.term_id = t.term_id
                    INNER JOIN academic_years ay ON t.ay_id = ay.ay_id
                    WHERE ss.teacher_id = ? 
                    AND a.subject_code = ? 
                    AND sec.section_id = ?
                    GROUP BY a.attendance_id
                    ORDER BY COALESCE(s.s_lname, a.time_in, a.time_out) DESC

                    ");
                    $stmt->bind_param("isi", $teacher_id, $subject_code, $section_id);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    $i = 1;
                    $presentCount = $lateCount = $absentCount = 0;
                    $today = date('Y-m-d');
                    $scannedToday = [];

                    while ($row = $res->fetch_assoc()):
                        $status = $row['status'] ?? null;

                        if (!empty($row['time_in']) && date('Y-m-d', strtotime($row['time_in'])) === $today) {
                            $scannedToday[] = (int)$row['s_id'];
                            if ($status === 'Present') $presentCount++;
                            elseif ($status === 'Late') $lateCount++;
                            elseif ($status === 'Absent') $absentCount++;
                        }
              ?>
                <tr data-attendance-id="<?= $row['attendance_id'] ?>" >
                  <td class="d-lg-none"></td>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?></td>
                  <td><?= htmlspecialchars(' ' . $row['section_code']) ?></td>
                  <td>
                    <?php
                      if ($row['time_in']) {
                        $timeIn = date('H:i:s', strtotime($row['time_in']));
                        if ($timeIn === "00:00:00") {
                          echo date('M j, Y', strtotime($row['time_in'])) . ' 00:00';
                        } else {
                          echo date('M j, Y g:i A', strtotime($row['time_in']));
                        }
                      } else {
                        echo '<span class="text-muted">' . date('M j, Y') . ' —</span>';
                      }
                    ?>
                  </td>
                  <td>
                    <?php
                      if ($row['time_out']) {
                        $timeOut = date('H:i:s', strtotime($row['time_out']));
                        if ($timeOut === "00:00:00") {
                          echo date('M j, Y', strtotime($row['time_out'])) . ' 00:00';
                        } else {
                          echo date('M j, Y g:i A', strtotime($row['time_out']));
                        }
                      } else {
                        echo '<span class="text-muted">' . date('M j, Y') . ' —</span>';
                      }
                    ?>
                  </td>
                    <td>
                    <?= "A.Y. {$row['year_start']}-{$row['year_end']} | {$row['semester']}" ?>
                    </td>
                  <td class="text-center">
                    <?php
                      $badgeClass = 'secondary';
                      if ($status === 'Present') $badgeClass = 'success';
                      elseif ($status === 'Late') $badgeClass = 'warning';
                      elseif ($status === 'Absent') $badgeClass = 'danger';
                    ?>
                    <span class="badge bg-<?= $badgeClass ?>"><?= $status ?: 'N/A' ?></span>
                  </td>
                </tr>
              <?php endwhile; } ?>
            </tbody>
          </table>
        </div>
      </div>
      
      </div>
      
    </div>

    <!-- Toast Notifications -->
    <div aria-live="polite" aria-atomic="true" style="position: relative;">
      <div id="toast-container" style="position: fixed; top: 1rem; right: 1rem; z-index: 9999;"></div>
    </div>
  </div>

<!-- Enroll Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="enrollForm">
        <div class="modal-header">
          <h5 class="modal-title" id="enrollModalLabel">Enroll Irregular Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="studentID" class="form-label">Student IDCODE</label>
            <input type="text" class="form-control" id="studentID" name="idcode" required>
          </div>
          <!-- Hidden inputs for current subject/schedule -->
          <input type="hidden" id="ss_id" name="ss_id">
          <input type="hidden" id="subject_id" name="subject_id">
          <input type="hidden" id="subject_code" name="subject_code">
          <input type="hidden" id="subject_description" name="subject_description">
          <input type="hidden" id="term_id" name="term_id">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Enroll</button>
        </div>
      </form>
    </div>
  </div>
</div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS & Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

  
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>


    <!-- Instascan QR Scanner -->
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    </script>

    <!-- Include SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            const subject_code = new URLSearchParams(window.location.search).get("subject_code");
            const section_id = new URLSearchParams(window.location.search).get("section_id");
            if (!subject_code || !section_id) {
                showToast( "warning","Missing subject or section.");
                return;
            }

  // ---------- DATA TABLE ----------
window.studentsTable = $('#attendanceTable').DataTable({
    responsive: true,          // keeps columns responsive
    autoWidth: false,          // disable automatic column resizing
    scrollY: '60vh',           // vertical scroll only
    scrollCollapse: true,
    paging: true,
    ordering: true,
    order: [[1, 'asc']],       // sort by Name ascending
    pageLength: 20,
    lengthMenu: [5, 10, 25, 50, 100],
    scroller: true,
    columnDefs: [
        { 
            orderable: false,
            className: 'text-center',
            targets: 0,  // first column = toggle
            render: () => '<button class="btn btn-sm btn-primary toggle-details-btn">+</button>'
        }
    ]
});

            const html5QrCode = new Html5Qrcode("reader");
            const config = {
                fps: 10,
                qrbox: {
                    width: 350,
                    height: 350
                }
            };
            let scannerRunning = false;

            function formatDateTime() {
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                };
                return new Date().toLocaleString('en-US', options);
            }

            function onScanSuccess(decodedText) {
                const audio = document.getElementById("scan-sound");
                audio.currentTime = 0;
                audio.play();
                html5QrCode.pause();

                $.post("save_attendance.php", {
                    qr_code: decodedText,
                    subject_code,
                    section_id
                }, function(res) {
                    if (res.success) {
                        showToast( "success",res.message ||  "success","Attendance logged!");
                        updateTable(res.data);
                        // Call this after table updates
                        updateCounters();
                    } else {
                        showToast( "warning",res.message ||  "warning","Error logging attendance");
                    }
                    setTimeout(() => html5QrCode.resume(), 500);
                }, "json").fail(() => {
                    showToast("danger","Error communicating with server.");
                    setTimeout(() => html5QrCode.resume(), 500);
                });
            }

            // ✅ Turn On Scanner
            $("#btnTurnOn").on("click", function() {
                if (scannerRunning) return;
                Html5Qrcode.getCameras().then(devices => {
                    if (devices.length) {
                        html5QrCode.start({
                            facingMode: "environment"
                        }, config, onScanSuccess);
                        scannerRunning = true;
                        $("#btnTurnOn").prop("disabled", true);
                        $("#btnTurnOff").prop("disabled", false);

                        // Log teacher's time_in
                        $.post("save_attendance.php", {
                            subject_code,
                            section_id,
                            teacher_action: "time_in"
                        }, function(res) {
                            if ("success",res.success) showToast("success",res.message);
                            else showToast("warning",res.message || "warning","Error logging teacher in.");
                        }, "json");
                    } else {
                        showToast("warning","No camera found.");
                    }
                });
            });

            // ✅ Turn Off Scanner
            $("#btnTurnOff").on("click", function() {
                if (!scannerRunning) return;
                $(this).prop("disabled", true);

                const timeOutStamp = formatDateTime();

                // Step 0: Pre-check if schedule exists
                $.post("check_schedule.php", {
                    subject_code,
                    section_id
                }, function(scheduleRes) {
                    if (!scheduleRes.success) {
                        showToast("warning",scheduleRes.message || "warning","No class schedule now. Cannot turn off scanner.");
                        $("#btnTurnOff").prop("disabled", false);
                        return;
                    }

                    // 🔹 Trap: Check if class is already dismissed
                    const now = new Date();
                    const endTime = new Date();
                    const [h, m, s] = scheduleRes.end_time.split(":");
                    endTime.setHours(h, m, s || 0);

                    if (now > endTime) {
                        showToast("warning","Class already dismissed. Cannot turn off scanner.");
                        $("#btnTurnOff").prop("disabled", false);
                        return;
                    }

                    // Step 1: Stop scanner
                    html5QrCode.stop().then(() => {
                        scannerRunning = false;
                        $("#btnTurnOn").prop("disabled", false);

                        // Step 2: Teacher time_out
                        $.post("save_attendance.php", {
                            subject_code,
                            section_id,
                            teacher_action: "time_out",
                            timestamp: timeOutStamp
                        }, function(res) {
                            if (res.success) {
                                // Step 3: Students time_out
                                $.post("close_attendance.php", {
                                    subject_code,
                                    section_id,
                                    timestamp: timeOutStamp,
                                    only_time_in: 1
                                }, function(res) {
                                    if (res.success) {
                                        if (res.dismissed && res.dismissed == 1) {
                                            // ❌ Don’t show teacher or student success toast
                                            showToast("info","Class is already dismissed. No further actions allowed.");
                                        } else {
                                            // ✅ Show both teacher + student logout toasts
                                            showToast("info","Teacher logged OUT at " + timeOutStamp);
                                            showToast( "info","All students logged OUT at " + timeOutStamp);
                                        }
                                        setTimeout(() => location.reload(), 2000);
                                    } else {
                                        showToast("warning",res.message || "warning","Error logging students' time out.");
                                        $("#btnTurnOff").prop("disabled", false);
                                    }
                                }, "json").fail(() => {
                                    showToast("danger","Error communicating with server (students).");
                                    $("#btnTurnOff").prop("disabled", false);
                                });
                            } else {
                                showToast("warning",res.message || "warning","Error logging teacher time out.");
                                $("#btnTurnOff").prop("disabled", false);
                            }
                        }, "json").fail(() => {
                            showToast("danger","Error communicating with server (teacher).");
                            $("#btnTurnOff").prop("disabled", false);
                        });


                    }).catch(err => {
                        showToast("danger","Error stopping scanner: " + err);
                        $("#btnTurnOff").prop("disabled", false);
                    });

                }, "json").fail(() => {
                    showToast("danger","Error checking schedule on server.");
                    $("#btnTurnOff").prop("disabled", false);
                });
            });

            document.getElementById("presentCount").innerText = <?= $presentCount ?>;
            document.getElementById("lateCount").innerText = <?= $lateCount ?>;
            document.getElementById("absentCount").innerText = <?= $absentCount ?>;

            function updateCounters() {
                let present = 0,
                    late = 0,
                    absent = 0;
                const today = new Date().toISOString().split("T")[0]; // yyyy-mm-dd

                document.querySelectorAll("#attendanceTable tbody tr").forEach(row => {
                    const timeInCell = row.querySelector("td:nth-child(4)");
                    if (!timeInCell) return;

                    const text = timeInCell.innerText.trim();
                    const rowDate = text ? new Date(text).toISOString().split("T")[0] : today;

                    // ✅ Skip if not today
                    if (rowDate !== today) return;

                    const statusBadge = row.querySelector("td:nth-child(6) .badge");
                    if (!statusBadge) return;
                    const status = statusBadge.innerText.trim();

                    if (status === "Present") present++;
                    else if (status === "Late") late++;
                    else if (status === "Absent") absent++;
                });

                document.getElementById("presentCount").innerText = present;
                document.getElementById("lateCount").innerText = late;
                document.getElementById("absentCount").innerText = absent;
            }


            // ------------------ Table Update ------------------
            function updateTable(data) {
                const row = $(`#attendanceTable tbody tr[data-attendance-id="${data.id}"]`);
                const todayDate = new Date().toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
                const timeIn = data.time_in || `<span class="text-muted">${todayDate} —</span>`;
                const timeOut = data.time_out ? data.time_out : (data.status === "Absent" ? `<span class="text-muted">${todayDate} —</span>` : "—");
                const badge = getBadge(data.status);

                if (row.length) {
                    row.find("td").eq(3).html(timeIn);
                    row.find("td").eq(4).html(timeOut);
                    row.find("td").eq(5).html(badge);
                    row.addClass("highlight");
                    setTimeout(() => row.removeClass("highlight"), 1500);
                } else {
                    const newRow = dataTable.row.add([
                        dataTable.rows().count() + 1,
                        data.name,
                        data.course_section,
                        timeIn,
                        timeOut,
                        badge,
                        `<button class="btn btn-danger btn-sm" onclick="deleteAttendance(${data.id}, this)">X</button>`
                    ]).draw(false).node();

                    $(newRow).attr("data-attendance-id", data.id).addClass("highlight");
                    setTimeout(() => $(newRow).removeClass("highlight"), 1500);
                }
            }

            function getBadge(status) {
                const badgeMap = {
                    "Present": "success",
                    "Late": "warning",
                    "Absent": "danger"
                };
                const badgeClass = badgeMap[status] || "secondary";
                return `<span class="badge badge-${badgeClass}">${status}</span>`;
            }

            // ------------------ Toast ------------------
           function showToast(type, message) {
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
            icon = 'warning' || 'danger';
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


     const enrollModal = document.getElementById('enrollModal');

if (enrollModal) {
  enrollModal.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;

    document.getElementById('ss_id').value = button.getAttribute('data-ss-id');
    document.getElementById('subject_id').value = button.getAttribute('data-subject-id');
    document.getElementById('subject_code').value = button.getAttribute('data-subject-code');
    document.getElementById('subject_description').value = button.getAttribute('data-subject-description');
    document.getElementById('term_id').value = button.getAttribute('data-term-id');

    // Clear the Student IDCODE field
    document.getElementById('studentID').value = '';
  });
}

const enrollForm = document.getElementById('enrollForm');

if (enrollForm) {
  enrollForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('enroll_irregular.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text()) // always read as text first
    .then(text => {
      try {
        return JSON.parse(text); // try parsing JSON
      } catch (err) {
        console.error("Server returned non-JSON:", text);
        throw new Error("Invalid JSON response from server");
      }
    })
    .then(data => {
      if (data.status === 'success') {
        showToast('success',data.message);
        this.reset();

        // ✅ Close modal only on success
       $('#enrollModal').modal('hide');

        // Optionally refresh your table here
      } else {
        showToast('warning',data.message);
      }
    })
    .catch(err => {
      console.error("Fetch error:", err);
      showToast('error','An error occurred while enrolling the student. Check console for details.');
    });
  });
}

            // ------------------ SweetAlert2 Delete ------------------
            window.deleteAttendance = function(id, btn) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will remove the attendance record!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('delete_attendance.php', {
                            attendance_id: id
                        }, function(res) {
                            if (res.success) {
                                const row = window.dataTable.row($(btn).closest("tr"));
                                if (row) row.remove().draw(false);
                                showToast("Attendance deleted.", "info");
                                updateCounters();
                            } else {
                                showToast("danger",res.message || "danger","Delete failed");
                            }
                        }, 'json').fail(() => {
                            showToast("danger","Error deleting attendance.");
                        });
                    }
                });
            };

            // ------------------ Live Clock ------------------
            function updateClock() {
                $('#liveClock').text(new Date().toLocaleString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }));
            }
            updateClock();
            setInterval(updateClock, 1000);
        });
    </script>

</body>

</html>