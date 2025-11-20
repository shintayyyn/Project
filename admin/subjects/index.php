<?php
require_once __DIR__ . '/../../includes/db.php';

// =======================
// Fetch all terms
// =======================
$terms = [];
$active_term_id = null;
$terms_query = "
    SELECT 
        t.term_id,
        CONCAT('A.Y. ', ay.year_start, '-', ay.year_end, ' | ',
            CASE
                WHEN t.semester = 1 THEN '1st Semester'
                WHEN t.semester = 2 THEN '2nd Semester'
                WHEN t.semester = 3 THEN 'Summer'
                ELSE t.semester
            END
        ) AS term_label,
        t.is_active
    FROM academic_terms t
    JOIN academic_years ay ON t.ay_id = ay.ay_id
    ORDER BY ay.year_start DESC, t.semester DESC
";
$terms_result = $conn->query($terms_query);
while ($row = $terms_result->fetch_assoc()) {
    $terms[] = $row;
    if ((int)$row['is_active'] === 1) {
        $active_term_id = (int)$row['term_id'];
    }
}

// =======================
// Fetch all subjects with term info and teachers assigned only for active term
// =======================
$subjects_query = "
    SELECT 
        s.subject_id,
        s.subject_code,
        s.subject_description,
        s.units,
        s.term_id,
        d.degree_code,
        d.degree_name,
        at.semester,
        ay.year_start,
        ay.year_end,
        COALESCE(
            GROUP_CONCAT(
                CASE WHEN st.term_id = ? THEN CONCAT(t.t_fname, ' ', t.t_lname) END
                SEPARATOR ', '
            ),
            'No teachers assigned'
        ) AS teachers
    FROM subjects s
    JOIN degrees d ON s.degree_id = d.degree_id
    LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id
    LEFT JOIN teachers t ON st.t_id = t.t_id
    LEFT JOIN academic_terms at ON s.term_id = at.term_id
    LEFT JOIN academic_years ay ON at.ay_id = ay.ay_id
    GROUP BY s.subject_id
    ORDER BY d.degree_code, s.subject_code
";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $active_term_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// =======================
// Fetch degrees
// =======================
$degrees = [];
$sql = "SELECT degree_code, degree_name FROM degrees ORDER BY degree_name ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $degrees[] = $row;
}

// =======================
// Group subjects by degree
// =======================
$subjects_by_degree = [];
while ($row = $subjects_result->fetch_assoc()) {
    $degree_code = $row['degree_code'];
    if (!isset($subjects_by_degree[$degree_code])) {
        $subjects_by_degree[$degree_code] = [
            'degree_name' => $row['degree_name'],
            'subjects' => []
        ];
    }

    // Format term label from academic_terms
    $semester_label = match((int)$row['semester']) {
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => 'Summer',
        default => $row['semester']
    };

    $row['term_label'] = (!empty($row['year_start']) && !empty($row['year_end']))
        ? "A.Y. {$row['year_start']} - {$row['year_end']} | {$semester_label}"
        : "<span class='text-muted'>No term assigned</span>";

    $subjects_by_degree[$degree_code]['subjects'][] = $row;
}


?>




<!-- Main content -->
 <div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
  <!-- Left side: Title + Breadcrumb -->
  <div>
    <h2 class="mb-1 fw-bold">Subjects Management</h2>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">Subjects</li>
      </ol>
    </nav>
  </div>

  <!-- Right side: Button -->
  <div>
    <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
      <i class="bi bi-plus"></i> Add New Subject
    </button>
  </div>
</div>

    <div id="card-container">
