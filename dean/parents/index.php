<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_type'] = 'dean';

// Protect this page: Allow only dean users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header("Location: /Project/login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

$base_url = '/Project/dean/parents/processes';

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search); // prevent SQL injection
    $search_condition = "WHERE p.p_fname LIKE '%$safe_search%' OR p.p_lname LIKE '%$safe_search%' OR p.p_id LIKE '%$safe_search%'";
}

// Full query to get parents with child's name and section
$sql = "
    SELECT p.*, CONCAT(s.s_fname, ' ', IFNULL(s.s_mname, ''), ' ', s.s_lname, ' ', IFNULL(s.s_suffix, '')) AS child_full_name,
           sec.section_code, p.p_password_plain
    FROM parents p
    LEFT JOIN parent_student ps ON p.p_id = ps.p_id
    LEFT JOIN students s ON ps.s_id = s.s_id
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    $search_condition
    GROUP BY p.p_id
    ORDER BY p.p_lname ASC
";

$result = $conn->query($sql);
?>
<link rel="stylesheet" href="../../assets/css/content.css">
<script src="../../assets/js/showAlert.js"></script>
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
<center>
<div class="container-fluid p-0">
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Manage Parents</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Parents</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Parent
        </button>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Parents List</h5>
            <form id="searchForm" class="d-flex align-items-center gap-2" style="width: 50%;">
                <input type="hidden" name="page" value="parents">
                <div class="flex-grow-1">
                    <input type="text" class="form-control" name="search" placeholder="Search by name or ID..." oninput="delayedSubmit()" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Search</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive p-3" style="max-height: 100%; overflow-y: auto;">
                <table id="parentsTable" class="table table-hover align-middle p-2">
                    <thead>
                        <tr class="px-2 text-center">
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Birthdate</th>
                            <th>Age</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Password</th>
                            <th>Status</th>
                            <th>Child's Name - Section</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="parentsTableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-parent-id="<?php echo htmlspecialchars($row['p_id']); ?>">
                                    <td><?php echo htmlspecialchars($row['p_id']); ?></td>
                                   <td>
                                    <?php
                                        $lname = htmlspecialchars($row['p_lname']);
                                        $fname = htmlspecialchars($row['p_fname']);
                                        $mname = $row['p_mname'] ? htmlspecialchars($row['p_mname'][0]) . '.' : '';
                                        $suffix = htmlspecialchars($row['p_suffix'] ?? '');
                                        echo "$lname, $fname $mname $suffix";
                                    ?>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['p_gender']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['p_bdate'])); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['p_age']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['p_cnum']); ?></td>
                                    <td><?php echo htmlspecialchars($row['p_email']); ?></td>
                                    <td class="td-password">
                                        <div class="password-wrapper">
                                            <span class="dots">••••••••</span>
                                            <span class="real-password" style="display: none;"><?php echo htmlspecialchars($row['p_password_plain']);?></span>
                                            <button type="button" class="eye-button">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                   <td class="text-center student-status">
                                        <span class="badge bg-<?php echo $row['p_status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($row['p_status']); ?>
                                        </span>
                                    </td>

                                    <td class="text-center"><?php echo htmlspecialchars($row['child_full_name'] . ' - ' . ($row['section_code'] ?? 'No child record.')); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary px-2 btn-edit-parent" data-bs-toggle="modal" data-bs-target="#editParentModal" data-parent-id="<?php echo $row['p_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger px-2 btn-delete-parent" data-parent-id="<?php echo $row['p_id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                    
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="14" class="text-center">No parent information found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</center>

<!-- Add Parent Modal -->
<div class="modal fade" id="addParentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addParentForm" method="POST" novalidate>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="p_fname" id="add_p_fname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="p_lname" id="add_p_lname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="p_mname" pattern="[A-Za-z\-\s]*">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="p_suffix" pattern="[A-Za-z\-\s\.]*">
                            <div class="invalid-feedback">Please enter a valid suffix</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="p_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="p_bdate" id="add_p_bdate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="p_cnum" pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" title="Please enter a valid 11-digit phone number starting with 09" required>
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="p_email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="text" name="p_password_plain" id="add_p_password_plain" class="form-control" readonly>
                            <small class="text-muted">Password will auto-generate based on name and birthdate</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="p_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Select Child (Optional)</label>
                            <select class="form-select" name="child_id">
                                <option value="">-- No Child Selected --</option>
                                <?php

$students_query = "
    SELECT 
        s.s_id, 
        s.s_fname, 
        s.s_lname, 
        s.s_mname, 
        sec.section_code 
    FROM students s
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
    LEFT JOIN sections sec ON ss.section_id = sec.section_id
    ORDER BY s.s_lname ASC
";

$students_result = $conn->query($students_query);

while ($student = $students_result->fetch_assoc()) {
    $full_name = $student['s_lname'] . ', ' . $student['s_fname'] .
                 (!empty($student['s_mname']) ? ' ' . $student['s_mname'] : '');
    $section = $student['section_code'] ?? 'No Section';

    echo "<option value='" . htmlspecialchars($student['s_id']) . "'>" . 
         htmlspecialchars("{$full_name} - {$section}") . "</option>";
}
?>

                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addParentForm" class="btn btn-primary">Add Parent</button>
            </div>
        </div>
    </div>
</div>


<!-- Edit Parent Modal -->
<div class="modal fade" id="editParentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editParentForm">
                    <input type="hidden" name="p_id" id="edit_p_id">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="p_fname" id="edit_p_fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="p_lname" id="edit_p_lname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="p_mname" id="edit_p_mname">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="p_suffix" id="edit_p_suffix">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="p_gender" id="edit_p_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="p_bdate" id="edit_p_bdate" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="p_cnum" id="edit_p_cnum" pattern="^09[0-9]{9}$" maxlength="11" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="p_email" id="edit_p_email" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="p_status" id="edit_p_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                         <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="p_password_plain" id="edit_p_password_plain" class="form-control" readonly>
                    </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Child</label>
                            <select class="form-select" name="child_id" id="edit_child_id">
                                <option value="">Select Child</option>
                                <!-- Options will be dynamically filled by jQuery -->
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editParentForm" class="btn btn-primary">Update Parent</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
    $('#parentsTable').DataTable({
        scrollY: '50vh',           // Adjust height for approx. 10 rows
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
    // Password toggle functionality
    $(document).on('click', '.eye-button', function() {
        const $wrapper = $(this).closest('.password-wrapper');
        const $dots = $wrapper.find('.dots');
        const $password = $wrapper.find('.real-password');
        const $icon = $(this).find('i');

        if ($dots.is(':visible')) {
            $dots.hide();
            $password.show();
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            $dots.show();
            $password.hide();
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('parentsTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((a, b) => {
        const idA = parseInt(a.dataset.parentId);
        const idB = parseInt(b.dataset.parentId);
        return idA - idB; // ascending order
    });

    rows.forEach(row => tbody.appendChild(row)); // re-append in sorted order
});

</script>

  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>

<script src="/Project/dean/parents/js/add_parent.js"></script>
<script src="/Project/dean/parents/js/edit_parent.js"></script>
<script src="/Project/dean/parents/js/delete_parent.js"></script>
</body>
