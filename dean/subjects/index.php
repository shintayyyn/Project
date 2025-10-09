<?php
require_once __DIR__ . '/../../includes/db.php';

// Fetch all subjects
$subjects_query = "SELECT s.*, GROUP_CONCAT(CONCAT(t.t_fname, ' ', t.t_lname) SEPARATOR ', ') as teachers 
                  FROM subjects s 
                  LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id 
                  LEFT JOIN teachers t ON st.t_id = t.t_id 
                  GROUP BY s.subject_id 
                  ORDER BY s.subject_code";
$subjects_result = $conn->query($subjects_query);

// Fetch all teachers for the dropdown
$teachers_query = "SELECT t_id, t_fname, t_lname FROM teachers ORDER BY t_lname, t_fname";
$teachers_result = $conn->query($teachers_query);
?>

<style>
                .table-responsive {
                    overflow-x: hidden;
                }
                .btn-group {
                    display: flex;
                    gap: 2px;
                    flex-wrap: wrap;
                }
                .btn-group .btn {
                    padding: 0.25rem 0.5rem;
                    font-size: 0.875rem;
                    white-space: nowrap;
                }
                .table td {
                    border: none;
                    max-width: 200px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
            </style>
<!-- Main content -->
<div class="container-fluid px-4">
        <div>
        <h1 class="h2 mb-2">Subjects Management</h1>
         <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Subjects</li>
                </ol>
            </nav>
    </div>
    <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
      <i class="bi bi-plus-lg me-2"></i> Add New Subject
    </button>

    <div id="alertMessage" class="alert" style="display: none;"></div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Subjects List</h5>
        </div>
        <div class="card-body">
           <div class="table-responsive p-3" style="max-height: 100%; overflow-y: auto;">
                <table id="subjectsTable" class="table table-hover align-middle p-2">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Description</th>
                            <th>Units</th>
                            <th>Assigned Teachers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subjectsTableBody">
                        <?php while ($row = $subjects_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-3"><?php echo htmlspecialchars($row['subject_code']); ?></td>
                                <td class="px-3"><?php echo htmlspecialchars($row['subject_description']); ?></td>
                                <td class="px-3"><?php echo htmlspecialchars($row['units']); ?></td>
                                <td class="px-3"><?php echo htmlspecialchars($row['teachers'] ?? 'No teachers assigned'); ?></td>
                                <td class="px-3">
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-success btn-sm assign-teacher" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#assignTeacherModal"
                                                data-id="<?php echo $row['subject_id']; ?>"
                                                data-code="<?php echo htmlspecialchars($row['subject_code']); ?>">
                                            Assign
                                        </button>
                                        <button type="button" 
                                                class="btn btn-primary btn-sm edit-subject" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editSubjectModal"
                                                data-id="<?php echo $row['subject_id']; ?>"
                                                data-code="<?php echo htmlspecialchars($row['subject_code']); ?>"
                                                data-description="<?php echo htmlspecialchars($row['subject_description']); ?>"
                                                data-units="<?php echo htmlspecialchars($row['units']); ?>">
                                            Edit
                                        </button>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm delete-subject"
                                                data-id="<?php echo $row['subject_id']; ?>">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1" aria-labelledby="assignTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignTeacherModalLabel">Manage Subject Teachers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Current Teachers Section -->
                <div class="mb-4">
                    <h6 class="mb-3">Currently Assigned Teachers</h6>
                    <div id="currentTeachersList" class="list-group">
                        <!-- Teachers will be loaded here dynamically -->
                    </div>
                </div>

                <!-- Assign New Teacher Section -->
                <form id="assignTeacherForm">
                    <input type="hidden" name="action" value="assign_teacher">
                    <input type="hidden" name="subject_id" id="assign_subject_id">
                    <input type="hidden" name="subject_code" id="assign_subject_code">
                    <div class="mb-3">
                        <label for="teacher_id" class="form-label">Assign New Teacher</label>
                        <select class="form-select" id="teacher_id" name="teacher_id" required>
                            <option value="">Select a teacher...</option>
                            <?php 
                            $teachers_result->data_seek(0);
                            while ($teacher = $teachers_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['t_id']; ?>">
                                    <?php echo htmlspecialchars($teacher['t_lname'] . ', ' . $teacher['t_fname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Assign Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
   $(document).ready(function () {
    $('table').DataTable({
        scrollY: '650px',
        scrollCollapse: true,
        responsive: true,
        paging: true,
        ordering: true,
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: -1 } // Make last column unsortable (e.g., action buttons)
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

document.addEventListener('DOMContentLoaded', function() {
    const alertMessage = document.getElementById('alertMessage');
    const BASE_URL = '/Project/dean/subjects/';
    
    function showAlert(message, type) {
        alertMessage.className = 'alert alert-' + type;
        alertMessage.textContent = message;
        alertMessage.style.display = 'block';
        setTimeout(() => {
            alertMessage.style.display = 'none';
        }, 3000);
    }

    function attachEventListeners() {
        // Edit button listeners
        document.querySelectorAll('.edit-subject').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const code = this.getAttribute('data-code');
                const description = this.getAttribute('data-description');
                const units = this.getAttribute('data-units');

                document.getElementById('edit_subject_id').value = id;
                document.getElementById('edit_subject_code').value = code;
                document.getElementById('edit_subject_description').value = description;
                document.getElementById('edit_units').value = units;
            });
        });

        // Delete button listeners
        document.querySelectorAll('.delete-subject').forEach(button => {
            button.addEventListener('click', async function() {
                if (confirm('Are you sure you want to delete this subject?')) {
                    const subjectId = this.getAttribute('data-id');
                    try {
                        const formData = new FormData();
                        formData.append('delete', subjectId);
                        
                        const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            showAlert('Subject deleted successfully', 'success');
                            updateSubjectsTable(data.subjects);
                        } else {
                            showAlert(data.message || 'Error deleting subject', 'danger');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showAlert('An error occurred while deleting the subject', 'danger');
                    }
                }
            });
        });
    }

    window.unassignTeacher = async function(subjectId, teacherId) {
        if (!confirm('Are you sure you want to remove this teacher from the subject?')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'unassign_teacher');
            formData.append('subject_id', subjectId);
            formData.append('teacher_id', teacherId);

            const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                showAlert('Teacher removed successfully', 'success');
                loadCurrentTeachers(subjectId);
                if (data.subjects) {
                    updateSubjectsTable(data.subjects);
                }
            } else {
                showAlert(data.message || 'Error removing teacher', 'danger');
            }
        } catch (error) {
            console.error('Error unassigning teacher:', error);
            showAlert('Error removing teacher from subject', 'danger');
        }
    };

    async function loadCurrentTeachers(subjectId) {
        try {
            const response = await fetch(`${BASE_URL}subjects_ajax.php?action=get_teachers&subject_id=${subjectId}`);
            const data = await response.json();
            
            const teachersList = document.getElementById('currentTeachersList');
            teachersList.innerHTML = '';
            
            if (data.teachers && data.teachers.length > 0) {
                data.teachers.forEach(teacher => {
                    const teacherItem = document.createElement('div');
                    teacherItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                    teacherItem.innerHTML = `
                        <span>${teacher.name}</span>
                        <button type="button" class="btn btn-danger btn-sm" onclick="unassignTeacher(${subjectId}, ${teacher.t_id})">
                            <i class="bi bi-x-lg"></i> Remove
                        </button>
                    `;
                    teachersList.appendChild(teacherItem);
                });
            } else {
                teachersList.innerHTML = '<div class="list-group-item text-muted">No teachers assigned</div>';
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            showAlert('Error loading current teachers', 'danger');
        }
    }

    function updateSubjectsTable(subjects) {
        const tbody = document.getElementById('subjectsTableBody');
        tbody.innerHTML = subjects.map(subject => `
            <tr>
                <td class="px-3">${subject.subject_code}</td>
                <td class="px-3">${subject.subject_description}</td>
                <td class="px-3">${subject.units}</td>
                <td class="px-3">${subject.teachers || 'No teachers assigned'}</td>
                <td class="px-3">
                    <div class="btn-group" role="group">
                        <button type="button" 
                                class="btn btn-success btn-sm assign-teacher" 
                                data-bs-toggle="modal" 
                                data-bs-target="#assignTeacherModal"
                                data-id="${subject.subject_id}"
                                data-code="${subject.subject_code}">
                            Assign
                        </button>
                        <button type="button" 
                                class="btn btn-primary btn-sm edit-subject" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editSubjectModal"
                                data-id="${subject.subject_id}"
                                data-code="${subject.subject_code}"
                                data-description="${subject.subject_description}"
                                data-units="${subject.units}">
                            Edit
                        </button>
                        <button type="button" 
                                class="btn btn-danger btn-sm delete-subject"
                                data-id="${subject.subject_id}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        attachEventListeners();
    }

    // Add Subject Form Submit
    document.getElementById('addSubjectForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                method: 'POST',
                body: new FormData(this)
            });
            const data = await response.json();
            if (data.status === 'success') {
                showAlert('Subject added successfully', 'success');
                this.reset();
                updateSubjectsTable(data.subjects);
                bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
            } else {
                showAlert(data.message || 'Error adding subject', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while adding the subject', 'danger');
        }
    });

    // Edit Subject Form Submit
    document.getElementById('editSubjectForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        try {
            const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                method: 'POST',
                body: new FormData(this)
            });
            const data = await response.json();
            if (data.status === 'success') {
                showAlert('Subject updated successfully', 'success');
                updateSubjectsTable(data.subjects);
                bootstrap.Modal.getInstance(document.getElementById('editSubjectModal')).hide();
            } else {
                showAlert(data.message || 'Error updating subject', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while updating the subject', 'danger');
        }
    });

    // Assign teacher form submission
    document.getElementById('assignTeacherForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'assign_teacher');

        try {
            const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                showAlert('Teacher assigned successfully', 'success');
                this.reset();
                loadCurrentTeachers(formData.get('subject_id'));
                if (data.subjects) {
                    updateSubjectsTable(data.subjects);
                }
            } else {
                showAlert(data.message || 'Error assigning teacher', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred while assigning the teacher', 'danger');
        }
    });

    // Update assign teacher modal to load current teachers
    const assignTeacherModal = document.getElementById('assignTeacherModal');
    assignTeacherModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const subjectId = button.getAttribute('data-id');
        const subjectCode = button.getAttribute('data-code');
        
        document.getElementById('assign_subject_id').value = subjectId;
        document.getElementById('assign_subject_code').value = subjectCode;
        
        loadCurrentTeachers(subjectId);
    });

    // Initial event listeners attachment
    attachEventListeners();
});
</script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>