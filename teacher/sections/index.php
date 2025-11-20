<?php
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// ✅ Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    die("No active term found.");
}

// Get teacher's sections grouped by degree for the active term, including male/female counts
$sections_query = "
SELECT
    sec.section_id,
    sec.section_code,
    d.degree_id,
    d.degree_code,
    d.degree_name,

    -- Total students (regular + irregular)
    COUNT(DISTINCT s_all.s_id) AS student_count,

    -- Male/Female totals
    COUNT(DISTINCT CASE WHEN s_all.s_gender = 'Male' THEN s_all.s_id END) AS male_count,
    COUNT(DISTINCT CASE WHEN s_all.s_gender = 'Female' THEN s_all.s_id END) AS female_count,

    -- Regular students
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 1 THEN s_all.s_id END) AS regular_count,
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 1 AND s_all.s_gender = 'Male' THEN s_all.s_id END) AS regular_male_count,
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 1 AND s_all.s_gender = 'Female' THEN s_all.s_id END) AS regular_female_count,

    -- Irregular students
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 2 THEN s_all.s_id END) AS irregular_count,
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 2 AND s_all.s_gender = 'Male' THEN s_all.s_id END) AS irregular_male_count,
    COUNT(DISTINCT CASE WHEN s_all.is_regular = 2 AND s_all.s_gender = 'Female' THEN s_all.s_id END) AS irregular_female_count

FROM sections_schedules ss
INNER JOIN sections sec ON ss.section_id = sec.section_id
INNER JOIN degrees d ON sec.degree_id = d.degree_id

LEFT JOIN (
    -- Regular students
    SELECT st.s_id, st.s_gender, st.is_regular, ss.section_id
    FROM students_sections ss
    INNER JOIN students st ON ss.s_id = st.s_id
    WHERE ss.term_id = ?

    UNION

    -- Irregular students
    SELECT st.s_id, st.s_gender, st.is_regular, sec2.section_id
    FROM subject_enrollments se
    INNER JOIN students st ON se.s_id = st.s_id
    INNER JOIN sections sec2 ON se.section_code = sec2.section_code
    WHERE se.term_id = ?
      AND st.is_regular = 2
      AND se.enrollment_status = 'Enrolled'
) s_all ON s_all.section_id = sec.section_id

WHERE ss.teacher_id = ? AND ss.term_id = ?
GROUP BY sec.section_id, d.degree_id, d.degree_code, d.degree_name
ORDER BY sec.year_level ASC, d.degree_name DESC, sec.section_code DESC



";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("iiii", $term_id, $term_id, $teacher_id, $term_id);
$stmt->execute();
$result = $stmt->get_result();

