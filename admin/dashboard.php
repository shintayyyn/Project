<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}


require_once __DIR__ . '/../includes/db.php';

// Get user type for display purposes
$user_type = ucfirst($_SESSION['user_type']);

// Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define the content path based on folder structure
switch($page) {
    case 'dashboard':
        $content = 'dashboard/index.php';
        $title = 'Dashboard';
        break;
    case 'students':
        $content = 'students/index.php';
        $title = 'Manage Students';
        break;
    case 'teachers':
        $content = 'teachers/index.php';
        $title = 'Manage Teachers';
        break;
    case 'parents':
        $content = 'parents/index.php';
        $title = 'Manage Parents';
        break;
    case 'sections':
        $content = 'sections/index.php';
        $title = 'Manage Sections';
        break;
    case 'subjects':
        $content = 'subjects/index.php';
        $title = 'Manage Subjects';
        break;
    case 'rooms':
        $content = 'rooms/index.php';
        $title = 'Manage Rooms';
        break;
    case 'schedules':
        $content = 'schedules/index.php';
        $title = 'Manage Schedules';
        break;
    case 'degrees':
        $content = 'degrees/index.php';
        $title = 'Manage Departments';
        break;
    case 'upload_data':
        $content = 'upload_data/index.php';
        $title = 'Upload Data';
        break;
    // case 'generateqr':
    //     $content = 'generateqr/index.php';
    //     $title = 'Generate QR Codes';
    //     break;
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
    <title>Admin - <?php echo $title; ?></title>
          <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
     <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
      <!-- Font Awesome CDN (version 5 or 6) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <meta name="base-url" content="../admin">
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
        body{
            overflow-x: hidden;
            overflow-y: visible;
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
           box-shadow: rgba(0, 0, 0, 0.15) 1.95px 1.95px 2.6px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
         /* Sidebar header centered */
.sidebar-header {
    display: flex;
    flex-direction: column; /* Stack items vertically */
    align-items: center;    /* Center horizontally */
    justify-content: center; /* Center vertically */
    text-align: center;     /* Ensure text inside is centered */
}

/* Avatar circle */
.profile-image2 {
    width: 85px;
    height: 85px;
    border: 4px solid white;
    background: linear-gradient(145deg, var(--secondary) 0%, var(--primary) 100%);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 10px; /* Space below avatar */
}


        
      

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin: 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .sidebar-header .dean-badge {
            background: rgba(255,255,255,0.1);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.9);
            margin-top: 0.5rem;
            display: inline-block;
        }
        
        .nav-section {
            padding: 0.5rem 0;
            margin: 0 0.75rem;
        }

        .nav-section-label {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.75rem 1rem;
        }

        .sidebar a {
            color: rgba(255,255,255,.85);
            text-decoration: none;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            transition: all var(--transition-speed);
            margin: 0.25rem 0;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            opacity: 0;
            z-index: -1;
            transition: opacity var(--transition-speed);
            border-radius: 8px;
        }
        
        .sidebar a:hover::before,
        .sidebar a.active::before {
            opacity: 1;
        }

        .sidebar a:hover, .sidebar a.active {
            color: #fff;
            background: rgba(255,255,255,.15);
            transform: translateX(5px);
        }

        .sidebar a i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            margin-right: 12px;
            transition: all var(--transition-speed);
        }

        .sidebar a:hover i {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-footer a {
            margin: 0;
            color: rgba(255,255,255,0.7);
        }

        .sidebar-footer a:hover {
            transform: translateX(0);
            background: rgba(255,255,255,0.1);
        }

        
 .sidebar .dropdown-toggle {
      background-image: none !important;
    background-repeat: no-repeat !important;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    color: rgba(255,255,255,.85);
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px;
    margin: 0.25rem 0;
    position: relative;
    overflow: hidden;
    z-index: 1;
    transition: all var(--transition-speed);
    cursor: pointer;
}

.sidebar .dropdown-toggle::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    opacity: 0;
    z-index: -1;
    transition: opacity var(--transition-speed);
    border-radius: 8px;
}

