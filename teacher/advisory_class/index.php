<?php
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$t_id = $_SESSION['user_id'];

// Get active term
$activeTermQuery = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
$activeTerm = $activeTermQuery->fetch_assoc();
$term_id = $activeTerm['term_id'] ?? null;
if (!$term_id) {
    die("No active term found.");
}

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE t_id = ?");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch teacher's sections for the active term
$stmt = $conn->prepare("
    SELECT sec.section_id, sec.section_name, sec.section_code
    FROM sections_advisors sa
    JOIN sections sec ON sa.section_id = sec.section_id
    WHERE sa.t_id = ? AND sa.term_id = ?
");
$stmt->bind_param("ii", $t_id, $term_id);
$stmt->execute();
$sectionsResult = $stmt->get_result();
$sections = [];
while ($row = $sectionsResult->fetch_assoc()) {
    $sections[$row['section_id']] = $row;
}
$stmt->close();

// Fetch advisory students in active term
$placeholders = implode(',', array_fill(0, count($sections), '?'));
$types = str_repeat('i', count($sections));
$studentStmt = $conn->prepare("
    SELECT 
        ss.ss_id, 
        ss.s_id, 
        ss.is_Mayor,
        ss.section_id,
        sec.section_code,
        sec.section_name,

        -- Correct student name fields from students table
        s.s_fname,
        s.s_lname,
        s.s_mname,
        s.s_suffix

    FROM students_sections ss
    JOIN students s ON s.s_id = ss.s_id
    JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.section_id IN ($placeholders) AND ss.term_id = ?
");

$params = array_merge(array_keys($sections), [$term_id]);
$studentStmt->bind_param($types . 'i', ...$params);
$studentStmt->execute();
$studentsResult = $studentStmt->get_result();
$students = [];
while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}
$studentStmt->close();

// Fetch current mayors in active term
$mayorStmt = $conn->prepare("
    SELECT ss_id, s_fname, s_lname, section_id
    FROM students_sections
    WHERE is_Mayor = 1 AND term_id = ? AND section_id IN ($placeholders)
");
$mayorStmt->bind_param('i' . $types, $term_id, ...array_keys($sections));
$mayorStmt->execute();
$mayorResult = $mayorStmt->get_result();
$currentMayors = [];
while ($row = $mayorResult->fetch_assoc()) {
    $currentMayors[$row['section_id']] = $row;
}
$mayorStmt->close();
?>

<!-- Head & Styles remain same -->

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>

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



    /* Main content */
    main {
        margin-left: 5px;
        width: calc(100% - 260px);

    }

    /* Container fluid */
    .container-fluid {
        width: 100%;
        padding: 0.5rem;
        margin: 0 auto;
        overflow-x: hidden;
    }

    /* Card styling */
    .card {
        border: none;
        border-radius: var(--card-border-radius);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04), 0 8px 16px rgba(0, 0, 0, 0.08);
        transition: transform var(--transition-speed), box-shadow var(--transition-speed);
        background: #fff;
        margin-bottom: 1rem;
        width: 100%;
        /* fixed max size for large devices */
        margin-left: auto;
        margin-right: auto;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08), 0 12px 24px rgba(0, 0, 0, 0.12);
    }

    /* Profile header */
    .profile-header {
        background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 1.5rem;
        border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1.5rem;
    }

    /* Avatar */
    .profile-avatar {
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.15);
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: 1px;
    }

    /* Profile info */
    .profile-info h1 {
        font-size: 1.25rem;
        margin: 0;
        font-weight: 600;
    }

    .profile-info p {
        margin: 0.25rem 0 0;
        opacity: 0.9;
        font-size: 0.9rem;
    }

    .profile-avatar {
        color: #033A70;
    }

    /* Table responsiveness */
    .table-responsive {
        overflow-x: auto;
    }

    .table thead {
        background: var(--primary);
        color: white;
    }

    #attendanceTable {
        width: 100% !important;
        text-align: center;
    }

    /* Tablet adjustments */
    @media (max-width: 992px) {

        body,
        main {
            overflow-y: auto;
            /* enable horizontal scroll if content is wider than screen */
        }

        .profile-header {
            justify-content: center;
            text-align: center;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.25rem;
        }

        .profile-info h1 {
            font-size: 1.1rem;
        }

        .profile-info p {
            font-size: 0.85rem;
        }
    }

    /* Mobile adjustments */
    @media (max-width: 576px) {
        main {
            margin-left: 0;
            width: 100%;
            padding: 0.25rem;
        }

        .card {
            margin: 0.5rem;
            max-width: 100%;
            /* full width for mobile */
        }

        .table thead {
            font-size: 0.85rem;
        }

        .table td,
        .table th {
            font-size: 0.8rem;
            padding: 0.35rem;
        }
    }
