<?php
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_POST['s_id'])) {
    echo "<div class='alert alert-danger text-center'>Student ID missing.</div>";
    exit;
}

$s_id = intval($_POST['s_id']);
$selectedTermId = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;

// 🔹 Fetch all terms with academic years (for dropdown)
$termsRes = $conn->query("
    SELECT 
        t.term_id,
        t.semester,
        t.is_active,
        ay.year_start,
        ay.year_end
    FROM academic_terms t
    INNER JOIN academic_years ay ON ay.ay_id = t.ay_id
    ORDER BY ay.year_start DESC, t.semester DESC
");

$terms = [];
while ($row = $termsRes->fetch_assoc()) {
    $terms[] = $row;
}

// 🔹 If no term selected, fallback to active term
if ($selectedTermId === 0) {
    $activeRes = $conn->query("
        SELECT term_id 
        FROM academic_terms 
        WHERE is_active = 1 
        LIMIT 1
    ");
    $activeTerm = $activeRes->fetch_assoc();
    if (!$activeTerm) {
        echo '<div class="alert alert-info text-center">No active academic term found.</div>';
        exit;
    }
    $selectedTermId = intval($activeTerm['term_id']);
}

// 🔹 Get selected term details
$termRes = $conn->query("
    SELECT 
        t.term_id,
        t.semester,
        ay.year_start,
        ay.year_end
    FROM academic_terms t
    INNER JOIN academic_years ay ON ay.ay_id = t.ay_id
    WHERE t.term_id = $selectedTermId
");
$term = $termRes->fetch_assoc();

if (!$term) {
    echo '<div class="alert alert-warning text-center">Selected term not found.</div>';
    exit;
}

$termSemester = intval($term['semester']);

// 🔹 Fetch student full name & section
$stmt = $conn->prepare("
    SELECT 
        CONCAT(
            s.s_lname, ', ', s.s_fname,
            IF(s.s_mname<>'', CONCAT(' ', LEFT(s.s_mname,1), '.'), ''), 
            IF(s.s_suffix<>'', CONCAT(' ', s.s_suffix), '')
        ) AS full_name,
        ss.section_code AS section
    FROM students AS s
    LEFT JOIN students_sections AS ss ON ss.s_id = s.s_id
    WHERE s.s_id = ?
");
$stmt->bind_param('i', $s_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo "<div class='alert alert-danger text-center'>Student not found.</div>";
    exit;
}

// 🔹 Attendance summary (total counts)
$semesterRes = $conn->query("
    SELECT 
        SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS Present,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS Late,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS Absent,
        SUM(CASE WHEN status='Excuse' THEN 1 ELSE 0 END) AS Excuse
    FROM attendance
    WHERE s_id=$s_id AND term_id=$selectedTermId
");
$semester = $semesterRes->fetch_assoc() ?? ['Present' => 0, 'Late' => 0, 'Absent' => 0, 'Excuse' => 0];

// 🔹 Define months based on semester
if ($termSemester === 1) {
    $allMonths = range(7, 12); // July–Dec
} else {
    $allMonths = range(1, 5);  // Jan–May
}

$statuses = ['Present', 'Late', 'Absent', 'Excuse'];
$months = [];
foreach ($allMonths as $m) {
    $months[$m] = array_fill_keys($statuses, 0);
}

// 🔹 Monthly summary (attendance breakdown)
$monthsRes = $conn->query("
    SELECT MONTH(time_in) AS month,
           SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS Present,
           SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS Late,
           SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS Absent,
           SUM(CASE WHEN status='Excuse' THEN 1 ELSE 0 END) AS Excuse
    FROM attendance
    WHERE s_id=$s_id AND term_id=$selectedTermId
    GROUP BY MONTH(time_in)
");
while ($row = $monthsRes->fetch_assoc()) {
    $m = intval($row['month']);
    if (isset($months[$m])) {
        $months[$m] = [
            'Present' => intval($row['Present']),
            'Late' => intval($row['Late']),
            'Absent' => intval($row['Absent']),
            'Excuse' => intval($row['Excuse'])
        ];
    }
}
?>

<!-- 🔹 Term selector -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-inline-block">
        <label class="form-label me-2 fw-bold">Term:</label>
        <select id="termSelect" class="form-select d-inline-flex form-select-sm">
            <?php foreach ($terms as $t): ?>
                <option value="<?= $t['term_id'] ?>" <?= $t['term_id'] == $selectedTermId ? 'selected' : '' ?>>
                    <?= 'S.Y. ' . $t['year_start'] . '–' . $t['year_end'] . ' - Semester ' . $t['semester'] ?>
                    <?= intval($t['is_active']) === 1 ? '(Active)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- 🔹 Monthly Summary Table -->
<div class="card mb-3">
    <div class="card-header fw-bold">Monthly Summary</div>
    <div class="card-body">
        <div class="table-responsive mb-3">
            <table class="table table-hover attendanceTable">
                <thead class="table-light">
                    <tr>
                        <th>Month</th>
                        <?php foreach ($statuses as $status): ?>
                            <th><?= $status ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($months as $monthNum => $data): ?>
                        <tr>
                            <td><?= date('F', mktime(0, 0, 0, $monthNum, 10)) ?></td>
                            <?php foreach ($statuses as $status): ?>
                                <td><?= $data[$status] ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔹 Summary Cards -->
<h4 class="fw-bold text-center mt-4">Total Attendance</h4>
<div class="row g-2 mb-3">
    <?php foreach ($statuses as $status): ?>
        <div class="col-6 col-md-3">
            <div class="card text-center border-white">
                <div class="card-body p-2">
                    <h6 class="card-header mb-1"><?= $status ?></h6>
                    <span class="h5"><?= $semester[$status] ?? 0 ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 🔹 Detailed Attendance Table -->
<div class="card">
    <div class="card-header fw-bold">All Attendance Records</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover display nowrap attendanceTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Subject Code</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $detailsRes = $conn->query("
                        SELECT a.time_in, a.time_out, a.status, s.subject_code
                        FROM attendance a
                        LEFT JOIN subjects s ON s.subject_code = a.subject_code
                        WHERE a.s_id=$s_id AND a.term_id=$selectedTermId
                        ORDER BY a.time_in ASC
                    ");
                    while ($row = $detailsRes->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($row['time_in'])) ?></td>
                        <td><?= htmlspecialchars($row['subject_code']) ?></td>
                        <td><?= $row['time_in'] ? date('H:i:s', strtotime($row['time_in'])) : '' ?></td>
                        <td><?= $row['time_out'] ? date('H:i:s', strtotime($row['time_out'])) : '' ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔹 AJAX Term Switch -->
<script>
$(document).on('change', '#termSelect', function () {
    const term_id = $(this).val();
    const s_id = <?= $s_id ?>;

    $.post('reports/s_analytics.php', { s_id, term_id }, function (res) {
        $('#analyticsContent').html(res);

        if ($.fn.DataTable.isDataTable('.attendanceTable')) {
            $('.attendanceTable').DataTable().destroy();
        }

        $('.attendanceTable').DataTable({
            scrollY: '50vh',
            scrollCollapse: true,
            paging: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            ordering: true,
            info: false,
            autoWidth: false,
            scrollX: false,
            responsive: false,
            columnDefs: [
                { targets: '_all' },
                { orderable: false, targets: -1 }
            ],
            dom: '<"row mb-2"<"col-sm-6"l><"col-sm-6"f>>tip',
            language: { lengthMenu: "Show _MENU_ entries" }
        });
    });
});
</script>
