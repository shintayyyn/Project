<?php
require_once '../includes/db.php';

// session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$parent_id = $_SESSION['user_id'];

// Fetch children of this parent
$children_query = "
    SELECT s.s_id, s.s_fname, s.idcode
    FROM students s
    INNER JOIN parent_student ps ON ps.s_id = s.s_id
    WHERE ps.p_id = ?
";
$stmt_child = $conn->prepare($children_query);
$stmt_child->bind_param("i", $parent_id);
$stmt_child->execute();
$children = $stmt_child->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_child->close();

$student_id = isset($_GET['child']) ? intval($_GET['child']) : '';
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : '';
$selected_term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : '';

$term_dropdown_query = "
    SELECT t.term_id,
           CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
    FROM academic_terms t
    INNER JOIN academic_years y ON t.ay_id = y.ay_id
    ORDER BY y.year_start DESC, t.semester ASC
";
$term_dropdown = $conn->query($term_dropdown_query);

$attendance_history = [];

if (!empty($student_id)) {
    $attendance_query = "
        SELECT a.s_id, s.idcode, s.s_fname, a.subject_code, a.section_code,
               a.time_in, a.time_out, a.status,
               CONCAT('A.Y. ', y.year_start, '-', y.year_end, ' | ', t.semester) AS term_name
        FROM attendance a
        INNER JOIN students s ON a.s_id = s.s_id
        LEFT JOIN academic_terms t ON a.term_id = t.term_id
        LEFT JOIN academic_years y ON t.ay_id = y.ay_id
        WHERE a.s_id = ?
    ";

    $params = [$student_id];
    $types = "i";

    if (!empty($selected_month)) {
        $attendance_query .= " AND MONTH(a.time_in) = ?";
        $params[] = $selected_month;
        $types .= "i";
    }

    if (!empty($selected_term_id)) {
        $attendance_query .= " AND t.term_id = ?";
        $params[] = $selected_term_id;
        $types .= "i";
    }

    $attendance_query .= " ORDER BY a.time_in DESC";

    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $attendance_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Attendance Report</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>
<body>
<div class="container mt-4">
    <h2 class="text-primary">Attendance Report</h2>
    <p class="text-muted">Track and filter your child's attendance below.</p>

    <div class="card p-3 mb-3">
        <label class="fw-bold">Select Child:</label>
        <select id="childFilter" class="form-select">
            <option value="">Choose a child...</option>
            <?php foreach ($children as $child): ?>
                <option value="<?= $child['s_id'] ?>" <?= ($student_id == $child['s_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($child['s_fname']) ?> (<?= htmlspecialchars($child['idcode']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!empty($student_id)): ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <label>Academic Term:</label>
            <select id="termFilter" class="form-select">
                <option value="">All Terms</option>
                <?php while ($row = $term_dropdown->fetch_assoc()): ?>
                    <option value="<?= $row['term_id'] ?>" <?= ($selected_term_id == $row['term_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['term_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label>Month:</label>
            <select id="monthFilter" class="form-select">
                <option value="">All Months</option>
                <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($selected_month==$m)?'selected':'' ?>>
                        <?= date('F', mktime(0,0,0,$m,1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <table id="attendanceTable" class="table table-bordered table-striped table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Subject</th>
                <th>Section</th>
                <th>Date</th>
                <th>Time</th>
                <th>Term</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($attendance_history as $a): ?>
            <tr>
                <td><?= $a['idcode'] ?></td>
                <td><?= $a['subject_code'] ?></td>
                <td><?= $a['section_code'] ?></td>
                <td><?= date('M d, Y', strtotime($a['time_in'])) ?></td>
                <td><?= date('h:i A', strtotime($a['time_in'])) ?> - <?= $a['time_out'] ? date('h:i A', strtotime($a['time_out'])) : '-' ?></td>
                <td><?= $a['term_name'] ?></td>
                <td><?= $a['status'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function(){
    $('#attendanceTable').DataTable();

    function updateURL() {
        const url = new URL(window.location.href);
        const child = $('#childFilter').val();
        const month = $('#monthFilter').val();
        const term = $('#termFilter').val();

        child ? url.searchParams.set('child', child) : url.searchParams.delete('child');
        month ? url.searchParams.set('month', month) : url.searchParams.delete('month');
        term ? url.searchParams.set('term_id', term) : url.searchParams.delete('term_id');

        window.location.href = url.toString();
    }

    $('#childFilter, #monthFilter, #termFilter').on('change', updateURL);
});
</script>

</body>
</html>