<!-- Filters Row -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
  <div class="position-relative">
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

    <select id="degreeFilter" class="form-select w-auto" 
            style="
              border: 1px solid #033A70; 
              border-radius: 8px; 
              height: 50px; 
              padding-left: 46px; /* icon space */
              padding-top: 0;
              padding-bottom: 0;
              display: inline-block;
              vertical-align: middle;
              -webkit-appearance: none;
              -moz-appearance: none;
              appearance: none;
            ">
      <option value="" selected>All Degrees</option>
      <?php foreach ($subjects_by_degree as $degree_code => $degree_data): ?>
        <option value="<?php echo htmlspecialchars($degree_code); ?>">
          <?php echo htmlspecialchars($degree_code . ' - ' . $degree_data['degree_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>


 <div class="d-flex align-items-center gap-2">
  <label for="searchInput" class="form-label mb-0"></label>
  <div class="position-relative flex-grow-1">
    <!-- Search icon inside span -->
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
      <i class="fa-brands fa-searchengin fa-lg"></i>
    </span>

    <!-- Input -->
    <input 
      type="text" 
      id="searchInput" 
      class="form-control ps-5 pe-5" 
      placeholder="Search..."
      style="
        height: 50px; 
        border: 1px solid #033A70; 
        border-radius: 8px; 
        vertical-align: middle;
      "
    >

    <!-- Clear button -->
    <button 
      type="button" 
      id="clearSearch" 
      class="btn-close position-absolute end-0 top-50 translate-middle-y me-2" 
      aria-label="Clear search" 
      style="display:none; width: 38px; height: 38px; font-size: 0.8rem;">
    </button>
  </div>
</div>


</div>

<!-- No data message (hidden by default) -->
<div id="noDataMessage" class="alert alert-info text-center d-none">
    No subjects found for the selected filter/search.
</div>
<div id="subjectsWrapper">
<?php if (!empty($subjects_by_degree)): ?>
    <?php foreach ($subjects_by_degree as $degree_code => $degree_data): ?>
        <div class="mb-4 degree-section" data-degree="<?php echo htmlspecialchars($degree_code); ?>">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-mortarboard-fill me-2"></i>
                        <?php echo htmlspecialchars($degree_code . ' - ' . $degree_data['degree_name']); ?>
                    </h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table table-hover align-middle p-2 subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Description</th>
                                    <th>Units</th>
                                    <th>Term Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody >
                                <?php if (!empty($degree_data['subjects'])): ?>
                                    <?php foreach ($degree_data['subjects'] as $subject): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['subject_description']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['units']); ?></td>
                                           <td>
                                                <?php if (!empty($subject['term_label'])): ?>
                                                    <?= htmlspecialchars($subject['term_label']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No Term Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 w-100">
                                                    <button class="btn btn-sm btn-primary flex-fill edit-subject"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editSubjectModal"
                                                        data-id="<?php echo $subject['subject_id']; ?>"
                                                        data-code="<?php echo htmlspecialchars($subject['subject_code']); ?>"
                                                        data-description="<?php echo htmlspecialchars($subject['subject_description']); ?>"
                                                        data-units="<?php echo htmlspecialchars($subject['units']); ?>">
                                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-danger flex-fill delete-subject"
                                                        data-id="<?php echo $subject['subject_id']; ?>">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No subjects available for this degree.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> <!-- card -->
        </div> <!-- mb-4 -->
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-info">No degrees or subjects found.</div>
<?php endif; ?>
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
                    <label for="degree_code" class="form-label">Department</label>
                    <select class="form-select js-example-basic-multiple" id="degree_code" name="degree_code[]" multiple="multiple" required>
                        <?php foreach ($degrees as $degree): ?>
                            <option value="<?= htmlspecialchars($degree['degree_code']) ?>">
                                <?= htmlspecialchars($degree['degree_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                            <small class="text-muted">Kindly select a department/s.</small>
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


<!-- DataTables CSS and JS for Bootstrap 5 -->
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
<!-- Select2 Bootstrap 5 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" />
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
    $('.js-example-basic-multiple').select2({
        theme: 'bootstrap-5',
        placeholder: "Select Department(s)",
        allowClear: true
    });
     $(document).ready(function() {
   $('.subjectsTable').DataTable({
    paging: true,
    searching: true,
    ordering: true,
    pageLength: 10,
    lengthMenu: [5, 10, 25, 50, 100],

    scrollY: "60vh",
    scrollCollapse: true,
    scrollX: false, // ❌ no horizontal scroll

    autoWidth: false, // helps prevent auto-expanding columns

    columnDefs: [
        { orderable: false, targets: -1 } // Disable sorting on last column (Actions)
    ],

    language: {
        lengthMenu: "Show _MENU_ entries",
        search: "Search:",
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        zeroRecords: "No matching records found",
        emptyTable: "No data available in table"
    }
});
     });

});

   document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = '/admin/subjects/'; // <-- Adjust path to match your project folder

    // -------------------- Update Subjects Table --------------------
    function updateSubjectsTable(subjects) {
        const container = document.getElementById('subjectsWrapper');
        container.innerHTML = ''; // Clear existing content

        if (!subjects.length) {
            container.innerHTML = `
                <div class="alert alert-info text-center">
                    No degrees or subjects found.
                </div>
            `;
            return;
        }

        // Group subjects by degree
        const grouped = {};
        subjects.forEach(subject => {
            if (!grouped[subject.degree_code]) {
                grouped[subject.degree_code] = {
                    degree_name: subject.degree_name,
                    subjects: []
                };
            }
            grouped[subject.degree_code].subjects.push(subject);
        });

        // Build grouped tables
        Object.entries(grouped).forEach(([degree_code, group]) => {
            const degreeSection = document.createElement('div');
            degreeSection.className = 'mb-4 degree-section';
            degreeSection.setAttribute('data-degree', degree_code);

            degreeSection.innerHTML = `
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-mortarboard-fill me-2"></i>
                            ${degree_code} - ${group.degree_name}
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive p-3">
                            <table class="table table-hover align-middle p-2 subjectsTable">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Description</th>
                                        <th>Units</th>
                                        <th>Term</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${group.subjects.map(subject => `
                                        <tr>
                                            <td>${subject.subject_code}</td>
                                            <td>${subject.subject_description}</td>
                                            <td>${subject.units}</td>
                                            <td>
                                                ${subject.term_label || '<span class="text-muted">No Term Assigned</span>'}
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2 w-100">
                                                    <button class="btn btn-sm btn-primary flex-fill edit-subject"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editSubjectModal"
                                                        data-id="${subject.subject_id}"
                                                        data-code="${subject.subject_code}"
                                                        data-description="${subject.subject_description}"
                                                        data-units="${subject.units}">
                                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-danger flex-fill delete-subject"
                                                        data-id="${subject.subject_id}">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(degreeSection);
        });

        // Reinitialize DataTables
        initializeDataTables();

        // Reattach events
        attachEventListeners();
    }

    // -------------------- Initialize DataTables --------------------
    function initializeDataTables() {
        const tables = document.querySelectorAll('.subjectsTable');
        tables.forEach(table => {
            if (!$.fn.DataTable.isDataTable(table)) {
                $(table).DataTable({
                    scrollY: '50vh',
                    scrollCollapse: true,
                    responsive: true,
                    paging: true,
                    ordering: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    columnDefs: [
                        { orderable: false, targets: -1 } // Last column unsortable
                    ]
                });
            }
        });
    }

    // -------------------- Event Listeners --------------------
    function attachEventListeners() {
        // Edit buttons
        document.querySelectorAll('.edit-subject').forEach(button => {
            button.addEventListener('click', function () {
                document.getElementById('edit_subject_id').value = this.dataset.id;
                document.getElementById('edit_subject_code').value = this.dataset.code;
                document.getElementById('edit_subject_description').value = this.dataset.description;
                document.getElementById('edit_units').value = this.dataset.units;
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-subject').forEach(button => {
            button.addEventListener('click', async function () {
                const subjectId = this.dataset.id;

                const confirmDelete = await Swal.fire({
                    icon: 'warning',
                    title: 'Are you sure?',
                    text: "This will delete the subject permanently!",
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                });

                if (!confirmDelete.isConfirmed) return;

                try {
                    const formData = new FormData();
                    formData.append('delete', subjectId);

                    const response = await fetch(BASE_URL + 'subjects_ajax.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        showAlert('success', 'Subject deleted successfully');
                        updateSubjectsTable(data.subjects);
                    } else {
                        showAlert('danger', data.message || 'Error deleting subject');
                    }
                } catch (error) {
                    console.error(error);
                    showAlert('danger', 'An error occurred while deleting the subject');
                }
            });
        });
    }
// -------------------- Add Subject --------------------
// -------------------- Add Subject --------------------
document.getElementById('addSubjectForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    try {
        const formData = new FormData(this);
        formData.append('action', 'add');

        // FIX: Append multiple degree_code[] properly
        const degreeSelect = document.getElementById('degree_code');
        const selectedDegrees = Array.from(degreeSelect.selectedOptions).map(opt => opt.value);

        // Remove any existing degree_code from FormData
        formData.delete('degree_code[]');

        // Append each selected degree_code[] manually
        selectedDegrees.forEach(code => {
            formData.append('degree_code[]', code);
        });

        // Debugging - check payload
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }

        const response = await fetch(BASE_URL + 'subjects_ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            showAlert('success', 'Subject added successfully');
            this.reset();
            updateSubjectsTable(data.subjects);

            // Proper way to close modal
            const addModalEl = document.getElementById('addSubjectModal');
            const addModal = bootstrap.Modal.getOrCreateInstance(addModalEl);
            addModal.hide();
        } else {
            showAlert('danger', data.message || 'Error adding subject');
        }
    } catch (error) {
        console.error(error);
        showAlert('danger', 'An error occurred while adding the subject');
    }
});

// -------------------- Edit Subject --------------------
document.getElementById('editSubjectForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    try {
        const formData = new FormData(this);
        formData.append('action', 'edit');

        const response = await fetch(BASE_URL + 'subjects_ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            showAlert('success', 'Subject updated successfully');
            updateSubjectsTable(data.subjects);

            // Proper way to close modal
            const editModalEl = document.getElementById('editSubjectModal');
            const editModal = bootstrap.Modal.getOrCreateInstance(editModalEl);
            editModal.hide();
        } else {
            showAlert('danger', data.message || 'Error updating subject');
        }
    } catch (error) {
        console.error(error);
        showAlert('danger', 'An error occurred while updating the subject');
    }
});

    // Initialize events
    attachEventListeners();
});
// <!-- JS Filtering -->
const degreeFilter = document.getElementById('degreeFilter');
const searchInput = document.getElementById('searchInput');
const noDataMessage = document.getElementById('noDataMessage');
const clearBtn = document.getElementById('clearSearch'); // 👈 clear button