// Group sections by degree
$degrees = [];
while ($row = $result->fetch_assoc()) {
    $degree_id = $row['degree_id'];
    if (!isset($degrees[$degree_id])) {
        $degrees[$degree_id] = [
            'name' => $row['degree_name'],
            'code' => $row['degree_code'],
            'sections' => []
        ];
    }

    $degrees[$degree_id]['sections'][] = [
        'id'             => $row['section_id'],
        'code'           => $row['section_code'],
        'student_count'  => (int)$row['student_count'],
        'male_count'     => (int)$row['male_count'],
        'female_count'   => (int)$row['female_count'],
        'regular_count'  => (int)$row['regular_count'],
        'irregular_count'=> (int)$row['irregular_count']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Class Lists</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
 <style>
:root {
    --primary: #033A70;
    --secondary: #033A70;
    --tertiary: #FFCB05;
    --quaternary: #D0EEFC;
    --background: #EDF8FD;
    --minimal: #3E7DCA;
    --sidebar-width: 250px;
    --card-border-radius: 0.75rem;
    --transition-speed: 0.3s;
}

/* Prevent horizontal scroll */
html, body {
    max-width: 100%;
    overflow-x: hidden;
}

/* Main content area */
main {
    margin-left: 5px;
    width: calc(100% - 260px);
    padding: 0;
    overflow-x: hidden;
     position: relative; /* ensures z-index stacking context */
}


/* Container adjustments */
.container-fluid {
    width: 100%;
    padding: 0;
    margin: 0;
    overflow-x: hidden;
}

/* Add padding to content */
h2.mb-4 {
    padding: 1rem;
}



.degree-body {
    padding: 1rem;
}

.student-list {
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
}

.student-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.student-item:last-child {
    border-bottom: none;
}

.student-item:hover {
    background: rgba(61, 82, 160, 0.02);
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--secondary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-weight: 500;
}

.student-info {
    flex-grow: 1;
}

.student-name {
    font-weight: 500;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.student-id {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Custom scrollbar */
.student-list::-webkit-scrollbar {
    width: 6px;
}

.student-list::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
}

.student-list::-webkit-scrollbar-thumb {
    background: var(--secondary);
    border-radius: 3px;
}

.section-stats {
    padding: 1rem;
    background: rgba(61, 82, 160, 0.02);
    border-radius: 0 0 var(--card-border-radius) var(--card-border-radius);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
}

.stats-icon {
    font-size: 1.25rem;
}


.section-header {
    background: var(--secondary);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: calc(var(--card-border-radius) - 1px) calc(var(--card-border-radius) - 1px) 0 0;
}
.section-card {
    border-radius: var(--card-border-radius);
    transition: transform 0.3s;
    background: linear-gradient(145deg, #ffffffff, #ffffffff);
    box-shadow: 2px 5px 10px #bebebe,
                -2px -5px 10px #ffffff;
    margin-bottom: 1rem;

    width: 100%;   
    height: 100%;  
}
.section-card:hover {
    transform: translateY(-2px);
}




.modal-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.student-list-container {
    padding: 1rem;
}

.student-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.student-item:last-child {
    border-bottom: none;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-weight: 500;
}

.student-info {
    flex-grow: 1;
}

.student-info h6 {
    margin: 0;
    font-weight: 600;
}

.student-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.875rem;
}

.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.student-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
}

.table td {
    vertical-align: middle;
}

/* Modal styling */
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.modal-header {
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
    border-bottom: 1px solid #eee;
    padding: 1rem 1.5rem;
}

.modal-footer {
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
    border-top: 1px solid #eee;
    padding: 1rem 1.5rem;
}

.student-avatar {
    width: 35px;
    height: 35px;
    background-color: #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6c757d;
}

/* Modern button styling */
.btn-show-students {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-show-students:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
    color: white;
}

.btn-show-students:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.btn-show-students i {
    font-size: 1rem;
}

.btn-show-students .spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

/* Loading state */
.btn-show-students.loading {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
    opacity: 0.8;
    cursor: wait;
}

.btn-show-students.loading:hover {
    transform: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

span.input-group-text{
    background: var(--primary);
}

.clear-3d {
    color: #012f58ff; /* base color */
    text-shadow: 
        1px 1px 0 #081b7933,
        2px 2px 2px #081b7933,
        3px 3px 3px #081b7933;
    transition: transform 0.2s, color 0.2s;
}

.clear-3d:hover {
    color: #dc3545;                 /* change color on hover */
    transform: translateY(-2px) scale(1.2); /* lift and scale for 3D effect */
    text-shadow: 
        2px 2px 1px #00000044,
        3px 3px 2px #00000033,
        4px 4px 3px #00000022;      /* stronger shadows for depth on hover */
}



</style>
<link rel="stylesheet" href="assets/css/content.css">
</head>
<body>
<main>
    <div class="container-fluid classContainer">
    <div class="row align-items-center mb-3 g-2 flex-wrap">
    <div class="col-md-8 col-12">
        <h2 class="mb-1 fw-bold">My Class Lists</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Class Lists</li>
            </ol>
        </nav>
    </div>

<div class="col-md-4 col-12 d-flex justify-content-end mt-2 mt-md-0 text-end">
    <!-- Search widget -->
    <div class="input">
        <div class="input-group">
            <span class="input-group-text text-warning">
                <i class="bi bi-search"></i>
            </span>
            <div class="position-relative">
        <!-- Input field with padding for icons -->
        <input type="text" id="searchSection" class="form-control  pe-5" placeholder="Search...">
        <!-- Clear "x" button on the right inside input -->
       <span id="clearSearch" class="position-absolute top-50 end-0 translate-middle-y pe-3" style="cursor: pointer;">
    <i class="bi bi-x text-secondary clear-3d"></i>
</span>

    </div>
        </div>
    </div>
</div>

        </div>

    <?php if(empty($degrees)): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No assigned sections yet.</div>
    <?php else: ?>
      
        <?php foreach($degrees as $degree): ?>
                <div class="degree-body">
                    <!-- Alert container (place this above the degree filter and search bar) -->
                    <div id="noResultsAlert" class="alert alert-info d-none">
                        <i class="bi bi-info-circle me-2"></i>No sections found.
                    </div>
                   <div class="row g-4">
                        <?php foreach($degree['sections'] as $section): ?>
                           <div class="col-6 col-md-6 col-sm-12">
                                <div class="section-card class-card-wrapper">
                                    <div class="section-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($section['code']); ?></h6>
                                        <button class="btn-show-students btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal<?= $section['id']; ?>">
                                            <i class="bi bi-people-fill"></i> Show Students
                                        </button>
                                    </div>
                                      <div class="p-2 text-muted">
                                        <i class="bi bi-people"></i> <?= $section['student_count']; ?> Students
                                        &nbsp; | &nbsp;
                                        <i class="bi bi-gender-male"></i> <?= $section['male_count']; ?> Male
                                        &nbsp; | &nbsp;
                                        <i class="bi bi-gender-female"></i> <?= $section['female_count']; ?> Female
                                    </div>

                                </div>
                                <!-- Modal -->
                                <div class="modal fade" id="studentModal<?= $section['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Students in Section <?php echo htmlspecialchars($section['code']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-end">
                                                 <i class="bi bi-people"></i> <?= $section['regular_count']; ?> Regular
                                                    &nbsp; | &nbsp;
                                                    <i class="bi bi-people-fill"></i> <?= $section['irregular_count']; ?> Irregular
                                                </div>
                                                <div id="studentList<?= $section['id']; ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
const studentListCache = new Map();
function loadStudentList(sectionId){
    const container = document.getElementById('studentList'+sectionId);
    const button = document.querySelector(`[data-bs-target="#studentModal${sectionId}"]`);
    
    // Show cached table HTML
    if(studentListCache.has(sectionId)){
        container.innerHTML = studentListCache.get(sectionId);
        return;
    }

    // Loading state
    container.innerHTML = `<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`;
    button.classList.add('loading');
    button.innerHTML=`<span class="spinner-border spinner-border-sm"></span> Loading...`;

    fetch(`sections/processes/get_students.php?section_id=${sectionId}`)
    .then(res => res.json())
    .then(data => {
        if(!data.success) throw new Error(data.error || 'Failed to load students');

        let html = `<div class="table-responsive">
                        <table class="table table-hover display nowrap" id="studentTable${sectionId}">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Status</th> <!-- 🔹 added column -->
                            </tr>
                        </thead>
                        <tbody>`;

        data.students.forEach(student => {
            // 🔹 Pick badge class
            const badgeClass = student.is_regular === "Regular" ? "bg-success" : "bg-danger";

            html += `
                <tr>
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <td>${student.gender}</td>
                    <td><span class="badge ${badgeClass}">${student.is_regular}</span></td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;

        studentListCache.set(sectionId, html);
        container.innerHTML = html;

        button.classList.remove('loading');
        button.innerHTML=`<i class="bi bi-people-fill"></i> Show Students`;
    })
    .catch(err => {
        container.innerHTML=`<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${err.message}</div>`;
        button.classList.remove('loading');
        button.innerHTML=`<i class="bi bi-people-fill"></i> Show Students`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {

        modal.addEventListener('show.bs.modal', function(){
            const sectionId = this.id.replace('studentModal','');
            loadStudentList(sectionId);
        });

        modal.addEventListener('shown.bs.modal', function(){
            const sectionId = this.id.replace('studentModal','');
            if ( $.fn.DataTable.isDataTable(`#studentTable${sectionId}`) ) {
                $(`#studentTable${sectionId}`).DataTable().destroy();
            }
            $(`#studentTable${sectionId}`).DataTable({
                responsive: true,
                pageLength: 10,
                paging: true,
                searching: true,
                ordering: true,
                scrollX: false,
                scrollY: '50vh',
                scrollCollapse: true
            });
        });

        modal.addEventListener('hidden.bs.modal', function(){
            const sectionId = this.id.replace('studentModal','');
            if ( $.fn.DataTable.isDataTable(`#studentTable${sectionId}`) ) {
                $(`#studentTable${sectionId}`).DataTable().destroy();
            }
            document.getElementById('studentList'+sectionId).innerHTML = '';
        });
    });
});

$(document).ready(function() {
    // Search input
    $('#searchSection').on('input', function() {
        var query = $(this).val().toLowerCase();
        var visibleCount = 0;

        $('.class-card-wrapper').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(query) > -1) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        // Show or hide alert
        if (visibleCount === 0) {
            $('#noResultsAlert').removeClass('d-none');
        } else {
            $('#noResultsAlert').addClass('d-none');
        }
    });

    // Clear button functionality
    $('#clearSearch').on('click', function() {
        $('#searchSection').val('').focus();  // Clear input and focus
        $('.class-card-wrapper').show();       // Show all sections
        $('#noResultsAlert').addClass('d-none'); // Hide alert
    });
});

</script>
</body>
</html>
