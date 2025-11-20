<?php
require_once('../includes/db.php');

// Add error reporting and debug logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch QR from generatedqrcode table
$qr_stmt = $conn->prepare("SELECT generated_qrcode FROM generatedqrcode WHERE id = ?");
$qr_stmt->bind_param("i", $_SESSION['user_id']);
$qr_stmt->execute();
$qr_row = $qr_stmt->get_result()->fetch_assoc();
$qr_stmt->close();

$qr_code = $qr_row['generated_qrcode'] ?? null;

// Fetch student data
$stmt = $conn->prepare("SELECT s.*, ss.section_code 
                       FROM students s 
                       LEFT JOIN students_sections ss ON s.s_id = ss.s_id 
                       WHERE s.s_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<style>


/* Main content area */
main {
    margin-left: 0;
    width: 100%;
    padding: 0;
    overflow-x: hidden;
}

/* Container adjustments */
.container-fluid {
    padding: 1.5rem 2rem;
    width: 100%;
    margin: 0;
    overflow-x: hidden;
}

@media (max-width: 767.98px) {
    .container-fluid {
        padding: 0.1rem;/* smaller horizontal padding on mobile */
    }
}

/* Card styling */
.card {
    border: none;
    border-radius: var(--card-border-radius);
    box-shadow: 0 0 10px rgba(0,0,0,0.1) !important;
    margin: 0;
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    background: #fff;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08), 0 12px 24px rgba(0, 0, 0, 0.12);
}

.profile-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.15);
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 500;
    letter-spacing: 1px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
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
    background-color: #f8f9fa;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(61, 82, 160, 0.1);
    background-color: #fff;
}

/* Button styles */
.btn-primary{
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
    border: none !important;
    padding: 0.875rem 2rem !important;
    font-weight: 500 !important;
    letter-spacing: 0.5px !important;
    border-radius: 8px !important;
    transition: none !important;
    transform: none !important;
    cursor: pointer !important;
    /* position: relative !important; */
    overflow: hidden !important;
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

#qrContainer .spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    z-index: 10;
    background: transparent;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}
.otp-input{
    width:45px;
}
</style>

<main>
    <div class="container-fluid">
        <div class="alert-container" id="alertContainer"></div>
        <div class="row g-0">
            <div class="col-md-12">
                <div class="card">
                    <div class="profile-header d-flex align-items-center justify-content-between flex-wrap">
    <div class="d-flex align-items-center mb-2 mb-md-0">
        <div class="profile-avatar me-3">
            <?php
            $initials = strtoupper(substr($student['s_fname'] ?? '', 0, 1) . substr($student['s_lname'] ?? '', 0, 1));
            echo htmlspecialchars($initials);
            ?>
        </div>
        <div class="profile-info">
            <h1 class="mb-0"><?php echo htmlspecialchars($student['s_fname'] ?? '') . ' ' . htmlspecialchars($student['s_lname'] ?? ''); ?></h1>
            <p class="mb-0">Student</p>
        </div>
    </div>

   <div class="bg-white shadow-sm rounded-pill px-4 py-2 text-muted small d-inline-flex align-items-center flex-nowrap">
    <i class="bi bi-clock-history me-1"></i>
    Last Updated:
    <span id="last-updated" class="ms-1 fw-semibold text-dark">
        <?= htmlspecialchars($_SESSION['last_updated']); ?>
    </span>
</div>

