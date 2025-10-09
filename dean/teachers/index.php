<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate login for testing (remove in production)
$_SESSION['user_type'] = 'dean';

// Protect this page: Allow only dean users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header("Location: /Project/login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

$base_url = '/Project/dean/teachers/processes';

// Get current dean ID
$dean_id = $_SESSION['t_id'] ?? null;
if (!$dean_id) {
    die('Dean ID not found in session.');
}

// Get the department of the dean
$dept_stmt = $conn->prepare("SELECT t_department FROM teachers WHERE t_id = ?");
$dept_stmt->bind_param("i", $dean_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();

if ($dept_result->num_rows === 0) {
    die('Dean record not found.');
}

$dean = $dept_result->fetch_assoc();
$dean_department = $conn->real_escape_string($dean['t_department']);
$dept_stmt->close();

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = "WHERE t.t_department = '$dean_department'";

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $search_condition .= " AND (
        t.t_fname LIKE '%$safe_search%' OR
        t.t_lname LIKE '%$safe_search%' OR
        t.t_id LIKE '%$safe_search%'
    )";
}

// Final SQL query
$sql = "
    SELECT t.*, CONCAT(t.t_fname, ' ', IFNULL(t.t_mname, ''), ' ', t.t_lname, ' ', IFNULL(t.t_suffix, '')) AS full_name,
           t.t_password
    FROM teachers t 
    $search_condition
    GROUP BY t.t_id
    ORDER BY t.t_lname ASC
";

$result = $conn->query($sql);
?>


<style>

td{
    border:none;
}
/* Password cell specific styles */
.td-password {
    position: relative;
    padding: 0 !important;
    text-align: center;
}

.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
    height: 100%;
}

.dots, .real-password {
    display: inline-block;
    text-align: center;
    width: auto;
    margin: 0 auto;
}

.eye-button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Clean up hover states */
.table-hover tbody tr:hover td {
    background-color: rgba(61, 82, 160, 0.05) !important;
}

/* Add these styles */
.alert {
    min-width: 300px;
    max-width: 600px;
    border: none;
    border-left: 4px solid;
    text-align: center;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.alert-success {
    background-color: #d1e7dd;
    border-left-color: #198754;
    color: #0f5132;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert.fade.show {
    animation: slideIn 0.3s ease-out;
}

/* Success message styles */
.success-message {
    background-color: #d1e7dd;
    border-left: 4px solid #198754;
    color: #0f5132;
    padding: 12px 20px;
    border-radius: 4px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    font-weight: 500;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px) translateX(-50%);
        opacity: 0;
    }
    to {
        transform: translateY(0) translateX(-50%);
        opacity: 1;
    }
}

/* Success message styles */
.message-notification {
    background-color: #d1e7dd;
    color: #0f5132;
    padding: 12px 24px;
    border-radius: 4px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    opacity: 0;
    transform: translateY(-20px) translateX(-50%);
    transition: all 0.3s ease;
}

.message-notification.show {
    opacity: 1;
    transform: translateY(0) translateX(-50%);
}

.message-notification i {
    font-size: 1.2em;
}

/* Alert Modal Styles */
#alertModal .modal-content {
    border-width: 2px;
}

#alertModal .modal-body i {
    display: block;
    margin: 0 auto;
}

#alertModal .modal-header {
    padding: 1rem 1rem 0;
}

#alertModal .btn-close:focus {
    box-shadow: none;
}

#alertModal p {
    color: #666;
}

/* Fix button hover effects */
.btn-group .btn {
    transition: background-color 0.2s ease, color 0.2s ease;
    transform: none !important;
}

.btn-group .btn:hover {
    transform: none !important;
}

.btn-group .btn:active {
    transform: none !important;
}

