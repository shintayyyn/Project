<?php
require_once __DIR__ . '/../../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Reports</title>
 <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
   
    /* Make Full Name column wider */
    #studentsTable th:nth-child(2),
    #studentsTable td:nth-child(2),
    #teachersTable th:nth-child(2),
    #teachersTable td:nth-child(2) {
        width: 50%; 
        min-width: 300px; 
    }

    #studentsTable th:nth-child(1),
    #studentsTable td:nth-child(1),
    #teachersTable th:nth-child(1),
    #teachersTable td:nth-child(1) {
        width: 20%;
        max-width: 150px;
    }

    #studentsTable th:nth-child(3),
    #studentsTable td:nth-child(3),
    #teachersTable th:nth-child(3),
    #teachersTable td:nth-child(3) {
        width: 10%;
        max-width: 100px;
        text-align: center;
    }

    /* Ensure table doesn't wrap weirdly */
    .table-responsive {
        overflow-x: auto;
    }

.avatar-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #0d6efd;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1rem;
    margin: 0 auto;
}

#studentsTable,#teachersTable,.attendanceTable{
overflow: hidden !important;
}

</style>
</head>
<body>
<div class="container-fluid">
    <h3 class="mb-2 fw-bold">Attendance Reports</h3>
     <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-3">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Attendance Reports</li>
                </ol>
            </nav>
    <ul class="nav nav-tabs mb-3" id="attendanceTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button">Students</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button">Teachers</button>
      </li>
    </ul>

    <div class="tab-content">
        <!-- Students Tab -->
        <div class="tab-pane fade show active" id="students">
            <div class="card p-2">
                <div class="table-responsive p-2">
                    <table class="table table-hover display nowrap" id="studentsTable">
                        <thead class="card-header">
                            <tr>
                                <th>ID Code</th>
                                <th>Full Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT * FROM students WHERE is_deleted=0 ORDER BY s_lname ASC");
                            while($student = $res->fetch_assoc()):
                                $fullName = $student['s_lname'] . ', ' . $student['s_fname'] . 
                                            ($student['s_mname'] ? ' ' . substr($student['s_mname'],0,1) . '.' : '') . 
                                            ($student['s_suffix'] ? ' ' . $student['s_suffix'] : '');
                            ?>
                            <tr>
                                <td><?= $student['idcode'] ?></td>
                                <td><?= htmlspecialchars($fullName) ?></td>
                                <td>
                                   <button 
                                    class="btn btn-sm btn-primary view-student" 
                                    data-id="<?= $student['s_id'] ?>" 
                                    data-name="<?= htmlspecialchars($fullName) ?>">
                                    View
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Teachers Tab -->
        <div class="tab-pane fade" id="teachers">
            <div class="card p-2">
                <div class="table-responsive p-2">
                    <table class="table table-hover display nowrap" id="teachersTable">
                        <thead class="card-header">
                            <tr>
                                <th>ID Code</th>
                                <th>Full Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT * FROM teachers WHERE is_deleted=0 ORDER BY t_lname ASC");
                            while($t = $res->fetch_assoc()):
                                $fullName = $t['t_lname'] . ', ' . $t['t_fname'] . 
                                            ($t['t_mname'] ? ' ' . substr($t['t_mname'],0,1) . '.' : '') . 
                                            ($t['t_suffix'] ? ' ' . $t['t_suffix'] : '');
                            ?>
                            <tr>
                                <td><?= $t['idcode'] ?></td>
                                <td><?= htmlspecialchars($fullName) ?></td>
                                <td>
                                  <button 
                                    class="btn btn-sm btn-primary view-teacher" 
                                    data-id="<?= $t['t_id'] ?>" 
                                    data-name="<?= htmlspecialchars($fullName) ?>">
                                    View
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"> Attendance Reports</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="analyticsContent">
        <!-- AJAX-loaded content (teacher/student attendance table) -->
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>

$(document).ready(function(){

    // Initialize Students and Teachers tables
    const tables = {
        students: $('#studentsTable').DataTable({
            scrollY: '50vh',
            scrollCollapse: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [5,10,25,50,100],
            ordering: true,
            autoWidth: false,
            scrollX: false,
            responsive: false,
            columnDefs: [{ targets: '_all'}],
            dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
            language: { lengthMenu: "Show _MENU_ entries" }
        }),
    };

    // Initialize teachers table only once when tab is shown
    $('button[data-bs-toggle="tab"][data-bs-target="#teachers"]').one('shown.bs.tab', function () {
        tables.teachers = $('#teachersTable').DataTable({
            scrollY: '50vh',
            scrollCollapse: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [5,10,25,50,100],
            ordering: true,
            autoWidth: false,
            scrollX: false,
            responsive: false,
            columnDefs: [{ targets: '_all'}],
            dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
            language: { lengthMenu: "Show _MENU_ entries" }
        });
    });

});



function loadAnalytics(url, idKey, idValue){
    $.post(url, { [idKey]: idValue }, function(res){
        $('#analyticsContent').html(res);

        const modalEl = document.getElementById('analyticsModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Initialize modal table after modal fully visible
        modalEl.addEventListener('shown.bs.modal', function initTable(){
            modalEl.removeEventListener('shown.bs.modal', initTable);

            if($.fn.DataTable.isDataTable('.attendanceTable')){
                // Adjust existing table
                attendanceTable.columns.adjust().draw(false);
            } else {
                // Initialize new table
                attendanceTable = $('.attendanceTable').DataTable({
                    scrollY: '50vh',
                    scrollCollapse: true,
                    paging: true,
                    pageLength: 10,
                    lengthMenu: [5,10,25,50,100],
                    ordering: true,
                    info: false,
                    autoWidth: false,
                    scrollX: false,
                    responsive: false,
                    columnDefs: [
                        { targets: '_all'},
                        { orderable: false, targets: -1 }
                    ],
                    dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
                    language: { lengthMenu: "Show _MENU_ entries" }
                });

               attendanceTable.columns.adjust().draw(false);
               
            }
        });
    });
}

// Load functions
function loadStudentAnalytics(s_id){ loadAnalytics('reports/s_analytics.php','s_id',s_id); }
function loadTeacherAnalytics(t_id){ loadAnalytics('reports/t_analytics.php','t_id',t_id); }

// Event listeners
$(document).on('click', '.view-student', function(){
    const id = $(this).data('id');
    const fullName = $(this).data('name');
    $('.modal-title').text('Attendance Reports - ' + fullName);
    loadStudentAnalytics(id);
});

$(document).on('click', '.view-teacher', function(){
    const id = $(this).data('id');
    const fullName = $(this).data('name');
    $('.modal-title').text('Attendance Reports - ' + fullName);
    loadTeacherAnalytics(id);
});



</script>

</body>
</html>
