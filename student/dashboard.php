<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../includes/db.php');

// Ensure only student can access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get student info
$s_id = $_SESSION['user_id'];
$query = "SELECT s_id, s_fname, s_mname, s_lname, s_suffix FROM students WHERE s_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $s_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

$fullName = $student['s_fname'] 
    . (!empty($student['s_mname']) ? ' ' . strtoupper(substr($student['s_mname'], 0, 1)) . '.' : '') 
    . ' ' . $student['s_lname'] 
    . (!empty($student['s_suffix']) ? ' ' . $student['s_suffix'] : '');
$_SESSION['user_name'] = $fullName;

// 🔹 Check if student is a Mayor in any section
$checkMayor = $conn->prepare("
    SELECT ss.section_id, ss.section_code 
    FROM students_sections ss 
    WHERE ss.s_id = ? AND ss.is_mayor = 1
");
$checkMayor->bind_param("i", $s_id);
$checkMayor->execute();
$mayorResult = $checkMayor->get_result();

if ($mayorResult->num_rows > 0) {
    $_SESSION['is_mayor'] = true;
    $_SESSION['mayor_section'] = $mayorResult->fetch_assoc();
} else {
    $_SESSION['is_mayor'] = false;
}

// 🔹 Student query (for section info)
$student_query = "
    SELECT s.*, sec.section_code 
    FROM students s 
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id 
    LEFT JOIN sections sec ON ss.section_id = sec.section_id 
    WHERE s.s_id = ?
";

// 🔹 Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
switch ($page) {
    case 'dashboard':
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
        break;
    case 'approve_devices':
        if ($_SESSION['is_mayor']) { // Only mayor can access
            $content = 'approve_devices/index.php';
            $title = 'Approve Devices';
        } else {
            $content = 'dashboard/index.php';
            $title = 'Dashboard';
        }
        break;
    case 'schedule':
        $content = 'schedule/index.php';
        $title = 'My Schedule';
        break;
    case 'profile':
        $content = 'profile/index.php';
        $title = 'My Profile';
        break;
    case 'attendance':
        $content = 'attendance/index.php';
        $title = 'Attendance';
        break;
    case 'attendance_report':
        $content = 'attendance_reports/index.php';
        $title = 'Attendance Report';
        break;
    default:
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
}

// 🔹 UNIVERSAL LAST UPDATED FETCH
$latest_update_query = "
  SELECT MAX(updated_time) AS last_update FROM (
    -- Students table
    SELECT MAX(s.s_updated_at) AS updated_time
    FROM students AS s
    WHERE s.s_id = {$s_id}

    UNION ALL

    -- Students_Sections table
    SELECT MAX(sts.updated_at) AS updated_time 
    FROM students_sections AS sts 
    WHERE sts.s_id = {$s_id}

    UNION ALL

    -- Sections_Schedules table
    SELECT MAX(ss.updated_at) AS updated_time 
    FROM sections_schedules AS ss
    JOIN students_sections AS sts ON ss.section_id = sts.section_id
    WHERE sts.s_id = {$s_id}
    AND ss.is_active = 1

    UNION ALL

    -- Subjects table
    SELECT MAX(sub.updated_at) AS updated_time 
    FROM subjects AS sub

    UNION ALL

    -- Approved_Devices table
    SELECT MAX(ad.updated_at) AS updated_time
    FROM approved_devices AS ad
    WHERE ad.student_id = {$s_id}
  ) AS combined;
";

$latest_update_result = $conn->query($latest_update_query);
$latest_update_row = $latest_update_result ? $latest_update_result->fetch_assoc() : null;

$last_updated = !empty($latest_update_row['last_update'])
    ? date("F j, Y g:i A", strtotime($latest_update_row['last_update']))
    : 'No updates yet';

// 🔹 Make last updated globally available for the dashboard
$_SESSION['last_updated'] = $last_updated;

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student - <?php echo $title; ?></title>
     <link rel="icon" type="image/png" href="../includes/logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
     <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    
    <style>
       *{
            font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
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
        
        body {
            margin: 0;
            padding: 0;
            background-color: var(--background);
            min-height: 100vh;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

         .sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    position: fixed;
    top: 0; left: 0;
    color: white;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}


/* ✅ Hide scrollbars for Chrome, Edge, Safari */
body::-webkit-scrollbar{
    display: none;
}

        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            background-color: var(--background);
            position: relative;
        }

      
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
.sidebar-scrollable {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}


/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}
::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.4);
}
        .student-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 1rem;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .nav-section {
            margin-bottom: 1.5rem;
        }
        
        .nav-section-label {
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 0.5rem;
        }
        
        .nav-section a {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
        }
        
        .nav-section a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-section a.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .nav-section a i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .sidebar-footer {
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-footer a {
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .sidebar-footer a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-footer a i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .content {
                margin-left: 0;
                width: 100%;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .card {
            background-color: white;
            border: none;
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 15px rgba(61, 82, 160, 0.1);
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(61, 82, 160, 0.15);
        }

        .card-header {
            background-color: var(--primary);
            border-bottom: 1px solid rgba(61, 82, 160, 0.1);
            padding: 1.25rem;
            border-top-left-radius: var(--card-border-radius) !important;
            border-top-right-radius: var(--card-border-radius) !important;
        }

        .card-body {
            padding: 1.5rem;
            background-color: white;
        }

        .card-title {
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }

        .card-title i {
            font-size: 1.1em;
            margin-right: 0.5rem;
            color: var(--secondary);
        }
                /* Avatar circle */
.profile-image2 {
    width: 85px;
    height: 85px;
    border: 4px solid white;
    background: var(--tertiary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 10px; /* Space below avatar */
}

.profile-avatar-wrapper2 {
    display: flex;
    justify-content: center;
}
/* Floating mobile nav bar */
.mobile-nav {
    position: fixed;
    bottom: 15px;
    left: 50%;
    transform: translateX(-50%);
    width: 95%;
    background: white;
    padding: 12px 0;
    border-radius: 30px;
   box-shadow:  20px 20px 60px #bebebe,
             10px 10px 80px #f0efefff;
    display: flex;
    justify-content: space-around;
    z-index: 9999;
}

/* Nav items */
.mobile-link {
    position: relative;
    text-align: center;
    color: #999;
    text-decoration: none;
    flex: 1;
    font-size: 12px;
    transition: 0.25s ease;
}

/* The icon bubble holder */
.icon-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: transparent;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 3px;
    transition: 0.25s ease;
}

/* Icon */
.mobile-link i {
    font-size: 22px;
    transition: 0.25s ease;
}

/* Active state (the magic part) */
.mobile-link.active .icon-wrapper {
    background: var(--primary);
    color: var(--tertiary);
    transform: translateY(-30px);
    box-shadow:  20px 20px 60px #bebebeff,
             -20px -20px 60px #ffffff;
}

/* Active icon color */
.mobile-link.active i {
    color: var(--tertiary);
    transform: scale(1.15);
}

/* Label highlight */
.mobile-link.active span {
    color: var(--primary);
    font-weight: 500;
}

/* Hover effect on mobile (optional, soft like the image) */
.mobile-link:hover .icon-wrapper {
    transform: translateY(-8px);
}

/* ✅ Hide sidebar + toggle button on mobile */
@media (max-width: 768px) {
    .sidebar {
        display: none !important;
    }
    .content {
        margin-left: 0 !important;
        width: 100% !important;
        padding-bottom: 80px; /* space for bottom nav */
    }
}

    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
         <div class="sidebar-header text-center">

    <!-- ✅ Profile Avatar -->
    <div class="profile-avatar-wrapper2 d-flex flex-column align-items-center mb-3">
        <div class="profile-image2 d-flex justify-content-center align-items-center mb-2">
            <?php
            // Generate initials
            $initials = '';
            if (!empty($student['s_fname']) || !empty($student['s_lname'])) {
                $initials = strtoupper(
                    substr($student['s_fname'] ?? '', 0, 1) .
                    substr($student['s_lname'] ?? '', 0, 1)
                );
                echo htmlspecialchars($initials); // Show initials
            }
            ?>
        </div>
    </div>

    <!-- ✅ Student Info -->
    <h3 class="text-center mb-1"><?php echo htmlspecialchars($fullName); ?></h3>

   <?php
// Role
$role = (!empty($_SESSION['is_mayor']) && $_SESSION['is_mayor']) ? "Class Mayor" : "Student";

// Active term_id
$term_id = $_SESSION['term_id'] ?? null;

// Fetch is_regular
$regQuery = $conn->prepare("SELECT is_regular FROM students WHERE s_id = ?");
$regQuery->bind_param("i", $s_id);
$regQuery->execute();
$is_regular = $regQuery->get_result()->fetch_assoc()['is_regular'] ?? 1;

// Defaults
$label = "Section";
$value = "None";

// CASE 1: Class Mayor
if (!empty($_SESSION['is_mayor'])) {

    $value = $_SESSION['mayor_section']['section_code'] ?? 'None';

// CASE 2: Regular student (is_regular = 1)
} elseif ($is_regular == 1) {

    $secQuery = $conn->prepare("
        SELECT section_code 
        FROM students_sections 
        WHERE s_id = ? AND term_id = ?
        LIMIT 1
    ");
    $secQuery->bind_param("ii", $s_id, $term_id);
    $secQuery->execute();
    $secRes = $secQuery->get_result()->fetch_assoc();
    $value = $secRes['section_code'] ?? 'None';

// CASE 3: Irregular student (is_regular = 2)
} elseif ($is_regular == 2) {

    $degQuery = $conn->prepare("
        SELECT degree_code 
        FROM students_degrees 
        WHERE s_id = ? AND term_id = ?
        LIMIT 1
    ");
    $degQuery->bind_param("ii", $s_id, $term_id);
    $degQuery->execute();
    $degRes = $degQuery->get_result()->fetch_assoc();

    $label = "Department";
    $value = $degRes['degree_code'] ?? "BSIT"; // fallback as you wanted
}
?>

<span class="student-badge text-wrap"><?php echo htmlspecialchars($role); ?></span>
<span class="student-badge text-wrap">
    <?php echo htmlspecialchars($label) . ": " . htmlspecialchars($value); ?>
</span>
</div>
                <div class="sidebar-scrollable">
                      <div class="nav-section">
                <div class="nav-section-label">Main</div>
                <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <?php if ($_SESSION['is_mayor']): ?>
                <a href="?page=approve_devices" class="<?php echo $page === 'approve_devices' ? 'active' : ''; ?>">
                    <i class="bi bi-phone"></i>
                    <span>Approve Devices</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="nav-section">
                <div class="nav-section-label">Academic</div>
                <a href="?page=schedule" class="<?php echo $page === 'schedule' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar3"></i>
                    <span>My Schedule</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-label">Account</div>
                <a href="?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
                <a href="?page=attendance" class="<?php echo $page === 'attendance' ? 'active' : ''; ?>">
                    <i class="bi bi-qr-code"></i>
                    <span>Attendance</span>
                </a>
            </div>

               <div class="nav-section">
                <div class="nav-section-label">Reports</div>
                <a href="?page=attendance_report" class="<?php echo $page === 'attendance_report' ? 'active' : ''; ?>">
                    <i class="bi bi-qr-code"></i>
                    <span>Attendance Report</span>
                </a>
            </div>
                </div>
             <div class="sidebar-footer">
                <a href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Content -->
        <div class="content">
            <?php if (file_exists($content)): ?>
                <div class="container-fluid px-0">
                    <?php include $content; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">Page not found.</div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ✅ MOBILE BOTTOM NAVBAR -->
<nav class="mobile-nav d-md-none">
    
    <!-- Dashboard -->
    <a href="?page=dashboard" class="mobile-link <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
        <div class="icon-wrapper">
    <i class="bi bi-speedometer2"></i>

        </div>
        <span>Home</span>
    </a>

    <!-- Approve Devices (Mayor Only) -->
    <?php if ($_SESSION['is_mayor']): ?>
    <a href="?page=approve_devices" class="mobile-link <?php echo $page === 'approve_devices' ? 'active' : ''; ?>">
        <div class="icon-wrapper">
    <i class="bi bi-phone"></i>

        </div>
        <span>Devices</span>
    </a>
    <?php endif; ?>

    <!-- My Schedule -->
    <a href="?page=schedule" class="mobile-link <?php echo $page === 'schedule' ? 'active' : ''; ?>">
        <div class="icon-wrapper">
    <i class="bi bi-calendar3"></i>
        </div>
        <span>Schedule</span>
    </a>

    <!-- Attendance (Scan) -->
    <a href="?page=attendance" class="mobile-link <?php echo $page === 'attendance' ? 'active' : ''; ?>">
       <div class="icon-wrapper">
    <i class="bi bi-qr-code"></i>

       </div>
        <span>Scan</span>
    </a>
    
    <!-- Attendance Report -->
    <a href="?page=attendance_report" class="mobile-link <?php echo $page === 'attendance_report' ? 'active' : ''; ?>">
        <div class="icon-wrapper">
    <i class="bi bi-clipboard-data"></i>

        </div>
        <span>Report</span>
    </a>

    <!-- Profile -->
    <a href="?page=profile" class="mobile-link <?php echo $page === 'profile' ? 'active' : ''; ?>">
        <div class="icon-wrapper">
    <i class="bi bi-person"></i>

        </div>
        <span>Profile</span>
    </a>

    <!-- Logout -->
    <a href="../logout.php" class="mobile-link">
        <div class="icon-wrapper">
        <i class="bi bi-box-arrow-right"></i>

        </div>
        <span>Logout</span>
    </a>

</nav>


    <script>
        function showAlert(message, type = 'success') {
    const validTypes = ['success', 'error', 'warning', 'info', 'question'];
    if (!validTypes.includes(type)) type = 'info';

    Swal.fire({
      icon: type,
      title: type === 'success' ? 'Success!' :
             type === 'error' ? 'Error!' :
             type === 'warning' ? 'Warning!' : 'Notice',
      text: message,
      timer: 3000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    });
}

    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>