.sidebar .dropdown-toggle:hover::before,
.sidebar .dropdown-toggle.active::before {
    opacity: 1;
}
.sidebar .dropdown-toggle::after {
    content: none !important;
    display: none !important;
}


.sidebar .dropdown-toggle:hover,
.sidebar .dropdown-toggle.active {
    color: #fff;
    background: rgba(255,255,255,.15);
    transform: translateX(5px);
}

.sidebar .dropdown-toggle .arrow {
    transition: transform var(--transition-speed);
}

.sidebar .dropdown-toggle.active .arrow {
    transform: rotate(90deg);
}

.sidebar .dropdown-container {
    display: none;
    flex-direction: column;
    padding-left: 1rem;
    padding-right: 0.5rem;
}

.sidebar .dropdown-container a {
    margin-left: 0.5rem;
    margin-top: 0.2rem;
}



        .content {
            margin-left: var(--sidebar-width);
            padding: 25px;
            background-color: var(--background);
            min-height: 100vh;
            max-width: calc(100vw - var(--sidebar-width));
            overflow-x: hidden;
        }
        
        .card {
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 6px rgba(61, 82, 160, 0.07);
            border: none;
            transition: transform var(--transition-speed);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #FFCB05, #FFCB05);
            transform: scaleX(0);
            transition: transform var(--transition-speed);
            transform-origin: left;
        }

        .card:hover::before {
            transform: scaleX(1);
        }
        
        .card-header {
            background: var(--primary);
            color: var(--tertiary);
            border-radius: var(--card-border-radius) var(--card-border-radius) 0 0 !important;
            padding: 1rem 1.5rem;
        }
        .card-body{
            max-height: 100vh;
            overflow-y: visible;
            overflow-x: hidden;
        }
        /* Hide horizontal scrollbar */
