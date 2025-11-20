<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Attendify</title>
   <link rel="icon" type="image/png" href="includes/logo.png">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;700&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
      background:var(--background-light);
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      overflow-x: hidden;
    }

    .container-fluid {
      min-height: 100vh;
      display: flex;
      flex-direction: row;
    }

    /* Left side */
    .left-panel {
      margin-left: -20px;
      flex: 1;
      background: #033A70;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem;
      text-align: center;
      cursor: none;
    }

    .left-panel .icon {
      font-size: 150px;
      color: #FFCB05;
      margin-bottom: 2rem;
    }

    .left-panel h2 {
      font-weight: bold;
      margin-bottom: 1rem;
    }

    .left-panel p {
      font-size: 1.1rem;
      line-height: 1.5;
      max-width: 400px;
    }

    /* Right side */
    .right-panel {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2rem;
      animation: slideUp 0.4s ease-out;
    }

    @keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

    .card {
     max-width: 450px;
    width: 100%;
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    overflow: hidden;
  
    }
    .card-body{
       padding: 2rem;
      background: var(--background-light);
    }

    .card-header {
     background: var(--primary);
      color: white;
      text-align: center;
      padding: 1rem;
    }

   .form-control {
      padding: 12px 15px 12px 65px;
      border-radius: 8px;
      border: 1px solid var(--border-light);
      background: white;
    }

    .input-group-text {
      background: transparent;
      border: none;
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 10;
      color: #033A70;
    }

    .btn-primary {
      background: linear-gradient(135deg, #033A70, #3E7DCA);
      border: none;
      border-radius: 8px;
      padding: 10px;
    }

    .btn-primary:hover {
      opacity: 0.9;
    }

    .small-text {
      text-align: center;
      margin-top: 1rem;
      font-size: 0.9rem;
    }

    @media(max-width: 768px) {
      .container-fluid {
        flex-direction: column;
      }
      .left-panel, .right-panel {
        flex: unset;
        width: 100%;
      }
      .left-panel{
         width: 110%;
      }
    }
    body::-webkit-scrollbar{
    display: none;
}
 .input-icon {
     background: linear-gradient(135deg, var(--primary), var(--hover-blue));
      color: white;
      font-size:20px;
      margin-left: -10px;
    }
    .login-link {
      display: block;
      text-align: center;
      margin-top: 1rem;
      font-size: 0.9rem;
      color: var(--primary);
      text-decoration: none;
    }

    .login-link:hover {
      text-decoration: underline;
    }
  
  </style>
</head>
<body>

<div class="container-fluid">
  <!-- Left Info -->
  <div class="left-panel">
    <i class="bi bi-shield-lock-fill icon"></i>
    <h2>Password Recovery</h2>
    <p>Reset your Attendify account password using your registered email and OTP verification.</p>
  </div>

  <!-- Right Form -->
  <div class="right-panel">
    <div class="card">
      <div class="card-header">
        <h4>Forgot Password</h4>
      </div>
      <div class="card-body">
        <div id="step1">
          <div class="mb-3 position-relative">
            <span class="input-group-text input-icon"><i class="bi bi-person-badge"></i></span>
            <input type="text" id="user_id" class="form-control" placeholder="Enter ID" required>
          </div>
          <div class="mb-3 position-relative">
            <span class="input-group-text input-icon"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" class="form-control" placeholder="Enter Email" required>
          </div>
          <button id="send-otp" class="btn btn-primary w-100">Send OTP</button>
        </div>

        <div id="step2" style="display:none;">
          <div class="mb-3 position-relative">
            <span class="input-group-text input-icon"><i class="bi bi-key"></i></span>
            <input type="text" id="otp" class="form-control" placeholder="Enter OTP" required>
          </div>
          <button id="verify-otp" class="btn btn-primary w-100">Verify OTP</button>
          <button id="resend-otp" class="btn btn-outline-secondary w-100 mt-2" style="display:none;">
          Resend OTP
        </button>

        </div>

        <!-- Step 3: New Password -->
        <div id="step3" style="display:none;">
          <div class="mb-3 position-relative">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="new-password" class="form-control" placeholder="New Password">
          </div>
          <div class="mb-3 position-relative">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" id="confirm-password" class="form-control" placeholder="Confirm Password">
          </div>
          <button id="save-password" class="btn btn-primary w-100">Save Password</button>
        </div>

        <div class="small-text">
          <a href="login.php" class="login-link">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11">
  <div id="toast-container"></div>
</div>

    <!-- SweetAlert2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

// =========================
// SWEETALERT HELPER
// =========================
function showAlert(type, message) {
    type = (type || "info").toLowerCase();

    let icon, title;

    switch(type) {
        case "success": icon = "success"; title = "Success!"; break;
        case "error": case "danger": icon = "error"; title = "Error!"; break;
        case "info": icon = "info"; title = "Notice"; break;
        case "warning": icon = "warning"; title = "Warning!"; break;
        default: icon = "info"; title = "Notice";
    }

    Swal.fire({
        icon: icon,
        title: title,
        text: message || "",
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener("mouseenter", Swal.stopTimer);
            toast.addEventListener("mouseleave", Swal.resumeTimer);
        }
    });
}

