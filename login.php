<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendify Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
  
  <style>
    *{
            font-family: 'Baloo 2', 'Nunito', 'Poppins', sans-serif;
        }
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

    .login-container {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }


    .welcome-section {
      flex: 0 0 50%;
      background: var(--primary);
      color: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      text-align: center;
      cursor: none;
    }

   .welcome-section img.logo {
    max-width: 350px;       /* keeps it responsive */
    height: auto;           /* maintain aspect ratio */
    object-fit: contain;    /* preserves image quality and aspect ratio */
    display: block;         /* removes inline spacing */
}


    .welcome-section h1 {
      font-size: 2rem;
      font-weight: bold;
    }

    .welcome-section p {
      opacity: 0.9;
    }

    .login-section {
      flex: 0 0 50%;
      background: var(--background-light);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
    }

  .login-card {
    max-width: 450px;
    width: 100%;
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    overflow: hidden;
    animation: slideUp 0.4s ease-out;
}

/* Enlarge login card on small screens */
@media (max-width: 768px) {
    .login-card {
        max-width: 100%; /* almost full width */
        margin: 1rem auto; /* center it */
        padding: -10rem; /* add extra padding for usability */
        border-radius: 1.5rem; /* slightly rounder */
    }
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

    .login-form {
      padding: 2rem;
      background: var(--background-light);
    }

    .input-group {
      position: relative;
      margin-bottom: 1.2rem;
    }

    .input-icon {
     background: linear-gradient(135deg, var(--primary), var(--hover-blue));
      color: white;
      font-size:20px;
    }

    .form-control {
      padding: 12px 15px 12px 15px;
      border-radius: 8px;
      border: 1px solid var(--border-light);
      background: white;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 5px rgba(3,58,112,0.2);
    }

    .toggle-password {
       position: absolute;
  right: 10px; /* distance from the right edge */
  top: 50%;
 transform: translateY(-50%);
      cursor: pointer;
      color: var(--hover-blue);
  z-index: 10;
  user-select: none;
    }

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
     color: white;
      opacity: 0.9;
    }

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

    @media (max-width: 992px) {
      .login-container {
        flex-direction: column;
      }
      .welcome-section, .login-section {
        flex: 0 0 100%;
        width: 100%;
      }
    }
    body::-webkit-scrollbar{
    display: none;
}
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
      <img src="assets/img/attendifylogo.png" alt="Attendify Logo" class="logo">
      <h1>Welcome to Attendify</h1>
      <p>Your Gateway to Academic Excellence</p>
    </div>

    <!-- Login Section -->
    <div class="login-section">
      <div class="login-card">
        <div class="login-header">
          <h4>Academic Portal Login</h4>
        </div>
        <form action="process_login.php" method="POST" class="login-form">
         <div class="input-group">
          <span class="input-group-text input-icon"><i class="bi bi-person-fill"></i></span>
          <input type="text" name="login_id" class="form-control" placeholder="ID or Email" required>
          </div>

         <div class="input-group position-relative">
  <span class="input-group-text input-icon"><i class="bi bi-shield-lock-fill"></i></span>
  <input type="password" name="password" class="form-control pe-5" placeholder="Password" id="password" required>
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

    // ✅ Generate a strong persistent device token
    function getDeviceToken() {
      let token = localStorage.getItem("device_token");
      if (!token) {
        const fingerprint = navigator.userAgent + navigator.platform + screen.width + screen.height;
        const base = btoa(fingerprint).substring(0, 20);
        const uniquePart = crypto.randomUUID().slice(0, 12);
        token = base + "-" + uniquePart;
        localStorage.setItem("device_token", token);
      }
      return token;
    }

    // Inject token before submit
    document.querySelector(".login-form").addEventListener("submit", function(e) {
      e.preventDefault();
      const token = getDeviceToken();
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "device_token";
      hidden.value = token;
      this.appendChild(hidden);

      const btn = this.querySelector(".btn-login");
      const errorSpan = document.getElementById("login-error");
      errorSpan.textContent = "";

      const formData = new FormData(this);
      btn.disabled = true;

      fetch(this.action, { method: "POST", body: formData })
        .then(res => res.text())
        .then(text => {
          let data;
          try { data = JSON.parse(text); }
          catch { throw new Error("Invalid JSON"); }

          if (data.choose_role) {
            window.location.href = data.teacher_redirect;
            return;
          }

          if (data.error) {
            errorSpan.textContent = data.error;
          } else if (data.success) {
            window.location.href = data.redirect;
          }
        })
        .catch(() => errorSpan.textContent = "An error occurred. Please try again.")
        .finally(() => btn.disabled = false);
    });
  </script>
</body>
</html>
