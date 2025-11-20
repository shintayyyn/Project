<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect this page: Allow only dean users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../../includes/db.php';
$dean_id = $_SESSION['user_id'];
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
// Fetch active term
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$term_id = ($term_result && $row = $term_result->fetch_assoc()) ? $row['term_id'] : 0;

$sections_query = "
    SELECT 
        s.section_id,
        s.section_code,
        s.year_level,
        s.max_students,
        d.degree_name,
        d.degree_code,
        sa.sa_id,
        sa.t_id,
        CONCAT(sa.t_lname, ', ', sa.t_fname, ' ', COALESCE(LEFT(sa.t_mname, 1), ''), '.') AS advisor_name,
        COALESCE(student_count.count, 0) AS student_count
    FROM sections s
    LEFT JOIN degrees d ON s.degree_id = d.degree_id
    LEFT JOIN sections_advisors sa 
        ON s.section_id = sa.section_id 
        AND sa.term_id = {$term_id} 
        AND sa.is_active = 1
    LEFT JOIN (
        SELECT section_id, COUNT(*) AS count
        FROM students_sections
        WHERE term_id = {$term_id}
        GROUP BY section_id
    ) student_count ON s.section_id = student_count.section_id
    WHERE s.term_id = {$term_id}
    ORDER BY s.section_code ASC
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
    <div id="alertContainer"></div>

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
<!-- Filters Row -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2" >
        <div class="position-relative d-none">
            <!-- Filter icon inside select -->
            <span 
                style="
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: var(--primary);
                    color: var(--tertiary);
                    border-top-left-radius: 8px;
                    border-bottom-left-radius: 8px;
                    width: 38px;
                ">
                <i class="fa-solid fa-filter"></i>
            </span>

          <?php
$dean_id = $_SESSION['user_id']; // Logged-in dean ID

// Fetch only degrees assigned to this dean
$degrees = [];
$result = $conn->query("SELECT degree_code, degree_name FROM degrees WHERE dean_id = {$dean_id} ORDER BY degree_name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $degrees[] = $row;
    }
}
?>

<select id="degreeFilter" class="form-select ps-5 w-auto" 
        style="border: 1px solid #033A70; border-radius: 8px; height: 50px;" readonly>
    <?php foreach ($degrees as $degree): ?>
        <option value="<?php echo htmlspecialchars($degree['degree_code']); ?>" selected>
            <?php echo htmlspecialchars($degree['degree_code'] . ' - ' . $degree['degree_name']); ?>
        </option>
    <?php endforeach; ?>
</select>


        </div>
    </div>

<div class="position-relative d-flex align-items-center" style="width: 100%; max-width: 400px;">
  <!-- Search icon inside input -->
  <span id="search-icon" 
        style="
          position: absolute;
          left: 0;
          top: 0;
          bottom: 0;
          display: flex; 
          align-items: center; 
          justify-content: center; 
          background: var(--primary); 
          color: var(--tertiary); 
          border-top-left-radius: 8px;
          border-bottom-left-radius: 8px;
          width: 38px;
        ">
    <i class="fa-brands fa-searchengin fa-lg"></i>
  </span>

  <!-- Input -->
  <input 
    type="text" 
    id="searchInput" 
    class="form-control ps-5 " 
    placeholder="Search..."
    style="
      border: 1px solid #033A70; 
      border-radius: 8px; 
      height: 50px;
    "
  >

  <!-- Clear button -->
  <button 
    type="button" 
    id="clearSearch" 
    class="btn-close position-absolute end-0 top-50 translate-middle-y me-2" 
    aria-label="Clear search" 
    style="display:none; width: 38px; height: 50px; font-size: 0.8rem;">
  </button>
</div>

</div>


<div class="row g-3" id="sectionsContainer">
<?php
// Fetch active term
$term_result = $conn->query("SELECT * FROM academic_terms WHERE is_active = 1 LIMIT 1");
$active_term = ($term_result && $row = $term_result->fetch_assoc()) ? $row : null;
$active_term_id = $active_term['term_id'] ?? 0;
$dean_id = $_SESSION['user_id']; // logged-in dean ID

