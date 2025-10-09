<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Attendify</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      padding: 0;
      overflow-x: hidden;
      font-family: 'Roboto', sans-serif;
      background: #f0f6fa;
    }

    .container-fluid {
      min-height: 100vh;
      display: flex;
      flex-direction: row;
    }

    /* Left side */
    .left-panel {
      flex: 1;
      background: #033A70;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem;
      text-align: center;
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
      background: #f9fcff;
      padding: 2rem;
    }

    .card {
      width: 100%;
      max-width: 400px;
      border: none;
      border-radius: 12px;
      box-shadow: 0px 6px 15px rgba(0,0,0,0.1);
      background: white;
    }

    .card-header {
      background: #033A70;
      color: white;
      text-align: center;
      padding: 1rem;
      border-top-left-radius: 12px;
      border-top-right-radius: 12px;
    }

    .form-control {
      border-radius: 8px;
      padding: 12px 15px 12px 40px;
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
        <!-- Step 1: ID + Email -->
        <div id="step1">
          <div class="mb-3 position-relative">
            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
            <input type="text" id="user_id" class="form-control" placeholder="Enter ID">
          </div>
          <div class="mb-3 position-relative">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" class="form-control" placeholder="Enter Email">
          </div>
          <button id="send-otp" class="btn btn-primary w-100">Send OTP</button>
        </div>

        <!-- Step 2: OTP -->
        <div id="step2" style="display:none;">
          <div class="mb-3 position-relative">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input type="text" id="otp" class="form-control" placeholder="Enter OTP">
          </div>
          <button id="verify-otp" class="btn btn-primary w-100">Verify OTP</button>
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
          <a href="login.php">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="toast-container"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Toast
function showToast(msg, type="info"){
  const toastId = "t"+Date.now();
  const bg = type === "success" ? "bg-success" : type==="error"?"bg-danger":"bg-info";
  const html = `<div id="${toastId}" class="toast align-items-center text-white ${bg} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>`;
  $("#toast-container").append(html);
  const t = new bootstrap.Toast(document.getElementById(toastId), { delay: 3000 });
  t.show();
}

// Step 1: Send OTP
$("#send-otp").click(function(){
  $.post("process_reset.php", {
    action:"send_otp", 
    user_id: $("#user_id").val(), 
    email: $("#email").val()
  }, function(data){
    if(data.success){
      showToast("OTP sent!", "success");
      $("#step1").hide(); $("#step2").show();
    } else showToast(data.error, "error");
  }, "json");
});

// Step 2: Verify OTP
$("#verify-otp").click(function(){
  $.post("process_reset.php", {
    action:"verify_otp", 
    user_id: $("#user_id").val(), 
    otp: $("#otp").val()
  }, function(data){
    if(data.success){
      showToast("OTP verified!", "success");
      $("#step2").hide(); $("#step3").show();
    } else showToast(data.error, "error");
  }, "json");
});

// Step 3: Save Password
$("#save-password").click(function(){
  const new_pass = $("#new-password").val();
  const confirm_pass = $("#confirm-password").val();
  if(new_pass !== confirm_pass){
    showToast("Passwords do not match!", "error");
    return;
  }
  $.post("process_reset.php", {
    action:"save_password", 
    user_id: $("#user_id").val(), 
    password:new_pass
  }, function(data){
    if(data.success){
      showToast("Password updated!", "success");
      setTimeout(()=>window.location.href="login.php", 2000);
    } else showToast(data.error, "error");
  }, "json");
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
