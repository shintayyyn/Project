<?php
require_once __DIR__ . '/../../includes/db.php';

// Fetch all subjects
$subjects_query = "SELECT 
                      s.*, 
                      d.degree_code, 
                      d.degree_name,
                      GROUP_CONCAT(CONCAT(t.t_fname, ' ', t.t_lname) SEPARATOR ', ') as teachers 
                  FROM subjects s 
                  JOIN degrees d ON s.degree_id = d.degree_id
                  LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id 
                  LEFT JOIN teachers t ON st.t_id = t.t_id 
                  GROUP BY s.subject_id 
                  ORDER BY d.degree_code, s.subject_code";

$subjects_result = $conn->query($subjects_query);

// Fetch all teachers for the dropdown
$teachers_query = "SELECT t_id, t_fname, t_lname FROM teachers ORDER BY t_lname, t_fname";
$teachers_result = $conn->query($teachers_query);

$degrees = [];
$sql = "SELECT degree_code, degree_name FROM degrees ORDER BY degree_name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $degrees[] = $row;
    }
}
$subjects_by_degree = [];

while ($row = $subjects_result->fetch_assoc()) {
    $degree_code = $row['degree_code']; // use degree_code for grouping
    if (!isset($subjects_by_degree[$degree_code])) {
        $subjects_by_degree[$degree_code] = [
            'degree_name' => $row['degree_name'],
            'subjects' => []
        ];
    }
    $subjects_by_degree[$degree_code]['subjects'][] = $row;
}
?>
<style>
    table{
        overflow: hidden;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Main content -->
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h2>Subjects Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Subjects</li>
                </ol>
            </nav>
        </div>
        <div class="col text-end">
            <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-plus-lg me-2"></i>Add New Subject
            </button>
        </div>
    </div>

    <div class="mb-3 d-flex align-items-center gap-2">
        <label for="filterDegree" class="form-label mb-0">Filter by Degree:</label>
        <select id="filterDegree" class="form-select w-auto">
            <option value="">All Degrees</option>
            <?php foreach ($subjects_by_degree as $degree_code => $degree_data): ?>
                <option value="<?php echo htmlspecialchars($degree_code); ?>">
                    <?php echo htmlspecialchars($degree_code . ' - ' . $degree_data['degree_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<div class="card">
    <div class="card-header p-4"></div>
        <div class="card-shadow p-2">
                 <div class=" table-responsive p-2">
        <table class="table table-hover align-middle" id="subjectsTable">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Degree</th>
                    <th>Subject Code</th>
                    <th>Description</th>
                    <th>Units</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects_by_degree as $degree_code => $degree_data): ?>
                    <?php if (!empty($degree_data['subjects'])): ?>
                        <?php foreach ($degree_data['subjects'] as $subject): ?>
                            <tr data-degree="<?php echo htmlspecialchars($degree_code); ?>">
                                <td><?php echo htmlspecialchars($degree_code) ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_description']); ?></td>
                                <td><?php echo htmlspecialchars($subject['units']); ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn btn-sm btn-primary edit-subject" title="Edit Subject" data-bs-toggle="modal" data-bs-target="#editSubjectModal" data-id="<?php echo $subject['subject_id']; ?>" data-code="<?php echo htmlspecialchars($subject['subject_code']); ?>" data-description="<?php echo htmlspecialchars($subject['subject_description']); ?>" data-units="<?php echo htmlspecialchars($subject['units']); ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-subject" title="Delete Subject" data-id="<?php echo $subject['subject_id']; ?>">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
        </div>
    </div>
</div>


<!--Modals-->

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectModalLabel">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSubjectForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="degree_code" class="form-label">Department</label>
                       <select class="form-select" id="degree_code" name="degree_code" required>
                        <option value="" disabled selected>Select Department</option>
                        <?php foreach ($degrees as $degree): ?>
                            <option value="<?= htmlspecialchars($degree['degree_code']) ?>">
                                <?= htmlspecialchars($degree['degree_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                  <!-- Academic Year -->
<div class="mb-3">
    <label for="academic_year" class="form-label">Academic Year</label>
    <div class="d-flex align-items-center gap-2">
        <select id="academic_year" name="academic_year" class="form-select me-2" required>
            <option value="">-- Select Academic Year --</option>
            <option value="2024-2025">2024-2025</option>
            <option value="2025-2026">2025-2026</option>
        </select>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAcademicYearModal">
            + Add New
        </button>
    </div>
</div>

<!-- Semester -->
<div class="mb-3">
    <label for="semester" class="form-label">Semester</label>
    <div class="d-flex align-items-center gap-2">
        <select id="semester" name="semester" class="form-select me-2" required>
            <option value="">-- Select Semester --</option>
            <option value="First">First</option>
            <option value="Second">Second</option>
        </select>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
            + Add New
        </button>
    </div>
</div>

                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject_description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="subject_description" name="subject_description" required>
                    </div>
                    <div class="mb-3">
                        <label for="units" class="form-label">Units</label>
                        <input type="number" class="form-control" id="units" name="units" min="1" max="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubjectModalLabel">Edit Subject</h5>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
            <form id="editSubjectForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="edit_subject_description" name="subject_description" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_units" class="form-label">Units</label>
                        <input type="number" class="form-control" id="edit_units" name="units" min="1" max="6" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


 <!-- Add Academic Year  and Semester Modal -->
<!-- Add Academic Year Modal -->
<div class="modal fade" id="addAcademicYearModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addAcademicYearForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Academic Year</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026-2027" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Semester Modal -->
<div class="modal fade" id="addSemesterModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="addSemesterForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Semester</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="text" name="semester" class="form-control" placeholder="e.g. Summer" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = './subjects/';
    const alertMessage = document.getElementById('alertMessage');

    /** =========================
     * Initialize DataTable
     ========================== */
  let subjectsDataTable;
if ($('#subjectsTable').length) {
    subjectsDataTable = $('#subjectsTable').DataTable({
        scrollY: '60vh',          // vertical scroll only if needed
        scrollCollapse: true,
        responsive: true,          // makes columns adjust to viewport
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        autoWidth: false,          // prevents horizontal scroll caused by automatic width
        columnDefs: [
            { orderable: false, targets: -1 } // Actions column unsortable
        ],
        dom:
            '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
            '<"row"<"col-sm-12"tr>>' +
            '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });
}


    /** =========================
     * Show Alert Helper
     ========================== */
   function showAlert(message, type = 'success') {
    // Normalize type to valid SweetAlert icons
    const validTypes = ['success', 'error', 'warning', 'info', 'question'];
    if (!validTypes.includes(type)) type = 'info';

    Swal.fire({
        icon: type,
        title: type === 'success' ? 'Success!' :
               type === 'error' ? 'Error!' :
               type === 'warning' ? 'Warning!' : 'Notice',
        text: message,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}


    /** =========================
     * Filter by Degree
     ========================== */
    document.getElementById('filterDegree').addEventListener('change', function() {
        const degree = this.value.toLowerCase();
        subjectsDataTable.rows().every(function() {
            const rowDegree = this.node().dataset.degree.toLowerCase();
            if (!degree || rowDegree === degree) {
                $(this.node()).show();
            } else {
                $(this.node()).hide();
            }
        });
    });

    /** =========================
     * Update Subjects Table
     ========================== */
    function updateSubjectsTable(subjects) {
        if (!subjectsDataTable) return;
        subjectsDataTable.clear();

        subjects.forEach(subject => {
            const degreeDisplay = `${subject.degree_code} - ${subject.degree_name}`;
            const teachers = subject.teachers || 'No teachers assigned';

            const rowNode = subjectsDataTable.row.add([
                degreeDisplay,
                subject.subject_code,
                subject.subject_description,
                subject.units,
                teachers,
                `
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-primary assign-teacher" data-bs-toggle="modal" data-bs-target="#assignTeacherModal" data-id="${subject.subject_id}" data-code="${subject.subject_code}">
                        <i class="fa-solid fa-user-check"></i>
                    </button>
                    <button class="btn btn-sm btn-primary edit-subject" data-bs-toggle="modal" data-bs-target="#editSubjectModal" data-id="${subject.subject_id}" data-code="${subject.subject_code}" data-description="${subject.subject_description}" data-units="${subject.units}">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-subject" data-id="${subject.subject_id}">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
                `
            ]).node();

            rowNode.dataset.degree = subject.degree_code;
        });

        subjectsDataTable.draw();
        attachEventListeners(); // Re-attach events for dynamic rows
    }

    /** =========================
     * Event Listeners
     ========================== */
    function attachEventListeners() {
        // Edit button
        document.querySelectorAll('.edit-subject').forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('edit_subject_id').value = this.dataset.id;
                document.getElementById('edit_subject_code').value = this.dataset.code;
                document.getElementById('edit_subject_description').value = this.dataset.description;
                document.getElementById('edit_units').value = this.dataset.units;
            });
        });

        // Delete button
        document.querySelectorAll('.delete-subject').forEach(button => {
            button.addEventListener('click', async function () {
                const subjectId = this.dataset.id;

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This action will permanently delete the subject.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then(async (result) => {
                    if (!result.isConfirmed) return;

                    try {
                        const formData = new FormData();
                        formData.append('delete', subjectId);

                        const response = await fetch(BASE_URL + 'subjects_ajax.php', { method: 'POST', body: formData });
                        const data = await response.json();

                        if (data.status === 'success') {
                            showAlert('Subject deleted successfully', 'success');
                            updateSubjectsTable(data.subjects);
                        } else {
                            showAlert(data.message || 'Error deleting subject', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('An error occurred while deleting the subject', 'error');
                    }
                });
            });
        });

        // Assign Teacher Modal
        const assignTeacherModal = document.getElementById('assignTeacherModal');
        assignTeacherModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const subjectId = button.dataset.id;
            const subjectCode = button.dataset.code;

            document.getElementById('assign_subject_id').value = subjectId;
            document.getElementById('assign_subject_code').value = subjectCode;
            document.getElementById('assignTeacherModalLabel').textContent = `Manage Subject Teachers - ${subjectCode}`;

            loadCurrentTeachers(subjectId);
        });
    }

    /** =========================
     * Assign Teacher
     ========================== */
    document.getElementById('assignTeacherForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'assign_teacher');

        try {
            const response = await fetch(BASE_URL + 'subjects_ajax.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                showAlert('Teacher assigned successfully', 'success');
                this.reset();
                if (data.subjects) updateSubjectsTable(data.subjects);
                loadCurrentTeachers(formData.get('subject_id'));
            } else {
                showAlert(data.message || 'Error assigning teacher', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while assigning the teacher.', 'danger');
        }
    });

    /** =========================
     * Load Current Teachers
     ========================== */
    async function loadCurrentTeachers(subjectId) {
        try {
            const response = await fetch(`${BASE_URL}subjects_ajax.php?action=get_teachers&subject_id=${subjectId}`);
            const data = await response.json();
            const teachersList = document.getElementById('currentTeachersList');
            teachersList.innerHTML = '';

            if (data.teachers && data.teachers.length) {
                data.teachers.forEach(teacher => {
                    teachersList.insertAdjacentHTML('beforeend', `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${teacher.name}</span>
                            <button type="button" class="btn btn-danger btn-sm" onclick="unassignTeacher(${subjectId}, ${teacher.t_id})">
                                <i class="bi bi-x-lg"></i> Remove
                            </button>
                        </div>
                    `);
                });
            } else {
                teachersList.innerHTML = '<div class="list-group-item text-muted">No teachers assigned</div>';
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            showAlert('Error loading current teachers', 'danger');
        }
    }

    /** =========================
     * Unassign Teacher
     ========================== */
    window.unassignTeacher = async function (subjectId, teacherId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This will unassign the teacher from this subject.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, unassign',
            cancelButtonText: 'Cancel'
        }).then(async (result) => {
            if (!result.isConfirmed) return;

            try {
                const formData = new FormData();
                formData.append('action', 'unassign_teacher');
                formData.append('subject_id', subjectId);
                formData.append('teacher_id', teacherId);

                const response = await fetch(BASE_URL + 'subjects_ajax.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.status === 'success') {
                    showAlert('Teacher removed successfully', 'success');
                    loadCurrentTeachers(subjectId);
                    if (data.subjects) updateSubjectsTable(data.subjects);
                } else {
                    showAlert(data.message || 'Error removing teacher', 'error');
                }
            } catch (error) {
                console.error('Error unassigning teacher:', error);
                showAlert('Error removing teacher from subject', 'error');
            }
        });
    };

    /** =========================
     * Add / Edit Subject Forms
     ========================== */
    document.getElementById('addSubjectForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            const formData = new FormData(this);
            formData.append('action', 'add');
            const response = await fetch(BASE_URL + 'subjects_ajax.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.status === 'success') {
                showAlert('Subject added successfully', 'success');
                this.reset();
                if (data.subjects) updateSubjectsTable(data.subjects);
                bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
            } else {
                showAlert(data.message || 'Error adding subject', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while adding the subject', 'danger');
        }
    });

    document.getElementById('editSubjectForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            const response = await fetch(BASE_URL + 'subjects_ajax.php', { method: 'POST', body: new FormData(this) });
            const data = await response.json();

            if (data.status === 'success') {
                showAlert('Subject updated successfully', 'success');
                if (data.subjects) updateSubjectsTable(data.subjects);
                bootstrap.Modal.getInstance(document.getElementById('editSubjectModal')).hide();
            } else {
                showAlert(data.message || 'Error updating subject', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while updating the subject', 'danger');
        }
    });

    attachEventListeners();
});
</script>