</div>

                    
                    <div class="card-body">
                        <form method="post" id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" name="firstname" value="<?php echo htmlspecialchars($student['s_fname'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" name="middlename" value="<?php echo htmlspecialchars($student['s_mname'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" name="lastname" value="<?php echo htmlspecialchars($student['s_lname'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Suffix</label>
                                        <input type="text" class="form-control" name="suffix" value="<?php echo htmlspecialchars($student['s_suffix'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="Male" <?php echo $student['s_gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo $student['s_gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Birthdate</label>
                                        <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($student['s_bdate'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" class="form-control" name="contact" value="<?php echo htmlspecialchars($student['s_cnum'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="d-flex flex-column flex-md-row">
    <input type="email" class="form-control me-md-2 mb-2 mb-md-0" name="email" id="emailInput"
           value="<?php echo htmlspecialchars($student['s_email'] ?? ''); ?>" required>
    <button type="button" class="btn btn-primary btn-sm" id="sendOtpBtn">Send OTP</button>
</div>

                                        <small class="form-text text-muted">You must verify OTP if you change your email.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
  <div class="form-group mb-3 position-relative">
    <label class="form-label fw-semibold">Password</label>
    
    <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Enter new password">
    
    <!-- Eye Icon -->
    <i class="bi bi-eye-slash" id="togglePassword"
       style="
         position: absolute;
         right: 15px;
         top: 50%;
         transform: translateY(-30%);
         cursor: pointer;
         color: #6c757d;
         font-size: 1.1rem;
       "></i>
    
    <small class="form-text text-muted">Leave blank to keep current password</small>
  </div>
                        </div>
                            </div>

                            <div class="text-end" style="margin-top: 5px;">
                                <button type="button" class="btn btn-primary" id="saveButton">Save Changes</button>
                            </div>
                        </form>
                         <!-- <hr>
                        <h5>Your QR Code</h5>
                        <div class="text-center mb-3" id="qrContainer" style="position: relative; width: 300px; margin: 0 auto;">
                            <?php if ($qr_code): ?>
                                <img 
                                    id="qrPreview"
                                    src="https://quickchart.io/qr?text=<?= urlencode($qr_code) ?>" 
                                    style="width: 100%; max-width: 300px; transition: opacity 0.3s;"
                                >
                                <div id="qrPreviewText" style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                                    <?= htmlspecialchars($qr_code) ?>
                                </div>
                            <?php else: ?>
                                <p>No QR Code found. Please generate one.</p>
                            <?php endif; ?>
                        </div>

                        <div class="text-center">
                            <button type="button" class="btn btn-primary" id="regenerateQR">Regenerate QR Code</button>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- OTP Verification Modal -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
     <div class="modal-header">
        <h5 class="modal-title fw-bold">Verify Email OTP</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p>Enter the 6-digit OTP sent to your new email:</p>
        <div class="d-flex justify-content-center gap-2 mb-2">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
          <input type="text" maxlength="1" class="otp-input form-control text-center">
        </div>
        <div id="otpFeedback" class="mb-2 text-center"></div>
        <div class="mb-2">
            <small id="otpTimerText" class="text-muted">OTP expires in 01:00</small>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-warning fw-semi-bold" id="resendOtpBtn">Resend OTP</button>
            <button type="button" class="btn btn-primary" id="verifyOtpBtn">Verify OTP</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$verified = 0;
$hasRecord = 0;

// Check latest email_verifications
$stmt = $conn->prepare("
    SELECT verified 
    FROM email_verifications 
    WHERE user_id = ? AND user_type = ? AND new_email = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['user_type'], $student['s_email']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $hasRecord = 1;
    $verified = $result['verified']; // 0 or 1
}
?>
<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<script>

    const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

togglePassword.addEventListener('click', function () {
  const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
  password.setAttribute('type', type);
  this.classList.toggle('bi-eye');
  this.classList.toggle('bi-eye-slash');
});
let originalEmail = "<?php echo addslashes($student['s_email']); ?>";
let emailHasRecord = <?php echo $hasRecord; ?>;
let emailIsVerified = <?php echo $verified; ?>;

const form = document.getElementById('profileForm');
const saveButton = document.getElementById('saveButton');

// Helper function for alerts
function showAlert(type, message) {
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible show" role="alert">
            <i class="bi bi-${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    const alertContainer = document.getElementById('alertContainer');
    if(alertContainer) {
        alertContainer.insertAdjacentHTML('beforeend', alertHtml);
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const emailInput = document.getElementById("emailInput");
    const sendOtpBtn = document.getElementById("sendOtpBtn");
    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");
    const regenerateBtn = document.getElementById("regenerateQR");
    const qrPreview = document.getElementById("qrPreview");
    const qrPreviewText = document.getElementById("qrPreviewText");
    const saveButton = document.getElementById('saveButton');
    const form = document.getElementById('profileForm');
    const otpFeedbackModal = document.querySelector('#otpModal #otpFeedback');
    const resendOtpBtn = document.getElementById("resendOtpBtn");
    const otpTimerText = document.getElementById("otpTimerText");

    let originalEmail = "<?php echo htmlspecialchars($student['s_email'] ?? ''); ?>";
   let otpVerified = localStorage.getItem("otpVerified") === "true"; // ✅ load from storage

    let otpTimer;
    let otpTimeLeft;

    const otpModal = new bootstrap.Modal(document.getElementById("otpModal"), { backdrop: "static", keyboard: false });

    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        setTimeout(() => alertContainer.innerHTML = '', 3000);
    }

function startOtpTimer(duration = 600) {
    clearInterval(otpTimer);

    const savedExpiry = localStorage.getItem("otpExpiresAt");
    let expiresAt;

    if (savedExpiry) {
        expiresAt = parseInt(savedExpiry, 10);

        // If expired already, clear storage and stop timer
        if (Date.now() >= expiresAt) {
            localStorage.removeItem("otpExpiresAt");
            otpTimeLeft = 0;
            updateTimerDisplay();
            return;
        }

        // Restore remaining time
        otpTimeLeft = Math.floor((expiresAt - Date.now()) / 1000);

        // Reopen modal if the page was refreshed or modal was closed
        otpModal.show();
        otpFeedbackModal.innerHTML = `<span class="text-warning">Please complete your OTP verification.</span>`;
        otpInputs.forEach(i => { 
            i.disabled = false; 
            if (!i.value) i.value = ""; 
        });
        if (verifyOtpBtn) verifyOtpBtn.disabled = false;

    } else {
        // Fresh OTP timer
        expiresAt = Date.now() + duration * 1000;
        localStorage.setItem("otpExpiresAt", expiresAt);
        otpTimeLeft = duration;

        otpModal.show();
    }

    updateTimerDisplay();

    otpTimer = setInterval(() => {
        const now = Date.now();
        otpTimeLeft = Math.floor((expiresAt - now) / 1000);

        if (otpTimeLeft <= 0) {
            clearInterval(otpTimer);
            localStorage.removeItem("otpExpiresAt");

            otpFeedbackModal.innerHTML = `<span class="text-danger">OTP expired. Please resend.</span>`;
            otpTimerText.textContent = "OTP expired";

            otpInputs.forEach(i => i.disabled = true);
            if (verifyOtpBtn) verifyOtpBtn.disabled = true;

            return;
        }

        updateTimerDisplay();
    }, 1000);
}

// ---------------------- CHECK EXISTING OTP ON PAGE LOAD ----------------------
if (!otpVerified) {   // ✅ only reopen if OTP is not verified
    const savedExpiry = localStorage.getItem("otpExpiresAt");
    if (savedExpiry && Date.now() < parseInt(savedExpiry, 10)) {
        otpInputs.forEach(i => { i.value = ""; i.disabled = false; });
        if (verifyOtpBtn) verifyOtpBtn.disabled = false;
        otpInputs[0].focus();
        otpModal.show();
        startOtpTimer();
    }
}



    function updateTimerDisplay() {
        const minutes = Math.floor(otpTimeLeft / 60).toString().padStart(2, "0");
        const seconds = (otpTimeLeft % 60).toString().padStart(2, "0");
        otpTimerText.textContent = `OTP expires in ${minutes}:${seconds}`;
    }

    function sendOtp() {
        const newEmail = emailInput.value.trim();
        if (!newEmail) {
    showAlert("danger", "Please enter an email address.");
    return;
}

if (newEmail === originalEmail) {
    // Case: Same email, but NOT verified or NO record → ALLOW OTP
    if (emailIsVerified == 0 || emailHasRecord == 0) {
        // allow sendOtp() to proceed
    }
    // Case: Same email and VERIFIED → BLOCK
    else if (emailIsVerified == 1) {
        showAlert("danger", "This email is already verified. Enter a different one.");
        return;
    }
}


        fetch("../verify_email/request_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "new_email=" + encodeURIComponent(newEmail)
        })
        .then(res => res.json())
        .then(data => {
           if (data.status === "success") {
    const expiresAt = Date.now() + 600000; // 10 mins
    localStorage.setItem("otpExpiresAt", expiresAt);

    otpVerified = false;
localStorage.setItem("otpVerified", "false"); // reset flag

    otpModal.show();
    otpFeedbackModal.innerHTML = `<span class="text-success">${data.message}</span>`;
    otpInputs.forEach(input => { input.value = ""; input.disabled = false; });
    if (verifyOtpBtn) verifyOtpBtn.disabled = false;
    otpInputs[0].focus();
    startOtpTimer(); // no duration needed
} else if (data.status === "warning" && data.otp_valid_until) {
    const expiresAt = Date.now() + 600000; // 10 mins
    localStorage.setItem("otpExpiresAt", expiresAt);

   otpVerified = false;
localStorage.setItem("otpVerified", "false"); // reset flag

    otpModal.show();
    otpFeedbackModal.innerHTML = `<span class="text-warning">${data.message}<br><small>Valid until: ${data.otp_valid_until}</small></span>`;
    otpInputs.forEach(input => { input.value = ""; input.disabled = false; });
    if (verifyOtpBtn) verifyOtpBtn.disabled = false;
    otpInputs[0].focus();
    startOtpTimer(); // no duration
}
 else {
                showAlert(data.status, data.message);
            }
        })
        .catch(err => {
            console.error(err);
            showAlert("danger", "Failed to send OTP. Please try again.");
        });
    }

    if (sendOtpBtn) sendOtpBtn.addEventListener("click", sendOtp);
    if (resendOtpBtn) resendOtpBtn.addEventListener("click", sendOtp);

    // OTP auto-focus logic
    otpInputs.forEach((input, i) => {
        input.addEventListener('input', () => {
            if (input.value.length && i < otpInputs.length - 1) otpInputs[i + 1].focus();
        });
        input.addEventListener('keydown', e => {
            if (e.key === "Backspace" && !input.value && i > 0) otpInputs[i - 1].focus();
        });
    });

    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener("click", () => {
            if (verifyOtpBtn.disabled) return;
            const otp = Array.from(otpInputs).map(input => input.value).join('');
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
    localStorage.setItem("otpVerified", "true");
    localStorage.removeItem("otpExpiresAt");

    localStorage.setItem("verifiedEmail", emailInput.value.trim()); // ✅ add this

    clearInterval(otpTimer);
    otpModal.hide();
    showAlert("success", "Email OTP verified successfully.");
}

            })
            .catch(err => {
                console.error(err);
                otpFeedbackModal.innerHTML = `<span class="text-danger">OTP verification failed.</span>`;
            });
        });
    }

    // Save profile with OTP verification
    if (saveButton) {
        saveButton.addEventListener("click", function(e) {
            e.preventDefault();
            saveButton.disabled = true;
            saveButton.textContent = "Saving...";

            const newEmail = emailInput.value.trim();
           const verifiedEmail = localStorage.getItem("verifiedEmail");

if (newEmail !== originalEmail) {
    if (!otpVerified) {
        showAlert("danger", "Please verify OTP before saving.");
        saveButton.disabled = false;
        saveButton.textContent = "Save Changes";
        return;
    }

    if (verifiedEmail !== newEmail) {
        showAlert("danger", "OTP was not verified for this email.");
        saveButton.disabled = false;
        saveButton.textContent = "Save Changes";
        return;
    }
}


            const formData = new FormData(form);
            fetch("/student/profile/update_profile.php", {
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
    }

    // ------------------- REGENERATE QR -------------------
    if (regenerateBtn && qrPreview) {
        const qrContainer = document.getElementById("qrContainer");
        regenerateBtn.addEventListener("click", function () {
            const spinner = document.createElement("div");
            spinner.classList.add("spinner");
            qrContainer.appendChild(spinner);

            qrPreview.style.opacity = 0;

            fetch("/student/profile/regenerate_qr.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    showAlert("success", "QR code regenerated successfully.");
                    const newQR = new Image();
                    newQR.src = data.generated_qrcode + "?t=" + new Date().getTime();
                    newQR.onload = () => {
                        if (qrPreview) {
                            qrPreview.src = newQR.src;
                            qrPreview.style.transition = "opacity 0.5s ease-in-out";
                            qrPreview.style.opacity = 1;
                        } else if (qrPreviewText) {
                            const img = document.createElement("img");
                            img.id = "qrPreview";
                            img.src = newQR.src;
                            img.alt = "QR Code";
                            img.style.width = "100%";
                            img.style.maxWidth = "300px";
                            qrPreviewText.replaceWith(img);
                        }
                        spinner.remove();
                        showAlert("success", data.message);
                        setTimeout(() => location.reload(), 1000);
                    };
                } else {
                    spinner.remove();
                    showAlert("danger", data.message);
                }
            })
            .catch(err => {
                spinner.remove();
                console.error(err);
                showAlert("danger", "Failed to regenerate QR code.");
            });
        });
    }
});



</script>