let otpExpiresAt = null; // store expiry time globally


// =========================
// STEP 1 — SEND OTP
// =========================
$("#send-otp").click(function(){

  $.post("process_reset.php", {
        action: "send_otp",
        user_id: $("#user_id").val(),
        email: $("#email").val()
  }, function(data){

        if (data.success) {

            // Store expiry time sent by PHP
            otpExpiresAt = data.expires_at; // example: "2025-02-18 10:45:00"

            showAlert("success", "OTP sent! Expires at: " + otpExpiresAt);

            $("#step1").hide();
            $("#step2").show();
            $("#resend-otp").hide(); // hide resend until needed

        } else {
            showAlert("error", data.error);
        }

  }, "json");
});


// =========================
// STEP 2 — VERIFY OTP
// =========================
$("#verify-otp").click(function(){

  $.post("process_reset.php", {
        action: "verify_otp",
        user_id: $("#user_id").val(),
        otp: $("#otp").val()
  }, function(data){

        if (data.success) {

            showAlert("success", "OTP verified!");
            $("#step2").hide();
            $("#step3").show();
            $("#resend-otp").hide();

        } else {

            showAlert("error", data.error);

            // If expired → allow resend
            if (data.error.toLowerCase().includes("expired")) {
                $("#resend-otp").show();
            }
        }

  }, "json");

});


// =========================
// RESEND OTP (ONLY AFTER EXPIRATION)
// =========================
$("#resend-otp").click(function(){

  $.post("process_reset.php", {
        action: "send_otp",
        user_id: $("#user_id").val(),
        email: $("#email").val()
  }, function(data){

        if (data.success) {

            otpExpiresAt = data.expires_at; // update expiry

            showAlert("success", "New OTP sent! Expires at: " + otpExpiresAt);

            $("#otp").val(""); // clear field
            $("#resend-otp").hide();
            $("#step2").show();

        } else {
            showAlert("error", data.error);
        }

  }, "json");

});


// =========================
// STEP 3 — SAVE PASSWORD
// =========================
$("#save-password").click(function(){

    const new_pass = $("#new-password").val();
    const confirm_pass = $("#confirm-password").val();

    if (new_pass !== confirm_pass) {
        showAlert("error", "Passwords do not match!");
        return;
    }

    $.post("process_reset.php", {
        action: "save_password",
        user_id: $("#user_id").val(),
        password: new_pass
    }, function(data){

        if (data.success) {
            showAlert("success", "Password updated!");
            setTimeout(() => window.location.href = "login.php", 2000);
        } else {
            showAlert("error", data.error);
        }

    }, "json");

});

</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