/* Update edit button hover styles */
.btn-edit-student:hover, .btn-edit-teacher:hover {
    background: linear-gradient(145deg, #2E4190 0%, #6180C8 100%) !important;
    color: white !important;
    border: none;
}
</style>
<link rel="stylesheet" href="../../assets/css/content.css">
<script src="../../assets/js/showAlert.js"></script>
<center>
    <div class="container-fluid p-0">
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Manage Teachers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Teachers</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Teacher
        </button>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Teachers List</h5>
            <form id="searchForm" class="d-flex align-items-center gap-2" style="width: 50%;">
                <input type="hidden" name="page" value="teachers">
                <div class="flex-grow-1">
                    <input type="text" class="form-control" name="search" placeholder="Search by name or ID..." oninput="delayedSubmit()" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Search</button>
            </form>
        </div>
            <div class="table-responsive p-3">
                <table id="teachersTable" class="table table-hover align-middle p-2">   
                    <thead>
                        <tr class="px-2 text-center">
                            <th  >ID</th>
                            <th  >Full Name</th>
                            <th  >Gender</th>
                            <th  >Birthdate</th>
                            <th  >Age</th>
                            <th  >Contact</th>
                            <th  >Email</th>
                            <th  >Status</th>
                            <th >Actions</th>
                        </tr>
                    </thead>
                    <tbody id="teachersTableBody" class="text-center">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-teacher-id="<?php echo htmlspecialchars($row['t_id']); ?>">
                                    <td><?php echo htmlspecialchars($row['t_id']); ?></td>
                                    <td>
                                    <?php
                                        $lname = htmlspecialchars($row['t_lname']);
                                        $fname = htmlspecialchars($row['t_fname']);
                                        $mname = $row['t_mname'] ? htmlspecialchars($row['t_mname'][0]) . '.' : '';
                                        $suffix = htmlspecialchars($row['t_suffix'] ?? '');
                                        echo "$lname, $fname $mname $suffix";
                                    ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['t_gender']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['t_bdate'])); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['t_age']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['t_cnum']); ?></td>
                                    <td><?php echo htmlspecialchars($row['t_email']); ?></td>
                                    <td class="text-center teacher-status">
                                        <span class="badge bg-<?php echo $row['t_status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($row['t_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary px-2 btn-edit-teacher" data-bs-toggle="modal" data-bs-target="#deditTeacherModal" data-teacher-id="<?php echo $row['t_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger px-2 btn-delete-teacher" data-teacher-id="<?php echo $row['t_id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No teacher information found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</center>


<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTeacherForm" method="POST" novalidate>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="t_fname" id="add_t_fname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="t_lname" id="add_t_lname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="t_mname" pattern="[A-Za-z\-\s]*">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="t_suffix" pattern="[A-Za-z\-\s\.]*">
                            <div class="invalid-feedback">Please enter a valid suffix</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="t_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="t_bdate" id="add_t_bdate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="t_cnum" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" title="Please enter a valid 11-digit phone number starting with 09" required>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="t_email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="t_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addTeacherForm" class="btn btn-primary">Add Teacher</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="deditTeacherModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="deditTeacherForm">
                    <input type="hidden" name="t_id" id="edit_t_id">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="t_fname" id="edit_t_fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="t_lname" id="edit_t_lname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="t_mname" id="edit_t_mname">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="t_suffix" id="edit_t_suffix">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="t_gender" id="edit_t_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="t_bdate" id="edit_t_bdate" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="t_cnum" id="edit_t_cnum" pattern="^09[0-9]{9}$" maxlength="11" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="t_email" id="edit_t_email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="t_status" id="edit_t_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="deditTeacherForm" class="btn btn-primary">Update Teacher</button>
            </div>
        </div>
    </div>
</div>

 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>
<script>
     $(document).ready(function () {
    $('#teachersTable').DataTable({
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
    const tbody = document.getElementById('teachersTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((a, b) => {
        const idA = parseInt(a.dataset.teacherId);
        const idB = parseInt(b.dataset.teacherId);
        return idA - idB; // ascending order
    });

    rows.forEach(row => tbody.appendChild(row)); // re-append in sorted order
});
//Add Teacher
document.addEventListener('DOMContentLoaded', function () {
    const addTeacherForm = document.getElementById('addTeacherForm');
    const addTeacherModal = new bootstrap.Modal(document.getElementById('addTeacherModal'));
    const messageContainer = document.getElementById('messageContainer');

    // Auto-generate password based on name and birthdate
    const fnameInput = document.getElementById('add_t_fname');
    const lnameInput = document.getElementById('add_t_lname');
    const bdateInput = document.getElementById('add_t_bdate');
    const passwordInput = document.getElementById('add_t_password');

    function generatePassword() {
        const fname = fnameInput.value.trim().toLowerCase();
        const lname = lnameInput.value.trim().toLowerCase();
        const bdate = bdateInput.value.replace(/-/g, '');
        if (fname && lname && bdate) {
            passwordInput.value = fname.charAt(0) + lname + bdate;
        } else {
            passwordInput.value = '';
        }
    }

    fnameInput.addEventListener('input', generatePassword);
    lnameInput.addEventListener('input', generatePassword);
    bdateInput.addEventListener('input', generatePassword);

    addTeacherForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!addTeacherForm.checkValidity()) {
            addTeacherForm.classList.add('was-validated');
            return;
        }

        const formData = new FormData(addTeacherForm);

        fetch('/Project/dean/teachers/processes/add_teacher.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Teacher added successfully', 'success');
                addTeacherModal.hide();
                addTeacherForm.reset();
                addTeacherForm.classList.remove('was-validated');
                // Optionally, reload or update the teachers table
                location.reload();
            } else {
                showMessage(data.message || 'Failed to add teacher', 'danger');
            }
        })
        .catch(() => {
            showMessage('An error occurred while adding teacher', 'danger');
        });
    });

    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        messageContainer.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.classList.add('hide');
            alertDiv.addEventListener('transitionend', () => alertDiv.remove());
        }, 4000);
    }
});

