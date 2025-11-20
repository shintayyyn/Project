  <?php
  error_reporting(1);

  if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
      header("Location:  /dean/login.php");
      exit;
  }

  require_once __DIR__ . '/../../includes/db.php';

  $base_url = '/dean/teachers/processes';
  $search = isset($_GET['search']) ? $_GET['search'] : '';
  $search_condition = $search ? "WHERE t_fname LIKE '%$search%' OR t_lname LIKE '%$search%' OR t_id LIKE '%$search%'" : '';


  $dean_id = intval($_SESSION['t_id']);

// Fetch degrees assigned to the dean
$degree_ids = [];
$stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE dean_id = ?");
$stmt->bind_param("i", $dean_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $degree_ids[] = intval($row['degree_id']);
}
$stmt->close();

// Prevent SQL error if no assigned degrees
$degree_list = !empty($degree_ids) ? implode(",", $degree_ids) : "0";

  // Fetch Departments
  $departments = [];
  $result = $conn->query("SELECT degree_id, degree_name FROM degrees ORDER BY degree_name");
  if ($result) {
      while ($row = $result->fetch_assoc()) {
          $departments[] = $row;
      }
  }

  // Fetch Teachers
$sql = "
    SELECT 
        t.*, 
        d.degree_code AS department_name,
        t.t_status
    FROM teachers t
    LEFT JOIN degrees d ON t.t_department = d.degree_id
    WHERE t.t_department IN ($degree_list)
    $search_condition
    ORDER BY t.t_lname ASC
";
$result = $conn->query($sql);
  ?>

<style>
.active-row {
  background-color: #ffffffff !important;
  transition: background-color 0.3s ease;
}


.profile-avatar {
    background-color: #033A70;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
}

.card-body-empty {
    text-align: center;
    padding: 40px 10px;
    color: #666;
    font-size: 1.1rem;
}

.card-body-empty i {
    font-size: 2rem;
    color: #aaa;
    display: block;
    margin-bottom: 10px;
}

.card-body, table {
    overflow: hidden;
}

.table-hover tbody tr:hover td {
    background-color: rgba(61, 82, 160, 0.05) !important;
}

.action-buttons .btn {
    min-width: 80px;
}


</style>

<div class="container-fluid p-0">

    <!-- Notification Area -->
    <div id="notificationArea"></div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold">Manage Teachers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Teachers</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="bi bi-plus-lg me-2"></i>Add New Teacher
        </button>
    </div>

    <div class="row g-3">
        <!-- Table Column -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold">Teachers List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive p-3">
                        <table id="teachersTable" class="display nowrap table table-hover">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>ID Code</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Email</th>
                                    <th>Gender</th>
                                    <th>Birthdate</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                               <?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php
        // ✅ Generate initials (First + Middle + Last)
        $initials = strtoupper(
            ($row['t_fname'] ? substr($row['t_fname'], 0, 1) : '') .
            ($row['t_mname'] ? substr($row['t_mname'], 0, 1) : '') .
            ($row['t_lname'] ? substr($row['t_lname'], 0, 1) : '')
        );

        // ✅ Generate formatted full name
        $full_name = htmlspecialchars(
            "{$row['t_lname']}" . 
            (!empty($row['t_suffix']) ? ' ' . $row['t_suffix'] : '') . 
            ", {$row['t_fname']}" . 
            (!empty($row['t_mname']) ? ' ' . strtoupper(substr($row['t_mname'], 0, 1)) . '.' : '')
        );
        ?>
        <tr 
            data-teacher-id="<?= htmlspecialchars($row['t_id']) ?>"
            data-teacher-idcode="<?= htmlspecialchars($row['idcode']) ?>"
            data-teacher-name="<?= $full_name ?>"
            data-teacher-email="<?= htmlspecialchars($row['t_email']) ?>"
            data-teacher-dept="<?= htmlspecialchars($row['department_name']) ?>"
            data-teacher-status="<?= htmlspecialchars($row['t_status']) ?>"
            data-teacher-avatar="<?= htmlspecialchars($row['t_avatar'] ?? '') ?>"
            data-teacher-gender="<?= htmlspecialchars($row['t_gender']) ?>"
            data-teacher-bdate="<?= date('Y-m-d', strtotime($row['t_bdate'])) ?>"
            data-teacher-cnum="<?= htmlspecialchars($row['t_cnum']) ?>">
            <td></td>
            <td><?= htmlspecialchars($row['idcode']) ?></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($row['t_avatar'])): ?>
                        <img src=" /uploads/teachers/<?= htmlspecialchars($row['t_avatar']) ?>"
                             alt="Avatar" class="rounded-circle"
                             style="width:35px; height:35px; object-fit:cover;">
                    <?php else: ?>
                        <div class="profile-avatar rounded-circle text-white text-center"
                             style="width:35px; height:35px; font-size:0.9rem; line-height:35px;">
                            <?= $initials ?>
                        </div>
                    <?php endif; ?>
                    <?= $full_name ?>
                </div>
            </td>
            <td><span class="badge bg-warning text-dark rounded-pill"><?= htmlspecialchars($row['department_name']) ?></span></td>
            <td>
                <span class="badge bg-<?= $row['t_status'] === 'active' ? 'success' : 'danger' ?>">
                    <?= ucfirst($row['t_status']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['t_email']) ?></td>
            <td><?= htmlspecialchars($row['t_gender']) ?></td>
            <td><?= date('Y-m-d', strtotime($row['t_bdate'])) ?></td>
            <td><?= htmlspecialchars($row['t_cnum']) ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="10" class="text-center">No teachers found.</td>
    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Column -->
        <div class="col-lg-4">
            <div class="card shadow-sm" id="teacherDetailsCard">
             <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Teacher's Info</h5>
                </div>
                <div id="teacherDetailsBody" class="card-body"></div>
                <div class="card-body" id="teacherDetailsBody">
                     <div class="row" id="teacherCardsContainer"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php include __DIR__ . '/modals.php'; ?>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Bootstrap Bundle (includes Popper.js) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- Your custom JS -->
