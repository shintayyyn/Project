<?php
require_once __DIR__ . '/../../includes/db.php';

// Fetch all teachers for dean assignment dropdown
$teachers = $conn->query("SELECT t_id, CONCAT(t_fname, ' ', t_lname) AS name FROM teachers ORDER BY t_lname ASC");

// ----------------------
// Get active term ID
// ----------------------
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $active_term_id = $term_result->fetch_assoc()['term_id'];
} else {
    $active_term_id = null; // fallback if no active term found
}

// ----------------------
// Fetch degrees with sections & student counts (based on active term)
// ----------------------
$degree_query = "
    SELECT 
        d.degree_code, 
        d.degree_name, 
        d.description, 
        d.dean_id,
        ds.section_id, 
        ds.section_code,
        COALESCE(ss.student_count, 0) AS student_count
    FROM degrees d
    LEFT JOIN (
        SELECT ds1.section_id, ds1.degree_code, ds1.section_code
        FROM degrees_sections ds1
        WHERE ds1.updated_at = (
            SELECT MAX(ds2.updated_at)
            FROM degrees_sections ds2
            WHERE ds2.degree_code = ds1.degree_code
        )
    ) ds ON d.degree_code = ds.degree_code
    LEFT JOIN (
        SELECT section_id, COUNT(*) AS student_count
        FROM students_sections
        WHERE term_id = ?
        GROUP BY section_id
    ) ss ON ds.section_id = ss.section_id
    ORDER BY d.degree_name
";

$stmt = $conn->prepare($degree_query);
$stmt->bind_param('i', $active_term_id);
$stmt->execute();
$result = $stmt->get_result();