.dataTables_wrapper {
    overflow-x: hidden !important;
}


        .btn-primary {
            border-radius: 8px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all var(--transition-speed);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(145deg, var(--secondary) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(61, 82, 160, 0.2);
        }

        .btn-primary::after {
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

        .btn-primary:active::after {
            width: 200px;
            height: 200px;
            opacity: 0;
        }

        .table {
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .table thead th {
            background-color: rgba(61, 82, 160, 0.05);
            font-weight: 600;
            border-bottom: 2px solid var(--quaternary);
        }

        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }

        .table tr {
            transition: all var(--transition-speed);
        }

        .table tbody tr:hover {
            background-color: rgba(61, 82, 160, 0.05);
            transform: scale(1.002);
        }

        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
            transition: all var(--transition-speed);
        }

        .badge:hover {
            transform: scale(1.1);
        }

        .modal-content {
            border-radius: var(--card-border-radius);
            border: none;
        }

        .modal-header {
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 1px solid var(--quaternary);
            transition: all var(--transition-speed);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.25rem rgba(112, 145, 230, 0.25);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--secondary);
        }

        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: loading 1.5s infinite;
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
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h3>Admin Panel</h3>
                  <span class="m-2 badge bg-success rounded-pill">Administrator</span>
                   <?php
            $current_term = $conn->query("SELECT ay.year_start, ay.year_end, t.semester 
                FROM academic_terms t 
                JOIN academic_years ay ON t.ay_id = ay.ay_id 
                WHERE t.is_active = 1
                ORDER BY t.term_id DESC LIMIT 1")->fetch_assoc();
            if ($current_term) {
               echo '<span class="badge rounded-pill bg-warning text-black">' 
                . htmlspecialchars($current_term['year_start']) . ' - ' 
                . htmlspecialchars($current_term['year_end']) . ' | ' 
                . htmlspecialchars($current_term['semester']) . 
            '</span>';

            } else {
                echo "No academic term set.";
            }
            ?>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const dropdowns = document.querySelectorAll('.dropdown-toggle');
                        dropdowns.forEach(toggle => {
                            const dropdown = toggle.nextElementSibling;
                            toggle.addEventListener('click', () => {
                                const isOpen = dropdown.style.display === 'flex';
                                dropdown.style.display = isOpen ? 'none' : 'flex';
                                toggle.classList.toggle('active');
                            });
                        });
                    });
                </script>

                <!-- Main Section -->
                <div class="nav-section">
                    <div class="nav-section-label">Main</div>
                    <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- Manage Users Section -->
                <?php $isUserPage = in_array($page, ['students', 'teachers', 'parents']); ?>
                <div class="nav-section">
                    <div class="nav-section-label">Manage Users</div>
                    <div class="dropdown-toggle <?php echo $isUserPage ? 'active' : ''; ?>">
                        <div class="d-flex align-items-center manageusers">
                            <i class="bi bi-people-fill"></i>
                            <span class="ms-2">Manage Users</span>
                        </div>
                        <i class="bi bi-chevron-right arrow"></i>
                    </div>

                    <div class="dropdown-container" style="display: <?php echo $isUserPage ? 'flex' : 'none'; ?>;">
                        <a href="?page=students" class="<?php echo $page === 'students' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i>
                            <span>Students</span>
                        </a>
                        <a href="?page=teachers" class="<?php echo $page === 'teachers' ? 'active' : ''; ?>">
                            <i class="bi bi-person-workspace"></i>
                            <span>Teachers</span>
                        </a>
                        <a href="?page=parents" class="<?php echo $page === 'parents' ? 'active' : ''; ?>">
                            <i class="bi bi-person"></i>
                            <span>Parents</span>
                        </a>
                        <!-- <a href="?page=generateqr" class="<?php echo $page === 'generateqr' ? 'active' : ''; ?>">
                        <i class="qr"></i>
                        <span>Generate QR</span>
                    </a> -->
                    </div>
                </div>

                <!-- Other Management Section -->
                <div class="nav-section">
                    <div class="nav-section-label">Other Management</div>
                    <a href="?page=sections" class="<?php echo $page === 'sections' ? 'active' : ''; ?>">
                        <i class="bi bi-collection"></i>
                        <span>Sections</span>
                    </a>
                    <a href="?page=subjects" class="<?php echo $page === 'subjects' ? 'active' : ''; ?>">
                        <i class="bi bi-book"></i>
                        <span>Subjects</span>
                    </a>
                    <a href="?page=rooms" class="<?php echo $page === 'rooms' ? 'active' : ''; ?>">
                        <i class="bi bi-building"></i>
                        <span>Rooms</span>
                    </a>
                    <a href="?page=schedules" class="<?php echo $page === 'schedules' ? 'active' : ''; ?>">
                        <i class="bi bi-calendar3"></i>
                        <span>Schedules</span>
                    </a>
                    <a href="?page=degrees" class="<?php echo $page === 'degrees' ? 'active' : ''; ?>">
                        <i class="bi bi-mortarboard"></i>
                        <span>Degrees</span>
                    </a>
                    <a href="?page=upload_data" class="<?php echo $page === 'upload_data' ? 'active' : ''; ?>">
                        <i class="bi bi-upload"></i>
                        <span>Upload Data</span>
                    </a>
                     <!-- <a href="?page=archives" class="<?php echo $page === 'archive' ? 'active' : ''; ?>">
                        <i class="bi bi-upload"></i>
                        <span>Archives</span>
                    </a> -->
                </div>

                <!-- Other Management Section -->
                <div class="nav-section">
                    <div class="nav-section-label">Attendance Reports</div>
                    <a href="?page=reports" class="<?php echo $page === 'reports' ? 'active' : ''; ?>">
                         <i class="bi bi-file-earmark-text me-2"></i>
                        <span>Reports</span>
                    </a>
                </div>

                <!-- Footer -->
                <div class="sidebar-footer">
                    <a href="logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="content">
                <?php
                if (file_exists($content)) {
                    include $content;
                } else {
                    echo '<div class="showAlert showAlert-danger">Page not found</div>';
                }
                ?>
            </div>
        </div>
    </div>


     <!-- SweetAlert2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <script>
    function showAlert(type, message) {
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
            icon = 'warning';
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

document.addEventListener('DOMContentLoaded', function () {
    // Add loading state to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!this.classList.contains('no-loading')) {
                this.classList.add('loading');
                setTimeout(() => this.classList.remove('loading'), 1000);
            }
        });
    });

    // Add active state to current sidebar link
    const currentPage = '<?php echo $page; ?>';
    document.querySelector(`a[href="?page=${currentPage}"]`)?.classList.add('active');

    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const ripple = document.createElement('span');
        const rect = button.getBoundingClientRect();

        ripple.style.width = ripple.style.height = '100px';
        ripple.style.left = `${event.clientX - rect.left}px`;
        ripple.style.top = `${event.clientY - rect.top}px`;
        ripple.classList.add('ripple');

        button.appendChild(ripple);
        setTimeout(() => ripple.remove(), 1000);
    }

    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', createRipple);
    });

    // Toggle dropdowns