</style>

<main>
    <div class="container-fluid">
        <div class="row g-0">
              <h2 class="fw-bold mb-3"><i class="bi bi-people-fill me-2"></i> My Advisory Class</h2>
                    <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Advisory Classes</li>
                </ol>
            </nav>
            <div class="col-md-12">
                <div class="card">
                    <!-- Card Header: Section Filter + Major Button -->
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="input-group input-group-sm w-auto">
                            <span class="input-group-text fw-bold bg-primary text-white">Section</span>
                            <select id="sectionFilter" class="form-select">
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= $sec['section_id'] ?>">
                                        <?= htmlspecialchars($sec['section_code'] . ' - ' . $sec['section_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Teacher Header -->
                    <div class="profile-header d-flex align-items-center justify-content-between mb-4">
                        <!-- Avatar + Teacher Info -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="profile-avatar rounded-circle bg-warning  d-flex justify-content-center align-items-center" style="width:60px; height:60px; font-size:1.5rem;">
                                <?php
                                $initials = strtoupper(substr($teacher['t_fname'] ?? '', 0, 1) . substr($teacher['t_lname'] ?? '', 0, 1));
                                echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <div class="profile-info">
                                <h4 class="mb-0">
                                    <?= htmlspecialchars(
                                        trim(
                                            $teacher['t_fname'] . ' ' .
                                                (!empty($teacher['t_mname']) ? strtoupper(substr($teacher['t_mname'], 0, 1)) . '. ' : '') .
                                                $teacher['t_lname'] . ' ' .
                                                ($teacher['t_suffix'] ?? '')
                                        )
                                    ) ?>
                                </h4>


                                <p class="mb-0 text-warning fst-italic" id="currentSectionCode"><?= htmlspecialchars(reset($sections)['section_code'] ?? '') ?> Department</p>
                            </div>
                        </div>

                        <!-- Assign/Change Mayor Button -->
                        <div class="text-end">
                            <button class="btn btn-sm btn-primary text-white" id="assignMayorBtn" data-bs-toggle="modal" data-bs-target="#assignMayorModal">
                                <i class="bi bi-person-plus-fill me-1 text-white"></i> <span id="assignMayorBtnText">Assign Mayor</span>
                            </button>


                        </div>
                    </div>


                    <!-- Advisory Table -->
                    <div class="card-body">
                         <div class="table-responsive">
                            <table id="advisoryTable" class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Mayor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="advisoryTableBody">
                                    <!-- JS will populate rows based on selected section -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Assign/Change Mayor Modal -->
<div class="modal fade" id="assignMayorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header profile-header">
                <h5 class="modal-title fw-bold">Select Student for Mayor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Scrollable List -->
                <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                    <?php
                    $result->data_seek(0); // reset result pointer
                    while ($row = $result->fetch_assoc()): ?>
                        <label class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?>
                                <small class="text-muted">(<?= htmlspecialchars($row['section_code']) ?>)</small>
                            </div>
                            <div>
                                <?php if ($row['is_Mayor']): ?>
                                    <span class="badge bg-success me-2">Current Mayor</span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary assign-mayor"
                                    data-id="<?= $row['ss_id'] ?>"
                                    data-student="<?= htmlspecialchars($row['s_fname'] . ' ' . $row['s_lname']) ?>"
                                    data-section="<?= htmlspecialchars($row['section_code']) ?>">
                                    Assign
                                </button>
                            </div>
                        </label>
                    <?php endwhile; ?>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- Attendance Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg rounded-4">
            <div class="modal-header profile-header">
                <div class="profile-avatar">
                    <i class="bi bi-calendar-check fs-3"></i>
                </div>
                <div class="profile-info">
                    <h5 class="modal-title fw-bold" id="attendanceModalLabel">Attendance</h5>
                    <p class="mb-0" id="attendanceModalStudentName">Student Attendance Records</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="card-header table  text-center" id="attendanceTable">
                        <thead>
                            <tr class="text-center">
                                <th>#</th>
                                <th>Subject Code</th>
                                <th>Section Code</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceListContainer">
                            <!-- JS will populate rows here -->
                            <!-- if no records: <tr><td colspan="6" class="text-center">No records found</td></tr> -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Assign Modal -->
<div class="modal fade" id="confirmAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header profile-header">
                <h5 class="modal-title fw-bold">Confirm Assignment</h5>
            </div>
            <div class="modal-body" id="confirmAssignBody">
                Are you sure you want to assign this student as the <strong>Mayor</strong>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="openAssignMayor">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAssignBtn">Yes, Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 2000">
    <div id="actionToast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">Success</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- ADD THIS: Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        let selectedSSId = null;
        const attendanceCache = new Map();

        // All students data and current mayors from PHP
        const students = <?= json_encode($students) ?>;
        const currentMayors = <?= json_encode($currentMayors) ?>;

        const advisoryTableEl = $('#advisoryTable');

        // Initialize advisory DataTable once
        const advisoryTable = advisoryTableEl.DataTable({
            responsive: true,
            scrollY: '650px',
            scrollCollapse: true,
            paging: true,
            autoWidth: false
        });

        // Populate advisory table based on selected section
        function populateTable(section_id) {
            advisoryTable.clear();
            let i = 1;

            const filteredStudents = students.filter(s => String(s.section_id) === String(section_id));

            if (filteredStudents.length === 0) {
                advisoryTable.row.add(['', 'No students found', '', '', '']).draw(false);
            }

            filteredStudents.forEach(s => {
                const mayorBadge = s.is_Mayor == 1 ?
                    '<span class="badge bg-success">Mayor</span>' :
                    '<span class="badge bg-secondary">-</span>';

                advisoryTable.row.add([
                    i++,
                    `${s.s_lname}${s.s_suffix ? ' ' + s.s_suffix : ''}, ${s.s_fname}${s.s_mname ? ' ' + s.s_mname.charAt(0).toUpperCase() + '.' : ''}`,
                    s.section_code,
                    mayorBadge,
                    `<button class="btn btn-sm btn-outline-success view-attendance" 
                    data-id="${s.s_id}" 
                    data-name="${s.s_fname} ${s.s_lname}">
                    <i class="bi bi-calendar-check"></i> View Attendance
                </button>`
                ]).draw(false);
            });
        }


        function updateMayorButton(section_id) {
            const btn = $('#assignMayorBtn');
            const iconEl = btn.find('i');
            const btnTextEl = $('#assignMayorBtnText');

            let currentMayorName = '';

            // ✅ Find current mayor and format name (Dela Cruz Jr., Juan B.)
            if (currentMayors[section_id]) {
                const mayor = students.find(s => s.ss_id == currentMayors[section_id].ss_id);
                if (mayor) {
                    const middleInitial = mayor.s_mname ? mayor.s_mname.charAt(0).toUpperCase() + '.' : '';
                    const suffix = mayor.s_suffix ? ' ' + mayor.s_suffix : '';
                    currentMayorName = `${mayor.s_lname}${suffix}, ${mayor.s_fname} ${middleInitial}`.trim();
                }
            }

            // ✅ Always enforce consistent yellow button style
            btn.removeClass().addClass('btn btn-sm btn-warning bg-warning text-dark');

            iconEl
                .removeClass('bi-person-plus-fill bi-arrow-clockwise text-dark text-white')
                .attr('style', 'color:#000 !important')
                .addClass(currentMayorName ? 'bi bi-arrow-clockwise' : 'bi bi-person-plus-fill');


            // ✅ Update button label text only (not the icon)
            if (currentMayorName) {
                btnTextEl.html(`Change Mayor <span class="text-dark">(${currentMayorName})</span>`);
            } else {
                btnTextEl.text('Assign Mayor');
            }
        }


        // Populate Assign Mayor modal
        function populateAssignMayorModal(section_id) {
            const modalBody = $('#assignMayorModal .list-group');
            modalBody.empty();
            const sectionStudents = students.filter(s => String(s.section_id) === String(section_id));
            sectionStudents.forEach(s => {
                const isMayor = s.is_Mayor == 1;
                const mayorBadge = isMayor ? '<span class="badge bg-success me-2">Current Mayor</span>' : '';
                const disabled = isMayor ? 'disabled' : '';
                const row = `<label class="list-group-item d-flex justify-content-between align-items-center">
                <div>${s.s_fname} ${s.s_lname} <small class="text-muted">(${s.section_code})</small></div>
                <div>
                    ${mayorBadge}
                    <button class="btn btn-sm btn-outline-primary assign-mayor" data-id="${s.ss_id}" data-student="${s.s_fname} ${s.s_lname}" data-section="${s.section_code}" ${disabled}>Assign</button>
                </div>
            </label>`;
                modalBody.append(row);
            });
        }

        // Initialize first section
        const firstSectionId = $('#sectionFilter').val();
        $('#currentSectionCode').text($("#sectionFilter option:selected").text().split(' - ')[0] + ' Department');
        populateTable(firstSectionId);
        updateMayorButton(firstSectionId);
        populateAssignMayorModal(firstSectionId);

        // Section change
        $('#sectionFilter').on('change', function() {
            const selectedSection = $(this).val();
            const sectionText = $("#sectionFilter option:selected").text().split(' - ')[0];
            $('#currentSectionCode').text(sectionText + ' Department');

            populateTable(selectedSection);
            updateMayorButton(selectedSection);
            populateAssignMayorModal(selectedSection);
        });

        function initAttendanceDataTable() {
            const tableEl = $('#attendanceTable');

            if (!$.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable({
                    scrollY: '50vh',
                    scrollX: true,
                    scrollCollapse: true,
                    responsive: true,
                    paging: true,
                    ordering: true,
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    autoWidth: true,
                    columnDefs: [{
                            orderable: false,
                            targets: 0
                        } // first column unsortable
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
            } else {
                const dt = tableEl.DataTable();
                dt.clear();
                dt.rows.add($('#attendanceListContainer tr')).draw(false);
                dt.columns.adjust().responsive.recalc();
            }
        }

        // Trigger column adjustment when modal opens
        $('#attendanceModal').on('shown.bs.modal', function() {
            const tableEl = $('#attendanceTable');
            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().columns.adjust().responsive.recalc();
            }
        });

        // View Attendance
        $(document).on('click', '.view-attendance', function() {
            const s_id = $(this).data('id');
            const studentName = $(this).data('name');
            const container = $('#attendanceListContainer');
            const modalEl = document.getElementById('attendanceModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            $('#attendanceModalStudentName').text(`Attendance of ${studentName}`);

            // While loading
            container.html('<tr><td colspan="6" class="text-center">Loading attendance...</td></tr>');

            // ✅ Use cache if available
            if (attendanceCache.has(s_id)) {
                const html = attendanceCache.get(s_id);
                container.html(html);
                reinitAttendanceTable();
                return;
            }

            // ✅ Fetch attendance HTML
            fetch(`advisory_class/view_attendance.php?s_id=${s_id}`)
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.text();
                })
                .then(html => {
                    html = html.trim();

                    // Allow whitespace/newlines before <tr>
                    if (!/<tr[\s>]/i.test(html)) {
                        throw new Error('Invalid table body HTML');
                    }

                    container.html(html);
                    attendanceCache.set(s_id, html);

                    // Only reinit DataTable if there are records
                    if (!html.includes('No attendance records')) {
                        reinitAttendanceTable();
                    }
                })
                .catch(err => {
                    console.error('Fetch failed:', err);
                    container.html('<tr><td colspan="6" class="text-center text-danger">Failed to load attendance</td></tr>');
                });
        });


        // ✅ Safe DataTable reinitialization
        function reinitAttendanceTable() {
            const tableEl = $('#attendanceTable');

            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().clear().destroy();
            }

            const dt = tableEl.DataTable({
                scrollY: '50vh',
                scrollX: true,
                scrollCollapse: true,
                responsive: true,
                paging: true,
                ordering: true,
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50, 100],
                autoWidth: false,
                columnDefs: [{
                        orderable: false,
                        targets: 0
                    } // Disable sorting for "#" column
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

            // ✅ Make # column auto-increment start from 1 always
            dt.on('order.dt search.dt draw.dt', function() {
                dt.column(0, {
                    search: 'applied',
                    order: 'applied'
                }).nodes().each((cell, i) => {
                    cell.innerHTML = i + 1;
                });
            }).draw();
        }


        // ✅ Fully reset modal when closed
        $('#attendanceModal').on('hidden.bs.modal', function() {
            const tableEl = $('#attendanceTable');
            const container = $('#attendanceListContainer');

            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().clear().destroy();
            }

            container.html('');
            $('#attendanceModalStudentName').text('Student Attendance Records');
        });



        // Assign/Change Mayor modal
        $('#assignMayorBtn').on('click', function() {
            const selectedSection = $('#sectionFilter').val();
            populateAssignMayorModal(selectedSection);
        });

        // Confirm Assign Mayor
        $(document).on('click', '.assign-mayor', function() {
            selectedSSId = $(this).data('id');
            const selectedStudentName = $(this).data('student');
            const section_name = $(this).data('section');

            $('#confirmAssignBody').html(
                `Are you sure you want to assign <strong>${selectedStudentName}</strong> as the new <strong>Mayor of ${section_name}</strong>?`
            );

            const assignModalEl = document.getElementById('assignMayorModal');
            const assignModal = bootstrap.Modal.getInstance(assignModalEl);
            if (assignModal) {
                $(assignModalEl).fadeOut(200, function() {
                    assignModal.hide();
                });
                assignModalEl.addEventListener('hidden.bs.modal', function() {
                    const confirmModal = new bootstrap.Modal(document.getElementById('confirmAssignModal'));
                    confirmModal.show();
                }, {
                    once: true
                });
            } else {
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmAssignModal'));
                confirmModal.show();
            }
        });

        $('#confirmAssignBtn').on('click', function() {
            if (!selectedSSId) return;
            fetch(`advisory_class/assign_mayor.php`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `ss_id=${selectedSSId}`
                })
                .then(res => res.json())
                .then(data => {
                    bootstrap.Modal.getInstance(document.getElementById('confirmAssignModal')).hide();
                    const toastEl = $('#actionToast');
                    const toastBody = $('#toastMessage');
                    toastBody.text(data.message);

                    const toast = new bootstrap.Toast(document.getElementById('actionToast'));
                    if (data.status === "success") {
                        toastEl.removeClass("bg-danger").addClass("bg-success");
                        toast.show();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        toastEl.removeClass("bg-success").addClass("bg-danger");
                        toast.show();
                    }
                });
        });

      
    const cancelBtn = document.getElementById('openAssignMayor');
    const assignMayorModal = new bootstrap.Modal(document.getElementById('assignMayorModal'));

    cancelBtn.addEventListener('click', () => {
        // Close any open modal first
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modalEl => {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if(modalInstance) modalInstance.hide();
        });

        // Open the Assign Mayor Modal
        assignMayorModal.show();
    });
    });
</script>