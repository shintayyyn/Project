<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];


// ✅ Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ✅ Fetch parent data
$stmt = $conn->prepare("SELECT * FROM parents WHERE p_id = ?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$result = $stmt->get_result();
$parent = $result->fetch_assoc();
$stmt->close();

// ✅ If no parent found, force logout
if (!$parent) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}
?>
<style>
/* Hide scrollbar globally */
* {
    scrollbar-width: none !important;  /* Firefox */
}

*::-webkit-scrollbar {
    display: none !important;  /* Chrome, Safari, Edge */
}

/* Main content area */
main {
    margin-top: 10px;
    width: calc(100% - 120px);
    padding: 0;
    overflow-x: hidden;   /* Prevent horizontal scrollbar */
    overflow-y: auto;     /* Allow scrolling but hide bar */
}

/* Container adjustments */
.container-fluid {
    width: 100%;
    padding: 0;
    margin: 0;
    overflow-x: hidden;
}


/* Page title */
h2.mb-4 {
    margin: 0.5rem 0 1rem 0.5rem;
}

/* Card styling */
.card {
    border: none;
    border-radius: var(--card-border-radius);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04), 0 8px 16px rgba(0, 0, 0, 0.08);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08), 0 12px 24px rgba(0, 0, 0, 0.12);
}

.profile-header {
    background:var(--primary);
    color: var(--tertiary);
    padding: 1rem;
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background:var(--tertiary);
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 1px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    color: var(--primary);
}

.profile-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    border-color: rgba(255, 255, 255, 0.5);
}

.profile-info h1 {
    font-size: 1.5rem;
    margin: 0;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.profile-info p {
    margin: 0.5rem 0 0;
    opacity: 0.9;
    font-size: 1rem;
    letter-spacing: 0.5px;
}

.card-body {
    background: var(--quaternary);
    padding: 2.5rem;
}

/* Form styling */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: #444;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-control, .form-select {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all var(--transition-speed);
    background-color: var(--background);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(61, 82, 160, 0.1);
    background-color: #fff;
}

/* Override Bootstrap button styles */
.btn-primary {
    display: inline-flex; /* ensures stable sizing */
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
    border: none !important;
    padding: 0.875rem 2rem !important;
    font-weight: 500 !important;
    letter-spacing: 0.5px !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    position: relative !important;
    overflow: hidden !important;
    line-height: 1.2; /* stabilizes height */
    transition: 
        background 0.2s ease, 
        color 0.2s ease, 
        font-weight 0.2s ease,
        transform 0.2s ease-in-out !important;
    will-change: transform; /* hint for smoother scaling */
}

.btn-primary:hover {
    background: var(--tertiary) !important;
    color: var(--primary) !important;
    font-weight: 600 !important;
    transform: scale(1.05) !important; /* pop effect */
}

.btn-primary:active {
    transform: scale(0.95) !important; /* pressed effect */
}


.btn-primary:disabled {
    background: #6c757d !important;
    cursor: not-allowed !important;
}

