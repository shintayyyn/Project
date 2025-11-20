<?php
// =======================
// Session check
// =======================
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header("Location: /dean/login.php");
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

// =======================
// Get current active term
// =======================
$current_term = $conn->query("
    SELECT term_id, CONCAT(year_start, '-', year_end, ' | ', semester) AS term_label
    FROM academic_terms t
    JOIN academic_years ay ON ay.ay_id = t.ay_id
    WHERE t.is_active = 1
    LIMIT 1
")->fetch_assoc();

$current_term_id = $current_term['term_id'] ?? null;
$term_label = $current_term['term_label'] ?? '';

$base_url = '/dean/students/processes';

// =======================
// Get Dean's degree_id from sections they handle
// =======================
$dean_id = $_SESSION['t_id'] ?? null;
if (!$dean_id) {
    die('<div class="alert alert-danger">Dean ID not found in session.</div>');
}

$dean_query = "
    SELECT t_department AS degree_id
    FROM teachers
    WHERE t_id = " . intval($dean_id) . "
    LIMIT 1
";
$dean_result = $conn->query($dean_query);
$dean_degree_id = $dean_result ? ($dean_result->fetch_assoc()['degree_id'] ?? null) : null;

if (!$dean_degree_id) {
    die('<div class="alert alert-danger">No department linked to this dean.</div>');
}


// =======================
// Fetch students only under the dean's degree
// =======================
$sql = "
SELECT 
    s.*,
    COALESCE(sd.degree_id, '') AS degree_id,
    COALESCE(sd.degree_code, 'Not assigned') AS degree_code,
    COALESCE(sec_merged.section_id, sec_single.section_id, '') AS section_id,
    COALESCE(sec_merged.section_code, sec_single.section_code, 'Not yet assigned') AS section_code,
    CONCAT(
        COALESCE(p.p_lname, ''),
        COALESCE(CONCAT(' ', p.p_suffix), ''),
        ', ',
        COALESCE(p.p_fname, ''),
        CASE 
            WHEN p.p_mname IS NOT NULL AND p.p_mname <> '' 
            THEN CONCAT(' ', LEFT(p.p_mname, 1), '.') 
            ELSE '' 
        END
    ) AS parent_fullname,
    COALESCE(GROUP_CONCAT(DISTINCT se.subject_code ORDER BY se.updated_at DESC SEPARATOR ', '), 'None') AS enrolled_subjects,
    CASE WHEN s.term_id = {$current_term_id} THEN 1 ELSE 0 END AS is_active_term
FROM students s
LEFT JOIN (
    SELECT sd1.s_id, sd1.degree_id, sd1.degree_code, sd1.term_id
    FROM students_degrees sd1
    INNER JOIN (
        SELECT s_id, MAX(term_id) AS max_term
        FROM students_degrees
        GROUP BY s_id
    ) sd2 ON sd1.s_id = sd2.s_id AND sd1.term_id = sd2.max_term
) AS sd ON s.s_id = sd.s_id
LEFT JOIN parent_student ps ON s.s_id = ps.s_id
LEFT JOIN parents p ON ps.p_id = p.p_id
LEFT JOIN (
    SELECT ss.s_id, sec.section_id, sec.section_code
    FROM students_sections ss
    JOIN sections sec ON ss.section_id = sec.section_id
) AS sec_single ON s.s_id = sec_single.s_id AND s.is_regular = 1
LEFT JOIN (
    SELECT se.s_id,
           GROUP_CONCAT(DISTINCT se.section_code ORDER BY se.updated_at DESC SEPARATOR ', ') AS section_code,
           GROUP_CONCAT(DISTINCT sec.section_id ORDER BY se.updated_at DESC SEPARATOR ',') AS section_id
    FROM subject_enrollments se
    JOIN sections sec ON se.section_code = sec.section_code
    WHERE se.is_status = 1 AND se.enrollment_status = 'Enrolled'
    GROUP BY se.s_id
) AS sec_merged ON s.s_id = sec_merged.s_id AND s.is_regular != 1
LEFT JOIN subject_enrollments se ON s.s_id = se.s_id

-- ✅ Only students under this dean's department
WHERE sd.degree_id = {$dean_degree_id}

GROUP BY s.s_id
ORDER BY is_active_term DESC, s.s_id DESC, s.s_created_at DESC
";

$result = $conn->query($sql);

?>

<style>
.active-row {
  background-color: #ffffffff !important;
  transition: background-color 0.3s ease;
}


.profile-avatar {
    background-color: #033A70;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
}

.card-body-empty {
    text-align: center;
    padding: 40px 10px;
    color: #666;
    font-size: 1.1rem;
}

.card-body-empty i {
    font-size: 2rem;
    color: #aaa;
    display: block;
    margin-bottom: 10px;
}

.card-body, table {
    overflow: hidden;
}

.table-hover tbody tr:hover td {
    background-color: rgba(61, 82, 160, 0.05) !important;
}

.action-buttons .btn {
    min-width: 80px;
}

#studentsTable td:nth-child(2) {
  white-space: normal !important;
  word-wrap: break-word !important;
}
.card-body, table {
    overflow: hidden;
}
.dataTables_wrapper {
    overflow: hidden;
}

