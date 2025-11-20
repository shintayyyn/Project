<?php
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../admin/login.php");
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/avatar_helper.php';

$base_url = '/admin/parents/processes';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = $search ? "WHERE p.p_fname LIKE '%$search%' OR p.p_lname LIKE '%$search%' OR p.idcode LIKE '%$search%'" : '';
/**
 * Fetch Parents with their child and section
 * GROUP BY p.p_id ensures no duplicate parents even if a parent has multiple children
 */
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = $search ? "AND (p.p_fname LIKE '%$search%' OR p.p_lname LIKE '%$search%' OR s.s_fname LIKE '%$search%' OR s.s_lname LIKE '%$search%')" : '';

$sql = "
SELECT 
    ps.p_id,
    ps.s_id,
    ps.term_id,
    ps.created_at AS ps_created,
    ps.updated_at AS ps_updated,
    p.idcode,
    p.p_fname,
    p.p_lname,
    p.p_mname,
    p.p_suffix,
    p.p_address,
    p.p_cnum,
    p.p_email,
    p.p_gender,
    p.p_bdate,
    p.p_status,
    s.s_fname AS section_fname,
    s.s_lname AS section_lname,
    s.section_code,
    st.s_fname,
    st.s_lname,
    st.s_mname,
    st.s_suffix,
    st.s_bdate,
    st.s_gender,
    at.term_id,
    at.ay_id,
    at.semester,
    at.is_active AS term_active,
    ay.year_start,
    ay.year_end
FROM parent_student ps
LEFT JOIN parents p ON ps.p_id = p.p_id
LEFT JOIN students_sections s ON ps.s_id = s.s_id
LEFT JOIN students st ON ps.s_id = st.s_id
LEFT JOIN academic_terms at ON ps.term_id = at.term_id
LEFT JOIN academic_years ay ON at.ay_id = ay.ay_id
WHERE 1=1
$search_condition
ORDER BY ps.p_id, ps_created DESC";


$result = $conn->query($sql);


?>

<style>
.active-row {
    background-color: #f0f8ff !important;
    transition: background-color 0.3s ease;
}

.card-body,table{
    overflow: hidden;
}

.profile-avatar {
    background-color: #033A70;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
    font-weight: bold;
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

.table-hover tbody tr:hover td {
    background-color: rgba(61, 82, 160, 0.05) !important;
}

.action-buttons .btn {
    min-width: 80px;
}
#parentsTable td {
    white-space: normal !important;
    word-wrap: break-word;
}

</style>

<div class="container-fluid p-0">
    <div id="notificationArea"></div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold">Manage Parents</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Parents</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row g-3">
        <!-- Table Column -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="fw-bold">Parents List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive p-3 ">
                        <table id="parentsTable" class="display nowrap table table-hover">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>ID Code</th>
                                    <th>Full Name</th>
                                    <th>Child</th>
                                    <th>Email</th>
                                    <th>Gender</th>
                                    <th>Birthdate</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                </tr>
                            </thead>
                        <tbody>

