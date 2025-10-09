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
                                    <small id="emailError" class="form-text text-muted">You must verify OTP if you change your email.</small>
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

<!-- OTP Modal -->
<div class="modal fade" id="otpModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter OTP</h5>
        <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Please check your email and enter the OTP.</p>
        <input type="text" id="otpInput" class="form-control" placeholder="Enter OTP">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="verifyOtpBtn">Verify</button>
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
    const emailError = document.getElementById("emailError");
    const otpInput = document.getElementById("otpInput");
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");
    const saveButton = document.getElementById("saveButton");
    const form = document.getElementById("profileForm");
    const regenerateBtn = document.getElementById("regenerateQR");
    const qrPreview = document.getElementById("qrPreview");
    const qrContainer = document.getElementById("qrContainer");
    const bootstrapOtpModal = new bootstrap.Modal(document.getElementById('otpModal'));

    let originalEmail = "<?php echo htmlspecialchars($student['s_email'] ?? ''); ?>";
    let otpVerified = false;
    let lastCheckedEmail = "";
    let lastCheckStatus = "";
    let controller;

    if (sendOtpBtn) sendOtpBtn.disabled = true;

    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        if(alertContainer) {
            alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        }
    }

    // ------------------- EMAIL CHECK -------------------
    emailInput.addEventListener("input", function () {
        const newEmail = emailInput.value.trim();
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!newEmail || newEmail === originalEmail || !emailPattern.test(newEmail)) {
            emailError.textContent = "Enter a valid email and verify OTP if you change it.";
            emailError.className = "form-text text-muted";
            sendOtpBtn.disabled = true;
            return;
        }

        if (newEmail === lastCheckedEmail) {
            sendOtpBtn.disabled = (lastCheckStatus !== "success");
            emailError.textContent = (lastCheckStatus === "success")
                ? "Proceed: you can request OTP."
                : "This email is already used.";
            emailError.className = (lastCheckStatus === "success") ? "form-text text-success" : "form-text text-danger";
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();

        emailError.textContent = "Checking...";
        emailError.className = "form-text text-info";
        sendOtpBtn.disabled = true;

        fetch("/Project/student/profile/request_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "new_email=" + encodeURIComponent(newEmail),
            signal: controller.signal
        })
        .then(res => res.json())
        .then(data => {
            lastCheckedEmail = newEmail;
            if (data.status === "exists") {
                emailError.textContent = "This email is already used.";
                emailError.className = "form-text text-danger";
                otpVerified = false;
                sendOtpBtn.disabled = true;
                lastCheckStatus = "exists";
            } else if (data.status === "success") {
                emailError.textContent = "Proceed: you can request OTP.";
                emailError.className = "form-text text-success";
                sendOtpBtn.disabled = false;
                lastCheckStatus = "success";
            } else {
                emailError.textContent = data.message || "Error occurred.";
                emailError.className = "form-text text-danger";
                otpVerified = false;
                sendOtpBtn.disabled = true;
                lastCheckStatus = "error";
            }
        })
        .catch(err => { if (err.name !== "AbortError") console.error(err); });
    });

   let otpSent = false;
let otpExpireTimer = null;

// ------------------- SEND OTP -------------------
if (sendOtpBtn) {
    sendOtpBtn.addEventListener("click", function () {
        const newEmail = emailInput.value.trim();
        if (sendOtpBtn.disabled || otpSent) return; // prevent sending if OTP already sent

        sendOtpBtn.disabled = true;

        fetch("/Project/student/profile/request_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "new_email=" + encodeURIComponent(newEmail)
        })
        .then(res => res.json())
        .then(data => {
            lastCheckedEmail = newEmail;
            if (data.status === "success") {
                otpVerified = false;
                otpSent = true;
                otpInput.value = "";
                emailError.textContent = "OTP sent! Check your email.";
                emailError.className = "form-text text-success";

                // Start OTP expiration timer (5 minutes)
                if (otpExpireTimer) clearTimeout(otpExpireTimer);
                otpExpireTimer = setTimeout(() => {
                    otpSent = false;
                    sendOtpBtn.disabled = false;
                    emailError.textContent = "OTP expired. You can request a new OTP.";
                    emailError.className = "form-text text-warning";
                }, 5 * 60 * 1000); // 5 minutes

                // Show OTP modal
                bootstrapOtpModal.show();
            } else {
                emailError.textContent = data.message || "Failed to send OTP.";
                emailError.className = "form-text text-danger";
                sendOtpBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            emailError.textContent = "Failed to send OTP.";
            emailError.className = "form-text text-danger";
            sendOtpBtn.disabled = false;
        });
    });
}

// ------------------- VERIFY OTP -------------------
if (verifyOtpBtn) {
    verifyOtpBtn.addEventListener("click", function () {
        if (!otpInput.value) {
            showAlert("danger", "Please enter the OTP.");
            return;
        }

        fetch("/Project/student/profile/verify_email_update.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "otp=" + encodeURIComponent(otpInput.value)
        })
        .then(res => res.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === "success") {
                otpVerified = true;
                otpSent = false; // allow sending new OTP if needed in future
                if (otpExpireTimer) clearTimeout(otpExpireTimer);
                bootstrapOtpModal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            showAlert("danger", "OTP verification failed.");
        });
    });
}

    // ------------------- SAVE PROFILE -------------------
    if (saveButton) {
        saveButton.addEventListener("click", function (e) {
            e.preventDefault();
            const newEmail = emailInput.value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailPattern.test(newEmail)) {
                showAlert("danger", "Please enter a valid email address.");
                return;
            }
            if (newEmail !== originalEmail && !otpVerified) {
                showAlert("danger", "Please verify OTP before saving.");
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = "Saving...";

            const formData = new FormData(form);
            fetch("/Project/student/profile/update_profile.php", {
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
                showAlert("danger", "Error while saving changes.");
            });
        });
    }

    // ------------------- REGENERATE QR -------------------
    if (regenerateBtn && qrPreview) {
        regenerateBtn.addEventListener("click", function () {
            const spinner = document.createElement("div");
            spinner.classList.add("spinner");
            qrContainer.appendChild(spinner);

            qrPreview.style.opacity = 0;

            fetch("/Project/student/profile/regenerate_qr.php", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    const newQR = new Image();
                    newQR.src = data.qr_path + "?t=" + new Date().getTime();
                    newQR.onload = () => {
                        qrPreview.src = newQR.src;
                        qrContainer.style.transition = "opacity 0.5s";
                        qrContainer.style.opacity = 1;
                        spinner.remove();
                    };
                    showAlert("success", data.message);
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


