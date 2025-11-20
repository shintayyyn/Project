<?php
require_once __DIR__ . '/../../includes/db.php';

if(!isset($_POST['t_id'])) {
    echo "<div class='alert alert-danger text-center'>Teacher ID missing.</div>";
    exit;
}

$t_id = intval($_POST['t_id']);
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
while($row = $termsRes->fetch_assoc()){
    $terms[] = $row;
}

// 🔹 If no term selected, default to active term
if($selectedTermId === 0){
    $activeRes = $conn->query("
        SELECT term_id 
        FROM academic_terms 
        WHERE is_active = 1 
        LIMIT 1
    ");
    $activeTerm = $activeRes->fetch_assoc();
    if(!$activeTerm){
        echo '<div class="alert alert-info text-center">No active term found.</div>';
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

if(!$term){
    echo '<div class="alert alert-warning text-center">Selected term not found.</div>';
    exit;
}

$termSemester = intval($term['semester']);

// 🔹 Fetch teacher full name
$stmt = $conn->prepare("
    SELECT 
        CONCAT(t_lname, ', ', t_fname, 
               IF(t_mname<>'', CONCAT(' ', LEFT(t_mname,1), '.'), ''), 
               IF(t_suffix<>'', CONCAT(' ', t_suffix), '')
        ) AS full_name
    FROM teachers
    WHERE t_id=?
");
$stmt->bind_param("i", $t_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    echo "<div class='alert alert-danger text-center'>Teacher not found.</div>";
    exit;
}

// 🔹 Attendance summary
$semesterRes = $conn->query("
    SELECT 
        SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS Present,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS Late,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS Absent,
        SUM(CASE WHEN status='Excuse' THEN 1 ELSE 0 END) AS Excuse
    FROM teacher_attendance
    WHERE t_id=$t_id AND term_id=$selectedTermId
");
$semester = $semesterRes->fetch_assoc() ?? ['Present'=>0,'Late'=>0,'Absent'=>0,'Excuse'=>0];

// 🔹 Determine month range
if($termSemester === 1){
    $allMonths = range(7, 12); // July–Dec
} else {
    $allMonths = range(1, 5); // Jan–May
}

$statuses = ['Present','Late','Absent','Excuse'];
$months = [];
foreach($allMonths as $m){
    $months[$m] = array_fill_keys($statuses, 0);
}

// 🔹 Monthly summary
$monthsRes = $conn->query("
    SELECT MONTH(attendance_date) AS month,
           SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) AS Present,
           SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) AS Late,
           SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) AS Absent,
           SUM(CASE WHEN status='Excuse' THEN 1 ELSE 0 END) AS Excuse
    FROM teacher_attendance
    WHERE t_id=$t_id AND term_id=$selectedTermId
    GROUP BY MONTH(attendance_date)
");
while($row = $monthsRes->fetch_assoc()){
    $m = intval($row['month']);
    if(isset($months[$m])){
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
            <?php foreach($terms as $t): ?>
                <option value="<?= $t['term_id'] ?>" <?= $t['term_id']==$selectedTermId ? 'selected' : '' ?>>
                    <?= 'S.Y. '.$t['year_start'].'–'.$t['year_end'].' - Semester '.$t['semester'] ?>
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
                        <?php foreach($statuses as $status): ?>
                            <th><?= $status ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($months as $monthNum => $data): ?>
                        <tr>
                            <td><?= date('F', mktime(0,0,0,$monthNum,10)) ?></td>
                            <?php foreach($statuses as $status): ?>
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
    <?php foreach($statuses as $status): ?>
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

<!-- 🔹 Detailed Table -->
<div class="card">
    <div class="card-header fw-bold">All Attendance Records</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover display nowrap attendanceTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Subject Code</th>
                        <th>Section Code</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $detailsRes = $conn->query("
                        SELECT attendance_date, subject_code, section_code, time_in, time_out, status
                        FROM teacher_attendance
                        WHERE t_id=$t_id AND term_id=$selectedTermId
                        ORDER BY attendance_date ASC
                    ");
                    while($row = $detailsRes->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($row['attendance_date'])) ?></td>
                        <td><?= htmlspecialchars($row['subject_code']) ?></td>
                        <td><?= htmlspecialchars($row['section_code']) ?></td>
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
<script>
$(document).on('change', '#termSelect', function () {
    const term_id = $(this).val();
    const t_id = <?= $t_id ?>;

    $.post('reports/t_analytics.php', { t_id, term_id }, function (res) {
        // Replace the analytics content
        $('#analyticsContent').html(res);

        // 🔹 Reinitialize DataTable after new content is loaded
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


