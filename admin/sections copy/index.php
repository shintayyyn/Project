<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect this page: Allow only admin users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';

// --------------------- Debug Info ---------------------
// Total students
$debug_query = "SELECT COUNT(*) AS total FROM students";
$debug_result = $conn->query($debug_query);
$total_students = $debug_result ? $debug_result->fetch_assoc()['total'] : 0;

// Total assigned students
$debug_assigned = "SELECT COUNT(DISTINCT s_id) AS total FROM students_sections";
$assigned_result = $conn->query($debug_assigned);
$total_assigned = $assigned_result ? $assigned_result->fetch_assoc()['total'] : 0;

// --------------------- Unassigned Students with Active Degree ---------------------
$unassigned_query = "
    SELECT 
        s.*, 
        sd.degree_code,
        sd.degree_id,
        sd.enrollment_date,
        sd.status,
        sd.s_gender
    FROM students s
    LEFT JOIN students_sections ss ON s.s_id = ss.s_id
   LEFT JOIN (
    SELECT sd1.s_id, sd1.degree_code, sd1.degree_id, sd1.enrollment_date, sd1.status, sd1.s_gender
    FROM students_degrees sd1
    WHERE sd1.status = 'Active'
    AND sd1.enrollment_date = (
        SELECT MAX(sd2.enrollment_date)
        FROM students_degrees sd2
        WHERE sd2.s_id = sd1.s_id AND sd2.status = 'Active'
    )
) sd ON s.s_id = sd.s_id

    WHERE ss.s_id IS NULL
    ORDER BY s.s_lname, s.s_fname
";

$unassigned_students = $conn->query($unassigned_query);


// --------------------- Sections with Advisor and Student Count ---------------------
$sections_query = "
    SELECT 
        s.section_id,
        s.section_code,
        s.year_level,
        s.max_students,
        d.degree_name,
        d.degree_code,
        sa.t_id,
        CONCAT(sa.t_lname, ', ', sa.t_fname, ' ', COALESCE(LEFT(sa.t_mname, 1), ''), '.') AS advisor_name,
        COALESCE(student_count.count, 0) AS student_count
    FROM sections s
    LEFT JOIN degrees d ON s.degree_id = d.degree_id
    LEFT JOIN sections_advisors sa ON s.section_id = sa.section_id
    LEFT JOIN (
        SELECT section_id, COUNT(*) AS count
        FROM students_sections
        GROUP BY section_id
    ) student_count ON s.section_id = student_count.section_id
    ORDER BY s.section_code
";
$sections = $conn->query($sections_query);

// Group sections by degree for frontend
$sections_by_degree = [];
while ($section = $sections->fetch_assoc()) {
    $degree_name = $section['degree_name'] ?? 'Undefined';
    if (!isset($sections_by_degree[$degree_name])) {
        $sections_by_degree[$degree_name] = [
            'degree_name' => $degree_name,
            'sections' => []
        ];
    }
    $sections_by_degree[$degree_name]['sections'][] = $section;
}

$degrees = [];
$result = $conn->query("SELECT degree_code, degree_name FROM degrees ORDER BY degree_name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $degrees[] = $row;
    }
}
// --------------------- Teachers without Assigned Sections ---------------------
$teachers_query = "
    SELECT t.t_id, t.t_lname, t.t_fname, t.t_mname
    FROM teachers t
    LEFT JOIN sections_advisors sa ON t.t_id = sa.t_id
    WHERE sa.t_id IS NULL
    ORDER BY t.t_lname, t.t_fname
";
$teachers = $conn->query($teachers_query);

// --------------------- Fetch All Sections for Filtering ---------------------
$all_sections_query = "SELECT section_id, section_code, year_level FROM sections";
$all_sections_result = $conn->query($all_sections_query);

$all_sections = [];
while ($section = $all_sections_result->fetch_assoc()) {
    $parts = explode(' ', $section['section_code'], 2);
    $section['degree_code'] = $parts[0];
    $section['section_name'] = isset($parts[1]) ? trim($parts[1]) : '';
    $all_sections[] = $section;
}
?>


<!-- Update the container -->
<div class="container-fluid">
    <!-- Add message containers -->
    <div id="messageContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="notificationContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>
    <div id="alertContainer" class="position-fixed start-50 translate-middle-x" style="z-index: 1060; top: 20px;"></div>

    <!-- Add breadcrumb header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Section Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Sections</li>
                </ol>
            </nav>
        </div>
        <div class="row mb-4 mt-4">
        <div class="col">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                <i class="bi bi-plus-lg me-2"></i>Add New Section
            </button>
        </div>
    </div>

    </div>

   <!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" id="studentSectionTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="unassigned-tab" data-bs-toggle="tab" data-bs-target="#unassignedTabContent" type="button" role="tab" aria-controls="unassignedTabContent" aria-selected="true">
      Unassigned Students
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sectionsTabContent" type="button" role="tab" aria-controls="sectionsTabContent" aria-selected="false">
      Sections by Degree
    </button>
  </li>
</ul>