// Fetch all sections with advisor, degree, and term info (active term only for advisors)
$sections_query = "
    SELECT s.section_id, s.section_code, s.section_name, s.year_level, s.max_students,
           d.degree_name, d.degree_code,
           s.term_id AS section_term_id, -- for filtering
           sa.sa_id, sa.t_id,
           CONCAT(t.t_lname, ', ', t.t_fname, ' ', LEFT(t.t_mname,1),'.') AS advisor_name
    FROM sections s
    LEFT JOIN degrees d 
        ON s.degree_id = d.degree_id AND d.dean_id = {$dean_id}  -- filter by dean
    LEFT JOIN sections_advisors sa 
        ON s.section_id = sa.section_id AND sa.term_id = {$active_term_id} AND sa.is_active = 1
    LEFT JOIN teachers t ON sa.t_id = t.t_id
    WHERE d.degree_id IS NOT NULL -- ensures only degrees belonging to this dean
    ORDER BY d.degree_name, s.section_code
";

$sections_result = $conn->query($sections_query);
$sections = [];
while ($row = $sections_result->fetch_assoc()) {
    $sections[] = $row;
}



foreach ($sections as $section) { 
    $section_id = (int)$section['section_id'];
    $section_term_id = (int)$section['section_term_id']; // term for filtering

    // ------------------ Count unique students in this section for the active term ------------------
    $students_query = "
        SELECT COUNT(DISTINCT s_id) AS student_count
        FROM students_sections
        WHERE section_id = ? AND term_id = ?
    ";
    $stmt = $conn->prepare($students_query);
    $stmt->bind_param("ii", $section_id, $active_term_id);
    $stmt->execute();
    $students_count = $stmt->get_result()->fetch_assoc()['student_count'] ?? 0;
    $stmt->close();

    // Get latest updated_at for this section and active term
    $latest_updated_query = "
        SELECT MAX(updated_at) AS latest_updated
        FROM students_sections
        WHERE section_id = ? AND term_id = ?
    ";
    $stmt2 = $conn->prepare($latest_updated_query);
    $stmt2->bind_param("ii", $section_id, $active_term_id);
    $stmt2->execute();
    $updated_at = $stmt2->get_result()->fetch_assoc()['latest_updated'] ?? '-';
    $stmt2->close();

    // Term display (always active term)
   $term_display = '-';
if ($active_term) {
    // Fetch the academic year for the active term
    $ay_id = $active_term['ay_id'] ?? 0;
    $ay_result = $conn->query("SELECT year_start, year_end FROM academic_years WHERE ay_id = {$ay_id} LIMIT 1");
    $ay_row = $ay_result->fetch_assoc();
    $year_start = $ay_row['year_start'] ?? '-';
    $year_end = $ay_row['year_end'] ?? '-';

    $term_display = "A.Y. " . htmlspecialchars($year_start) . "-" . htmlspecialchars($year_end)
                    . " | " . htmlspecialchars($active_term['semester'] ?? '-');
}

?>
    <!-- No Records Placeholder -->
    <div id="noSectionsAlert" class="col-12 alert alert-info text-center" style="display: none;">
        No sections found for the selected filter(s).
    </div>
    <!-- Section Card HTML -->
    <div class="col-12 col-lg-4 section-card" 
         data-degree="<?php echo htmlspecialchars($section['degree_code']); ?>" 
         data-term-id="<?php echo $section_term_id; ?>">
        <div class="card h-100 mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($section['section_code']); ?></h5>
                    <small class="text-white"><?php echo $term_display; ?></small>
                </div>
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
            <div class="card-body" id="card-body-<?php echo $section['section_id']; ?>">
                <p class="card-text mb-1"><strong>Degree:</strong> <?php echo htmlspecialchars($section['degree_name'] ?? 'Undefined'); ?></p>
                <p class="card-text mb-1"><strong>Year Level:</strong> <?php echo htmlspecialchars($section['year_level']); ?></p>
                <p class="card-text mb-1"><strong>Max Students:</strong> <?php echo htmlspecialchars($section['max_students']); ?></p>

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
         <div class="card-footer d-flex justify-content-between align-items-center py-3">
                <span class="badge bg-primary section-count" 
                      data-section-id="<?php echo $section['section_id']; ?>" 
                      data-students-count="<?php echo $students_count; ?>" 
                      data-updated-at="<?php echo $updated_at; ?>">
                    <?php echo $students_count . ' ' . ($students_count == 1 ? 'Student' : 'Students'); ?>
                </span>
                <button class="btn btn-sm btn-warning view-students-btn" 
                        data-section-id="<?php echo $section['section_id']; ?>"
                        data-section-code="<?php echo htmlspecialchars($section['section_code']); ?>">
                    <i class="bi bi-people-fill"></i> Show Students
                </button>
         </div>
         <script>
            window.activeTermId = <?php echo json_encode($active_term_id); ?>;
         </script>
        </div>
    </div>
<?php } ?>
</div>
<!-- No data message (hidden by default) -->
<div id="noDataMessage" class="alert alert-info text-center d-none">
    No subjects found for the selected filter/search.
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
                        <select name="degree_id" id="degreeSelect" class="form-select" disabled>
                            <option value="" disabled selected>Select a Degree</option>
                            <?php
                            // Assuming session is started and teacher ID is in session
                            $teacher_id = $_SESSION['t_id'] ?? null;

                            if ($teacher_id) {
                                // Fetch only the teacher's degree/department
                                $stmt = $conn->prepare("
                                    SELECT degree_id, degree_name, degree_code 
                                    FROM degrees 
                                    WHERE degree_id = (SELECT t_department FROM teachers WHERE t_id = ?)
                                ");
                                $stmt->bind_param("i", $teacher_id);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($deg = $result->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($deg['degree_id']) . "' selected " .
                                        "data-degree-code='" . htmlspecialchars($deg['degree_code']) . "'>" .
                                        htmlspecialchars($deg['degree_name']) . " (" . htmlspecialchars($deg['degree_code']) . ")" .
                                        "</option>";
                                }
                            }
                            ?>
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
                            <span class="input-group-text" id="selectedDegreeCode"><i class="bi bi-people-fill"></i></span>
                            <input type="text" name="section_code" id="sectionCodeInput" 
                                   class="form-control" placeholder="e.g.,BSIT 3A, Alpha1, Squad-1" required
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>

            <form id="transferStudentForm">
                <div class="modal-body">
                    <!-- ✅ Hidden fields -->
                    <input type="hidden" name="student_id" id="transferStudentId">
                    <input type="hidden" name="old_section_id" id="transferOldSectionId"> <!-- ✅ Added -->
                    <input type="hidden" name="new_section_code" id="newSectionCode">

                    <div id="transferStudentInfo" class="mb-3"></div>

                    <div class="mb-3">
                        <label for="newSectionSelect" class="form-label">Select New Section</label>
                        <select name="new_section_id" class="form-select" required id="newSectionSelect">
                            <option value="">Choose a section...</option>
                            <?php foreach ($all_sections as $section): ?>
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
                    <button type="submit" class="btn btn-primary">Transfer Student</button>
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
                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    <table id="viewStudentsTable" class="display table table-hover nowrap">
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

#clearSearch {

  width: 0.75rem;
  height: 0.75rem;
  opacity: 0.6;
}
#clearSearch:hover {
  opacity: 1;
}


