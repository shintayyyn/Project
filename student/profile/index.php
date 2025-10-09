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
:root {
    --primary: #3d52a0;
    --secondary: #7091E6;
    --card-border-radius: 0.75rem;
    --transition-speed: 0.3s;
}

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
.btn-primary {
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
</style>

<main>
    <div class="container-fluid">
        <div class="alert-container" id="alertContainer"></div>
        <div class="row g-0">
            <div class="col-md-12">
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php
                            $initials = strtoupper(substr($student['s_fname'] ?? '', 0, 1) . substr($student['s_lname'] ?? '', 0, 1));
                            echo htmlspecialchars($initials);
                            ?>
                        </div>
                        <div class="profile-info">
                            <h1><?php echo htmlspecialchars($student['s_fname'] ?? '') . ' ' . htmlspecialchars($student['s_lname'] ?? ''); ?></h1>
                            <p>Student</p>
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
                                        <div class="input-group">
                                            <input type="email" class="form-control" name="email" id="emailInput"
                                                value="<?php echo htmlspecialchars($student['s_email'] ?? ''); ?>" required>
                                            <button type="button" class="btn btn-primary" id="sendOtpBtn">Send OTP</button>
                                        </div>
                                        <small class="form-text text-muted">You must verify OTP if you change your email.</small>
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

                            <div class="text-end" style="margin-top: -25px;">
                                <button type="button" class="btn btn-primary" id="saveButton">Save Changes</button>
                            </div>
                        </form>
                         <hr>
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
                        </div>
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
    let otpVerified = false;
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

    function startOtpTimer(duration = 60) {
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
                if (verifyOtpBtn) verifyOtpBtn.disabled = true;
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

    function sendOtp() {
        const newEmail = emailInput.value.trim();
        if (!newEmail || newEmail === originalEmail) {
            showAlert("danger", "Enter a new email different from your current one.");
            return;
        }

        fetch("../verify_email/request_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "new_email=" + encodeURIComponent(newEmail)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                otpVerified = false;
                otpModal.show();
                otpFeedbackModal.innerHTML = `<span class="text-success">${data.message}</span>`;
                otpInputs.forEach(input => { input.value = ""; input.disabled = false; });
                if (verifyOtpBtn) verifyOtpBtn.disabled = false;
                otpInputs[0].focus();
                startOtpTimer(60);
            } else if (data.status === "warning" && data.otp_valid_until) {
                otpVerified = false;
                otpModal.show();
                otpFeedbackModal.innerHTML = `<span class="text-warning">${data.message}<br><small>Valid until: ${data.otp_valid_until}</small></span>`;
                otpInputs.forEach(input => { input.value = ""; input.disabled = false; });
                if (verifyOtpBtn) verifyOtpBtn.disabled = false;
                otpInputs[0].focus();
                startOtpTimer(60);
            } else {
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
            if (newEmail !== originalEmail && !otpVerified) {
                showAlert("danger", "Please verify OTP before saving.");
                saveButton.disabled = false;
                saveButton.textContent = "Save Changes";
                return;
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


