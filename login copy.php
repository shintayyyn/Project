<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Portal Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            background: #033A70;
        }
        
        .login-container {
            display: flex;
            min-height: 100vh;
            width: 100vw;
            margin: 0;
            padding: 0;
        }
        
        .welcome-section {
            flex: 0 0 50%;
            width: 50%;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: #033A70;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            z-index: 1;
        }
        
        .login-section {
            flex: 0 0 50%;
            width: 50%;
            padding: 4rem;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #EDF8FD;
        }
        
        .school-logo {
            font-size: 250px;
            color: #FFCB05;
            margin-bottom: 3rem;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .login-card {
            width: 100%;
            max-width: 450px;
            background: #FFCB05 !important;
            box-shadow: rgba(0, 0, 0, 0.15) 1.95px 1.95px 2.6px;
            border-radius: 20px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        .role-selector{
            background: #EDF8FD;
            border-bottom: rgba(20, 8, 84, 0.15)  .05px solid;
        }
        .login-header{
            background: #033A70;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .nav-pills .nav-link {
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.3s ease;
            color: #033A70;
        }

        .nav-pills .nav-link.active {
            background: #D0EEFC;
            color: #033A70;
            box-shadow: 0 4px 15px rgba(61, 82, 160, 0.2);
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px 12px 45px;
            border: 1px solid #D0EEFC;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #033A70;
            box-shadow: 0 0 0 3px rgba(61, 82, 160, 0.1);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #033A70;
            z-index: 10;
        }

        .btn-login {
            background: linear-gradient(135deg, #033A70 0%, #3E7DCA 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 15px rgba(61, 82, 160, 0.2);
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
           box-shadow: rgba(0, 0, 0, 0.15) 1.95px 1.95px 2.6px;
        }

        .features-list {
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .features-list li {
            margin: 10px 0;
            display: flex;
            align-items: center;
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .features-list li i {
            margin-right: 10px;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            .welcome-section, .login-section {
                flex: 0 0 100%;
                width: 100%;
                padding: 2rem;
            }
            .school-logo {
                font-size: 180px;
                margin-bottom: 2rem;
            }
        }
        
        .tab-content {
            background: #EDF8FD;
            border-radius: 0 0 15px 15px;
        }
        
        .login-card {
            width: 100%;
            max-width: 450px;
            margin: 0;
            background: #D0EEFC !important;
            box-shadow: rgba(0, 0, 0, 0.15) 1.95px 1.95px 2.6px;
        }

        .signup-link {
            color: #033A70;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link:hover {
            color: #033A70;
            text-decoration: underline;
        }

        .modal-header {
            background: #FFCB05 !important;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="welcome-section">
            <span class="material-icons school-logo">account_balance</span>
            <h1>Welcome to ATTENDIFY</h1>
            <div class="welcome-text">
                <h4>Your Gateway to Academic Excellence</h4>
                <p class="mt-3">Access your academic resources, manage your courses,<br>and connect with your academic community.</p>
            </div>
            <div class="school-info mt-5">
                <h5>Attendify : Automated Attendance Tracker  </h5>
                <p>7XJ7+68Q Peninsula Place, Basak-Sudtunggan Rd<br>Cordova, Cebu, 6017</p>
            </div>
            <div class="features-list">
                <ul class="list-unstyled">
                    <li style="animation-delay: 0.3s"><i class="material-icons">check_circle</i> Connect with Teachers</li>
                    <li style="animation-delay: 0.4s"><i class="material-icons">check_circle</i> View Schedules</li>
                </ul>
            </div>
        </div>
        
        <div class="login-section">
            <div class="login-card">
                <div class="login-header">
                    <h4 class="mb-0">Academic Portal Login</h4>
                </div>
                
                <div class="role-selector">
                    <ul class="nav nav-pills nav-fill mb-3" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#student">Student</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#teacher">Teacher</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#parent">Parent</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#admin">Admin</button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content p-4">
                    <div class="tab-pane fade show active" id="student">
                        <form action="process_login.php" method="POST" class="login-form">
                            <input type="hidden" name="role" value="student">
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">email</i>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">lock</i>
                                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                <span class="text-danger" id="student-error"></span>
                            </div>
                            <button type="submit" class="btn btn-login">Login as Student</button>
                        </form>
                        <div class="text-center mt-3">
                            <span>Can’t access your account? </span>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" class="signup-link">Forgot Password</a>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="teacher">
                        <form action="process_login.php" method="POST" class="login-form">
                            <input type="hidden" name="role" value="teacher">
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">email</i>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">lock</i>
                                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                <span class="text-danger" id="teacher-error"></span>
                            </div>
                            <button type="submit" class="btn btn-login">Login as Teacher</button>
                        </form>
                        <div class="text-center mt-3">
                            <span>Don't have an account? </span>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" class="signup-link">Sign up</a>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="parent">
    <form action="process_login.php" method="POST" class="login-form">
        <input type="hidden" name="role" value="parent">
        <div class="mb-3">
            <div class="input-group">
                <i class="material-icons input-icon">email</i>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
        </div>
        <div class="mb-3">
            <div class="input-group">
                <i class="material-icons input-icon">lock</i>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            <span class="text-danger" id="parent-error"></span>
        </div>
        <button type="submit" class="btn btn-login">Login as Parent</button>
    </form>
    <div class="text-center mt-3">
        <span>Don't have an account? </span>
        <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" class="signup-link">Sign up</a>
    </div>
</div>

                    <div class="tab-pane fade" id="admin">
                        <form action="admin/process_login.php" method="POST" class="login-form">
                            <input type="hidden" name="role" value="admin">
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">admin_panel_settings</i>
                                    <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <i class="material-icons input-icon">lock</i>
                                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                </div>
                                <span class="text-danger" id="admin-error"></span>
                            </div>
                            <button type="submit" class="btn btn-login">Login as Admin</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Role Choice Modal -->
<div class="modal fade" id="roleChoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #3d52a0 0%, #7091e6 100%); color: white;">
        <h5 class="modal-title">Choose Role</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p>You are both a Teacher and a Dean. How do you want to proceed?</p>
        <button type="button" class="btn btn-primary m-2" id="chooseDean">Dean Dashboard</button>
        <button type="button" class="btn btn-secondary m-2" id="chooseTeacher">Teacher Dashboard</button>
      </div>
    </div>
  </div>
</div>
    </div>

    <!-- Signup Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary); color: white;">
                    <h5 class="modal-title">Sign Up</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-pills nav-fill mb-3" id="signupTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="student-tab" data-bs-toggle="pill" data-bs-target="#studentSignup" type="button" role="tab">Student</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="teacher-tab" data-bs-toggle="pill" data-bs-target="#teacherSignup" type="button" role="tab">Teacher</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parent-tab" data-bs-toggle="pill" data-bs-target="#parentSignup" type="button" role="tab">Parent</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="signupTabContent">
                        <!-- Student Signup Form -->
                        <div class="tab-pane fade show active" id="studentSignup" role="tabpanel">
                            <form action="process_signup.php" method="POST" class="signup-form">
                                <input type="hidden" name="role" value="student">
                                <!-- Name Fields -->
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="s_fname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="s_lname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" name="s_mname" class="form-control name-input" pattern="[A-Za-z\-\s]*">
                                    </div>
                                </div>
                                <!-- Other Student Fields -->
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Suffix</label>
                                        <input type="text" name="s_suffix" class="form-control name-input" pattern="[A-Za-z\-\s\.]*">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select name="s_gender" class="form-select" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" name="s_bdate" class="form-control" required>
                                    </div>
                                </div>
                                <!-- Contact Details -->
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="s_cnum" class="form-control" pattern="[0-9]{11}" maxlength="11" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="s_email" class="form-control" required>
                                </div>
                                <!-- Degree Program -->
                                <div class="mb-3">
                                    <label class="form-label">Degree Program</label>
                                    <select name="s_degree" class="form-select" required>
                                        <option value="">Select Degree Program</option>
                                        <?php
                                        require_once 'includes/db.php';
                                        $sql = "SELECT degree_id, degree_code, degree_name FROM degrees ORDER BY degree_name";
                                        $result = $conn->query($sql);
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<option value='" . $row['degree_id'] . "'>" . $row['degree_code'] . " - " . $row['degree_name'] . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Password Fields -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="s_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-login w-100">Create Student Account</button>
                            </form>
                        </div>

                        <!-- Teacher Signup Form -->
                        <div class="tab-pane fade" id="teacherSignup" role="tabpanel">
                            <form action="process_signup.php" method="POST" class="signup-form">
                                <input type="hidden" name="role" value="teacher">
                                <!-- Name Fields -->
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="t_fname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="t_lname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" name="t_mname" class="form-control name-input" pattern="[A-Za-z\-\s]*">
                                    </div>
                                </div>
                                <!-- Other Teacher Fields -->
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Suffix</label>
                                        <input type="text" name="t_suffix" class="form-control name-input" pattern="[A-Za-z\-\s\.]*">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Gender</label>
                                        <select name="t_gender" class="form-select" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" name="t_bdate" class="form-control" required>
                                    </div>
                                </div>
                                <!-- Department -->
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" name="t_department" class="form-control" required>
                                </div>
                                <!-- Contact Details -->
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="t_cnum" class="form-control" pattern="[0-9]{11}" maxlength="11" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="t_email" class="form-control" required>
                                </div>
                                <!-- Password Fields -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="t_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-login w-100">Create Teacher Account</button>
                            </form>
                        </div>
<!-- Parent Signup Form -->
<div class="tab-pane fade" id="parentSignup" role="tabpanel">
    <form action="process_signup.php" method="POST" class="signup-form">
        <input type="hidden" name="role" value="parent">
        <!-- Name Fields -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="p_fname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="p_lname" class="form-control name-input" pattern="[A-Za-z\-\s]+" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="p_mname" class="form-control name-input" pattern="[A-Za-z\-\s]*">
            </div>
        </div>
        <!-- Other Parent Fields -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Suffix</label>
                <input type="text" name="p_suffix" class="form-control name-input" pattern="[A-Za-z\-\s\.]*">
            </div>
            <div class="col-md-9 mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" required>
            </div>
        </div>
        <!-- Contact Details -->
        <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="tel" name="phone" class="form-control" pattern="[0-9]{11}" maxlength="11" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <!-- Password Fields -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
        </div>
        <button type="submit" class="btn btn-login w-100">Create Parent Account</button>
    </form>
</div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.login-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-login');
            const errorSpan = this.querySelector('.text-danger');
            
            errorSpan.textContent = '';
            btn.classList.add('loading');
            btn.disabled = true;

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log('Server response:', text); // Debug line
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Parse error:', e);
                    throw new Error('Invalid JSON response');
                }

                // --- Dean/Teacher Choice Logic ---
                if (data.choose_role) {
                    // Show modal for dean/teacher choice
                    var roleModal = new bootstrap.Modal(document.getElementById('roleChoiceModal'));
                    roleModal.show();
                    // Store redirect URLs for later
                    window._deanRedirect = data.dean_redirect || 'dean/dashboard.php';
                    window._teacherRedirect = data.teacher_redirect || 'teacher/dashboard.php';
                    return;
                }
                // --- End Dean/Teacher Choice Logic ---

                if (data.error) {
                    errorSpan.textContent = data.error;
                } else if (data.success) {
                    window.location.href = data.redirect;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorSpan.textContent = 'An error occurred. Please try again.';
            })
            .finally(() => {
                btn.classList.remove('loading');
                btn.disabled = false;
            });
        });
    });

    // Handle role choice buttons
    document.getElementById('chooseDean')?.addEventListener('click', function() {
        window.location.href = window._deanRedirect || 'dean/dashboard.php';
    });
    document.getElementById('chooseTeacher')?.addEventListener('click', function() {
        window.location.href = window._teacherRedirect || 'teacher/dashboard.php';
    });

    document.querySelectorAll('.signup-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('process_signup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.error) {
                        alert(data.error);
                    } else if (data.success) {
                        alert(data.success);
                        location.reload();
                    }
                } catch (e) {
                    console.error('Server response:', text);
                    alert('An error occurred. Please check the console for details.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
</script>
</body>
</html>
