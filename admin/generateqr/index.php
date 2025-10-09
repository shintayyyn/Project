<?php
require_once(__DIR__ . '/../../includes/db.php');

// Only allow admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../../login.php');
    exit();
}

// Get active term_id
$term_id = null;
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = (int)$term_row['term_id'];
} else {
    die('No active term found.');
}

// Unified query: regulars from students_sections + irregulars from subject_enrollments
// Unified query: all regulars from students_sections + all from subject_enrollments
$query = "
SELECT 
    st.s_id,
    st.s_fname,
    st.s_lname,
    st.s_mname,
    st.s_suffix,
    COALESCE(sec.section_code, se.section_code) AS section_code, -- ✅ prefer students_sections, fallback to subject_enrollments
    st.is_regular,
    st.s_gender
FROM students st
LEFT JOIN students_sections ss 
    ON st.s_id = ss.s_id AND ss.term_id = ?
LEFT JOIN sections sec 
    ON ss.section_id = sec.section_id
LEFT JOIN subject_enrollments se 
    ON st.s_id = se.s_id AND se.term_id = ? AND se.enrollment_status = 'Enrolled'
ORDER BY st.s_lname ASC, st.s_fname ASC;

";


$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $term_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Build a clean display name
        $lname   = $row['s_lname'] ?? '';
        $fname   = $row['s_fname'] ?? '';
        $mname   = $row['s_mname'] ?? '';
        $suffix  = $row['s_suffix'] ?? '';
        $section = $row['section_code'] ?? '';

        $minitial = $mname ? ' ' . strtoupper(substr($mname, 0, 1)) . '.' : '';
        $suffix_part = $suffix ? ' ' . $suffix : '';
        $fullName = trim($lname . ', ' . $fname . $minitial . $suffix_part);

        $students[] = [
            'id'      => (int)$row['s_id'],
            'label'   => $fullName . ' - ' . $section,
            'name'    => $fullName,
            'section' => $section,
            'status'  => ((int)$row['is_regular'] === 1 ? 'Regular' : 'Irregular'),
            'gender'  => $row['s_gender'] ?? ''
        ];
    }
} else {
    // Fallback if get_result() is not available: bind_result + fetch
    $stmt->bind_result($sid, $sfname, $slname, $smname, $ssuffix, $s_section_code, $s_is_regular, $s_gender);
    while ($stmt->fetch()) {
        $minitial = $smname ? ' ' . strtoupper(substr($smname, 0, 1)) . '.' : '';
        $suffix_part = $ssuffix ? ' ' . $ssuffix : '';
        $fullName = trim($slname . ', ' . $sfname . $minitial . $suffix_part);

        $students[] = [
            'id'      => (int)$sid,
            'label'   => $fullName . ' - ' . $s_section_code,
            'name'    => $fullName,
            'section' => $s_section_code,
            'status'  => ((int)$s_is_regular === 1 ? 'Regular' : 'Irregular'),
            'gender'  => $s_gender
        ];
    }
}

$stmt->close();

// Now you have $students[] ready to use for dropdowns / QR generation
// Example: var_export($students);
// Or continue to build your form/UI below.
?>



<style>
     body{
        overflow-y: auto; 
    }

    .card{
        overflow: hidden;
    }
    .card-body {
    padding: 20px;
}

    /* White Flash Blur Overlay */
#antiScreenshotOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    backdrop-filter: blur(30px);
    background-color: rgba(255, 255, 255, 0.75);
    z-index: 999998;
    display: none;
    pointer-events: none;
    transition: opacity 0.5s ease;
}

/* Alert Notification Box */
.screenshot-alert {
    position: fixed;
    top: 30px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 999999;
    background-color: #ffc107;
    color: #1a1a1a;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 600;
    display: none;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    animation: fadeInSlide 0.3s ease-out;
}

.screenshot-alert i {
    font-size: 1.2rem;
}

/* Animation for smooth alert appearance */
@keyframes fadeInSlide {
    from {
        transform: translate(-50%, -20px);
        opacity: 0;
    }
    to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
}

.highlight-row {
    animation: popHighlight 2s ease;
    background-color: #fff3cd !important; /* Bootstrap warning background */
}

@keyframes popHighlight {
    0% {
        background-color: #fff3cd;
        transform: scale(1.02);
    }
    50% {
        background-color: #ffeb99;
        transform: scale(1);
    }
    100% {
        background-color: transparent;
        transform: none;
    }
}


</style>

<div id="antiScreenshotOverlay"></div>

<!-- Screenshot Warning Alert -->
<div id="screenshotAlert" class="screenshot-alert">
    <i class="bi bi-shield-lock-fill"></i>
    Screenshots are disabled on this page.
</div>