function applyFilters() {
    let selected = degreeFilter.value.toLowerCase();
    let search = searchInput.value.toLowerCase();
    let anyVisible = false;

    document.querySelectorAll('.degree-section').forEach(section => {
        let degreeCode = section.getAttribute('data-degree').toLowerCase();
        let matchDegree = !selected || degreeCode === selected;

        let anyRowVisible = false;
        section.querySelectorAll('tbody tr').forEach(row => {
            let rowText = row.innerText.toLowerCase();
            let matchSearch = rowText.includes(search);
            if (matchDegree && matchSearch) {
                row.style.display = '';
                anyRowVisible = true;
            } else {
                row.style.display = 'none';
            }
        });

        if (anyRowVisible) {
            section.style.display = '';
            anyVisible = true;
        } else {
            section.style.display = 'none';
        }
    });

    // Toggle "No Data" message visibility
    noDataMessage.classList.toggle('d-none', anyVisible);
}

// 🔹 Event listeners
degreeFilter.addEventListener('change', applyFilters);
searchInput.addEventListener('keyup', applyFilters);

// 🔹 Clear button logic
if (clearBtn) {
    // Show/hide clear button when typing
    searchInput.addEventListener('input', () => {
        clearBtn.style.display = searchInput.value ? 'block' : 'none';
    });

    // Clear search and re-apply filters
    clearBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        searchInput.focus();
        applyFilters();
    });

    // Optional: hover effect
    clearBtn.addEventListener('mouseover', () => (clearBtn.style.opacity = '1'));
    clearBtn.addEventListener('mouseout', () => (clearBtn.style.opacity = '0.8'));
}

</script>