/* Alert styles */
.alert-container {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1060;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.alert {
    min-width: 300px;
    max-width: 600px;
    margin-bottom: 1rem;
    border: none;
    border-left: 4px solid;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    text-align: center;
}

.alert-success {
    background-color: #d1e7dd;
    border-left-color: #198754;
    color: #0f5132;
}

.alert-info {
    background-color: #cff4fc;
    border-left-color: #0dcaf0;
    color: #055160;
}

.alert-danger {
    background-color: #f8d7da;
    border-left-color: #dc3545;
    color: #842029;
}

@keyframes slideIn {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert.show {
    animation: slideIn 0.3s ease-out;
}
/* Mobile & Tablet adjustments */
@media (max-width: 991px) {
    main {
        width: 100% !important;
        margin-top: 0.5rem;
        padding: 0.5rem;
    }

    .profile-header {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        text-align: center;
    }

    .profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .profile-info h1 {
        font-size: 1.2rem;
    }

    .card-body {
        padding: 1.5rem 1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-control, .form-select {
        font-size: 0.95rem;
        padding: 0.6rem 0.9rem;
    }

    .btn-primary {
        width: 100%;
        padding: 0.75rem;
        font-size: 0.95rem;
    }

    .input-group .btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }

    /* OTP inputs smaller on mobile */
    .otp-input {
        width: 35px !important;
        height: 35px !important;
        font-size: 1rem;
    }
}

@media (max-width: 575px) {
    .profile-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }

    .profile-info h1 {
        font-size: 1rem;
    }
   .card {
        max-width: calc(100% - 10px); /* 30px left/right on small screens */
        margin: 0 auto 1rem auto;
        border-radius: 8px; /* slightly smaller for mobile */
    }
    .card-body {
        padding: 1rem;
    }

    .otp-input {
        width: 30px !important;
        height: 30px !important;
        font-size: 0.9rem;
    }
}

</style>
<center>
<main>
    <div class="container-fluid">
        <div class="alert-container" id="alertContainer"></div>
        <div class="row g-0">
            <div class="col-md-12">
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php
                            $initials = strtoupper(substr($parent['p_fname'] ?? '', 0, 1) . substr($parent['p_lname'] ?? '', 0, 1));
                            echo htmlspecialchars($initials);
                            ?>
                        </div>
                        <div class="profile-info">
                            <h1><?php echo htmlspecialchars($parent['p_fname'] ?? '') . ' ' . htmlspecialchars($parent['p_lname'] ?? ''); ?></h1>
                            <p>Parent Profile</p>
                        </div>
                    </div>
                    
                    <div class="card-body text-start">
                        <form method="post" id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="firstname" value="<?php echo htmlspecialchars($parent['p_fname'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" name="middlename" value="<?php echo htmlspecialchars($parent['p_mname'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="lastname" value="<?php echo htmlspecialchars($parent['p_lname'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Suffix</label>
                                        <input type="text" class="form-control" name="suffix" value="<?php echo htmlspecialchars($parent['p_suffix'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="Male" <?php echo $parent['p_gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $parent['p_gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($parent['p_bdate'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" class="form-control" name="contact" value="<?php echo htmlspecialchars($parent['p_cnum'] ?? ''); ?>">
                                    </div>
                                </div>
                               <div class="col-md-6">
                                    <div class="form-group mb-3">
                                    <label class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" name="email" id="emailInput"
                                            value="<?php echo htmlspecialchars($parent['p_email'] ?? ''); ?>" required>
                                        <button type="button" class="btn btn-primary" id="sendOtpBtn">Send OTP</button>
                                    </div>
                                    <small class="form-text text-muted">You must verify OTP if you change your email.</small>
                                    <div id="otpFeedback" class="mt-1"></div>
                                </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($parent['p_address'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="active" <?php echo $parent['p_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $parent['p_status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" placeholder="Enter new password">
                                        <small class="form-text text-muted">Leave blank to keep current password</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end text-md-end text-center" style="margin-top: -10px;">
                            <button type="button" class="btn btn-primary" id="saveButton">Save Changes</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</center>

<!-- OTP Verification Modal -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Verify Email OTP</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p>Enter the 6-digit OTP sent to your new email:</p>
        <div class="d-flex justify-content-center gap-2 mb-2">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
          <input type="text" maxlength="1" class="otp-input form-control text-center" style="width:40px;">
        </div>
        <div id="otpFeedback" class="mb-2 text-center"></div>
        <div class="mb-2">
            <small id="otpTimerText" class="text-muted">OTP expires in 01:00</small>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="resendOtpBtn">Resend OTP</button>
            <button type="button" class="btn btn-primary" id="verifyOtpBtn">Verify OTP</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('profileForm');
    const saveButton = document.getElementById('saveButton');
    const emailInput = document.getElementById("emailInput");
    const sendOtpBtn = document.getElementById("sendOtpBtn");
    const resendOtpBtn = document.getElementById("resendOtpBtn");
    const otpFeedbackModal = document.querySelector('#otpModal #otpFeedback');
    const otpTimerText = document.getElementById("otpTimerText");
    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");

    // Parent's original email
    let originalEmail = "<?php echo htmlspecialchars($parent['p_email'] ?? ''); ?>";
    let otpVerified = false;
    let otpTimer;
    let otpTimeLeft;

    const otpModal = new bootstrap.Modal(document.getElementById("otpModal"), { 
        backdrop: "static", 
        keyboard: false 
    });

    // --------------------
    // Alert helper
    // --------------------
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        setTimeout(() => alertContainer.innerHTML = '', 3000);
    }

    // --------------------
    // OTP Timer
    // --------------------
    function startOtpTimer(duration = 600) {
        clearInterval(otpTimer);
        otpTimeLeft = duration;
        updateTimerDisplay();
        otpTimer = setInterval(() => {
            otpTimeLeft--;
            if (otpTimeLeft <= 0) {
                clearInterval(otpTimer);
                otpFeedbackModal.innerHTML = `<span class="text-danger">OTP expired. Please resend.</span>`;
                otpTimerText.textContent = "OTP expired";
                otpInputs.forEach(input => input.disabled = true);
                verifyOtpBtn.disabled = true;
            } else {
                updateTimerDisplay();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(otpTimeLeft / 60).toString().padStart(2, "0");
        const seconds = (otpTimeLeft % 60).toString().padStart(2, "0");
        otpTimerText.textContent = `OTP expires in ${minutes}:${seconds}`;
    }

    // --------------------
    // Send / Resend OTP
    // --------------------
    function sendOtp() {
        const newEmail = emailInput.value.trim();

        // Prevent OTP if same as verified email
        if (!newEmail || newEmail === originalEmail) {
            showAlert("danger", "Enter a new email different from your current verified email.");
            return;
        }

        fetch("../verify_email/request_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "new_email=" + encodeURIComponent(newEmail)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success" || (data.status === "warning" && data.otp_valid_until)) {
                otpVerified = false;
                otpModal.show();
                otpFeedbackModal.innerHTML = `<span class="text-success">${data.message}</span>`;
                otpInputs.forEach(input => { input.value = ""; input.disabled = false; });
                verifyOtpBtn.disabled = false;
                otpInputs[0].focus();

                let otpDuration = 600;
                if (data.otp_valid_until) {
                    const now = new Date();
                    const expires = new Date(data.otp_valid_until);
                    otpDuration = Math.floor((expires - now) / 1000);
                    if (otpDuration <= 0) otpDuration = 600;
                }
                startOtpTimer(otpDuration);
            } else {
                showAlert(data.status, data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert("danger", "Failed to send OTP. Please try again.");
        });
    }

    sendOtpBtn.addEventListener("click", sendOtp);
    resendOtpBtn.addEventListener("click", sendOtp);

    // --------------------
    // OTP input auto-focus
    // --------------------
    otpInputs.forEach((input, i) => {
        input.addEventListener('input', () => {
            if (input.value.length && i < otpInputs.length - 1) otpInputs[i + 1].focus();
        });
        input.addEventListener('keydown', e => {
            if (e.key === "Backspace" && !input.value && i > 0) otpInputs[i - 1].focus();
        });
    });

    // --------------------
    // Verify OTP
    // --------------------
    verifyOtpBtn.addEventListener("click", () => {
        if (verifyOtpBtn.disabled) return;

        const otp = Array.from(otpInputs).map(input => input.value.trim()).join('');
        if (otp.length < otpInputs.length) {
            otpFeedbackModal.textContent = "Please enter the complete OTP.";
            return;
        }

        fetch("../verify_email/verify_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "otp=" + encodeURIComponent(otp)
        })
        .then(res => res.json())
        .then(data => {
            otpFeedbackModal.innerHTML = `<span class="text-${data.status === 'success' ? 'success' : 'danger'}">${data.message}</span>`;
            if (data.status === "success") {
                otpVerified = true;
                clearInterval(otpTimer);
                otpModal.hide();
                showAlert("success", "Email OTP verified successfully.");

                // Mark email as verified
                originalEmail = emailInput.value.trim();
                const emailDisplay = document.querySelector("#emailDisplay");
                if (emailDisplay) emailDisplay.textContent = originalEmail;
            }
        })
        .catch(err => {
            console.error(err);
            otpFeedbackModal.innerHTML = `<span class="text-danger">OTP verification failed.</span>`;
        });
    });

    // --------------------
    // Save Profile
    // --------------------
    saveButton.addEventListener("click", function(e) {
        e.preventDefault();
        saveButton.disabled = true;
        saveButton.textContent = "Saving...";

        const newEmail = emailInput.value.trim();
        if (newEmail !== originalEmail && !otpVerified) {
            showAlert("danger", "Please verify OTP before saving.");
            saveButton.disabled = false;
            saveButton.textContent = "Save Changes";
            return;
        }

        const formData = new FormData(form);
        fetch("profile/update_profile.php", {
            method: "POST",
            body: formData,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(res => res.json())
        .then(data => {
            saveButton.disabled = false;
            saveButton.textContent = "Save Changes";
            showAlert(data.status, data.message);
            if (data.status === "success") setTimeout(() => location.reload(), 1500);
        })
        .catch(err => {
            console.error(err);
            saveButton.disabled = false;
            saveButton.textContent = "Save Changes";
            showAlert("danger", "An error occurred while saving changes.");
        });
    });

});
</script>