// ----------------------
// Organize data by degree
// ----------------------
$degrees = [];
while ($row = $result->fetch_assoc()) {
    $degree_code = $row['degree_code'];
    if (!isset($degrees[$degree_code])) {
        $degrees[$degree_code] = [
            'name' => $row['degree_name'],
            'description' => $row['description'],
            'dean_id' => $row['dean_id'],
            'sections' => [],
            'total_students' => 0
        ];
    }
    if ($row['section_id']) {
        $degrees[$degree_code]['sections'][] = [
            'section_code' => $row['section_code'],
            'student_count' => $row['student_count']
        ];
        $degrees[$degree_code]['total_students'] += $row['student_count'];
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Degrees Management</title>
<!-- <link rel="stylesheet" href="../css/common.css"> -->

    <style>
/* Main content area fix */
body {
    overflow-x: hidden;
    background-color: #f0f2f5;
    margin: 0;
    padding: 0;
}

main {
    min-height: 100vh;
    margin: 0;
    padding: 1rem;
    background-color: #f0f2f5;
}

/* Container and row adjustments */
.container-fluid {
    padding: 0 15px;
    margin-right: auto;
    margin-left: auto;
}

/* ===== Degree Cards ===== */
.degree-card {
    transition: transform 0.2s ease-in-out;
    margin-bottom: 1rem;
    background-color: #ffffff;
    border-radius: 10px;
    overflow: visible !important; 
    height:100vh;
}

.degree-card:hover {
    transform: translateY(-4px);
}

/* ===== Card Body (No Scrolls) ===== */
.card-body {
    overflow: hidden !important; /* Prevent any scroll */
}

/* ===== Stat Cards Inside Degree Card ===== */
.stat-card {
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: scale(1.02);
}

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

/* ===== Degree Description ===== */
.degree-description {
    max-height: 100px;
    overflow: hidden;
    margin-bottom: 1rem;
}

/* ===== Table Adjustments ===== */
.sections-list {
  overflow-y: visible;             /* scroll vertically only */
  overflow-x: hidden;           /* prevent horizontal scroll */
    width: 100%;
}

.sectionsTable {
    width: 100%;
    table-layout: fixed; /* Makes columns behave consistently */
    border-collapse: collapse;
}

.sectionsTable th, 
.sectionsTable td {
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
}

/* Prevents text wrapping in header */
.sectionsTable thead th {
    font-size: 0.85rem;
}

/* ===== Responsive Cards (3 → 2 → 1) ===== */
@media (max-width: 1400px) {
    .row-cols-xl-3 > * {
        flex: 0 0 auto;
        width: 50%; /* 2 cards per row */
    }
}

@media (max-width: 992px) {
    .row-cols-xl-3 > * {
        width: 100%; /* 1 card per row on smaller screens */
    }
}

/* Button adjustments */
.btn-group {
    margin: 0;
    padding: 0;
}

.btn {
    white-space: nowrap;
}
        .degree-card {
            transition: transform 0.2s;
        }
        .degree-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            border-radius: 10px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: scale(1.02);
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
        }

        .dataTables_wrapper {
  padding: 10px 10px 15px 10px;  /* ⬅️ space all around */
  overflow: visible !important;  /* prevent clipping inside card */
}

.table {
  margin-bottom: 5px !important;  /* keep table tidy inside card */
}

.dataTables_scrollBody {
  overflow-x: hidden !important; /* ensure no horizontal cut */
}
.card .dataTables_wrapper {
  border-radius: 8px;
}

.dataTables_length,
.dataTables_filter {
  margin-bottom: 10px;  /* space below top controls */
}

.dataTables_info,
.dataTables_paginate {
  margin-top: 10px;     /* space above bottom controls */
  font-size: 0.875rem;
}


/* Ensure card never hides footer controls */
.card {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Keep DataTable body scrollable but footer fixed inside card */
.dataTables_wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}

/* Scroll only the table body */
.dataTables_scrollBody {
  flex: 1;
  overflow-y: auto !important;
  overflow-x: hidden !important;
  max-height: 30vh;
}

/* Footer (info + pagination) always visible */
.dataTables_info,
.dataTables_paginate {
  background: #fff;
  z-index: 10;
  position: sticky;
  bottom: 0;
  padding: 8px 0;
}

/* Better spacing & responsive layout */
.dataTables_length,
.dataTables_filter {
  margin-bottom: 8px;
}

.dataTables_info {
  margin-bottom: 5px;
  font-size: 0.875rem;
}

.dataTables_paginate {
  margin-bottom: 10px;
}

/* Responsive pagination alignment */
@media (max-width: 768px) {
  .dataTables_length,
  .dataTables_filter,
  .dataTables_info,
  .dataTables_paginate {
    text-align: center;
    display: block;
    width: 100%;
  }
}


    </style>
</head>
<body>
    <!-- Main content -->
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1 fw-bold">Manage Degrees</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Degrees</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addDegreeModal">
        <i class="bi bi-plus-lg me-2"></i>Add Degree
    </button>
</div>

      <!-- Filters Row -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">

  <!-- Left Side: Degree Filter -->
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
          padding-left: 46px; /* space for icon */
          -webkit-appearance: none;
          -moz-appearance: none;
          appearance: none;
        ">
        <option value="">All Degrees</option>
        <?php foreach ($degrees as $degree_code => $degree): ?>
          <option value="<?= htmlspecialchars($degree_code); ?>">
            <?= htmlspecialchars($degree_code . ' - ' . $degree['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Right Side: Search Input -->
  <div class="d-flex align-items-center gap-2">
    <div class="position-relative flex-grow-1">
      <!-- Search icon -->
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

      <!-- Search Input -->
      <input 
        type="text" 
        id="searchSection" 
        class="form-control ps-5 pe-5" 
        placeholder="Search..."
        style="
          height: 50px; 
          border: 1px solid #033A70; 
          border-radius: 8px; 
          vertical-align: middle;
          min-width: 250px;
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



       <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="degreesContainer">
    <?php foreach ($degrees as $degree_code => $degree) : ?>
    <?php
        // Fetch dean name for display
        $degree_dean = '';
        if (!empty($degree['dean_id'])) {
            $dean_stmt = $conn->prepare("SELECT CONCAT(t_fname, ' ', t_lname) AS name FROM teachers WHERE t_id = ?");
            $dean_stmt->bind_param("i", $degree['dean_id']);
            $dean_stmt->execute();
            $dean_stmt->bind_result($degree_dean);
            $dean_stmt->fetch();
            $dean_stmt->close();
        }
    ?>
    <div class="col">
        <div class="card h-100 shadow-sm degree-card"
             data-degree-code="<?php echo htmlspecialchars($degree_code); ?>"
             data-degree-name="<?php echo htmlspecialchars($degree['name']); ?>"
             data-description="<?php echo htmlspecialchars($degree['description']); ?>"
             data-dean-id="<?php echo htmlspecialchars($degree['dean_id'] ?? ''); ?>">
             
            <div class="card-header header1">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($degree_code); ?></h5>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-warning px-2 edit-degree" data-bs-toggle="modal" data-bs-target="#editDegreeModal">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-danger px-2 delete-degree">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <h6 class="card-title"><?php echo htmlspecialchars($degree['name']); ?></h6>
                <p class="card-text small text-muted degree-description"><?php echo htmlspecialchars($degree['description']); ?></p>
                <p class="mb-1"><strong>Dean:</strong> <?php echo $degree_dean ?: '<span class="text-muted text-primary">None</span>'; ?></p>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="card bg-light stat-card">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Sections</h6>
                                        <h3 class="mb-0"><?php echo count($degree['sections']); ?></h3>
                                    </div>
                                    <i class="bi bi-collection stat-icon text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-light stat-card">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title mb-0">Students</h6>
                                        <h3 class="mb-0"><?php echo $degree['total_students']; ?></h3>
                                    </div>
                                    <i class="bi bi-people stat-icon text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sections-list">
                    <table class="table table-responsive nowrap table-sm table-hover sectionsTable mb-0">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th class="text-end">Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($degree['sections'] as $section) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($section['section_code']); ?></td>
                                <td class="text-end"><?php echo $section['student_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
    </div>

    <!-- Add Degree Modal -->
    <div class="modal fade" id="addDegreeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Degree</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDegreeForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="degree_code" class="form-label">Degree Code</label>
                            <input type="text" class="form-control" id="degree_code" name="degree_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="degree_name" class="form-label">Degree Name</label>
                            <input type="text" class="form-control" id="degree_name" name="degree_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="degree_description" class="form-label">Description</label>
                            <textarea class="form-control" id="degree_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="add_dean_id" class="form-label">Dean (Teacher)</label>
                            <select class="form-select" id="add_dean_id" name="dean_id">
                                <option value="">-- No Dean --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['t_id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addDegreeForm" class="btn btn-primary">Add Degree</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Degree Modal -->
    <div class="modal fade" id="editDegreeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Degree</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editDegreeForm" class="needs-validation" novalidate>
                        <input type="hidden" id="edit_original_code" name="original_code">
                        <div class="mb-3">
                            <label for="edit_degree_code" class="form-label">Degree Code</label>
                            <input type="text" class="form-control" id="edit_degree_code" name="degree_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_degree_name" class="form-label">Degree Name</label>
                            <input type="text" class="form-control" id="edit_degree_name" name="degree_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_dean_id" class="form-label">Dean (Teacher)</label>
                            <select class="form-select" id="edit_dean_id" name="dean_id">
                                <option value="">-- No Dean --</option>
                                <?php
                                // Re-fetch teachers for edit modal (since $teachers is exhausted)
                                $teachers2 = $conn->query("SELECT t_id, CONCAT(t_fname, ' ', t_lname) AS name FROM teachers ORDER BY t_lname ASC");
                                foreach ($teachers2 as $teacher): ?>
                                    <option value="<?php echo $teacher['t_id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editDegreeForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteDegreeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Degree</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this degree? This action cannot be undone.</p>
                    <input type="hidden" id="delete_degree_code">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="admin/assets/jquery.min.js"></script>
    <script src="admin/assets/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
$(document).ready(function () {
  $('.sectionsTable').each(function () {
    $(this).DataTable({
      scrollY: '30vh',          // vertical scroll
      scrollCollapse: true,
      scrollX: false,           // disable horizontal scroll
      responsive: true,
      paging: true,
      ordering: true,
      info: true,
      lengthChange: true,
      searching: true,
      autoWidth: false,
      dom:
        '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
        '<"row"<"col-sm-12"tr>>' +
        '<"row mb-2 mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
      language: {
        lengthMenu: "Show _MENU_ entries",
        search: "Search:",
        info: "Showing _START_ to _END_ of _TOTAL_ "
      },
      drawCallback: function () {
        $(this).closest('.dataTables_wrapper').find('.dataTables_scrollBody').css('max-height', '30vh');
      }
    });
  });
});



        $(document).ready(function() {
            // Add Degree
            $('#addDegreeForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'add');

                $.ajax({
                    url: '/admin/ajax/degrees_ajax.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#addDegreeModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.error || 'Failed to add degree');
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'Failed to add degree';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.error || errorMessage;
                        } catch (e) {
                            errorMessage = xhr.responseText || errorMessage;
                        }
                        alert(errorMessage);
                    }
                });
            });

            // Edit Degree
            $('.edit-degree').on('click', function() {
                const card = $(this).closest('.card');
                $('#edit_original_code').val(card.data('degree-code'));
                $('#edit_degree_code').val(card.data('degree-code'));
                $('#edit_degree_name').val(card.data('degree-name'));
                $('#edit_description').val(card.data('description'));
                $('#edit_dean_id').val(card.data('dean-id') || '');
            });

            $('#editDegreeForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'edit');

                $.ajax({
                    url: '/admin/ajax/degrees_ajax.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#editDegreeModal').modal('hide');
                            location.reload();
                        } else {
                            alert(response.error);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseJSON?.error || 'Something went wrong');
                    }
                });
            });

         // Delete Degree using SweetAlert