<!-- Tabs Content -->
<div class="tab-content" id="studentSectionTabsContent">
  <!-- Unassigned Students Tab
  <div class="tab-pane fade show active" id="unassignedTabContent" role="tabpanel" aria-labelledby="unassigned-tab">
    <div class="card mt-2" id="unassignedStudentsCard" style="max-height: 180vh; width: 100%;"> 
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Unassigned Students</h5>
        <span class="badge bg-info" id="displayedCountBadge">
            Displayed: <?php echo $unassigned_students->num_rows; ?>
        </span>
    </div>
    <div class="card-body">
        <?php if($unassigned_students->num_rows > 0) { ?>
        <div class="table-responsive p-3" id="unassignedTableContainer">
            <table id="unassignedStudentsTable" class="table table-hover align-middle p-2">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Degree</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayed_count = 0;
                    while($student = $unassigned_students->fetch_assoc()) {
                        $displayed_count++;
                    $degrees = [];
                        $result = $conn->query("SELECT degree_id, degree_code, degree_name FROM degrees ORDER BY degree_name ASC");

                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $degrees[] = $row;
                            }
                        }


                    ?>
                    <tr data-student-id="<?php echo htmlspecialchars($student['s_id']); ?>">
                        <td><?php echo htmlspecialchars($student['idcode']); ?></td>
                        <td><?php echo htmlspecialchars($student['s_lname'] . ', ' . $student['s_fname'] . ' ' . ($student['s_mname'] ? substr($student['s_mname'], 0, 1) . '.' : '')); ?></td>
                        <td><?php echo htmlspecialchars($student['degree_code']); ?></td>
                        <td>
                            <button type="button" 
                                    class="btn btn-primary btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#assignModal"
                                    data-student-id="<?php echo $student['s_id']; ?>"
                                    data-degree-code="<?php echo $student['degree_code']; ?>">
                                Assign to Section
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
            <div class="alert alert-success mx-3 my-3" id="noUnassignedMessage"> 
                No unassigned students found. All students have been assigned to sections.
            </div>
        <?php } ?>
    </div>
</div>

  </div> -->
  <div class="tab-pane fade show active" id="unassignedTabContent" role="tabpanel">
    <div class="card mt-2">
        <div class="card-header">
            <h5 class="mb-0">Upload Unassigned Students</h5>
        </div>
        <div class="card-body">
            <form id="uploadStudentsForm" enctype="multipart/form-data">
    <div class="mb-3">
        <label for="studentFile" class="form-label">Choose Excel/CSV File</label>
        <input type="file" class="form-control" id="studentFile" name="student_file" accept=".csv, .xls, .xlsx" required>
    </div>
    <button type="submit" class="btn btn-primary">Upload & Assign</button>
</form>
<div id="uploadResult" class="mt-3"></div>

            <div id="uploadResult" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- Sections Tab -->
<div class="tab-pane fade" id="sectionsTabContent" role="tabpanel" aria-labelledby="sections-tab">
    <!-- Degree Filter as Button Pills -->
    <div class="mb-3">
        <label class="form-label">Filter by Degree:</label>
        <div id="degreeFilterButtons" class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary active" data-degree="">All</button>
            <?php foreach ($degrees as $deg) { ?>
                <button type="button" class="btn btn-outline-primary" data-degree="<?php echo htmlspecialchars($deg['degree_code']); ?>">
                    <?php echo htmlspecialchars($deg['degree_code']); ?>
                </button>
            <?php } ?>
        </div>
    </div>

    <div class="row g-3" id="sectionsContainer">
            <?php foreach ($sections as $section) { 
    // Query students in this section
    $students_query = "
        SELECT s.*, ss.updated_at
        FROM students s
        JOIN students_sections ss ON s.s_id = ss.s_id
        WHERE ss.section_id = " . (int)$section['section_id'] . "
        ORDER BY s.s_lname, s.s_fname
    ";
    $students_result = $conn->query($students_query);
    $students_count = $students_result ? $students_result->num_rows : 0;

    // Get the latest updated_at separately (safe & accurate)
    $latest_updated_query = "
        SELECT MAX(updated_at) AS latest_updated
        FROM students_sections
        WHERE section_id = " . (int)$section['section_id'] . "
    ";
    $latest_updated_result = $conn->query($latest_updated_query);
    $updated_at = 'N/A';
    if ($latest_updated_result && $row = $latest_updated_result->fetch_assoc()) {
        $updated_at = $row['latest_updated'] ?: 'N/A';
    }
?>


        <div class="col-12 col-lg-4 section-card" data-degree="<?php echo htmlspecialchars($section['degree_code']); ?>">
            <div class="card h-100 mb-3">
    <!-- Card Header -->
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><?php echo htmlspecialchars($section['section_code']); ?></h5>
        </div>
        <!-- Edit Button -->
        <div>
            <button class="btn btn-sm btn-secondary edit-section-btn" 
                    data-section-id="<?php echo $section['section_id']; ?>"
                    data-section-code="<?php echo htmlspecialchars($section['section_code']); ?>"
                    data-year-level="<?php echo $section['year_level']; ?>"
                    data-max-students="<?php echo $section['max_students']; ?>">
                <i class="bi bi-pencil-square"></i> Edit
            </button>    
        </div>
    </div>
    <div class="card-body">
        <p class="card-text mb-1">
            <strong>Degree:</strong> <?php echo htmlspecialchars($section['degree_name'] ?? 'Undefined'); ?>
        </p>
        <p class="card-text mb-1">
            <strong>Year Level:</strong> <?php echo htmlspecialchars($section['year_level']); ?>
        </p>
        <p class="card-text mb-1">
            <strong>Max Students:</strong> <?php echo htmlspecialchars($section['max_students']); ?>
        </p>
        <?php if ($section['advisor_name']): ?>
    <div class="advisor-container" data-section-id="<?php echo $section['section_id']; ?>">
        <strong>Advisor:</strong>
        <span class="text-primary"><?php echo htmlspecialchars($section['advisor_name']); ?></span>
        <div class="btn-group btn-group-sm ms-2">
            <button class="btn btn-primary px-2 edit-advisor-btn"
                    data-bs-toggle="modal" 
                    data-bs-target="#assignAdvisorModal"
                    data-section-id="<?php echo $section['section_id']; ?>"
                    data-advisor-id="<?php echo $section['advisor_id'] ?? ''; ?>"
                    data-mode="edit">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-danger delete-advisor-btn"
                    data-section-id="<?php echo $section['section_id']; ?>">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="no-advisor-placeholder">
        <span class="text-muted me-2">No advisor assigned</span>
        <button class="btn btn-sm btn-success assign-advisor-btn"
                data-bs-toggle="modal" 
                data-bs-target="#assignAdvisorModal"
                data-section-id="<?php echo $section['section_id']; ?>"
                data-mode="add">
            <i class="bi bi-person-plus"></i>
        </button>
    </div>
<?php endif; ?>

        </div>
    <!-- Counters and Buttons below card-body -->
    <div class="card-footer d-flex justify-content-between align-items-center py-3">
        <span class="badge bg-primary section-count" 
              data-section-id="<?php echo $section['section_id']; ?>" 
              data-students-count="<?php echo $students_count; ?>" 
              data-updated-at="<?php echo $updated_at; ?>">
            <?php echo $students_count; ?> Students
        </span>
        <button class="btn btn-sm btn-warning view-students-btn" 
                data-section-id="<?php echo $section['section_id']; ?>"
                data-section-code="<?php echo htmlspecialchars($section['section_code']); ?>">
                <i class="bi bi-people-fill"></i> Show Students
        </button>
    </div>
</div>

        </div>
        <?php } ?>
        </div>
    </div>
