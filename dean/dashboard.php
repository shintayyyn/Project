<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access - Not logged in");
}

$t_id = $_SESSION['user_id'];

// Fetch teacher info to check if dean
$query = "SELECT t_id, t_fname, t_lname, is_dean FROM teachers WHERE t_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $t_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// Check if teacher exists
if (!$teacher) {
    die("Unauthorized access - Teacher not found");
}

// ✅ Allow if user_type is dean OR teacher has is_dean = 1
if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] !== 'dean' && (int)$teacher['is_dean'] !== 1)) {
    die("Unauthorized access - You do not have permission to access this page");
}

// Set user_type to dean if teacher is a dean
if ((int)$teacher['is_dean'] === 1) {
    $_SESSION['user_type'] = 'dean';
}

// For display purposes
$user_type = ucfirst($_SESSION['user_type']);

// Page routing
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

switch ($page) {
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
    <title>Departmental Dean- <?php echo $title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    <meta name="base-url" content="/Project/dean">
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
        
        .sidebar-header {
            padding: 1.5rem 1.5rem 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 0.5rem;
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


        .btn {
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

        .btn::after {
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

        .btn:active::after {
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
                    <h3>Departmental Dean Panel</h3>
                    <span class="dean-badge">Administrator</span>
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
                    <div class="nav-section-label">Main</div>
                    <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-label">Management</div>
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
                    
                </div>

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
                    echo '<div class="alert alert-danger">Page not found</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function() {
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
        });

      
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

        function editStudent(studentId) {
            // Fix the path to be relative to current directory
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
                    // Update form fields
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
                alert('Error fetching student data: ' + error.message);
            });
        }

        // Add this right after Bootstrap script
        function handleEditStudentSubmit(formId) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const messageContainer = document.getElementById('messageContainer') || document.createElement('div');
                
                try {
                    const response = await fetch('/Project/dean/students/processes/edit_student.php', {
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

        // Initialize form handler
        document.addEventListener('DOMContentLoaded', function() {
            handleEditStudentSubmit('editStudentForm');
            // ...rest of your DOMContentLoaded code...
        });
    </script>
</body>
</html>