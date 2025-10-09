<?php
session_start();
require_once('../includes/db.php');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$t_id = $_SESSION['user_id'];

// Fetch teacher info including is_dean
$query = "SELECT t_id, t_fname, t_lname, is_dean FROM teachers WHERE t_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $t_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    header('Location: ../login.php');
    exit();
}

// Default role is teacher
$_SESSION['user_type'] = 'teacher';
$_SESSION['is_dean'] = (int)$teacher['is_dean'];

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    $term_id = null;
}

// Advisory class check with term_id
$is_adviser = false;
$advisory_sections = [];

if ($term_id) {
    $advisor_query = "SELECT section_code 
                      FROM sections_advisors 
                      WHERE t_id = ? AND term_id = ?";
    $stmt = $conn->prepare($advisor_query);
    $stmt->bind_param("ii", $t_id, $term_id);
    $stmt->execute();
    $advisor_result = $stmt->get_result();

    $is_adviser = $advisor_result->num_rows > 0;

    while ($row = $advisor_result->fetch_assoc()) {
        $advisory_sections[] = $row['section_code'];
    }
}

// Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
switch ($page) {
    case 'dashboard':
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
        break;
    case 'schedule':
        $content = 'schedule/index.php';
        $title = 'My Schedule';
        break;
    case 'subjects':
        $content = 'subjects/index.php';
        $title = 'My Subjects';
        break;
    case 'approve_absent':
        $content = 'approve_absent_student/index.php';
        $title = 'Approve Absent Students';
        break;
    case 'sections':
        $content = 'sections/index.php';
        $title = 'Class Sections';
        break;
    case 'advisory_class':
        $content = 'advisory_class/index.php';
        $title = 'Advisory Class';
        break;
    case 'reports':
        $content = 'reports/index.php';
        $title = 'Attendance Reports';
        break;
    case 'profile':
        $content = 'profile/index.php';
        $title = 'Profile';
        break;
    case 'attendance':
        $content = 'reports/index.php';
        $title = 'Attendance';
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
    <title>Teacher - <?php echo $title; ?></title>
    
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
        
            .btn-warning {
      background: linear-gradient(135deg, var(--primary), var(--hover-blue));
      color: white;
      border: none;
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      transition: 0.3s;
    }

    .btn-warning:hover {
      background: var(--hover-blue);
    }


        .btn-warning::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-warning:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding-top: 1rem;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: transform var(--transition-speed) ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .teacher-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .nav-section {
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .nav-section-label {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .nav-section a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .nav-section a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-section a.active {
            background: rgba(255,255,255,0.2);
            font-weight: 500;
        }
        
        .nav-section a i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            background-color: var(--background);
            transition: margin-left var(--transition-speed) ease;
        }

        .content .container-fluid {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        .content .row {
            margin-right: -15px;
            margin-left: -15px;
            display: flex;
            flex-wrap: wrap;
        }

        /* Card styles */
        .card {
            margin-bottom: 1.5rem;
            border: none;
            border-radius: var(--card-border-radius);
            background: white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        /* Dashboard specific fixes */
        [data-page="dashboard"] .container-fluid {
            width: calc(100% - 30px);
            max-width: 100%;
            overflow-x: hidden;
        }

        [data-page="dashboard"] .row {
            margin-right: -10px;
            margin-left: -10px;
            width: 100%;
        }

        [data-page="dashboard"] .col-md-4,
        [data-page="dashboard"] .col-lg-3,
        [data-page="dashboard"] .col-xl-3 {
            padding-right: 10px;
            padding-left: 10px;
            position: relative;
            width: 100%;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .content.sidebar-shown {
                margin-left: var(--sidebar-width);
            }

            [data-page="dashboard"] .container-fluid {
                width: calc(100% - 20px);
            }

            [data-page="dashboard"] .row {
                margin-right: -10px;
                margin-left: -10px;
            }

            [data-page="dashboard"] .col-md-4,
            [data-page="dashboard"] .col-lg-3,
            [data-page="dashboard"] .col-xl-3 {
                padding-right: 5px;
                padding-left: 5px;
            }
        }
        
      

        .sidebar-footer a:hover {
            opacity: 1;
        }
        
        .sidebar-footer i {
            margin-right: 0.5rem;
        }
        
        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                max-width: 100vw;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
        /* Custom Scrollbar Styles */
::-webkit-scrollbar {
    width: 8px;
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(108, 117, 125, 0.5);
    border-radius: 10px;
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(108, 117, 125, 0.8);
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.4);
    border-radius: 10px;
}

/* For Firefox */
* {
    scrollbar-width: thin;
    scrollbar-color: rgba(108, 117, 125, 0.5) rgba(255, 255, 255, 0.4);
}
    </style>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
    <h3><?php echo htmlspecialchars($teacher['t_fname'] . ' ' . $teacher['t_lname']); ?></h3>
    <div class="teacher-badge">
        Teacher
        <?php if (!empty($advisory_sections)) : ?>
            <span class="badge bg-success ms-2">Adviser</span>
        <?php endif; ?>
    </div>

    <!-- ✅ Switch Panel only visible if is_dean = 1 -->
    <?php if (!empty($teacher['is_dean']) && (int)$teacher['is_dean'] === 1): ?>
        <div class="mt-2 justify-content-center">
            <button id="switchDashboardBtn" class="btn bg-warning w-auto">
                <i class="fa-solid fa-repeat"></i> Switch Panel
            </button>
        </div>
    <?php endif; ?>
</div>


                <div class="nav-section">
                    <div class="nav-section-label">Navigation</div>
                    <a href="?page=dashboard" class="<?php echo ($page === 'dashboard') ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                    <a href="?page=schedule" class="<?php echo ($page === 'schedule') ? 'active' : ''; ?>">
                        <i class="bi bi-calendar3"></i>
                        My Schedule
                    </a>
                    <a href="?page=subjects" class="<?php echo ($page === 'subjects') ? 'active' : ''; ?>">
                        <i class="bi bi-book"></i>
                        My Subjects
                    </a>
                    <a href="?page=sections" class="<?php echo ($page === 'sections') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        Sections
                    </a>
                    <a href="?page=approve_absent" class="<?php echo ($page === 'approve_absent') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        Approve Excuses
                    </a>
                    <?php if ($is_adviser): ?>
                        <a href="?page=advisory_class" class="<?php echo ($page === 'advisory_class') ? 'active' : ''; ?>">
                            <i class="bi bi-person-lines-fill"></i>
                            My Advisory Class
                        </a>
                    <?php endif; ?>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Account</div>
                    <a href="?page=profile" class="<?php echo ($page === 'profile') ? 'active' : ''; ?>">
                        <i class="bi bi-person"></i>
                        Profile
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-label">Reports</div>
                     <a href="?page=attendance" class="<?php echo ($page === 'attendance') ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        Attendance
                    </a>
                   <hr class="bg-white">
                     <div class="sidebar-footer">
                    <a href="../logout.php">
                        <i class="bi bi-box-arrow-left"></i>
                        Logout
                    </a>
                </div>
                </div>
            </div>
            <!-- Content -->
            <div class="content">
                <?php include $content; ?>
            </div>
        </div>
    </div>
    <script>
  document.getElementById('switchDashboardBtn')?.addEventListener('click', function () {
    const isDean = <?php echo json_encode($_SESSION['is_dean'] ?? false); ?>;

    const teacherDashboard = "/teacher/dashboard.php";
    const deanDashboard = "/dean/dashboard.php";
    const currentPath = window.location.pathname;

    if (!isDean) {
        // If not a dean, they should only ever see teacher dashboard
        window.location.href = teacherDashboard;
        return;
    }

    // Toggle based on current location
    if (currentPath.startsWith("/teacher/")) {
        // Currently in teacher dashboard → go to dean
        window.location.href = deanDashboard;
    } else if (currentPath.startsWith("/dean/")) {
        // Currently in dean dashboard → go to teacher
        window.location.href = teacherDashboard;
    } else {
        // If lost somewhere else, default to teacher dashboard
        window.location.href = teacherDashboard;
    }
});
</script>

</body>
</html>