<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection with correct path
require_once(__DIR__ . '/../includes/db.php');

// Check if user is logged in and is a parent
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// Get parent information with concatenated full name
$query = "SELECT p_id, CONCAT(p_fname, ' ', IFNULL(p_mname, ''), ' ', p_lname, ' ', IFNULL(p_suffix, '')) AS full_name FROM parents WHERE p_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();

if (!$parent) {
    // Parent not found, force logout or redirect
    header('Location: ../login.php');
    exit();
}

$_SESSION['user_name'] = trim($parent['full_name']);

// Get all children for this parent (with detailed student info)
$children_query = "
    SELECT s.s_id, s.s_fname, s.s_lname, s.s_mname, s.s_suffix, s.s_gender, s.s_bdate, s.s_age, 
           s.s_cnum, s.s_email, s.s_password, s.s_status, sd.degree_code
    FROM students s
    LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
    INNER JOIN parent_student ps ON s.s_id = ps.s_id
    WHERE ps.p_id = ?
    ORDER BY s.s_lname ASC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result();
$children_list = [];
while ($row = $children->fetch_assoc()) {
    $children_list[] = $row;
}

// Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define the content path based on folder structure
switch($page) {
    case 'dashboard':
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
        break;
    case 'schedule':
        $content = 'schedule/index.php';
        $title = 'Children\'s Schedules';
        break;
    case 'profile':
        $content = 'profile/index.php';
        $title = 'My Profile';
        break;
     case 'absentform':
        $content = 'absentform/index.php';
        $title = 'Absent Request';
        break;
    case 'requeststatus':
        $content = 'request_status/index.php';
        $title = 'Request Status';
        break;
    case 'reports':
        $content = 'reports/index.php';
        $title = 'Attendance Reports';
        break;
    default:
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent - <?php echo $title; ?></title>
    
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
        .navbar.mobile{
            position: fixed;
            padding: 1rem;
            background: var(--primary) !important;
        }
        body { margin: 0; padding: 0; background-color: var(--background); }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
            position: fixed;
            top: 0; left: 0; height: 100vh; padding: 1.5rem;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000;
            transition: transform var(--transition-speed) ease; color: white;
        }
        .content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; width: calc(100% - var(--sidebar-width)); background-color: var(--background); position: relative; }
        .sidebar-header { padding-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem; }
        .sidebar-header h3 { color: white; margin: 0; font-size: 1.25rem; font-weight: 600; }
        .parent-badge { display: inline-block; padding: 0.25rem 0.5rem; background: rgba(255,255,255,0.1); border-radius: 1rem; font-size: 0.75rem; margin-top: 0.5rem; }
        .nav-section { margin-bottom: 1.5rem; }
        .nav-section-label { text-transform: uppercase; font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 0.5rem; }
        .nav-section a { display: flex; align-items: center; color: rgba(255,255,255,0.8); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 0.25rem; transition: all 0.2s; }
        .nav-section a:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-section a.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-section a i { margin-right: 0.75rem; font-size: 1.1rem; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; width: 100%; padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .sidebar-footer a { display: flex; align-items: center; color: rgba(255,255,255,0.8); text-decoration: none; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-footer a i { margin-right: 0.75rem; font-size: 1.1rem; }
        .card { background-color: white; border: none; border-radius: var(--card-border-radius); box-shadow: 0 4px 15px rgba(61, 82, 160, 0.1); transition: transform var(--transition-speed), box-shadow var(--transition-speed); margin-bottom: 1.5rem; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(61, 82, 160, 0.15); }
        .card-header { background-color: white; border-bottom: 1px solid rgba(61, 82, 160, 0.1); padding: 1.25rem; border-top-left-radius: var(--card-border-radius) !important; border-top-right-radius: var(--card-border-radius) !important; }
        .card-body { padding: 1.5rem; background-color: white; }
        .card-title { color: var(--primary); font-weight: 600; display: flex; align-items: center; margin-bottom: 0; }
        .card-title i { font-size: 1.1em; margin-right: 0.5rem; color: var(--secondary); }
        .navbar.mobile .nav-link {
            flex: 1;               /* equal space for each */
            min-width: 0;          /* allow shrinking */
            padding: 0.25rem 0;    /* tighter padding */
            }
       /* Hide sidebar on devices smaller than md (768px) */
        @media (max-width: 760px) {
            .sidebar {
                display: none !important; /* completely hide */
            }
            .content {
                margin-left: 0;
                margin-bottom: 60px; /* leave space for bottom navbar */
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
               <h3>
                    <?php echo htmlspecialchars($parent && isset($parent['name']) ? $parent['name'] : 'Parent'); ?>
                </h3>
                <span class="parent-badge">Parent</span>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-label">Main</div>
                <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-label">Child's Whereabout</div>
                <a href="?page=schedule" class="<?php echo $page === 'schedule' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar3"></i>
                    <span>Children's Schedules</span>
                </a>
                <a href="?page=absentform" class="<?php echo $page === 'absentform' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Absent Request</span>
                </a>
                 <a href="?page=requeststatus" class="<?php echo $page === 'requeststatus' ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Request Status</span>
                </a>
            </div>
             <div class="nav-section">
                <div class="nav-section-label">Reports</div>
                <a href="?page=reports" class="<?php echo $page === 'reports' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-line fs-5"></i>
                    <span>Child's Attendance Report</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-label">Account</div>
                <a href="?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
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
    
    <!--Mobile Navigation-->
<nav class="navbar mobile bg-primary navbar-expand d-md-none fixed-bottom">
  <div class="container-fluid justify-content-around px-1">
    <a class="nav-link text-center" href="?page=dashboard" data-bs-toggle="tooltip" data-bs-placement="top" title="Dashboard">
      <i class="bi bi-speedometer2 fs-5 <?php echo $page === 'dashboard' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="?page=schedule" data-bs-toggle="tooltip" data-bs-placement="top" title="Schedules">
      <i class="bi bi-calendar3 fs-5 <?php echo $page === 'schedule' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="?page=absentform" data-bs-toggle="tooltip" data-bs-placement="top" title="Absent Request">
      <i class="bi bi-file-earmark-text fs-5 <?php echo $page === 'absentform' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="?page=requeststatus" data-bs-toggle="tooltip" data-bs-placement="top" title="Request Status">
      <i class="bi bi-clipboard-check fs-5 <?php echo $page === 'requeststatus' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="?page=reports" data-bs-toggle="tooltip" data-bs-placement="top" title="Reports">
      <i class="bi bi-bar-chart-line fs-5 <?php echo $page === 'reports' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="?page=profile" data-bs-toggle="tooltip" data-bs-placement="top" title="Profile">
      <i class="bi bi-person-circle fs-5 <?php echo $page === 'profile' ? 'text-warning' : 'text-white'; ?>"></i>
    </a>
    <a class="nav-link text-center" href="../logout.php" data-bs-toggle="tooltip" data-bs-placement="top" title="Logout">
      <i class="bi bi-box-arrow-right fs-5 text-white"></i>
    </a>
  </div>
</nav>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>