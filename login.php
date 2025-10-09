<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendify Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

  <style>
    :root {
      --primary: #033A70;
      --accent: #FFCB05;
      --background-light: #EDF8FD;
      --border-light: #D0EEFC;
      --hover-blue: #3E7DCA;
    }

    body {
      margin: 0;
      padding: 0;
      background: var(--primary);
      font-family: Arial, sans-serif;
      overflow-x: hidden;
    }

    /* Layout Container */
    .login-container {
      display: flex;
      min-height: 100vh;
      width: 100%;
      overflow: hidden;
    }

    /* Welcome Section */
    .welcome-section {
      flex: 0 0 50%;
      background: var(--primary);
      color: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      text-align: center;
    }

    .welcome-section img.logo {
      max-width: 200px;
      margin-bottom: 1.5rem;
      object-fit: contain;
    }

    .welcome-section h1 {
      font-size: 2rem;
      font-weight: bold;
      margin-bottom: 0.5rem;
    }

    .welcome-section p {
      opacity: 0.9;
    }

    /* Login Section */
    .login-section {
      flex: 0 0 50%;
      background: var(--background-light);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
    }

    /* Login Card */
    .login-card {
      width: 100%;
      max-width: 450px;
      background: white;
      border-radius: 1rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      overflow: hidden;
      animation: slideUp 0.4s ease-out;
    }

    @keyframes slideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .login-header {
      background: var(--primary);
      color: white;
      text-align: center;
      padding: 1rem;
    }

    /* Form Styling */
    .login-form {
      padding: 2rem;
      background: var(--background-light);
    }

    .input-group {
      position: relative;
      margin-bottom: 1.2rem;
    }

    .input-icon {
      position: absolute;
      top: 50%;
      left: 15px;
      transform: translateY(-50%);
      color: var(--primary);
    }

    .form-control {
      padding: 12px 15px 12px 45px;
      border-radius: 8px;
      border: 1px solid var(--border-light);
      background: white;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 5px rgba(3,58,112,0.2);
    }

    /* Password Toggle */
    .toggle-password {
      position: absolute;
      top: 50%;
      right: 15px;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--hover-blue);
    }

    /* Buttons */
    .btn-login {
      background: linear-gradient(135deg, var(--primary), var(--hover-blue));
      color: white;
      border: none;
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      transition: 0.3s;
    }

    .btn-login:hover {
      background: var(--hover-blue);
    }

    /* Forgot Password */
    .forgot-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      font-size: 0.9rem;
      color: var(--primary);
      text-decoration: none;
    }

    .forgot-link:hover {
      text-decoration: underline;
    }

    /* Responsive Layout */
    @media (max-width: 992px) {
      .login-container {
        flex-direction: column;
      }
      .welcome-section, .login-section {
        flex: 0 0 100%;
        width: 100%;
      }
      .welcome-section img.logo {
        max-width: 150px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Welcome Panel -->
    <div class="welcome-section">
      <img src="assets/img/attendifylogo.png" alt="Attendify Logo" class="logo">
      <h1>Welcome to Attendify</h1>
      <p>Your Gateway to Academic Excellence</p>
    </div>

    <!-- Login Panel -->
    <div class="login-section">
      <div class="login-card">
        <div class="login-header">
          <h4>Academic Portal Login</h4>
        </div>
        <form action="process_login.php" method="POST" class="login-form">
          <!-- ID Field -->
          <div class="input-group">
            <i class="material-icons input-icon">badge</i>
            <input type="text" name="login_id" class="form-control" placeholder="Enter your ID or Email" required>
          </div>

          <!-- Password Field -->
          <div class="input-group">
            <i class="material-icons input-icon">lock</i>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" id="password" required>
            <span class="material-icons toggle-password" onclick="togglePassword()">visibility_off</span>
          </div>

          <span class="text-danger" id="login-error"></span>
          <button type="submit" class="btn btn-login mt-2">Login</button>
          <a href="forgot_password.php" class="forgot-link">Can't access your account? Forgot Password</a>
        </form>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Password Toggle Logic
    function togglePassword() {
      const field = document.getElementById("password");
      const icon = document.querySelector(".toggle-password");
      if (field.type === "password") {
        field.type = "text";
        icon.textContent = "visibility";
      } else {
        field.type = "password";
        icon.textContent = "visibility_off";
      }
    }

    // Login Form Submit
    document.querySelector('.login-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const btn = this.querySelector('.btn-login');
      const errorSpan = document.getElementById('login-error');
      errorSpan.textContent = '';

      const formData = new FormData(this);

      fetch(this.action, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(text => {
        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          throw new Error('Invalid JSON response');
        }

        if (data.choose_role) {
          var roleModal = new bootstrap.Modal(document.getElementById('roleChoiceModal'));
          roleModal.show();
          window._deanRedirect = data.dean_redirect || 'dean/dashboard.php';
          window._teacherRedirect = data.teacher_redirect || 'teacher/dashboard.php';
          return;
        }

        if (data.error) {
          errorSpan.textContent = data.error;
        } else if (data.success) {
          window.location.href = data.redirect;
        }
      })
      .catch(() => {
        errorSpan.textContent = 'An error occurred. Please try again.';
      });
    });

    // Role Selection Actions
    document.getElementById('chooseDean').addEventListener('click', function() {
      fetch('set_role.php', {
        method: 'POST',
        body: new URLSearchParams({ role: 'dean' })
      }).then(() => {
        window.location.href = window._deanRedirect;
      });
    });

    document.getElementById('chooseTeacher').addEventListener('click', function() {
      fetch('set_role.php', {
        method: 'POST',
        body: new URLSearchParams({ role: 'teacher' })
      }).then(() => {
        window.location.href = window._teacherRedirect;
      });
    });
  </script>
</body>
</html>