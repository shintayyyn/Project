<?php
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../../login.php');
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Get teacher's sections grouped by degree
$sections_query = "SELECT DISTINCT 
                    sec.section_id,
                    sec.section_code,
                    d.degree_id,
                    d.degree_code,
                    d.degree_name,
                    COUNT(DISTINCT st.s_id) as student_count
                  FROM sections_schedules ss
                  INNER JOIN sections sec ON ss.section_id = sec.section_id
                  INNER JOIN degrees d ON sec.degree_id = d.degree_id
                  LEFT JOIN students_sections sts ON sec.section_id = sts.section_id
                  LEFT JOIN students st ON sts.s_id = st.s_id
                  WHERE ss.teacher_id = ?
                  GROUP BY sec.section_id
                  ORDER BY d.degree_name, sec.section_code";

$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $teacher_id);
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
        'id' => $row['section_id'],
        'code' => $row['section_code'],
        'student_count' => $row['student_count']
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
    max-width: calc(100% - 10px);
    padding: 0;
    overflow-x: hidden;
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

.degree-card {
    border-radius: var(--card-border-radius);
    box-shadow: 0 4px 6px rgba(38, 59, 134, 0.07);
    border: none;
    transition: transform 0.3s;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
    max-width: calc(100% - 230px);
    top: -10px;
    background: var(--quaternary);
}



.degree-header {
    background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%) !important;
    color: white;
    border-radius: 0 !important;
    padding: 1rem 1.5rem;
}

.degree-body {
    padding: 1.5rem;
    max-width: calc(100% - 2rem);
}

.section-card {
    border-radius: var(--card-border-radius);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 2px 4px rgba(61, 82, 160, 0.05);
    transition: transform 0.3s;
    background: #ede8f5;
    margin-bottom: 1rem;
}


.section-card:hover {
    transform: translateY(-2px);
}

.section-header {
    background: var(--secondary);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: calc(var(--card-border-radius) - 1px) calc(var(--card-border-radius) - 1px) 0 0;
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

.sections-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

@media (max-width: 992px) {
    .sections-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sections-container {
        grid-template-columns: 1fr;
    }
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

.modal-body {
    max-height: 450px;
    overflow-y: auto;
    padding: 1.5rem;
}

/* Table styling */
.table {
    margin-bottom: 0;
}

.table-responsive {
    max-height: 400px; /* Adjust the height as needed */
    overflow-y: auto;
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

</style>
<link rel="stylesheet" href="assets/css/content.css">
</head>
<body>
<main class="container-fluid my-4">
    <h2 class="mb-4">My Class Lists</h2>
    <?php if(empty($degrees)): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No assigned sections yet.</div>
    <?php else: ?>
        <?php foreach($degrees as $degree): ?>
            <div class="degree-card">
                <div class="degree-header">
                    <h5 class="mb-0"><i class="bi bi-mortarboard me-2"></i><?php echo htmlspecialchars($degree['name']); ?> 
                    <small class="text-white-50">(<?php echo htmlspecialchars($degree['code']); ?>)</small></h5>
                </div>
                <div class="degree-body">
                    <div class="row g-3">
                        <?php foreach($degree['sections'] as $section): ?>
                            <div class="col-md-4">
                                <div class="section-card">
                                    <div class="section-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Section <?php echo htmlspecialchars($section['code']); ?></h6>
                                        <button class="btn-show-students btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal<?= $section['id']; ?>">
                                            <i class="bi bi-people-fill"></i> Show Students
                                        </button>
                                    </div>
                                    <div class="p-2 text-muted"><i class="bi bi-people"></i> <?php echo $section['student_count']; ?> Students</div>
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
                                                <div id="studentList<?= $section['id']; ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
    
    if(studentListCache.has(sectionId)){
        container.innerHTML = studentListCache.get(sectionId);
        $(`#studentTable${sectionId}`).DataTable({responsive:true, pageLength:10, paging:true, searching:true, ordering:true});
        return;
    }

    container.innerHTML = `<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`;
    button.classList.add('loading');
    button.innerHTML=`<span class="spinner-border spinner-border-sm"></span> Loading...`;

    fetch(`sections/processes/get_students.php?section_id=${sectionId}`)
    .then(res=>res.json())
    .then(data=>{
        if(!data.success) throw new Error(data.error||'Failed to load students');

        let html=`<div class="table-responsive"><table class="table table-hover" id="studentTable${sectionId}"><thead><tr><th>#</th><th>Name</th><th>Gender</th></tr></thead><tbody>`;
        data.students.forEach(student => {
        html += `
            <tr>
                <td><div class="student-avatar">${student.id}</div></td>
                <td>${student.name}</td>
                <td>${student.gender}</td>
            </tr>
        `;
    });
        html+=`</tbody></table></div>`;
        studentListCache.set(sectionId, html);
        container.innerHTML=html;

        $(`#studentTable${sectionId}`).DataTable({responsive:true, pageLength:10, paging:true, searching:true, ordering:true});
        button.classList.remove('loading');
        button.innerHTML=`<i class="bi bi-people-fill"></i> Show Students`;
    })
    .catch(err=>{
        container.innerHTML=`<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>${err.message}</div>`;
        button.classList.remove('loading');
        button.innerHTML=`<i class="bi bi-people-fill"></i> Show Students`;
    });
}

document.addEventListener('DOMContentLoaded',()=>{
    const modals=document.querySelectorAll('.modal');
    modals.forEach(modal=>{
        modal.addEventListener('show.bs.modal',function(){
            const sectionId=this.id.replace('studentModal','');
            loadStudentList(sectionId);
        });
        modal.addEventListener('hidden.bs.modal',function(){
            const sectionId=this.id.replace('studentModal','');
            const table=$(`#studentTable${sectionId}`).DataTable();
            if(table) table.destroy();
            document.getElementById('studentList'+sectionId).innerHTML='';
        });
    });
});

</script>
</body>
</html>
