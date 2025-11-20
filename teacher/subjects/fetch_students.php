<?php
session_start();
require_once('../../includes/db.php');

header('Content-Type: text/html; charset=utf-8'); // prevent extra spaces

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// Get parameters
$section_code = $_GET['section_id'] ?? null;
$subject_code = $_GET['subject_code'] ?? null;
if (!$section_code || !$subject_code) {
    http_response_code(400);
    echo "Missing parameters";
    exit;
}

// Get active term
$term_row = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
$term_id = $term_row['term_id'] ?? null;
if (!$term_id) { echo "No active term."; exit; }

// Fetch subject_id
$subject_row = $conn->query("SELECT subject_id FROM subjects WHERE subject_code = '{$conn->real_escape_string($subject_code)}' LIMIT 1")->fetch_assoc();
$subject_id = $subject_row['subject_id'] ?? null;
if (!$subject_id) { echo "Subject not found."; exit; }

// Fetch students 
$query = "
SELECT * FROM (
    -- Regular students
    SELECT 
        s.s_id, 
        s.idcode,  -- ✅ Added: include student ID code
        CONCAT_WS(' ', s.s_lname, s.s_fname, s.s_mname, s.s_suffix) AS full_name, 
        s.s_gender, 
        s.is_regular,
        sec.section_code
    FROM students_sections ss
    INNER JOIN students s ON ss.s_id = s.s_id
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE sec.section_code = ? AND ss.term_id = ?

    UNION ALL

    -- Irregular students
    SELECT 
        s.s_id, 
        s.idcode,  -- ✅ Added: include student ID code
        CONCAT_WS(' ', s.s_lname, s.s_fname, s.s_mname, s.s_suffix) AS full_name, 
        s.s_gender, 
        s.is_regular,
        se.section_code
    FROM subject_enrollments se
    INNER JOIN students s ON se.s_id = s.s_id
    WHERE se.subject_id = ? 
      AND se.term_id = ? 
      AND s.is_regular = 2 
      AND se.enrollment_status = 'Enrolled'
      AND se.section_code = ?   -- ✅ Ensure only those in this section_code
) AS students_union
ORDER BY full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("siiss", $section_code, $term_id, $subject_id, $term_id, $section_code);
$stmt->execute();
$result = $stmt->get_result();
$total = $result->num_rows;

if ($total === 0) {
    echo '<div class="text-center text-muted py-3">No students found.</div>';
    exit;
}
?>

<div>Total Students: <strong><?= $total ?></strong></div>

<div class="table-responsive">
<table id="studentsTable" class="table table-hover" style="width:100%;">
   <thead class="card-header text-white">
    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Gender</th>
        <th>Status</th>
        <th>Section</th>
    </tr>
</thead>
<tbody class="table-light">
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['idcode']) ?></td>
            <td><?= htmlspecialchars($row['full_name']) ?></td>
            <td><?= htmlspecialchars($row['s_gender']) ?></td>
            <td><?= $row['is_regular'] == 1 ? 'Regular' : 'Irregular' ?></td>
            <td><?= htmlspecialchars($row['section_code']) ?></td>
        </tr>
    <?php endwhile; ?>
</tbody>
</table>
</div>

<style>
/* Optional: set min-widths to help alignment */
#studentsTable th:nth-child(1), #studentsTable td:nth-child(1) { min-width: 80px; }
#studentsTable th:nth-child(2), #studentsTable td:nth-child(2) { min-width: 200px; }
#studentsTable th:nth-child(3), #studentsTable td:nth-child(3) { min-width: 80px; }
#studentsTable th:nth-child(4), #studentsTable td:nth-child(4) { min-width: 100px; }
#studentsTable th:nth-child(5), #studentsTable td:nth-child(5) { min-width: 120px; }
</style>