</style>
<main>
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold">Manage Students</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Students</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Student
        </button>
    </div>

    <!-- Students Lists -->
    <div class="row g-3">
    <!-- Students List Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm ">
           <div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h5 class="mb-0 fw-bold">Students List</h5>
        <small>
            Unassigned Students: 
            <span id="unassignedCounter" class="badge bg-danger ms-2">0</span>
        </small>
    </div>
    <button id="exportStudentsBtn" class="btn btn-warning fw-bold w-auto">Export Students</button>
</div>

            <div class="card-body">
                <div class="table-responsive p-3">
                    <table id="studentsTable" class="display nowrap table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Section</th>
                            </tr>
                        </thead>
                      <tbody>
                   <?php while($row = $result->fetch_assoc()): ?>
                       <?php
$lastName = trim($row['s_lname']);
$suffix = !empty($row['s_suffix']) ? ' ' . trim($row['s_suffix']) : '';
$firstName = trim($row['s_fname']);
$middleInitial = !empty($row['s_mname']) ? ' ' . strtoupper($row['s_mname'][0]) . '.' : '';
$fullName = "{$lastName}{$suffix}, {$firstName}{$middleInitial}";

// Generate initials for avatar
$initials = strtoupper(substr($row['s_fname'], 0, 1) . substr($row['s_lname'], 0, 1));

// Avatar path
$avatar_path = "/uploads/students/student_{$row['s_id']}.jpg";
$server_path = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;
$avatar_exists = file_exists($server_path);

// Determine active term
$term_id = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")
                ->fetch_assoc()['term_id'] ?? 0;

// Initialize section_code
$section_code = '';