<script src="./teachers/js/edit_teacher.js"></script>
<script src="./teachers/js/add_teacher.js"></script>
<script src="./teachers/js/delete_teacher.js"></script>
<script>
  // Initialize DataTable globally
  window.table = $('#teachersTable').DataTable({
    scrollY: '50vh',
    scrollCollapse: true,
    paging: true,
    responsive: {
      details: {
        type: 'column',
        target: 0
      }
    },
    columnDefs: [
      { className: 'dtr-control', orderable: false, targets: 0 },
      { targets: [4, 5, 6, 7, 8], visible: false } // Hide extra columns
    ],
    order: [[1, 'asc']],
    language: {
      paginate: {
        previous: "Previous",
        next: "Next"
      }
    },
    dom: '<"top d-flex justify-content-between mb-2"lf>rt<"bottom d-flex justify-content-between align-items-center mt-2"ip><"clear">'
  });

  // Sticky footer for pagination controls
  $('.bottom').css({
    position: 'sticky',
    bottom: '0',
    background: '#fff',
    padding: '10px 0',
    zIndex: '10'
  });

  // ✅ Default message when no teacher is selected
  $('#teacherDetailsBody').html(`
    <div class="card-body-empty d-flex flex-column">
      <i class="bi bi-person-lines-fill display-4 d-block mb-2 fs-1"></i>
                        No teacher selected.
     <small class=" fst-italic">Click a row in the table to view teacher information.</small>
    </div>
  `);

  // SweetAlert helper
  function showAlert(type, message) {
    Swal.fire({
      icon: type,
      title: type === 'success' ? 'Success!' : 'Error!',
      text: message,
      timer: 3000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    });
  }

  // ✅ Handle table row click
  $('#teachersTable tbody').on('click', 'tr', function() {
    $('#teachersTable tbody tr').removeClass('active-row');
    $(this).addClass('active-row');

    const teacherId = $(this).data('teacher-id');
    const idcode = $(this).data('teacher-idcode');
    const teacherName = $(this).data('teacher-name');
    const teacherEmail = $(this).data('teacher-email');
    const teacherDept = $(this).data('teacher-dept');
    const teacherStatus = $(this).data('teacher-status');
    const teacherAvatar = $(this).data('teacher-avatar');
    const teacherGender = $(this).data('teacher-gender');
    const teacherBdate = $(this).data('teacher-bdate');
    const teacherCnum = $(this).data('teacher-cnum');

    // ✅ Build badge
    const statusBadge = teacherStatus.toLowerCase() === 'active'
      ? `<span class="badge rounded-pill bg-success">${teacherStatus.charAt(0).toUpperCase() + teacherStatus.slice(1)}</span>`
      : `<span class="badge rounded-pill bg-danger">${teacherStatus.charAt(0).toUpperCase() + teacherStatus.slice(1)}</span>`;

    // ✅ Avatar logic
    const avatarHTML = teacherAvatar
      ? `<img src="/uploads/teachers/${teacherAvatar}" 
               alt="Teacher Avatar" class="rounded-circle mb-3" 
               style="width:120px; height:120px; object-fit:cover;">`
      : `<div class="profile-avatar mx-auto mb-3 d-flex align-items-center justify-content-center text-white rounded-circle"
               style="width:120px; height:120px; font-size:2rem; font-weight:bold;">
           ${teacherName.split(/[ ,]+/).map(n => n.charAt(0)).join('').substring(0,2).toUpperCase()}
         </div>`;

    // ✅ Update teacher info body
    $('#teacherDetailsBody').html(`
      ${avatarHTML}
      <h5 class="fw-bold">${teacherName}</h5>
      <p class="text-muted mb-1"><strong>ID Code:</strong> ${idcode}</p>
      <p class="text-muted mb-1"><strong>Email:</strong> ${teacherEmail}</p>
      <p class="text-muted mb-1"><strong>Department:</strong> ${teacherDept}</p>
      <p class="text-muted mb-1"><strong>Status:</strong> ${statusBadge}</p>
      <p class="text-muted mb-1"><strong>Gender:</strong> ${teacherGender}</p>
      <p class="text-muted mb-1"><strong>Birthdate:</strong> ${teacherBdate}</p>
      <p class="text-muted mb-1"><strong>Contact:</strong> ${teacherCnum}</p>
    `);

    // ✅ Add Edit/Delete buttons inline in existing card header
    const header = $('.card-header:has(h5:contains("Teacher\'s Info"))');
    header.find('.action-buttons').remove(); // Remove old buttons if any

    header.append(`
      <div class="action-buttons d-flex gap-2">
        <button class="btn btn-sm btn-primary btn-edit-teacher" 
                data-bs-toggle="modal" 
                data-bs-target="#editTeacherModal" 
                data-teacher-id="${teacherId}">
          <i class="bi bi-pencil-square me-1"></i>Edit
        </button>
        <button class="btn btn-sm btn-danger btn-delete-teacher" 
                data-teacher-id="${teacherId}">
          <i class="bi bi-trash me-1"></i>Delete
        </button>
      </div>
    `);
  });
</script>