<!-- Add your usual page layout here -->
<div class="container-fluid">
      <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">QR Code Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href=" dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Generated QR Codes</li>
                </ol>
            </nav>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQRModal">
            <i class="bi bi-plus-lg me-2"></i>Add QR Code
        </button>
    </div>


    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">QR Code List</h5>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive p-3" style="max-height: 100%; overflow-y: auto;">
                <table class="table table-hover align-middle p-2" id="qrTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Section</th>
                            <th>QR Code Image</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $qrResult = mysqli_query($conn, "SELECT * FROM generatedqrcode");
                        while ($qr = mysqli_fetch_assoc($qrResult)) {
                        ?>
                            <tr>
                                <td><?= $qr['id'] ?></td>
                                <td><?= htmlspecialchars($qr['full_name']) ?></td>
                                <td><?= htmlspecialchars($qr['section']) ?></td>
                               <td>
                                <img src="https://quickchart.io/qr?text=<?= urlencode($qr['generated_qrcode']) ?>" alt="QR Code" style="width:20%">
                            </td>

                                <td><?= $qr['created_at'] ?></td>
                                <td><?= $qr['updated_at'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info view-qr" data-id="<?= $qr['id'] ?>" data-code="<?= htmlspecialchars($qr['generated_qrcode']) ?>">
                                    <i class="bi bi-eye me-1"></i>View
                                </button>

                                   <button class="btn btn-sm btn-warning regenerate-qr" data-id="<?= $qr['id'] ?>">
                                    <i class="bi bi-arrow-repeat me-1"></i>Regenerate
                                </button>

                                    <button class="btn btn-sm btn-danger delete-qr" data-id="<?= $qr['id'] ?>">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add QR Code Modal -->
<div class="modal fade" id="addQRModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addQRForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Select Student</label>
                        <select class="form-select" name="id" id="student_id" required>
                            <option value="" disabled selected>Choose student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>"
                                    data-name="<?= htmlspecialchars($student['name']) ?>"
                                    data-section="<?= htmlspecialchars($student['section']) ?>">
                                    <?= htmlspecialchars($student['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="full_name" id="qr_full_name">
                    <input type="hidden" name="section" id="qr_section">
                    <!-- <div class="mb-3">
                        <label for="generated_qrcode" class="form-label">QR Code Text</label>
                        <input type="text" class="form-control" name="generated_qrcode" required>
                    </div> -->
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add QR Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View QR Modal -->
<div class="modal fade" id="viewQRModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header">
        <h5 class="modal-title">QR Code Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
         <img id="previewQRImg" src="" alt="QR Code" class="img-fluid border shadow-lg rounded zoom-img" style="max-width: 500px; width: 100%;">
        <p class="mb-0 text-muted"><small id="qrTextPreview"></small></p>
      </div>
    </div>
  </div>
</div>

<!-- DataTables CSS and JS for Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" />
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- SCRIPT: Handle dropdown & AJAX -->
<script>
      $(document).ready(function () {
    $('#qrTable').DataTable({
        scrollY: '650px',           // Adjust height for approx. 10 rows
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        ordering: true,
        columnDefs: [
            { orderable: false, targets: -1 }
        ],
        dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
        language: {
            lengthMenu: "Show _MENU_ entries"
        }
    });
});
document.addEventListener('DOMContentLoaded', function () {
    const addQRForm = document.getElementById('addQRForm');
    const qrFullNameInput = document.getElementById('qr_full_name');
    const qrSectionInput = document.getElementById('qr_section');
    const studentSelect = document.getElementById('student_id');

    function showToast(message, color = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${color} alert-dismissible fade show`;
        toast.role = 'alert';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.getElementById('notificationContainer').appendChild(toast);
        setTimeout(() => {
            toast.classList.remove('show');
            toast.remove();
        }, 4000);
    }

    // Fill full name and section on select
    studentSelect.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        qrFullNameInput.value = selected.getAttribute('data-name');
        qrSectionInput.value = selected.getAttribute('data-section');
    });

  addQRForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(addQRForm);

    fetch('generateqr/processes/generate_qrcode.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addQRModal'));
            modal.hide();

            showToast('QR Code generated successfully!', 'success');

            // Create new row HTML
            const newRowHTML = `
                <tr data-id="${data.qr.id}" class="highlight-row">
                    <td>${data.qr.id}</td>
                    <td>${data.qr.full_name}</td>
                    <td>${data.qr.section}</td>
                    <td><img src="https://quickchart.io/qr?text=${encodeURIComponent(data.qr.generated_qrcode)}" style="width:20%"></td>
                    <td>${data.qr.created_at}</td>
                    <td>${data.qr.updated_at}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-qr" data-id="${data.qr.id}" data-code="${data.qr.generated_qrcode}">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                        <button class="btn btn-sm btn-warning regenerate-qr" data-id="${data.qr.id}">
                            <i class="bi bi-arrow-repeat me-1"></i>Regenerate
                        </button>
                        <button class="btn btn-sm btn-danger delete-qr" data-id="${data.qr.id}">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </td>
                </tr>
            `;

            const tbody = document.querySelector('#qrTable tbody');
            tbody.insertAdjacentHTML('beforeend', newRowHTML);

            // Sort rows by ID descending
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const idA = parseInt(a.children[0].textContent.trim());
                const idB = parseInt(b.children[0].textContent.trim());
                return idA - idB;
            });
            rows.forEach(row => tbody.appendChild(row));

            // Re-bind buttons
            bindQRButtons();

            // Highlight and scroll to the new row
            const newRow = tbody.querySelector(`tr[data-id="${data.qr.id}"]`);
            if (newRow) {
                newRow.classList.add('highlight-row');
                newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Remove highlight after 2 seconds
                setTimeout(() => {
                    newRow.classList.remove('highlight-row');
                }, 2000);
            }

            // Reset form
            addQRForm.reset();
        } else {
            showToast(data.message || 'Failed to generate QR Code.', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Something went wrong. Please try again.', 'danger');
    });
});

    function bindQRButtons() {
        document.querySelectorAll('.view-qr').forEach(button => {
            button.onclick = function () {
                const qrText = this.getAttribute('data-code');
                const img = document.getElementById('previewQRImg');
                const preview = document.getElementById('qrTextPreview');
                img.src = 'https://quickchart.io/qr?text=' + encodeURIComponent(qrText);
                preview.textContent = qrText;
                new bootstrap.Modal(document.getElementById('viewQRModal')).show();
            };
        });

      document.querySelectorAll('.regenerate-qr').forEach(button => {
    button.onclick = function () {
        const qrId = this.getAttribute('data-id');
        if (!confirm('Are you sure you want to regenerate the QR code?')) return;

        fetch('generateqr/processes/regenerate_qrcode.php?id=' + qrId)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.qr) {
                    showToast('QR Code regenerated successfully!', 'success');

                    const row = this.closest('tr');
                    const newQRText = data.qr.generated_qrcode;
                    const newQRUrl = 'https://quickchart.io/qr?text=' + encodeURIComponent(newQRText);

                    // Update QR image
                    const qrImg = row.querySelector('img');
                    qrImg.src = newQRUrl;

                    // Update updated_at timestamp
                    row.querySelectorAll('td')[5].textContent = data.qr.updated_at;

                    // Update View button data-code
                    const viewBtn = row.querySelector('.view-qr');
                    viewBtn.setAttribute('data-code', newQRText);

                    // Auto-open View Modal with new QR
                    document.getElementById('previewQRImg').src = newQRUrl;
                    document.getElementById('qrTextPreview').textContent = newQRText;
                    new bootstrap.Modal(document.getElementById('viewQRModal')).show();
                } else {
                    showToast(data.message || 'Regeneration failed.', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error occurred during regeneration.', 'danger');
            });
    };
});

        document.querySelectorAll('.delete-qr').forEach(button => {
            button.onclick = function () {
                const qrId = this.getAttribute('data-id');
                if (!confirm('Are you sure you want to delete this QR code?')) return;

                fetch('generateqr/processes/delete_qrcode.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({ id: qrId })
})
.then(res => res.json())
.then(data => {
    if (data.status === 'success') {
        showToast('QR Code deleted.', 'success');
        this.closest('tr').remove();
    } else {
        showToast(data.message || 'Delete failed.', 'danger');
    }
});

            };
        });
    }

    // Initial binding
    bindQRButtons();
});

(function AntiScreenshotModule() {
    const overlay = document.getElementById('antiScreenshotOverlay');
    const alertBox = document.getElementById('screenshotAlert');

    function flashOverlay(duration = 2000) {
        if (!overlay) return;
        overlay.style.display = 'block';
        overlay.style.opacity = '1';
        setTimeout(() => {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 1000);
        }, duration);
    }

    function showScreenshotAlert() {
        if (!alertBox) return;
        alertBox.style.display = 'flex';
        alertBox.classList.add('show');
        setTimeout(() => {
            alertBox.style.display = 'none';
            alertBox.classList.remove('show');
        }, 3000);
    }
document.addEventListener("visibilitychange", function () {
  if (document.hidden) {
    document.body.style.filter = "blur(20px)";
  } else {
    document.body.style.filter = "none";
  }
});

    function blockDangerousKeys(e) {
        const key = e.key.toLowerCase();

        // Common screenshot triggers
        if (
            key === "printscreen" ||
            (e.ctrlKey && e.shiftKey && key === "s") ||  // Ctrl+Shift+S (Snipping Tool trigger on some systems)
            (e.metaKey && e.shiftKey && ["3", "4", "5"].includes(key)) || // macOS
            (e.ctrlKey && key === "p")
        ) {
            e.preventDefault();
            navigator.clipboard.writeText("");
            flashOverlay();
            showScreenshotAlert();
        }

        // Developer tools blocking
        if (
            key === "f12" ||
            (e.ctrlKey && e.shiftKey && ["i", "j", "c"].includes(key)) ||
            (e.ctrlKey && key === "u")
        ) {
            e.preventDefault();
            flashOverlay();
            showScreenshotAlert();
        }
    }

    // Optional: blur screen when tab is hidden (might catch snipping attempts)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            overlay.style.display = 'block';
            overlay.style.opacity = '1';
        } else {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 500);
        }
    });

    document.addEventListener('keydown', blockDangerousKeys);
})();


</script>



<!-- <script src="../assets/js/antiscreenshot.js"></script> -->