//Edit Teacher 

document.addEventListener('DOMContentLoaded', function () {
    const editTeacherForm = document.getElementById('deditTeacherForm');
    const editTeacherModal = new bootstrap.Modal(document.getElementById('deditTeacherModal'));
    const messageContainer = document.getElementById('messageContainer');

    // Load teacher data into edit form when edit button is clicked
    document.querySelectorAll('.btn-edit-teacher').forEach(button => {
        button.addEventListener('click', function () {
            const teacherId = this.getAttribute('data-teacher-id');
            fetch(`/Project/dean/teachers/processes/get_teacher.php?id=${teacherId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const teacher = data.data;
                        editTeacherForm.t_id.value = teacher.t_id;
                        editTeacherForm.t_fname.value = teacher.t_fname;
                        editTeacherForm.t_lname.value = teacher.t_lname;
                        editTeacherForm.t_mname.value = teacher.t_mname;
                        editTeacherForm.t_suffix.value = teacher.t_suffix;
                        editTeacherForm.t_gender.value = teacher.t_gender;
                        editTeacherForm.t_bdate.value = teacher.t_bdate;
                        editTeacherForm.t_cnum.value = teacher.t_cnum;
                        editTeacherForm.t_email.value = teacher.t_email;
                        editTeacherForm.t_status.value = teacher.t_status;
                        editTeacherForm.t_password.value = teacher.t_password;
                    } else {
                        showMessage(data.message || 'Failed to load teacher data', 'danger');
                        editTeacherModal.hide();
                    }
                })
                .catch(() => {
                    showMessage('An error occurred while loading teacher data', 'danger');
                    editTeacherModal.hide();
                });
        });
    });

    editTeacherForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!editTeacherForm.checkValidity()) {
            editTeacherForm.classList.add('was-validated');
            return;
        }

        const formData = new FormData(editTeacherForm);

        fetch('/Project/dean/teachers/processes/update_teacher.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showMessage('Teacher updated successfully', 'success');
                editTeacherModal.hide();
                editTeacherForm.classList.remove('was-validated');
                // Optionally, reload or update the teachers table
                location.reload();
            } else {
                showMessage(data.message || 'Failed to update teacher', 'danger');
            }
        })
        .catch(() => {
            showMessage('An error occurred while updating teacher', 'danger');
        });
    });

    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        messageContainer.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.classList.add('hide');
            alertDiv.addEventListener('transitionend', () => alertDiv.remove());
        }, 4000);
    }
});

//Delete Teacher
document.addEventListener('DOMContentLoaded', function () {
    const messageContainer = document.getElementById('messageContainer');

    document.querySelectorAll('.btn-delete-teacher').forEach(button => {
        button.addEventListener('click', function () {
            const teacherId = this.getAttribute('data-teacher-id');
            if (!confirm('Are you sure you want to delete this teacher?')) {
                return;
            }

            const formData = new FormData();
            formData.append('t_id', teacherId);

            fetch('/Project/dean/teachers/processes/delete_teacher.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Teacher deleted successfully', 'success');
                    // Optionally, remove the row from the table
                    const row = document.querySelector(`tr[data-teacher-id="${teacherId}"]`);
                    if (row) {
                        row.remove();
                    }
                } else {
                    showMessage(data.message || 'Failed to delete teacher', 'danger');
                }
            })
            .catch(() => {
                showMessage('An error occurred while deleting teacher', 'danger');
            });
        });
    });

    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        messageContainer.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.classList.remove('show');
            alertDiv.classList.add('hide');
            alertDiv.addEventListener('transitionend', () => alertDiv.remove());
        }, 4000);
    }
});
</script>
</body>
</html>