<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php
        $p_id = $row['p_id'];

        // Parent full name
        $full_name = $p_id 
            ? htmlspecialchars(
                $row['p_lname'] . 
                (!empty($row['p_suffix']) ? ' ' . $row['p_suffix'] : '') . 
                ', ' . $row['p_fname'] . 
                (!empty($row['p_mname']) ? ' ' . strtoupper(substr($row['p_mname'], 0, 1)) . '.' : '')
              ) 
            : '<span class="badge bg-secondary">Solo child</span>';

        // Fetch children with year level readable
        $children_sql = "
            SELECT st.s_fname, st.s_lname, st.s_mname, st.s_suffix, s.section_code, 
                   yl.level_name
            FROM parent_student ps
            LEFT JOIN students st ON ps.s_id = st.s_id
            LEFT JOIN students_sections s ON ps.s_id = s.s_id
            LEFT JOIN year_levels yl ON st.year_level = yl.id
            WHERE ps.p_id = {$p_id}
        ";
        $children_result = $conn->query($children_sql);
        $children = [];
        if ($children_result && $children_result->num_rows > 0) {
            while ($c = $children_result->fetch_assoc()) {
                $mInitial = !empty($c['s_mname']) ? ' ' . strtoupper(substr($c['s_mname'], 0, 1)) . '.' : '';
                $suffix = !empty($c['s_suffix']) ? ' ' . $c['s_suffix'] : '';
                $section = !empty($c['section_code']) ? $c['section_code'] : 'Not yet assigned';
                $year_level = !empty($c['level_name']) ? $c['level_name'] : '-';

                $children[] = "{$c['s_lname']}{$suffix}, {$c['s_fname']}{$mInitial} ({$section}) [{$year_level}]";
            }
        }

        // Display children or just View All button if more than 1
        if (count($children) > 1) {
            $child_display = '<button class="btn btn-sm btn-outline-primary view-all-children" data-parent-id="'.$p_id.'">View All</button>';
        } else {
            $child_display = $children[0] ?? 'No child linked';
        }

        // Avatar
        $avatar_path = $p_id ? "../uploads/parents/parent_{$p_id}.jpg" : '';
        $server_path = $_SERVER['DOCUMENT_ROOT'] . $avatar_path;
        $avatar_exists = file_exists($server_path);
        $initials = $p_id ? strtoupper(substr($row['p_fname'], 0, 1) . substr($row['p_lname'], 0, 1)) : '';
        ?>
        <tr 
            data-parent-id="<?= htmlspecialchars($p_id) ?>"
            data-parent-idcode="<?= htmlspecialchars($row['idcode']) ?>"
            data-parent-name="<?= htmlspecialchars($full_name) ?>"
            data-parent-email="<?= htmlspecialchars($row['p_email']) ?>"
            data-parent-status="<?= htmlspecialchars($row['p_status']) ?>"
            data-parent-gender="<?= htmlspecialchars($row['p_gender']) ?>"
            data-parent-bdate="<?= !empty($row['p_bdate']) ? date('Y-m-d', strtotime($row['p_bdate'])) : '' ?>"
            data-parent-cnum="<?= htmlspecialchars($row['p_cnum']) ?>"
            data-parent-address="<?= htmlspecialchars($row['p_address'] ?? '-') ?>"
            data-parent-avatar="<?= $avatar_exists ? $avatar_path : '' ?>"
        >
            <td></td>
            <td><?= htmlspecialchars($row['idcode'] ?? '-') ?></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($avatar_exists): ?>
                        <img src="<?= $avatar_path ?>" alt="Avatar" class="rounded-circle" style="width:35px; height:35px; object-fit:cover;">
                    <?php else: ?>
                        <div class="profile-avatar" style="width:35px; height:35px; font-size:0.9rem;"><?= $initials ?></div>
                    <?php endif; ?>
                    <?= $full_name ?>
                </div>
            </td>
            <td><?= $child_display ?></td>
            <td>
                <span class="badge bg-<?= ($row['p_status'] ?? 'inactive') === 'active' ? 'success' : 'danger' ?>">
                    <?= ucfirst($row['p_status'] ?? 'Inactive') ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['p_email'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['p_gender'] ?? '-') ?></td>
            <td><?= !empty($row['p_bdate']) ? date('Y-m-d', strtotime($row['p_bdate'])) : '-' ?></td>
            <td><?= htmlspecialchars($row['p_cnum'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['p_address'] ?? '-') ?></td>
            <td style="display:none;"><?= htmlspecialchars($row['ps_created']) ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="10" class="text-center">No parents found.</td>
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
            <div class="card shadow-sm" id="parentDetailsCard">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Personal Information</h5>
                </div>
                <div class="card-body" id="parentDetailsBody">
                    <div class="card-body-empty d-flex flex-column" id="noParentSelected">
                        <i class="bi bi-person-lines-fill"></i>
                        No parent selected.
                        <small class=" fst-italic">Click a row in the table to view teacher information.</small>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'modals.php'; ?>

<!-- Modal HTML -->
<div class="modal fade" id="childrenModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Children of <span id="parentName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush text-left" id="childrenList">
                    <!-- Children will be populated here -->
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/admin/parents/js/edit_parent.js"></script>
<script src="/admin/parents/js/delete_parent.js"></script>

<script>
$(document).ready(function() {
   window.parentsTable = $('#parentsTable').DataTable({
    scrollY: '50vh',
    scrollCollapse: true,
    paging: true,
    responsive: true,
    autoWidth: false,
    columnDefs: [
        { className: 'dtr-control', orderable: false, targets: 0 },
        { targets: [4,5,6,7,8,9], visible: false }, // hide extra columns
        { targets: 10, visible: false } // hidden column for ps_created
    ],
    order: [[10, 'desc']] // order by ps_created descending
});


$(document).on('click', '.view-all-children', function(){
    const parentId = $(this).data('parent-id');
    $('#childrenList').empty(); // clear previous list
    $('#parentName').text($(this).closest('tr').data('parent-name'));

    $.ajax({
        url: '/admin/parents/processes/get_children.php',
        method: 'GET',
        data: { parent_id: parentId },
        dataType: 'json',
        success: function(res){
            if(res.success && res.children.length > 0){
                res.children.forEach(function(child){
                    $('#childrenList').append(
                        `<li class="list-group-item">
                            ${child.name} - ${child.section} (${child.term}, ${child.year_level})
                        </li>`
                    );
                });
            } else {
                $('#childrenList').append('<li class="list-group-item">No children found.</li>');
            }
            $('#childrenModal').modal('show');
        },
        error: function(xhr, status, error){
            console.error('AJAX error:', error);
        }
    });
});


    // Row click - show parent details
    $('#parentsTable tbody').on('click', 'tr', function() {
    $('#parentsTable tbody tr').removeClass('active-row');
    $(this).addClass('active-row');

    const parentId = $(this).data('parent-id');
    const name = $(this).data('parent-name');
    const email = $(this).data('parent-email');
    const status = $(this).data('parent-status');
    const avatar = $(this).data('parent-avatar');
    const gender = $(this).data('parent-gender');
    const bdate = $(this).data('parent-bdate');
    const cnum = $(this).data('parent-cnum');
    const address = $(this).data('parent-address');
    const termLabel = $(this).data('term-label') || '-';

    const statusBadge = status.toLowerCase() === 'active'
        ? `<span class="badge rounded-pill bg-success">Active</span>`
        : `<span class="badge rounded-pill bg-danger">Inactive</span>`;

    let avatarHTML = avatar
        ? `<img src="${avatar}" class="rounded-circle mb-3" style="width:120px; height:120px; object-fit:cover;">`
        : `<div class="profile-avatar mx-auto mb-3" style="width:120px; height:120px; font-size:2rem;">
            ${name.split(/[ ,]+/).map(n => n.charAt(0)).join('').substring(0,2).toUpperCase()}
          </div>`;

    $('#parentDetailsBody').html(`
        ${avatarHTML}
        <h5>${name}</h5>
        <p class="text-muted mb-1"><strong>Email:</strong> ${email}</p>
        <p class="text-muted mb-1"><strong>Status:</strong> ${statusBadge}</p>
        <p class="text-muted mb-1"><strong>Gender:</strong> ${gender}</p>
        <p class="text-muted mb-1"><strong>Birthdate:</strong> ${bdate}</p>
        <p class="text-muted mb-1"><strong>Contact:</strong> ${cnum}</p>
        <p class="text-muted mb-1"><strong>Address:</strong> ${address}</p>
        <p class="text-muted mb-1"><strong>Term:</strong> ${termLabel}</p>
    `);
});

// Populate child select
const $childSelect = $('#editParentForm select[name="child_id"]');
if ($childSelect.length) {
    $childSelect.empty();
    $('<option>', { value: '', text: 'Select Child' }).appendTo($childSelect);
    if (response.children && response.children.length > 0) {
        $.each(response.children, function (index, child) {
            $('<option>', {
                value: child.s_id,
                text: `${child.full_name} - ${child.section_code || 'Not Assigned'}`,
                selected: child.s_id == parent.child_id // pre-select current child if any
            }).appendTo($childSelect);
        });
    }
}

});
</script>