$('.delete-degree').on('click', function() {
    const degreeCode = $(this).closest('.card').data('degree-code');

    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete the degree: ${degreeCode}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // AJAX request to delete degree
            $.ajax({
                url: '/admin/ajax/degrees_ajax.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    degree_code: degreeCode
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: `Degree ${degreeCode} has been deleted.`,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.error || 'Something went wrong', 'error');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', xhr.responseJSON?.error || 'Something went wrong', 'error');
                }
            });
        }
    });
});

            // Reset forms when modals are closed
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form').trigger('reset');
            });
        });

// --------------------- Filter Degrees ---------------------
const degreeFilter = document.getElementById('degreeFilter');
const searchSection = document.getElementById('searchSection');
const clearBtn = document.getElementById('clearSearch');
const degreesContainer = document.getElementById('degreesContainer'); // Parent container of all degree cards
const noDegreesAlertId = 'noDegreesAlert';

function filterDegrees() {
    const degreeValue = degreeFilter.value.toLowerCase();
    const searchValue = searchSection.value.toLowerCase();
    let visibleCount = 0;

    document.querySelectorAll('.degree-card').forEach(card => {
        const cardDegreeCode = (card.dataset.degreeCode || '').toLowerCase();
        const cardDegreeName = (card.dataset.degreeName || '').toLowerCase();
        const cardDescription = (card.dataset.description || '').toLowerCase();
        const cardText = card.textContent.toLowerCase();

        const matchesDegree = !degreeValue || cardDegreeCode === degreeValue;
        const matchesSearch =
            !searchValue ||
            cardDegreeName.includes(searchValue) ||
            cardDegreeCode.includes(searchValue) ||
            cardDescription.includes(searchValue) ||
            cardText.includes(searchValue);

        const isVisible = matchesDegree && matchesSearch;
        card.closest('.col').style.display = isVisible ? '' : 'none';

        if (isVisible) visibleCount++;
    });

    // --- Counter Badge (always above alert) ---
    let countBadge = document.getElementById('visibleCountBadge');
    if (!countBadge) {
        countBadge = document.createElement('div');
        countBadge.id = 'visibleCountBadge';
        countBadge.className = 'badge bg-primary mb-2 p-2 fs-6';
        degreesContainer.parentNode.insertBefore(countBadge, degreesContainer);
    }
    countBadge.textContent = `Showing ${visibleCount} ${visibleCount === 1 ? 'Program Offered' : 'Programs Offered'}`;

    // --- Alert Box (below counter, above container) ---
    let alertBox = document.getElementById(noDegreesAlertId);
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = noDegreesAlertId;
        alertBox.className = 'alert alert-info text-center';
        alertBox.textContent = 'No degrees found for the selected filter.';
        // Insert alert right after the badge
        countBadge.after(alertBox);
    }

    alertBox.style.display = visibleCount === 0 ? '' : 'none';
}

// 🔹 Event listeners
degreeFilter?.addEventListener('change', filterDegrees);
searchSection?.addEventListener('input', filterDegrees);

// 🔹 Clear button logic
if (clearBtn) {
    searchSection.addEventListener('input', () => {
        clearBtn.style.display = searchSection.value ? 'inline-block' : 'none';
    });

    clearBtn.addEventListener('click', () => {
        searchSection.value = '';
        clearBtn.style.display = 'none';
        searchSection.focus();
        filterDegrees();
    });

    clearBtn.addEventListener('mouseover', () => (clearBtn.style.opacity = '1'));
    clearBtn.addEventListener('mouseout', () => (clearBtn.style.opacity = '0.8'));
}

// Initial call
filterDegrees();


        
    </script>
</body>
</html>