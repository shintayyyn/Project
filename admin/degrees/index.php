
<?php
require_once __DIR__ . '/../../includes/db.php';

// Fetch all teachers for dean assignment dropdown
$teachers = $conn->query("SELECT t_id, CONCAT(t_fname, ' ', t_lname) AS name FROM teachers ORDER BY t_lname ASC");

// Fetch degrees with dean_id for modal population
$degree_query = "
    SELECT d.degree_code, d.degree_name, d.description, d.dean_id,
           ds.section_id, ds.section_code,
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
        GROUP BY section_id
    ) ss ON ds.section_id = ss.section_id
    ORDER BY d.degree_name
";
$result = $conn->query($degree_query);


// Organize data by degree
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

.row {
    margin-right: -15px;
    margin-left: -15px;
    display: flex;
    flex-wrap: wrap;
}

.col {
    padding: 10px;
}

/* Degree specific styles */
.degree-card {
    transition: transform 0.2s;
    margin-bottom: 1rem;
    width: 100%;
    background-color: #ffffff;
}
/* 
.header1{
    background-color: #033A70;
    color: #D0EEFC;
} */

.degree-card:hover {
    transform: translateY(-2px);
}


.stat-box {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    width: 100%;
}

.stat-box:hover {
    background: #e9ecef;
}

.stat-box i {
    font-size: 1.5rem;
    margin-right: 0.5rem;
}

.degree-description {
    max-height: 100px;
    overflow-y: auto;
    margin-bottom: 1rem;
    width: 100%;
}

/* Card body adjustments */
.card-body {
    padding: 1rem;
    overflow-x: hidden;
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
    </style>
</head>
<body>
    <!-- Main content -->
    <div class="container-fluid px-4">
        <h2>Manage Degrees</h2>
         <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Degrees</li>
                </ol>
            </nav>
        <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addDegreeModal">
            <i class="bi bi-plus-lg me-2"></i>Add Degree
        </button>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-0">
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
                                <button type="button" class="btn btn-primary px-2 edit-degree" data-bs-toggle="modal" data-bs-target="#editDegreeModal">
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
                        <p class="card-text small text-muted mb-2 degree-description"><?php echo htmlspecialchars($degree['description']); ?></p>
                        <p class="mb-1"><strong>Dean:</strong> <?php echo $degree_dean ?: '<span class="text-muted text-primary">None</span>'; ?></p>
                        <div class="row g-3 mb-4">
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
                            <table class="table table-responsive nowrap table-sm table-hover" id="sectionsTable">
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
    <script>
      $(document).ready(function () {
    var roomsTable = $('#sectionsTable').DataTable({
        scrollY: '50vh',           // vertical scroll height
        scrollCollapse: true,      // collapse if fewer rows
        responsive: true,          // make table responsive
        paging: true,
        ordering: true,
        info: true,
        lengthChange: true,
        searching: true,
        scrollX: false,            // disable horizontal scroll
        autoWidth: false,          // important for alignment
        columnDefs: [
            { orderable: false, targets: -1 } // make last column (Actions) unsortable
        ],
        dom: '<"row mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row mt-2"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        drawCallback: function(settings) {
            $('#roomsTable').css('table-layout', 'fixed'); // fix headers/cells alignment
        },
        language: {
            lengthMenu: "Show _MENU_ entries",
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
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

            // Delete Degree
            $('.delete-degree').on('click', function() {
                const degreeCode = $(this).closest('.card').data('degree-code');
                $('#delete_degree_code').val(degreeCode);
                $('#deleteDegreeModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                const degreeCode = $('#delete_degree_code').val();
                $.ajax({
                    url: '/admin/ajax/degrees_ajax.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        degree_code: degreeCode
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#deleteDegreeModal').modal('hide');
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

            // Reset forms when modals are closed
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('form').trigger('reset');
            });
        });
    </script>
</body>
</html>