// Fetch section_code depending on is_regular
if ($term_id) {
    if ($row['is_regular'] == 1) {
        // Regular student: fetch from students_sections for active term
        $stmtSec = $conn->prepare("
            SELECT section_code
            FROM students_sections
            WHERE s_id = ? AND term_id = ?
            ORDER BY ss_id DESC
            LIMIT 1
        ");
    } else {
        // Irregular student: fetch from subject_enrollments for active term
        $stmtSec = $conn->prepare("
            SELECT section_code
            FROM subject_enrollments
            WHERE s_id = ? AND term_id = ?
            ORDER BY se_id DESC
            LIMIT 1
        ");
    }

    $stmtSec->bind_param("ii", $row['s_id'], $term_id);
    $stmtSec->execute();
    $secResult = $stmtSec->get_result()->fetch_assoc();
    $stmtSec->close();

    $section_code = $secResult['section_code'] ?? '';
}

// Extract year level from section_code (if present)
$yearLevel = null;
if (!empty($section_code)) {
    $parts = explode(' ', $section_code); // e.g., ['BSHM', '3A']
    if (isset($parts[1])) {
        preg_match('/\d+/', $parts[1], $matches);
        if (!empty($matches)) {
            $yearLevel = (int)$matches[0];
        }
    }
}

$row['section_code'] = $section_code; // assign for display
?>
 <tr class="student-row"
    data-student-id="<?= htmlspecialchars($row['s_id']); ?>"
    data-idcode="<?= htmlspecialchars($row['idcode']); ?>"
    data-fullname="<?= htmlspecialchars($fullName); ?>"
    data-email="<?= htmlspecialchars($row['s_email']); ?>"
    data-parent="<?= !empty($row['parent_fullname']) ? htmlspecialchars($row['parent_fullname']) : 'Solo (No Parent)'; ?>"
    data-status="<?= htmlspecialchars($row['s_status']); ?>"
    data-gender="<?= htmlspecialchars($row['s_gender']); ?>"
    data-bdate="<?= date('Y-m-d', strtotime($row['s_bdate'])); ?>"
    data-age="<?= htmlspecialchars($row['s_age']); ?>"
    data-cnum="<?= htmlspecialchars($row['s_cnum']); ?>"
    data-address="<?= htmlspecialchars($row['s_address'] ?? ''); ?>"
    data-section="<?= htmlspecialchars($row['section_code'] ?? ''); ?>"
    data-is_regular="<?= htmlspecialchars($row['is_regular'] ?? ''); ?>"
    data-degree="<?= htmlspecialchars($row['degree_code'] ?? ''); ?>"
    data-year_level="<?= htmlspecialchars($row['year_level'] ?? ''); ?>"
>

        <td><?= htmlspecialchars($row['idcode']); ?></td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <?php if ($avatar_exists): ?>
                    <img src="<?= $avatar_path ?>" alt="Avatar" class="rounded-circle"
                         style="width:35px; height:35px; object-fit:cover;">
                <?php else: ?>
                    <div class="profile-avatar rounded-circle text-center text-white"
                         style="width:35px; height:35px; font-size:0.9rem; line-height:35px;">
                        <?= $initials ?>
                    </div>
                <?php endif; ?>
                <?= $fullName ?>
            </div>
        </td>
       <td>
    <?php if (!empty($row['section_code'])): ?>
        <span class="badge rounded-pill" style="background: var(--tertiary); color: var(--primary);">
            <?= htmlspecialchars($row['section_code']); ?>
        </span>
    <?php else: ?>
        <span class="badge rounded-pill bg-secondary">Not yet assigned</span>
    <?php endif; ?>
</td>
    </tr>
<?php endwhile; ?>
</tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information + Actions -->
<div class="col-lg-4">
    <div class="card shadow-sm flex-fill" id="studentDetailsCard">
       <div class="card-header d-flex justify-content-between align-items-center">
  <h5 class="mb-0 fw-bold">Personal Information</h5>
</div>

        <div class="card-body">
            
                    <div class="card-body-empty" id="noStudentSelected">
               <i class="bi bi-person-lines-fill display-4 d-block mb-2"></i>
                No student selected.
                <p class="small fst-italic">Click a row in the table to view teacher information.</p>
            </div>

            <!-- Details content (hidden by default) -->
            <div id="studentDetailsContent" class="d-none flex-fill">
                <!-- Avatar -->
                <div class="mb-3 d-flex flex-column align-items-center">
                    <img id="studentAvatar" src="" class=" rounded-circle shadow mb-2 d-none" style="width:100px;height:100px;object-fit:cover;">
                    <div id="studentInitials" class="profile-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold shadow mb-2" style="width:100px;height:100px;font-size:32px;color:#fff;">
                        ??
                    </div>
                </div>

                 <div class="action-buttons mb-2 text-center">
                    <button type="button" class="btn btn-sm btn-primary btn-edit-student">
                    <i class="bi bi-pencil-square me-1"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-delete-student">
                    <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </div>

                <!-- Student Info -->
                <div class="text-start flex-fill" id="studentDetails">
                    <h5 class="fw-bold"><span id="detailFullName"></span></h5>
                    <p class="text-muted  mb-1"><strong>ID:</strong> <span id="detailId"></span></p>
                    <p class="text-muted  mb-1"><strong>Email:</strong> <span id="detailEmail"></span></p>
                    <p class="text-muted  mb-1"><strong>Parent:</strong> <span id="detailParent"></span></p>
                    <p class="text-muted  mb-1"><strong>Status:</strong> <span id="detailStatus"></span></p>
                    <p class="text-muted  mb-1"><strong>Gender:</strong> <span id="detailGender"></span></p>
                    <p class="text-muted  mb-1"><strong>Birthdate:</strong> <span id="detailBdate"></span></p>
                    <p class="text-muted  mb-1"><strong>Age:</strong> <span id="detailAge"></span></p>
                    <p class="text-muted  mb-1"><strong>Contact:</strong> <span id="detailCnum"></span></p>
                    <p class="text-muted  mb-1"><strong>Address:</strong> <span id="detailAddress"></span></p>
                    <p class="text-muted  mb-1"><strong>Degree:</strong> <span id="detailDegree"></span></p>
                    <p class="text-muted mb-1"><strong>Year Level:</strong> <span id="detailYearLevel"></span></p>
                    <p class="text-muted">Active Term: <?= htmlspecialchars($term_label); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

</div>
</main>
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Student and Parent</h5>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm" method="POST" novalidate>
                    <input type="hidden" name="term_id" value="<?= htmlspecialchars($current_term_id); ?>">
                    <!-- Student Info -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control name-input" name="s_fname" id="s_fname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid first name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control name-input" name="s_lname" id="s_lname" pattern="[A-Za-z\-\s]+" required>
                            <div class="invalid-feedback">Please enter a valid last name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" class="form-control name-input" name="s_mname" pattern="[A-Za-z\-\s]*">
                            <div class="invalid-feedback">Please enter a valid middle name</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Suffix</label>
                            <input type="text" class="form-control name-input" name="s_suffix" pattern="[A-Za-z\-\s\.]*">
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
                            <input type="date" class="form-control" name="s_bdate" id="s_bdate" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="s_cnum" required pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" title="Please enter a valid 11-digit phone number starting with 09">
                            <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="s_email" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="s_status" disabled>
                                <option value="active">Active</option>
                                <option value="inactive" selected>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                                    <label class="form-label mb-0" for="year_level">Year Level:</label>

                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select" id="year_level" name="year_level" required>
                                        <option value="">Select Year Level</option>
                                        <?php
                                        $year_levels_query = "SELECT id, level_name FROM year_levels ORDER BY id ASC";
                                        $year_levels_result = $conn->query($year_levels_query);
                                        while ($yl = $year_levels_result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($yl['id']) . '">' . htmlspecialchars($yl['level_name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div id="yearLevelsContainer" class="mt-2"></div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Degree Program</label>
                                <select name="degree_id" id="degreeSelect" class="form-select" required>
    <option value="" disabled selected>Select a Degree</option>
    <?php
    // Get dean's assigned degree
    $dean_id = $_SESSION['user_id'] ?? null;

    if ($dean_id) {
        $stmt = $conn->prepare("
            SELECT degree_id, degree_name, degree_code
            FROM degrees
            WHERE dean_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $dean_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($deg = $result->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($deg['degree_id']) . "' selected " .
                 "data-degree-code='" . htmlspecialchars($deg['degree_code']) . "'>" .
                 htmlspecialchars($deg['degree_name']) . " (" . htmlspecialchars($deg['degree_code']) . ")" .
                 "</option>";
        }
        $stmt->close();
    }
    ?>
</select>

                                <div class="invalid-feedback">Please select a degree program</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Student Address</label>
                                <textarea class="form-control" name="s_address" id="s_address" rows="2" required></textarea>
                                <div class="invalid-feedback">Please enter the student's address</div>
                            </div>
                    </div>
          <!-- Solo Student Checkbox -->
<div class="col-md-6 form-check">
    <input class="form-check-input" type="checkbox" id="is_solo" name="is_solo" value="1">
    <label class="form-check-label" for="is_solo">Solo Student</label>
</div>

<!-- Irregular Student Checkbox -->
<div class="col-md-6 form-check">
    <input class="form-check-input" type="checkbox" id="is_irregular" name="is_irregular" value="1">
    <label class="form-check-label" for="is_irregular">Irregular Student</label>
</div>


<script>
    // ========== SOLO TOGGLE ==========
function toggleSolo(checkbox, remarksInput) {
    checkbox.addEventListener('change', function() {
        this.value = this.checked ? '2' : '1';  
        // auto-update remarks
        if (remarksInput) {
            remarksInput.value = this.checked ? "Solo (No Parent)" : "Living with Parent/Guardians";
        }
    });
    this.value = this.checked ? '2' : '1';
}

// ========== IRREGULAR TOGGLE ==========
function toggleIrregular(checkbox) {
    checkbox.addEventListener('change', function() {
        this.value = this.checked ? '2' : '1';  
    });
    checkbox.value = checkbox.checked ? '2' : '1';
}

// Init
const soloCheckbox = document.getElementById('is_solo');
const irregularCheckbox = document.getElementById('is_irregular');
const remarksInput = document.getElementById('remarks'); // must exist in your form

if (soloCheckbox) toggleSolo(soloCheckbox, remarksInput);
if (irregularCheckbox) toggleIrregular(irregularCheckbox);

</script>


<!-- Parent Info Section -->
<hr class="my-4">
<div id="parentInfo">
<h6 class="mb-3">Parent Information</h6>

<div class="card shadow-sm p-4 mb-4 rounded-4 border-0" style="background: #f8f9fa;">
    <!-- Toggle to use existing parent -->
    <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" id="useExistingParentCheckbox">
        <label class="form-check-label fw-semibold ms-2" for="useExistingParentCheckbox">
            Use Existing Parent Information
        </label>
    </div>

    <!-- Existing Parent Section -->
    <div id="existingParentSection" class="mb-3" style="display: none;">
        <label class="form-label fw-bold">Select Existing Parent</label>
        <select class="form-select shadow-sm rounded-3" id="existingParentSelect" name="existing_parent_id">
            <option value="">-- Select Parent --</option>
            <?php
            $parents_query = "SELECT p_id, CONCAT(p_lname, ', ', p_fname, ' ', IFNULL(p_mname,'')) AS parent_name, p_email 
                              FROM parents 
                              ORDER BY p_lname, p_fname";
            $parents_result = $conn->query($parents_query);
            while ($parent = $parents_result->fetch_assoc()) {
                echo "<option value='" . htmlspecialchars($parent['p_id']) . "'>" . 
                     htmlspecialchars($parent['parent_name'] . " (" . $parent['p_email'] . ")") . "</option>";
            }
            ?>
        </select>
        <div class="form-text mt-2 text-muted">
            Choose an existing parent to link with this student.
        </div>
    </div>

    <!-- Divider -->
    <div class="text-center mb-3" id="parentDivider">
        <span class="badge bg-secondary rounded-pill px-3">OR</span>
    </div>

    <!-- New Parent Section -->
    <div id="newParentSection">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">First Name</label>
                <input type="text" class="form-control shadow-sm rounded-3" name="p_fname" id="p_fname" pattern="[A-Za-z\-\s]+" required>
                <div class="invalid-feedback">Please enter a valid first name</div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">Last Name</label>
                <input type="text" class="form-control shadow-sm rounded-3" name="p_lname" id="p_lname" pattern="[A-Za-z\-\s]+" required>
                <div class="invalid-feedback">Please enter a valid last name</div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">Middle Name</label>
                <input type="text" class="form-control shadow-sm rounded-3" name="p_mname" pattern="[A-Za-z\-\s]*">
                <div class="invalid-feedback">Please enter a valid middle name</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">Suffix</label>
                <input type="text" class="form-control shadow-sm rounded-3" name="p_suffix" pattern="[A-Za-z\-\s\.]*">
                <div class="invalid-feedback">Please enter a valid suffix</div>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">Gender</label>
                <select class="form-select shadow-sm rounded-3" name="p_gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label fw-semibold">Birthdate</label>
                <input type="date" class="form-control shadow-sm rounded-3" name="p_bdate" id="p_bdate" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Parent Address</label>
            <div class="input-group shadow-sm rounded-3">
                <input class="form-control rounded-start-3" name="p_address" id="p_address" required>
                <button class="btn btn-outline-secondary rounded-end-3" type="button" id="copyAddressBtn" title="Copy Student Address">
                    Same as Student Address
                </button>
            </div>
            <div class="invalid-feedback">Please enter the parent's address</div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Contact Number</label>
                <input type="tel" class="form-control shadow-sm rounded-3" name="p_cnum" 
                       pattern="^09[0-9]{9}$" maxlength="11" placeholder="09XXXXXXXXX" required>
                <div class="invalid-feedback">Please enter a valid 11-digit phone number starting with 09</div>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Email</label>
               <input 
                    type="email" 
                    class="form-control shadow-sm rounded-3" 
                    name="p_email" 
                    pattern="[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$" 
                    required
                    >   
                <div class="invalid-feedback">Please enter a valid email address</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Status</label>
                <select class="form-select shadow-sm rounded-3" name="p_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>
</div>
</div>
                </form>
            <div class="modal-footer">
                <button type="submit" form="addStudentForm" class="btn btn-primary">Add Student & Parent</button>
            </div>
        </div>
    </div>
    </div>
</div>
<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form id="editStudentForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Update Student Information</h5>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="s_id" id="edit_s_id">

          <!-- Student Info -->
          <h6 class="mb-3">Student Information</h6>
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
              <label class="form-label">Address</label>
              <input type="text" class="form-control" name="s_address" id="edit_s_address">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="s_email" id="edit_s_email" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="s_status" id="edit_s_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Degree Program</label>
            <select class="form-select" name="degree_id" id="edit_degree_id" required disabled>
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

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Bootstrap Bundle (includes Popper.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- Update script paths to use absolute paths -->
<script src="/dean/students/js/add_student.js"></script>
<script src="/dean/students/js/edit_student.js"></script>
<script src="/dean/students/js/delete_student.js"></script>


<script>

   $(document).ready(() => {
    // ---------- DATA TABLE ----------
  
window.studentsTable = $('#studentsTable').DataTable({
    responsive: { details: false },
    columnDefs: [
        { 
            orderable: false, 
            className: 'text-center', 
            render: () => '<button class="btn btn-sm btn-primary toggle-details-btn">+</button>' 
        },
        {
            targets: 1, // 2nd column (Full Name)
            createdCell: function (td) {
                $(td).css({
                    'white-space': 'normal',
                    'word-wrap': 'break-word'
                });
            }
        }
    ],
    order: [[1, 'desc']],
    pageLength: 25,
    lengthMenu: [5, 10, 25, 50, 100],
    scrollY: '50vh',
    scrollCollapse: true,
    scroller: true
});
// ---------- FILTERED COUNTER ----------
studentsTable.on('draw', function () {
    let count = studentsTable.rows({ filter: 'applied' }).count();
    $('#filteredCount').text(count);
});


    // ---------- TOGGLE DETAILS BUTTON ----------
    $('#studentsTable tbody').on('click', '.toggle-details-btn', function () {
        var tr = $(this).closest('tr');
        var row = studentsTable.row(tr);

        if (row.child.isShown()) {
            row.child.hide();
            $(this).text('+');
        } else {
            row.child('<div>Additional info here...</div>').show();
            $(this).text('−');
        }
    });

    // ---------- EXPORT STUDENTS ----------
    document.getElementById('exportStudentsBtn').addEventListener('click', function() {
        fetch('/dean/students/processes/export_unassigned.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message || 'Export successful.', 'success');
                    const link = document.createElement('a');
                    link.href = '/dean/students/processes/download_temp.php?file=' + encodeURIComponent(data.file);
                    link.download = data.file;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    showAlert(data.message || 'Export failed', 'error');
                }
            }).catch(err => console.error(err));
    });

    function showAlert(message, type) {
        Swal.fire({
            icon: type,
            title: type.charAt(0).toUpperCase() + type.slice(1),
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
// ---------- PARENT TOGGLE (SOLO / EXISTING / NEW) ----------
const soloCheckbox = document.getElementById('is_solo');   // ✅ matches your checkbox
const parentInfo = document.getElementById('parentInfo');
const remarksInput = document.getElementById('remarks');

const useExistingParentCheckbox = document.getElementById("useExistingParentCheckbox");
const existingParentSection = document.getElementById("existingParentSection");
const newParentSection = document.getElementById("newParentSection");
const parentDivider = document.getElementById("parentDivider");

const newParentInputs = newParentSection ? newParentSection.querySelectorAll("input, select, textarea") : [];
const existingParentSelect = document.getElementById("existingParentSelect");

// Solo toggle
function toggleSolo() {
    if (!soloCheckbox) return;

    // ✅ 2 = Solo, 1 = With Parent
    soloCheckbox.value = soloCheckbox.checked ? '2' : '1';

    if (remarksInput) {
        remarksInput.value = soloCheckbox.checked
            ? "Solo (No Parent)"
            : "Living with Parents/Guardians";
    }

    if (parentInfo) {
        parentInfo.style.display = soloCheckbox.checked ? 'none' : 'block';

        parentInfo.querySelectorAll('input, select, textarea')
            .forEach(el => el.required = !soloCheckbox.checked);
    }
}

if (soloCheckbox) {
    soloCheckbox.addEventListener('change', toggleSolo);
    toggleSolo(); // run once on load
}

// Existing vs New Parent toggle
function toggleParentSections() {
    if (!useExistingParentCheckbox) return;

    if (useExistingParentCheckbox.checked) {
        existingParentSection.style.display = "block";
        parentDivider.style.display = "none";
        newParentSection.style.display = "none";

        newParentInputs.forEach(input => {
            input.dataset.prevRequired = input.required;
            input.required = false;
        });

        existingParentSelect.required = true;
    } else {
        existingParentSection.style.display = "none";
        parentDivider.style.display = "block";
        newParentSection.style.display = "block";

        newParentInputs.forEach(input => {
            if (input.dataset.prevRequired === "true") {
                input.required = true;
            }
        });

        existingParentSelect.required = false;
        existingParentSelect.value = "";
    }
}

if (useExistingParentCheckbox) {
    toggleParentSections();
    useExistingParentCheckbox.addEventListener("change", toggleParentSections);
}

    // ---------- COPY ADDRESS ----------
    const copyBtn = document.getElementById('copyBtn');
    const parentAddress = document.getElementById('parentAddress');
    const studentAddress = document.getElementById('studentAddress');

    if (copyBtn) {
        copyBtn.addEventListener("click", () => {
            parentAddress.value = studentAddress.value;
        });
    }
    

  // ---------- ROW SELECTION ----------
$('#studentsTable tbody').on('click', 'tr', function() {
    $('#studentsTable tbody tr').removeClass('active-row');
    $(this).addClass('active-row');

    const studentId = $(this).data('student-id');
    const fullName = $(this).data('fullname');
    const email = $(this).data('email');
    const parent = $(this).data('parent');   // ✅ parent comes from dataset
    const status = $(this).data('status').toUpperCase();
    const gender = $(this).data('gender');
    const bdate = $(this).data('bdate');
    const age = $(this).data('age');
    const cnum = $(this).data('cnum');
    const address = $(this).data('address');
    const degree = $(this).data('degree') || '-';
    const yearLevel = $(this).data('year_level') || '-';
    const avatar = $(this).data('avatar');
    const idcode = $(this).data('idcode');
    const termLabel = "<?= htmlspecialchars($term_label); ?>";
    const isRegularRaw = $(this).data('is_regular');
    const isRegular = (isRegularRaw === true || isRegularRaw === "true" || isRegularRaw === 1 || isRegularRaw === "1");

    // ✅ FIXED: show parent name properly
    $('#detailParent').html(`<span class="badge bg-info">${parent}</span>`);
    
    $('#detailStatus').html(
        `<span class="badge bg-primary">${status}</span> 
         ${isRegular ? '<span class="badge bg-success ms-1">REGULAR</span>' : '<span class="badge bg-danger ms-1">IRREGULAR</span>'}`
    );

    $('#noStudentSelected').hide();
    $('#studentDetailsContent').removeClass('d-none');

    if (avatar) {
        $('#studentAvatar').attr('src', avatar).removeClass('d-none');
        $('#studentInitials').addClass('d-none');
    } else {
        $('#studentAvatar').addClass('d-none');
        $('#studentInitials').removeClass('d-none')
            .text(fullName.split(' ').map(n => n.charAt(0)).join('').substring(0, 2).toUpperCase());
    }

    $('#detailId').text(studentId);
    $('#detailFullName').text(fullName);
    $('#detailEmail').text(email);
    $('#detailGender').text(gender);
    $('#detailBdate').text(bdate);
    $('#detailAge').text(age);
    $('#detailCnum').text(cnum);
    $('#detailAddress').text(address);
    $('#detailDegree').text(degree);
    $('#detailYearLevel').text(yearLevel);
    $('#detailTerm').text(termLabel);
});

    // ---------- AUTO-CAPITALIZE NAME INPUTS ----------
    function ucwordsWithHyphen(str) {
        return str.toLowerCase().split(/[\s-]+/).map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }
   
    $('#addStudentForm input[name="s_fname"], #addStudentForm input[name="s_lname"], #addStudentForm input[name="s_mname"], #addStudentForm input[name="s_suffix"]').on('input', function() {
        this.value = ucwordsWithHyphen(this.value);
    });
});


</script>


 
</body>
</html>