.card-body,table{
    overflow: hidden;
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
<script src="/dean/sections/js/section-operations.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


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

document.addEventListener('DOMContentLoaded', function () {
  const degreeFilter = document.getElementById('degreeFilter');
  const searchInput = document.getElementById('searchInput');
  const sectionCards = document.querySelectorAll('.section-card');
  const sectionsContainer = document.getElementById('sectionsContainer');
  const noSectionsAlert = document.getElementById('noSectionsAlert');

  // === Create a clear (×) button dynamically ===
  const clearBtn = document.createElement('button');
  clearBtn.type = 'button';
  clearBtn.id = 'clearSearch';
  clearBtn.className = 'btn-close position-absolute end-0 top-50 translate-middle-y me-2';
  clearBtn.setAttribute('aria-label', 'Clear search');
  clearBtn.style.display = 'none';
  clearBtn.style.fontSize = '0.6rem';
  clearBtn.style.opacity = '0.6';
  clearBtn.style.width = '0.75rem';
  clearBtn.style.height = '0.75rem';

  // Insert clear button next to search input
  const parent = searchInput.parentElement;
  parent.style.position = 'relative';
  searchInput.classList.add('pe-5');
  parent.appendChild(clearBtn);

  // === Filtering Function ===
  function filterSections() {
    const degreeValue = degreeFilter.value.toLowerCase().trim();
    const searchValue = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;

    sectionCards.forEach(card => {
      const degreeCode = (card.dataset.degree || '').toLowerCase();
      const sectionText = card.textContent.toLowerCase();
      const termId = card.dataset.termId ? card.dataset.termId.trim() : '';

      const matchesDegree = !degreeValue || degreeCode === degreeValue;
      const matchesSearch = !searchValue || sectionText.includes(searchValue);
      const validTerm = termId !== '';

      if (matchesDegree && matchesSearch && validTerm) {
        card.style.display = '';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    // Toggle alert visibility
    if (noSectionsAlert) {
      noSectionsAlert.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    // Toggle visibility of clear (×) button
    clearBtn.style.display = searchValue ? 'block' : 'none';

    // Update visible count badge
   // --- Counter Badge (always above alert) ---
let countBadge = document.getElementById('visibleCountBadge');
if (!countBadge) {
    countBadge = document.createElement('div');
    countBadge.id = 'visibleCountBadge';
    countBadge.className = 'badge bg-primary mb-2 p-2 fs-6';
    sectionsContainer.parentNode.insertBefore(countBadge, sectionsContainer);
}
countBadge.textContent = `Showing ${visibleCount} ${visibleCount === 1 ? 'Section' : 'Sections'}`;
  }

  // === Event Listeners ===
  degreeFilter.addEventListener('change', filterSections);
  searchInput.addEventListener('input', filterSections);

  // Clear input when X clicked
  clearBtn.addEventListener('click', () => {
    searchInput.value = '';
    clearBtn.style.display = 'none';
    searchInput.focus();
    filterSections(); // ✅ re-show all sections
  });

  // === Run once on load ===
  filterSections();
});



// ------------------ Fetch updated student count from server ------------------
function refreshSectionCount(sectionId, termId) {
  // Convert safely to integer
  sectionId = parseInt(sectionId);
  termId = parseInt(termId);

  console.log('refreshSectionCount → section_id:', sectionId, 'term_id:', termId);

  // Prevent invalid calls
  if (isNaN(sectionId) || isNaN(termId)) {
    console.warn('⚠️ Missing section_id or term_id');
    return;
  }

  fetch(`/dean/sections/processes/get_student_count.php?section_id=${sectionId}&term_id=${termId}`)
    .then(res => res.json())
    .then(data => {
      console.log('✅ Updated count:', data);
      const badge = document.querySelector(`.section-count[data-section-id="${sectionId}"]`);
      if (badge) {
        badge.dataset.studentsCount = data.count;
        badge.dataset.updatedAt = data.updated_at;
        badge.textContent = data.text;
      }
    })
    .catch(err => console.error('❌ Error refreshing section count:', err));
}
$(document).ready(function() {
    const $addForm = $('#addSectionForm');
    const $sectionsContainer = $('#sectionsContainer');

    if (!$addForm.length || !$sectionsContainer.length) return;

    // Ensure submit event is bound only once
    $addForm.off('submit').on('submit', function(e) {
        e.preventDefault();

        // Frontend validation
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        const $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true); // Prevent double clicks

        // Use FormData for POST
        const formData = new FormData(this);

        $.ajax({
            url: '/dean/sections/processes/add_section.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide modal and reset form
                    $('#addSectionModal').modal('hide');
                    $addForm[0].reset();
                    $addForm.removeClass('was-validated');

                    showAlert('success', response.message || 'Section added successfully');

                    // Build new section card dynamically
                    const newCard = `
<div class="col-12 col-lg-4 section-card" 
     data-degree="${response.degree_code}" 
     data-term-id="${response.term_id}">
    <div class="card h-100 mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">${response.formatted_section_code}</h5>
                <small class="text-white">
                    ${response.term_display || '-'}
                </small>
            </div>
            <div>
                <button class="btn btn-sm btn-secondary edit-section-btn" 
                        data-section-id="${response.section_id}"
                        data-section-code="${response.formatted_section_code}"
                        data-year-level="${response.year_level || 0}"
                        data-max-students="${response.max_students || 0}"
                        data-term-id="${response.term_id}">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="card-text mb-1"><strong>Degree:</strong> ${response.degree_name}</p>
            <p class="card-text mb-1"><strong>Year Level:</strong> ${response.year_level || '-'}</p>
            <p class="card-text mb-1"><strong>Max Students:</strong> ${response.max_students || 0}</p>
            <span class="text-muted me-2">No advisor assigned</span>
            <button class="btn btn-sm btn-success assign-advisor-btn"
                    data-bs-toggle="modal" 
                    data-bs-target="#assignAdvisorModal"
                    data-section-id="${response.section_id}"
                    data-mode="add">
                <i class="bi bi-person-plus"></i>
            </button>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center py-3">
            <span class="badge bg-primary section-count" 
                  data-section-id="${response.section_id}" 
                  data-students-count="0" 
                  data-term-id="${response.term_id}" 
                  data-updated-at="${Math.floor(Date.now() / 1000)}">
                0 Students
            </span>
            <button class="btn btn-sm btn-warning view-students-btn" 
                    data-section-id="${response.section_id}"
                    data-section-code="${response.formatted_section_code}"
                    data-term-id="${response.term_id}">
                <i class="bi bi-people-fill"></i> Show Students
            </button>
        </div>
    </div>
</div>
`;

                    $sectionsContainer.prepend(newCard);

                } else {
                    showAlert('danger', response.message || 'Failed to add section');
                }
            },
            error: function() {
                showAlert('danger', 'Server error occurred while adding section');
            },
            complete: function() {
                $submitBtn.prop('disabled', false); // Re-enable button
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

        // Initialize Select2
        const teacherSelect = $('#teacherSelect');
        teacherSelect.select2({
            placeholder: 'Select Advisor',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#assignAdvisorModal')
        });

        // Clear previous options
        teacherSelect.empty();
        teacherSelect.append('<option value=""></option>'); // placeholder

        // Fetch available teachers dynamically
        fetch(`/dean/sections/processes/get_available_teachers.php?mode=${mode}&current_advisor=${advisorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.teachers)) {
                    data.teachers.forEach(teacher => {
                        const fullName = `${teacher.t_lname}, ${teacher.t_fname}${teacher.t_mname ? ' ' + teacher.t_mname.charAt(0) + '.' : ''}`;
                        const option = new Option(fullName, teacher.t_id, false, false);

                        // Pre-select if edit mode and matches current advisor
                        if (mode === 'edit' && parseInt(teacher.t_id) === parseInt(advisorId)) {
                            option.selected = true;
                        }

                        teacherSelect.append(option);
                    });

                    // Refresh Select2 to reflect new options
                    teacherSelect.trigger('change');
                }
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

            fetch('/dean/sections/processes/assign_advisor.php', {
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

    fetch('/dean/sections/processes/edit_section.php', {
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

//         fetch('/dean/sections/processes/assign_student.php', {
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


// ================= Upload Students Form =================
document.getElementById('uploadStudentsForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  let sectionId = parseInt(formData.get('section_id')) || null;
  const termId = parseInt(window.activeTermId);

  fetch('/dean/sections/processes/upload_data.php', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      const div = document.getElementById('uploadResult');
      let html = '';

      if (data.success) {
        if (data.section_id) sectionId = parseInt(data.section_id);

        html = `
          <div class="alert alert-success">
            ${data.message}<br>
            Assigned: ${data.assigned}, Skipped: ${data.skipped}
          </div>
        `;
        console.log('✅ Upload success:', { sectionId, termId });

        // ✅ Always refresh actual count from DB (avoids duplicates)
        if (!isNaN(sectionId) && !isNaN(termId)) {
          console.log('🔄 Refreshing true section count...');
          refreshSectionCount(sectionId, termId);
        }

        // ✅ Show skipped students list
        if (data.skipped_students?.length) {
          html += `
            <div class="alert alert-warning mt-2">
              <strong>Skipped Students:</strong>
              <ul class="mb-0">
                ${data.skipped_students
                  .map(
                    (s) => `<li>${s.id_code} - ${s.name}: ${s.reason}</li>`
                  )
                  .join('')}
              </ul>
            </div>
          `;
        }
      } else {
        html = `<div class="alert alert-danger">${data.message}</div>`;
      }

      div.innerHTML = html;

      // ✅ Auto-refresh form after 5 seconds
      setTimeout(() => {
        console.log('🔁 Auto-refreshing form after 5 seconds...');
        document.getElementById('uploadStudentsForm').reset(); // clear form
        div.innerHTML = ''; // clear messages
      }, 5000);
    })
    .catch((err) => {
      console.error('❌ Upload error:', err);
      const div = document.getElementById('uploadResult');
      div.innerHTML = `
        <div class="alert alert-danger">Error uploading file</div>
      `;

      // ✅ Auto-refresh form even on error
      setTimeout(() => {
        console.log('🔁 Auto-refreshing form after error (5 seconds)...');
        document.getElementById('uploadStudentsForm').reset();
        div.innerHTML = '';
      }, 5000);
    });
});


// =================== View Students in Section ===================
$(document).on('click', '.view-students-btn', function() {
    const sectionId = $(this).data('section-id');
    const sectionCode = $(this).data('section-code');

    const modalEl = $('#viewStudentsModal');
    const tbody = $('#viewStudentsTable tbody');

    $('#viewStudentsTitle').text('Students in ' + sectionCode);
    tbody.html('<tr><td colspan="4">Loading...</td></tr>');

    // Destroy existing DataTable first to avoid caching issues
    if ($.fn.DataTable.isDataTable('#viewStudentsTable')) {
        $('#viewStudentsTable').DataTable().destroy();
    }

    $.ajax({
        url: '/dean/sections/get_section_students.php',
        type: 'GET',
        data: { section_id: sectionId },
        dataType: 'json',
        cache: false, // prevent caching
        success: function(response) {
            tbody.empty();

            if (response.count > 0) {
                response.students.forEach(s => {
                    tbody.append(`
                        <tr id="student-${s.s_id}">
                            <td>${s.idcode}</td>
                            <td>${s.s_lname}, ${s.s_fname} ${s.s_mname ? s.s_mname.charAt(0) + '.' : ''}</td>
                            <td>${s.degree_code}</td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-student-btn"
                                        data-student-id="${s.s_id}"
                                        data-student-name="${s.s_lname}, ${s.s_fname}"
                                        data-current-section="${sectionCode}"
                                        data-current-section-id="${s.section_id}"
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

                $('#viewStudentsTable').DataTable({
                    scrollY: '50vh',
                    scrollCollapse: true,
                    fixedHeader: true,
                    responsive: true,
                    paging: true,
                    ordering: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    columnDefs: [{ orderable: false, targets: -1 }]
                });

            } else {
                // Proper "No students" row
                tbody.html(`
                    <tr>
                        <td colspan="4" class="text-center text-info">
                            No students assigned to this section for the active term.
                        </td>
                    </tr>
                `);
            }

            // Show modal
            const modal = new bootstrap.Modal(modalEl[0]);
            modal.show();

            // Adjust columns after shown
            modalEl.off('shown.bs.modal').on('shown.bs.modal', function () {
                if ($.fn.DataTable.isDataTable('#viewStudentsTable')) {
                    $('#viewStudentsTable').DataTable().columns.adjust().draw();
                }
            });
        },
        error: function() {
            tbody.html('<tr><td colspan="4" class="text-center text-danger">Failed to load students.</td></tr>');
        }
    });
});

// =================== Transfer Student ===================
$(document).on('click', '.edit-student-btn', function () {
    const studentId = $(this).data('student-id');
    const studentName = $(this).data('student-name');
    const currentSection = $(this).data('current-section');
    const degreeCode = $(this).data('degree-code');
    const oldSectionId = $(this).data('current-section-id'); // ✅ ensure this exists in your button

    // Hide the view modal (but keep background)
    const viewModal = bootstrap.Modal.getInstance($('#viewStudentsModal'));
    if (viewModal) viewModal.hide();

    // Fill student info in modal
    $('#transferStudentId').val(studentId);
    $('#transferStudentInfo').html(`
        <p><strong>Student:</strong> ${studentName}</p>
        <p><strong>Current Section:</strong> ${currentSection}</p>
        <p><strong>Degree Program:</strong> ${degreeCode}</p>
    `);

    // ✅ Set hidden old_section_id value for payload
    $('#transferOldSectionId').val(oldSectionId);

    console.log('🔹 old_section_id set to:', oldSectionId);

    // Populate new section dropdown
    const $sectionSelect = $('#newSectionSelect');
    $sectionSelect.empty(); // Clear old options
    $sectionSelect.append('<option value="">Choose a section...</option>');

    // Extract year level from currentSection (e.g., BSIT 1A → 1)
    const yearMatch = currentSection.match(/\d+/);
    const currentYear = yearMatch ? parseInt(yearMatch[0]) : null;
    let validCount = 0;

    // Generate section options dynamically from PHP-rendered list
    <?php foreach ($all_sections as $section): ?>
        (function () {
            const sectionId = '<?php echo $section['section_id']; ?>';
            const sectionCode = '<?php echo $section['section_code']; ?>';
            const sectionDegree = '<?php echo $section['degree_code']; ?>';
            const sectionYear = '<?php echo $section['year_level'] ?? ''; ?>';

            // Show only sections with same degree + same year + not current section
            if (
                sectionDegree === degreeCode &&
                sectionYear == currentYear &&
                sectionCode !== currentSection
            ) {
                $sectionSelect.append(
                    `<option value="${sectionId}" 
                             data-degree-code="${sectionDegree}" 
                             data-section-code="${sectionCode}">
                        ${sectionCode}
                    </option>`
                );
                validCount++;
            }
        })();
    <?php endforeach; ?>

    // If no valid sections found, show placeholder
    if (validCount === 0) {
        $sectionSelect.html(
            '<option value="" disabled selected>No other available sections for this year level.</option>'
        );
    }

    // Show the transfer modal
    new bootstrap.Modal('#transferStudentModal').show();
});

// =================== Transfer Student Form ===================
$('#transferStudentForm').on('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const plainForm = Object.fromEntries(formData.entries());
    plainForm.student_id = parseInt(plainForm.student_id);
    plainForm.new_section_id = parseInt(plainForm.new_section_id);
    plainForm.old_section_id = parseInt(plainForm.old_section_id);
    plainForm.student_name = $('#transferStudentInfo p:first').text().replace('Student: ', '');

    if (!plainForm.new_section_id || isNaN(plainForm.new_section_id)) {
        showAlert('warning', 'Please select a valid new section.');
        return;
    }

    const $submitBtn = $('#transferStudentForm button[type="submit"]');
    $submitBtn.prop('disabled', true).text('Transferring...');

    fetch('/dean/sections/processes/transfer_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(plainForm)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {

            // Close Transfer Modal
            const transferModalEl = $('#transferStudentModal');
            const transferModal = bootstrap.Modal.getInstance(transferModalEl);
            if (transferModal) transferModal.hide();

            showAlert('success', 'Student has been successfully transferred.');

          console.log('Form data:', plainForm);

            // ✅ Refresh both sections with real-time count
           refreshSectionCount(plainForm.old_section_id, window.activeTermId);
            refreshSectionCount(plainForm.new_section_id, window.activeTermId);


        } else {
            showAlert('danger', data.message || 'Failed to transfer student.');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('danger', 'Server error occurred.');
    })
    .finally(() => $submitBtn.prop('disabled', false).text('Transfer'));
});

// =================== Delete Student ===================
$(document).on('click', '.remove-student-btn', function() {
    const studentId = $(this).data('student-id');
    const studentName = $(this).data('student-name');
    const sectionId = $(this).data('section-id');

    Swal.fire({
        title: 'Are you sure?',
        text: `Remove ${studentName} from this section?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Removing...',
                text: 'Please wait while we remove the student.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('/dean/sections/processes/remove_student.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ student_id: studentId, section_id: sectionId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // ✅ Update counter immediately
                    // ✅ Fetch accurate count after deletion
                    refreshSectionCount(sectionId, window.activeTermId);

                    // ✅ Close modal
                    const viewModalEl = $('#viewStudentsModal');
                    const viewModal = bootstrap.Modal.getInstance(viewModalEl);
                    if (viewModal) viewModal.hide();

                    // ✅ Refresh table dynamically
                    $('.view-students-btn[data-section-id="' + sectionId + '"]').click();

                    // ✅ Show success alert
                    showAlert('success', 'Student has been successfully removed.');
                } else {
                    showAlert('danger', data.message || 'Failed to remove student.');
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('danger', 'Something went wrong while removing the student.');
            });
        }
    });
});

</script>