</div>
</div>


    <!-- Single Assignment Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Student to Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="studentIdInput">
                        <div id="studentInfoDisplay"></div>
                        <div class="mb-3">
                            <label for="section" class="form-label">Select Section</label>
                            <select name="section_id" class="form-select" required id="sectionSelect">
                                <option value="">Choose a section...</option>
                                <?php foreach($all_sections as $section): ?>
                                    <option value="<?php echo $section['section_id']; ?>" 
                                            data-degree-code="<?php echo $section['degree_code']; ?>">
                                        <?php echo $section['section_code']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Note: Only sections matching the student's degree program can be assigned.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Assign Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
   <!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSectionForm" method="POST" novalidate>

                    <!-- Degree Selection -->
                    <div class="mb-3">
                    <label class="form-label">Degree Program</label>
                    <select name="degree_id" id="degreeSelect" class="form-select" required>
                        <option value="" disabled selected>Select a Degree</option>
                        <?php foreach ($degrees as $deg): ?>
                            <option value="<?= htmlspecialchars($deg['degree_id']) ?>" 
                                    data-degree-code="<?= htmlspecialchars($deg['degree_code']) ?>">
                                <?= htmlspecialchars($deg['degree_name']) ?> (<?= htmlspecialchars($deg['degree_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a degree program</div>
                </div>

                    <!-- Section Name -->
                    <div class="mb-3">
                        <label class="form-label">Section Name</label>
                        <input type="text" name="section_name" id="sectionNameInput" 
                               class="form-control" placeholder="e.g., Information Technology 3A" required>
                        <div class="invalid-feedback">Please enter a section name</div>
                    </div>

                    <!-- Section Code -->
                    <div class="mb-3">
                        <label class="form-label">Section Code</label>
                        <div class="input-group">
                            <span class="input-group-text" id="selectedDegreeCode">---</span>
                            <input type="text" name="section_code" id="sectionCodeInput" 
                                   class="form-control" placeholder="e.g., 3A, Alpha, Squad-1" required
                                   pattern="^[A-Za-z0-9\- ]+$"
                                   title="Allowed: letters, numbers, spaces, hyphens">
                        </div>
                        <div class="invalid-feedback">
                            Please enter a valid section code (letters, numbers, spaces, or hyphens only)
                        </div>
                    </div>

                    <!-- Max Students -->
                    <div class="mb-3">
                        <label class="form-label">Maximum Students</label>
                        <input type="number" name="max_students" class="form-control" 
                               value="40" min="1" max="100" required>
                        <div class="invalid-feedback">
                            Please enter a valid maximum number of students (1-100)
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addSectionForm" class="btn btn-primary">Add Section</button>
            </div>
        </div>
    </div>
</div>


    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editSectionForm" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="editSectionId" name="section_id">
                        <div class="mb-3">
                            <label for="editSectionCode" class="form-label">Section Code</label>
                            <input type="text" class="form-control" id="editSectionCode" name="section_code" required
                                   pattern="[A-Z]+ [1-4][A-Z]"
                                   title="Format: DEGREE YearLetter (e.g., BSIT 3A)">
                            <div class="invalid-feedback">Please enter a valid section code (e.g., BSIT 3A)</div>
                        </div>
                        <div class="mb-3">
                            <label for="editMaxStudents" class="form-label">Maximum Students</label>
                            <input type="number" class="form-control" id="editMaxStudents" name="max_students" required min="1" max="100">
                            <div class="invalid-feedback">Please enter a valid maximum number of students (1-100)</div>
                        </div>
                        <div class="mb-3">
                            <label for="editYearLevel" class="form-label">Year Level</label>
                            <select class="form-control" id="editYearLevel" name="year_level" required>
                                <option value="">Select Year Level</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                            <div class="invalid-feedback">Please select a year level</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Transfer Student Modal -->
    <div class="modal fade" id="transferStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transfer Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
               <form id="transferStudentForm">
    <div class="modal-body">
        <input type="hidden" name="student_id" id="transferStudentId">
        <input type="hidden" name="new_section_code" id="newSectionCode">
        
        <div id="transferStudentInfo" class="mb-3"></div>
        
        <div class="mb-3">
            <label for="newSectionSelect" class="form-label">Select New Section</label>
            <select name="new_section_id" class="form-select" required id="newSectionSelect">
                <option value="">Choose a section...</option>
                <?php foreach($all_sections as $section): ?>
                    <option value="<?php echo $section['section_id']; ?>" 
                            data-degree-code="<?php echo $section['degree_code']; ?>"
                            data-section-code="<?php echo $section['section_code']; ?>">
                        <?php echo $section['section_code']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" data-student-id="<?php echo htmlspecialchars($student['s_id']); ?>">Transfer Student</button>
    </div>
</form>
            </div>
        </div>
    </div>

    <!-- Assign Advisor Modal -->
    <div class="modal fade" id="assignAdvisorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Section Advisor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignAdvisorForm">
                    <div class="modal-body">
                        <input type="hidden" name="section_id" id="advisorSectionId">
                        <input type="hidden" name="section_code" id="advisorSectionCode">
                        <div class="mb-3">
                            <label class="form-label">Select Advisor</label>
                            <select name="advisor_id" id="teacherSelect" class="form-select" required>
                                <option value="">Choose an advisor...</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Assign Advisor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


<!-- View Students Modal -->
<div class="modal fade" id="viewStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewStudentsTitle">Students in Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="viewStudentsTable" class="table table-hover nowrap">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Degree</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<style>
 body {
    background: #EDF8FD;
    margin: 0;
    padding: 0;
}
.card-body{
    overflow-y: hidden;
}
.cbody2{
    background:#EDF8FD ;
}
.header1{
    background:#033A70;
    color:#EDE8F5;
}
.assigned-students .table thead th {
    background: none !important;
}

.card-header .btn {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease-in-out;
}

.card-header .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header .btn i {
    margin-right: 0.25rem;
}

.edit-section-btn:hover {
    background-color: #0d6efd;
    color: white;
}

.delete-section-btn:hover {
    background-color: #dc3545;
    color: white;
}

.assign-advisor-btn:hover {
    background-color: #198754;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.assign-advisor-btn {
    background-color: #198754;
    border-color: #198754;
    color: white;
}

.assign-advisor-btn:hover {
    background-color: #146c43;
    border-color: #146c43;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}

.edit-section-btn {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.edit-section-btn:hover {
    background-color: #0b5ed7;
    border-color: #0b5ed7;
}

.delete-section-btn {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.delete-section-btn:hover {
    background-color: #bb2d3b;
    border-color: #bb2d3b;
}

.edit-advisor-btn {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.edit-advisor-btn:hover {
    background-color: #0b5ed7;
    border-color: #0b5ed7;
}

.delete-advisor-btn {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.delete-advisor-btn:hover {
    background-color: #bb2d3b;
    border-color: #bb2d3b;
}

.card-info {
    margin-bottom: 0.5rem;
}

.assigned-students {
    margin-top: 0.5rem;
}

.assigned-students h6 {
    font-size: 0.9rem;
}

/* Card and Layout Adjustments */
.container-fluid {
    margin-top: -20px;
    margin-left: -15px;
    background: #EDF8FD;
    min-height: 100vh;
    padding: 25px;
    overflow-x: hidden; 
    max-width: 100%;    
}


/* Spacing Adjustments */
.card-info {
    margin-bottom: 0.75rem;
}

.card-info p {
    margin-bottom: 0.5rem;
}

.assigned-students {
    margin-top: 0.75rem;
}

/* Remove any potential horizontal overflow */
.row {
    margin-right: -12.5px; 
    margin-left: -12.5px;  
}

/* Update column spacing */
.col-12 {
    padding-right: 12.5px;  
    padding-left: 12.5px;   
}

/* Ensure content doesn't overflow */
* {
    max-width: none;
}

/* Custom Scrollbar Styles */
::-webkit-scrollbar {
    width: 8px;
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(108, 117, 125, 0.5);
    border-radius: 10px;
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(108, 117, 125, 0.8);
    border: 2px solid transparent;
    background-clip: padding-box;
}

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.4);
    border-radius: 10px;
}

/* For Firefox */
* {
    scrollbar-width: thin;
    scrollbar-color: rgba(108, 117, 125, 0.5) rgba(255, 255, 255, 0.4);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/admin/sections/js/section-operations.js"></script>

<!-- JavaScripts -->
<script>
$(document).ready(function () {
    $('#unassignedStudentsTable').DataTable({
        scrollY: '50vh',       // Vertical scroll
        scrollX: false,        // No horizontal scroll
        scrollCollapse: true,  // Table shrinks if fewer rows
        responsive: true,      // Optional: makes table responsive
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1 } // Last column unsortable
        ],
        dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
});

$(document).ready(function() {
    $('#degreeFilterButtons button').on('click', function() {
        const selected = $(this).data('degree');

        // Update active state
        $('#degreeFilterButtons button').removeClass('active');
        $(this).addClass('active');

        let visibleCount = 0;
        $('.section-card').each(function() {
            const degree = $(this).data('degree');
            const show = selected === "" || degree === selected;
            $(this).toggle(show);
            if(show) visibleCount++;
        });

        // Optional: show message if no sections found
        if(visibleCount === 0) {
            if($('#noSectionsMessage').length === 0) {
                $('#sectionsContainer').append('<div id="noSectionsMessage" class="alert alert-info" role="alert">No sections found for this degree.</div>');
            }
        } else {
            $('#noSectionsMessage').remove();
        }
    });
});

$(document).ready(function() {
    const $addForm = $('#addSectionForm');
    const $sectionsContainer = $('#sectionsContainer');

    if (!$addForm.length || !$sectionsContainer.length) return;

    $addForm.on('submit', function(e) {
        e.preventDefault();

        // Frontend validation
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true);

        $.ajax({
            url: '/admin/sections/processes/add_section.php',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide modal
                    $('#addSectionModal').modal('hide');

                    // Reset form
                    $addForm[0].reset();
                    $addForm.removeClass('was-validated');

                    showAlert('success', response.message || 'Section added successfully');

                    // Build new card HTML dynamically using the exact design
                    const newCard = `
                        <div class="col-12 col-lg-4 section-card" data-degree="${response.degree_code}">
                            <div class="card h-100 mb-3">
                                <!-- Card Header -->
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0">${response.formatted_section_code}</h5>
                                        <small>Advisor: None</small>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-secondary edit-section-btn" 
                                                data-section-id="${response.section_id}"
                                                data-section-code="${response.formatted_section_code}"
                                                data-year-level="${response.year_level || 0}"
                                                data-max-students="${response.max_students || 0}">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="card-body">
                                    <p class="card-text mb-1">
                                        <strong>Degree:</strong> ${response.degree_name}
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Year Level:</strong> ${response.year_level || 'N/A'}
                                    </p>
                                    <p class="card-text mb-1">
                                        <strong>Max Students:</strong> ${response.max_students || 0}
                                    </p>
                                    <span class="text-muted me-2">No advisor assigned</span>
                                    <button class="btn btn-sm btn-success assign-advisor-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#assignAdvisorModal"
                                            data-section-id="${response.section_id}"
                                            data-mode="add">
                                        <i class="bi bi-person-plus"></i>
                                    </button>
                                </div>

                                <!-- Card Footer -->
                                <div class="card-footer d-flex justify-content-between align-items-center py-3">
                                    <span class="badge bg-primary section-count" 
                                          data-section-id="${response.section_id}" 
                                          data-students-count="0" 
                                          data-updated-at="${Math.floor(Date.now() / 1000)}">
                                        0 Students
                                    </span>
                                    <button class="btn btn-sm btn-warning view-students-btn" 
                                            data-section-id="${response.section_id}"
                                            data-section-code="${response.formatted_section_code}">
                                        <i class="bi bi-people-fill"></i> Show Students
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    // Prepend the new card to the container
                    $sectionsContainer.prepend(newCard);

                } else {
                    showAlert('danger', response.message || 'Failed to add section');
                }
            },
            error: function() {
                showAlert('danger', 'Server error occurred while adding section');
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const assignAdvisorModal = document.getElementById('assignAdvisorModal');

    if (assignAdvisorModal) {
        assignAdvisorModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const mode = button.getAttribute('data-mode');
            const sectionId = button.getAttribute('data-section-id');
            const advisorId = button.getAttribute('data-advisor-id');

            // Fetch available teachers dynamically
            fetch(`/admin/sections/processes/get_available_teachers.php?mode=${mode}&current_advisor=${advisorId}`)
                .then(response => response.json())
                .then(data => {
                    const teacherSelect = document.getElementById('teacherSelect');
                    teacherSelect.innerHTML = '<option value="">Select Advisor</option>';

                    data.teachers.forEach(teacher => {
                        const option = document.createElement('option');
                        option.value = teacher.t_id;
                        option.textContent = `${teacher.t_lname}, ${teacher.t_fname} ${teacher.t_mname || ''}`;
                        if (mode === 'edit' && parseInt(teacher.t_id) === parseInt(advisorId)) {
                            option.selected = true;
                        }
                        teacherSelect.appendChild(option);
                    });
                });

            // Update hidden field with section ID
            const sectionIdInput = assignAdvisorModal.querySelector('input[name="section_id"]');
            sectionIdInput.value = sectionId;
        });
    }

    // Handle form submission to assign advisor dynamically
    const assignForm = document.getElementById('assignAdvisorForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(assignForm);
            const sectionId = formData.get('section_id');

            fetch('/admin/sections/processes/assign_advisor.php', {
                method: 'POST',
                body: JSON.stringify({
                    section_id: sectionId,
                    advisor_id: formData.get('advisor_id')
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close the modal
                        const modalInstance = bootstrap.Modal.getInstance(assignAdvisorModal);
                        modalInstance.hide();

                        // Find the correct advisor container for this section
                        const sectionCard = document.querySelector(`[data-section-id="${sectionId}"]`)?.closest('.section-card');

                        if (sectionCard) {
                            const advisorContainer = sectionCard.querySelector('.advisor-container');

                            if (advisorContainer) {
                                // Replace advisor name dynamically
                                advisorContainer.innerHTML = `
                                    <strong>Advisor:</strong>
                                    <span class="text-primary">${data.advisor_name}</span>
                                    <div class="btn-group btn-group-sm ms-2">
                                        <button class="btn btn-primary px-2 edit-advisor-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignAdvisorModal"
                                                data-section-id="${sectionId}"
                                                data-advisor-id="${data.t_id}"
                                                data-mode="edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-danger delete-advisor-btn"
                                                data-section-id="${sectionId}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                `;
                            } else {
                                // If there was no advisor container before
                                const placeholder = sectionCard.querySelector('.no-advisor-placeholder');
                                if (placeholder) placeholder.remove();

                                const newAdvisorHTML = `
                                    <div class="advisor-container" data-section-id="${sectionId}">
                                        <strong>Advisor:</strong>
                                        <span class="text-primary">${data.advisor_name}</span>
                                        <div class="btn-group btn-group-sm ms-2">
                                            <button class="btn btn-primary px-2 edit-advisor-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#assignAdvisorModal"
                                                    data-section-id="${sectionId}"
                                                    data-advisor-id="${data.t_id}"
                                                    data-mode="edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger delete-advisor-btn"
                                                    data-section-id="${sectionId}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;

                                sectionCard.querySelector('.card-body').insertAdjacentHTML('beforeend', newAdvisorHTML);
                            }
                        }

                        showAlert('success', data.message || 'Advisor assigned successfully.');
                    } else {
                        showAlert('danger', data.message || 'Failed to assign advisor.');
                    }
                })
                .catch(() => {
                    showAlert('danger', 'Server error occurred while assigning advisor.');
                });
        });
    }
});
// =================== Update Section ===================
$(document).ready(function() {
    // Initialize Bootstrap modals
const editSectionModal = document.getElementById('editSectionModal');
const bsEditModal = new bootstrap.Modal(editSectionModal);

// ================== Edit Section ==================
$(document).on('click', '.edit-section-btn', function(e) {
    e.preventDefault();
    const button = $(this);

    const sectionId    = button.data('section-id');
    const sectionCode  = button.data('section-code');
    const maxStudents  = button.data('max-students');
    const yearLevel    = button.data('year-level');

    // Reset and populate form
    const form = $('#editSectionForm')[0];
    form.reset();

    $('#editSectionId').val(sectionId);
    $('#editSectionCode').val(sectionCode);
    $('#editMaxStudents').val(maxStudents);
    $('#editYearLevel').val(yearLevel);

    // Show modal
    bsEditModal.show();
});

// Form Submit Handler
$('#editSectionForm').on('submit', function(e) {
    e.preventDefault();

    const form = $(this);
    if (!form[0].checkValidity()) {
        e.stopPropagation();
        form.addClass('was-validated');
        return;
    }

    const formData     = new FormData(form[0]);
    const sectionId    = formData.get('section_id');
    const sectionCode  = formData.get('section_code');
    const maxStudents  = formData.get('max_students');
    const yearLevel    = formData.get('year_level');

    fetch('/admin/sections/processes/edit_section.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const editButton = $(`.edit-section-btn[data-section-id="${sectionId}"]`);
            const card = editButton.closest('.card');

            // ✅ Update Section Code in header h5
            card.find('h5.mb-0').text(sectionCode);

            // ✅ Update Year Level
            card.find('.card-text').filter(function() {
                return $(this).text().trim().startsWith("Year Level:");
            }).html(`<strong>Year Level:</strong> ${yearLevel}`);

            // ✅ Update Max Students
            card.find('.card-text').filter(function() {
                return $(this).text().trim().startsWith("Max Students:");
            }).html(`<strong>Max Students:</strong> ${maxStudents}`);

            // ✅ Update Edit button attributes
            editButton.attr({
                'data-section-code': sectionCode,
                'data-max-students': maxStudents,
                'data-year-level': yearLevel
            });

            // ✅ Update section-count badge
            if (data.students_count !== undefined) {
                const countBadge = card.find('.section-count');
                countBadge.text(`${data.students_count} Students`);
                countBadge.attr('data-students-count', data.students_count);
            }
            if (data.updated_at !== undefined) {
                card.find('.section-count').attr('data-updated-at', data.updated_at);
            }

            // Close modal & reset validation
            bsEditModal.hide();
            form.removeClass('was-validated');
            showAlert('success', 'Section updated successfully');
        } else {
            showAlert('danger', data.message || 'Failed to update section');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showAlert('danger', 'Server error occurred');
    });
});

// Modal Close Handler
editSectionModal.addEventListener('hidden.bs.modal', function() {
    const form = $('#editSectionForm');
    form.removeClass('was-validated');
    $('body').removeClass('modal-open');
    $('.modal-backdrop').remove();
    $('body').css('padding-right', '');
});

// Assign Modal Handler
// const assignModal = document.getElementById('assignModal');
// if (assignModal) {
//     assignModal.addEventListener('show.bs.modal', function(event) {
//         const button = event.relatedTarget;
//         const studentId = button.getAttribute('data-student-id');
//         const studentDegree = button.getAttribute('data-degree-code');

//         // Set the student ID in the hidden input
//         document.getElementById('studentIdInput').value = studentId;

//         // Filter sections based on student's degree
//         const sectionSelect = document.getElementById('sectionSelect');
//         Array.from(sectionSelect.options).forEach(option => {
//             const sectionDegree = option.getAttribute('data-degree-code');
//             option.disabled = sectionDegree && sectionDegree !== studentDegree;
//             option.style.display = option.disabled ? 'none' : '';
//         });

//         // Reset selection
//         sectionSelect.value = '';
//     });

//     // Handle form submission
//     const assignForm = document.getElementById('assignStudentForm');
//     assignForm.addEventListener('submit', function(e) {
//         e.preventDefault();

//         const formData = new FormData(this);

//         fetch('/admin/sections/processes/assign_student.php', {
//             method: 'POST',
//             body: formData
//         })
//         .then(response => response.json())
//         .then(data => {
//           if (data.success) {
//     const studentId = formData.get('student_id');
//     const sectionId = data.section_id;

//     // 1️⃣ Remove student row from Unassigned Students table
//     const table = $('#unassignedStudentsTable').DataTable();
//     const row = table.rows().nodes().to$().filter(`[data-student-id="${studentId}"]`);
//     if (row.length) {
//         table.row(row).remove().draw(false);

//         // Update displayed count badge
//         const displayedBadge = $('#displayedCountBadge');
//         let currentCount = parseInt(displayedBadge.text().replace(/\D/g,'')) || 0;
//         displayedBadge.html(`Displayed: ${Math.max(currentCount - 1, 0)}`);
//     }

//     // 2️⃣ Update section count badge
//     const sectionCountBadge = $(`#section-count-${sectionId}`);
//     const currentCountSpan = sectionCountBadge.find('.current-count');
//     const currentCount = parseInt(currentCountSpan.text()) || 0;
//     currentCountSpan.text(currentCount + 1);

//     // 3️⃣ Close modal & show success toast
//     const modal = bootstrap.Modal.getInstance(assignModal);
//     modal.hide();
//     showAlert('success', data.message);
// }
//  else {
//                 showAlert('error', data.message || 'Failed to assign student');
//             }
//         })
//         .catch(error => {
//             console.error('Error:', error);
//             showAlert('error', 'Server error occurred');
//         });
//     });
// }
// });

});

document.getElementById('uploadStudentsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);

    fetch('/admin/sections/processes/upload_data.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        let div = document.getElementById('uploadResult');
        if (data.success) {
            div.innerHTML = `<div class="alert alert-success">${data.message}<br>Assigned: ${data.assigned}, Skipped: ${data.skipped}</div>`;
        } else {
            div.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(err => {
        document.getElementById('uploadResult').innerHTML = `<div class="alert alert-danger">Error uploading file</div>`;
    });
});

// =================== View Students in Section ===================
$(document).on('click', '.view-students-btn', function() {
    const sectionId = $(this).data('section-id');
    const sectionCode = $(this).data('section-code');

    $('#viewStudentsTitle').text('Students in ' + sectionCode);
    const tbody = $('#viewStudentsTable tbody');
    tbody.html('<tr><td colspan="4">Loading...</td></tr>');

    $.ajax({
        url: '/admin/sections/get_section_students.php',
        type: 'GET',
        data: { section_id: sectionId },
        dataType: 'json',
        success: function(response) {
            tbody.empty();
            if (response.students.length > 0) {
                response.students.forEach(s => {
                    tbody.append(`
                        <tr>
                            <td>${s.idcode}</td>
                            <td>${s.s_lname}, ${s.s_fname} ${s.s_mname ? s.s_mname.charAt(0) + '.' : ''}</td>
                            <td>${s.degree_code}</td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-student-btn"
                                        data-student-id="${s.s_id}"
                                        data-student-name="${s.s_lname}, ${s.s_fname}"
                                        data-current-section="${sectionCode}"
                                        data-degree-code="${s.degree_code}">
                                    Transfer
                                </button>
                                <button class="btn btn-sm btn-danger remove-student-btn"
                                        data-student-id="${s.s_id}"
                                        data-student-name="${s.s_lname}, ${s.s_fname}"
                                        data-section-id="${sectionId}"
                                        data-degree-code="${s.degree_code}">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `);
                });

                // Destroy previous DataTable instance if exists
                if ($.fn.DataTable.isDataTable('#viewStudentsTable')) {
                    $('#viewStudentsTable').DataTable().destroy();
                }

                // Initialize DataTable
                $('#viewStudentsTable').DataTable({
                    scrollY: '50vh',
                    scrollCollapse: true,
                    fixedHeader: true,
                    responsive: true,
                    paging: true,
                    ordering: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    columnDefs: [{ orderable: false, targets: -1 }],
                    dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    language: {
                        lengthMenu: "Show _MENU_ entries",
                        search: "Search:",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries"
                    }
                });

                // ✅ Show modal
                const modal = new bootstrap.Modal(document.getElementById('viewStudentsModal'));
                modal.show();

                // 🔹 Fix DataTable alignment when modal is fully shown
                $('#viewStudentsModal').off('shown.bs.modal').on('shown.bs.modal', function () {
                    $('#viewStudentsTable').DataTable().columns.adjust().draw();
                });

                // 🔹 Reset table and data when modal is hidden
                $('#viewStudentsModal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    // Clear table body
                    tbody.empty().html('<tr><td colspan="4">Loading...</td></tr>');

                    // Destroy DataTable completely to prevent duplicates
                    if ($.fn.DataTable.isDataTable('#viewStudentsTable')) {
                        $('#viewStudentsTable').DataTable().destroy();
                    }

                    // Reset modal title
                    $('#viewStudentsTitle').text('Students');

                    // Reset any transfer form data
                    plainForm = {
                        student_id: null,
                        student_name: '',
                        current_section_id: null,
                        new_section_id: null,
                        degree_code: ''
                    };
                });

            } else {
                tbody.html('<tr><td colspan="4">No students assigned.</td></tr>');
            }
        },
        error: function() {
            tbody.html('<tr><td colspan="4">Failed to load students.</td></tr>');
        }
    });
});
$('#viewStudentsModal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
    tbody.empty().html('<tr><td colspan="4">Loading...</td></tr>');
    if ($.fn.DataTable.isDataTable('#viewStudentsTable')) {
        $('#viewStudentsTable').DataTable().destroy();
    }
    $('#viewStudentsTitle').text('Students');
    plainForm = { student_id: null, student_name: '', current_section_id: null, new_section_id: null, degree_code: '' };
});

// =================== Transfer Student ===================
$(document).on('click', '.edit-student-btn', function() {
    const studentId = $(this).data('student-id');
    const studentName = $(this).data('student-name');
    const currentSection = $(this).data('current-section');
    const degreeCode = $(this).data('degree-code');

    // Hide view modal but keep it in the background
    const viewModal = bootstrap.Modal.getInstance($('#viewStudentsModal'));
    if (viewModal) viewModal.hide();

    $('#transferStudentId').val(studentId);
    $('#transferStudentInfo').html(`
        <p><strong>Student:</strong> ${studentName}</p>
        <p><strong>Current Section:</strong> ${currentSection}</p>
        <p><strong>Degree Program:</strong> ${degreeCode}</p>
    `);

    const $sectionSelect = $('#newSectionSelect');
    $sectionSelect.find('option').each(function() {
        $(this).toggle($(this).data('degree-code') === degreeCode);
    });
    $sectionSelect.val('');

    new bootstrap.Modal('#transferStudentModal').show();
});
// Transfer Form Submit
$('#transferStudentForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const plainForm = Object.fromEntries(formData.entries());
    plainForm.student_id = parseInt(plainForm.student_id);
    plainForm.new_section_id = parseInt(plainForm.new_section_id);
    plainForm.current_section_id = parseInt(plainForm.current_section_id);

    if (!plainForm.new_section_id || isNaN(plainForm.new_section_id)) {
        showAlert('warning', 'Please select a valid new section.');
        return;
    }

    const $submitBtn = $('#transferStudentForm button[type="submit"]');
    $submitBtn.prop('disabled', true).text('Transferring...');

    fetch('/admin/sections/processes/transfer_student.php', {
        method: 'POST',
        body: JSON.stringify(plainForm),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
   .then(data => {
    if (data.success) {
        showAlert('success', 'Student transferred successfully.');

        // Close Transfer Modal
        const transferModalEl = $('#transferStudentModal');
        const transferModal = bootstrap.Modal.getInstance(transferModalEl);
        if (transferModal) transferModal.hide();

        // Update counters after modal is fully hidden
        transferModalEl.on('hidden.bs.modal', function() {
            // Current section counter
            const $currentCounter = $('.section-count[data-section-id="' + plainForm.current_section_id + '"]');
            if ($currentCounter.length) {
                let oldCount = parseInt($currentCounter.data('students-count')) || 0;
                oldCount = Math.max(oldCount - 1, 0);
                $currentCounter.data('students-count', oldCount);
                $currentCounter.text(oldCount + (oldCount === 1 ? ' Student' : ' Students'));
            }

            // New section counter
            const $newCounter = $('.section-count[data-section-id="' + plainForm.new_section_id + '"]');
            if ($newCounter.length) {
                let newCount = parseInt($newCounter.data('students-count')) || 0;
                newCount = newCount + 1;
                $newCounter.data('students-count', newCount);
                $newCounter.text(newCount + (newCount === 1 ? ' Student' : ' Students'));
            }

        
    const oldTable = $('#studentsTable_' + plainForm.current_section_id).DataTable();
    oldTable.row('#student-' + plainForm.student_id).remove().draw(false);

// Add to new section's <s
    const newTable = $('#studentsTable_' + plainForm.new_section_id).DataTable();
    newTable.row.add([
    plainForm.student_id,
    plainForm.student_name, // make sure you send student_name from modal
    data.section_code,      // from backend response
    '<button class="btn btn-sm btn-primary edit-student-btn" ' +
        'data-student-id="' + plainForm.student_id + '" ' +
        'data-student-name="' + plainForm.student_name + '" ' +
        'data-current-section="' + data.section_code + '" ' +
        'data-degree-code="' + plainForm.degree_code + '">Transfer</button>'
]).draw(false);

            // Unbind this event to prevent duplicate triggers
            transferModalEl.off('hidden.bs.modal');
        });

    } else {
        showAlert('danger', data.message || 'Failed to transfer student.');
    }
})
  .catch(err => {
        console.error(err);
        showAlert('danger', 'Server error occurred.');
    }).then(data => {
    if (data.success) {
        showAlert('success', 'Student transferred successfully.');

        // Close Transfer Modal
        const transferModalEl = $('#transferStudentModal');
        const transferModal = bootstrap.Modal.getInstance(transferModalEl);
        if (transferModal) transferModal.hide();

        // Update counters and tables after modal is fully hidden
        transferModalEl.on('hidden.bs.modal', function() {
            // --- Update OLD Section Counter ---
            const $currentCounter = $('.section-count[data-section-id="' + plainForm.current_section_id + '"]');
            if ($currentCounter.length) {
                let oldCount = parseInt($currentCounter.data('students-count')) || 0;
                oldCount = Math.max(oldCount - 1, 0);
                $currentCounter.data('students-count', oldCount);
                $currentCounter.text(oldCount + (oldCount === 1 ? ' Student' : ' Students'));
            }

            // --- Update NEW Section Counter ---
            const $newCounter = $('.section-count[data-section-id="' + plainForm.new_section_id + '"]');
            if ($newCounter.length) {
                let newCount = parseInt($newCounter.data('students-count')) || 0;
                newCount = newCount + 1;
                $newCounter.data('students-count', newCount);
                $newCounter.text(newCount + (newCount === 1 ? ' Student' : ' Students'));
            }

            // --- Update OLD Section Table ---
            const oldTable = $('#studentsTable_' + plainForm.current_section_id).DataTable();
            oldTable.row('#student-' + plainForm.student_id).remove().draw(false);

            // --- Add to NEW Section Table ---
            const newTable = $('#studentsTable_' + plainForm.new_section_id).DataTable();
            newTable.row.add([
                plainForm.student_id,
                plainForm.student_name, // Make sure this comes from the modal
                data.section_code,      // From backend response
                '<button class="btn btn-sm btn-primary edit-student-btn" ' +
                    'data-student-id="' + plainForm.student_id + '" ' +
                    'data-student-name="' + plainForm.student_name + '" ' +
                    'data-current-section="' + data.section_code + '" ' +
                    'data-degree-code="' + plainForm.degree_code + '">Transfer</button>'
            ]).draw(false);

            // --- Reset plainForm after success ---
            plainForm = {
                student_id: null,
                student_name: '',
                current_section_id: null,
                new_section_id: null,
                degree_code: ''
            };

            // Unbind event to prevent duplicate triggers
            transferModalEl.off('hidden.bs.modal');
        });

    } else {
        showAlert('danger', data.message || 'Failed to transfer student.');
    }
})
    .finally(() => $submitBtn.prop('disabled', false).text('Transfer'));
});

// =================== Delete Student ===================
$(document).on('click', '.remove-student-btn', function() {
    const studentId = $(this).data('student-id');
    const studentName = $(this).data('student-name');
    const sectionId = $(this).data('section-id');

    if (!confirm(`Are you sure you want to remove ${studentName} from this section?`)) return;

    // Hide view modal but keep it in the background
    const viewModal = bootstrap.Modal.getInstance($('#viewStudentsModal'));
    if (viewModal) viewModal.hide();

    fetch('/admin/sections/processes/remove_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_id: studentId, section_id: sectionId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Student removed successfully.');

            // Reload DataTable without leaving tab
            $('.view-students-btn[data-section-id="' + sectionId + '"]').click();
        } else {
            showAlert('danger', data.message || 'Failed to remove student.');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('danger', 'Server error occurred.');
    });
});

 function showAlert(type, message) {
    Swal.fire({
        icon:type,
        title: type === 'success' ? 'Success!' : 'Error!',
        text: message,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}


</script>