document.addEventListener('DOMContentLoaded', function () {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const isActive = toggle.classList.contains('active');
            const dropdown = toggle.nextElementSibling;
            const arrow = toggle.querySelector('.arrow');

            // Close all others
            document.querySelectorAll('.dropdown-toggle').forEach(otherToggle => {
                if (otherToggle !== toggle) {
                    otherToggle.classList.remove('active');
                    const otherArrow = otherToggle.querySelector('.arrow');
                    if (otherArrow) otherArrow.classList.remove('rotate');

                    const otherDropdown = otherToggle.nextElementSibling;
                    if (otherDropdown && otherDropdown.classList.contains('dropdown-container')) {
                        otherDropdown.style.display = 'none';
                    }
                }
            });

            // Toggle current
            if (!isActive) {
                toggle.classList.add('active');
                if (arrow) arrow.classList.add('rotate');
                if (dropdown && dropdown.classList.contains('dropdown-container')) {
                    dropdown.style.display = 'flex';
                }
            } else {
                toggle.classList.remove('active');
                if (arrow) arrow.classList.remove('rotate');
                if (dropdown && dropdown.classList.contains('dropdown-container')) {
                    dropdown.style.display = 'none';
                }
            }
        });
    });
});
});

// Fetch and show student info in modal
function editStudent(studentId) {
    fetch(`students/processes/get_student.php?id=${studentId}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text || 'Network response was not ok');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.student) {
            const student = data.student;
            document.getElementById('edit_s_id').value = student.s_id;
            document.getElementById('edit_s_fname').value = student.s_fname;
            document.getElementById('edit_s_lname').value = student.s_lname;
            document.getElementById('edit_s_mname').value = student.s_mname || '';
            document.getElementById('edit_s_suffix').value = student.s_suffix || '';
            document.getElementById('edit_s_gender').value = student.s_gender;
            document.getElementById('edit_s_bdate').value = student.s_bdate;
            document.getElementById('edit_s_cnum').value = student.s_cnum;
            document.getElementById('edit_s_email').value = student.s_email;
            document.getElementById('edit_s_status').value = student.s_status;

            const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            editModal.show();
        } else {
            throw new Error(data.message || 'Failed to fetch student data');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error fetching student data: ' + error.message);
    });
}

// Handle form submit to update student info
function handleEditStudentSubmit(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const messageContainer = document.getElementById('messageContainer') || document.createElement('div');

        try {
            const response = await fetch('/Project/admin/students/processes/edit_student.php', {
                method: 'POST',
                body: new FormData(this)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                showAlert('success', 'Student updated successfully!');
                location.reload();
            } else {
                throw new Error(data.message || 'Update failed');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'Error updating student: ' + error.message);
        }
    });
}
</script>

</body>
</html>