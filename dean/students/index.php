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

$dean_id = $_SESSION['t_id'] ?? null;
if (!$dean_id) {
    die("Dean not logged in.");
}

// ✅ 2. Get degrees assigned to this dean
$degree_stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE dean_id = ?");
$degree_stmt->bind_param("i", $dean_id);
$degree_stmt->execute();
$degree_result = $degree_stmt->get_result();

$degree_ids = [];
while ($row = $degree_result->fetch_assoc()) {
    $degree_ids[] = $row['degree_id'];
}
$degree_stmt->close();

if (empty($degree_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No degrees assigned to this dean.']);
    exit;
}

// ✅ 3. Build query parts
$placeholders = implode(',', array_fill(0, count($degree_ids), '?'));
$types = str_repeat('i', count($degree_ids));
$params = $degree_ids;

// ✅ 4. Handle optional search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if ($search !== '') {
    $search_condition = " AND (s.s_fname LIKE ? OR s.s_lname LIKE ? OR s.s_id LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

// ✅ 5. Final query
$sql = "
    SELECT 
        s.*,
        d.degree_id,
        d.degree_code,
        d.degree_name,
        p.p_id,
        p.p_fname,
        p.p_lname,
        p.p_mname,
        p.p_suffix,
        p.p_gender,
        p.p_bdate,
        p.p_cnum,
        p.p_email,
        p.p_status,
        p.p_password_plain
    FROM students s
    LEFT JOIN students_degrees sd ON s.s_id = sd.s_id
    LEFT JOIN degrees d ON sd.degree_id = d.degree_id
    LEFT JOIN parent_student ps ON s.s_id = ps.s_id
    LEFT JOIN parents p ON ps.p_id = p.p_id
    WHERE sd.degree_id IN ($placeholders)
    $search_condition
    ORDER BY s.s_lname ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ✅ 6. Build the response
$students = [];

while ($row = $result->fetch_assoc()) {
    $student = [
        's_id' => $row['s_id'],
        's_fname' => $row['s_fname'],
        's_lname' => $row['s_lname'],
        's_mname' => $row['s_mname'],
        's_suffix' => $row['s_suffix'],
        's_gender' => $row['s_gender'],
        's_bdate' => $row['s_bdate'],
        's_cnum' => $row['s_cnum'],
        's_email' => $row['s_email'],
        's_status' => $row['s_status'],
        'degree_id' => $row['degree_id'],
        'degree_code' => $row['degree_code'],
        'degree_name' => $row['degree_name'],
    ];

    if (!empty($row['p_id'])) {
        $student['parent'] = [
            'p_id' => $row['p_id'],
            'p_fname' => $row['p_fname'],
            'p_lname' => $row['p_lname'],
            'p_mname' => $row['p_mname'],
            'p_suffix' => $row['p_suffix'],
            'p_gender' => $row['p_gender'],
            'p_bdate' => $row['p_bdate'],
            'p_cnum' => $row['p_cnum'],
            'p_email' => $row['p_email'],
            'p_status' => $row['p_status'],
            'p_password_plain' => $row['p_password_plain'],
        ];
    }

    $students[] = $student;
}
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

?>
<link rel="stylesheet" href="../../assets/css/content.css">
<script src="../../assets/js/showAlert.js"></script>
<style>

.card-body{
    overflow: hidden !important;
}
td{
    border: none;

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
   <div class="container-fluid">
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Manage Students</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Student
        </button>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Students List</h5>
        </div>
        <div class="card-body">
         <div class="table-responsive">
                <table id="studentsTable" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="px-2" >ID</th>
                            <th>Student's Name</th>
                            <th class="px-2 text-center" >Gender</th>
                            <th class="px-2">Birthdate</th>
                            <th class="px-2 text-center" >Age</th>
                            <th class="px-2 text-center" >Contact</th>
                            <th class="px-2 text-center" >Email</th>
                            <th class="px-2 text-center" >Parent Name</th>
                            <th class="px-2 text-center" >Status</th>
                            <th class="px-2 text-center" >Degree</th>
                            <th class="px-2 text-center" >Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-student-id="<?php echo htmlspecialchars($row['s_id']); ?>">
                                    <td><?php echo htmlspecialchars($row['s_id']); ?></td>
                                    <td class="student-fullname">
                                        <?php
                                            // Format: LNAME, FIRSTNAME M. SUFFIX
                                            $lname = htmlspecialchars($row['s_lname']);
                                            $fname = htmlspecialchars($row['s_fname']);
                                            $mname = $row['s_mname'] ? ' ' . htmlspecialchars($row['s_mname'][0]) . '.' : '';
                                            $suffix = $row['s_suffix'] ? ' ' . htmlspecialchars($row['s_suffix']) : '';

                                            echo "$lname, $fname$mname$suffix";
                                        ?>
                                    </td>
                                    <td class="text-center student-gender"><?php echo htmlspecialchars($row['s_gender']); ?></td>
                                    <td class="student-bdate"><?php echo date('Y-m-d', strtotime($row['s_bdate'])); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['s_age']); ?></td>
                                    <td class="student-cnum text-center"><?php echo htmlspecialchars($row['s_cnum']); ?></td>
                                    <td class="text-truncate student-email"><?php echo htmlspecialchars($row['s_email']); ?></td>
                                    <td class="text-center">
                                        <?php
                                            $p_mi = $row['p_mname'] ? $row['p_mname'][0] . '.' : '';
                                            $p_suffix = $row['p_suffix'] ?? '';
                                            if (!empty($row['p_lname'])) {
                                                echo htmlspecialchars($row['p_lname'] . ', ' . $row['p_fname'] . ' ' . $p_mi . ' ' . $p_suffix);
                                            } else {
                                                echo '<span class="text-muted">No Parent</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center student-status">
                                        <span class="badge bg-<?php echo $row['s_status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($row['s_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center student-degree"><?php echo htmlspecialchars($row['degree_code']); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-primary px-2 btn-edit-student" data-bs-toggle="modal" data-bs-target="#editStudentModal" data-student-id="<?php echo $row['s_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger px-2 btn-delete-student" data-student-id="<?php echo $row['s_id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                                <tr>
                                    <td colspan="15" class="text-center">No student information found.</td>
                                </tr>
                            <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
                        </div> 
</center>


<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm" method="POST" novalidate>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="s_fname" 
                                   pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="s_lname" 
                                   pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="s_mname" 
                                   pattern="[A-Za-z\-\s]*">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="s_suffix" 
                                   pattern="[A-Za-z\-\s\.]*">
                            <div class="invalid-feedback">Please enter a valid suffix</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="s_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="s_bdate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="s_cnum" required
                                   pattern="^09[0-9]{9}$" maxlength="11"
                                   placeholder="09XXXXXXXXX"
                                   title="Please enter a valid 11-digit phone number starting with 09">
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="s_email" required
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Degree Program</label>
                            <select class="form-select" name="degree_id" required>
                                <option value="">Select Degree Program</option>
                                <?php
                                $degrees_query = "SELECT degree_id, degree_code, degree_name FROM degrees ORDER BY degree_name";
                                $degrees_result = $conn->query($degrees_query);
                                while ($degree = $degrees_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($degree['degree_id']) . "'>" . 
                                         htmlspecialchars($degree['degree_code'] . " - " . $degree['degree_name']) . "</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a degree program</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="s_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addStudentForm" class="btn btn-primary">Add Student</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editStudentForm">
                    <input type="hidden" name="s_id" id="edit_s_id">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="s_fname" id="edit_s_fname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="s_lname" id="edit_s_lname" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control" name="s_mname" id="edit_s_mname">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control" name="s_suffix" id="edit_s_suffix">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="s_gender" id="edit_s_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="s_bdate" id="edit_s_bdate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="s_cnum" id="edit_s_cnum" pattern="[0-9]{11}" maxlength="11" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="s_email" id="edit_s_email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="s_status" id="edit_s_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Degree Program</label>
                            <select class="form-select" name="degree_id" id="edit_degree_id" required>
                                <option value="">Select Degree Program</option>
                                <?php
                                $degrees_query = "SELECT degree_id, degree_code, degree_name FROM degrees ORDER BY degree_name";
                                $degrees_result = $conn->query($degrees_query);
                                while ($degree = $degrees_result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($degree['degree_id']) . "'>" . 
                                         htmlspecialchars($degree['degree_code'] . " - " . $degree['degree_name']) . "</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select a degree program</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editStudentForm" class="btn btn-primary">Update Student</button>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
     $(document).ready(function () {
    $('#studentsTable').DataTable({
        scrollY: '50vh', 
        scrollX:false,          // Adjust height for approx. 10 rows
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
$(document).ready(function() {
    // Initialize Bootstrap modals
    var addModal = new bootstrap.Modal(document.getElementById('addStudentModal'));
    var editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));

    // Adapt JavaScript code for students
    function ucwordsWithHyphen(str) {
        return str.toLowerCase().split(/[\s-]+/).map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    // Add input handlers for name fields
    $('#addStudentForm input[name="s_fname"], #addStudentForm input[name="s_lname"], #addStudentForm input[name="s_mname"], #addStudentForm input[name="s_suffix"]').on('input', function() {
        this.value = ucwordsWithHyphen(this.value);
    });

});
let searchTimer;
function delayedSubmit() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        document.getElementById('searchForm').submit();
    }, 400); // wait 400ms after typing
}
</script>

 <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>
<!-- Update script paths to use absolute paths -->
<script src="/Project/dean/students/js/add_student.js"></script>
<script src="/Project/dean/students/js/edit_student.js"></script>
<script src="/Project/dean/students/js/delete_student.js"></script>
</body>
</html>
