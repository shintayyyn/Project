<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    echo '<div class="alert alert-warning">Invalid student session.</div>';
    exit;
}

// 🔹 Get student info
$stmt = $conn->prepare("SELECT s_fname, s_lname, is_regular FROM students WHERE s_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo '<div class="alert alert-danger">Student not found.</div>';
    exit;
}

$student_name = $student['s_fname'] . ' ' . $student['s_lname'];
$is_regular = $student['is_regular'];

// 🔹 Get active term
$termRow = $conn->query("
    SELECT t.term_id, t.semester, CONCAT(y.year_start,'-',y.year_end) AS sy
    FROM academic_terms t
    JOIN academic_years y ON t.ay_id = y.ay_id
    WHERE t.is_active = 1
    LIMIT 1
")->fetch_assoc();

$term_id = $termRow['term_id'] ?? null;
$term_name = $termRow['semester'] ?? '';
$term_year = $termRow['sy'] ?? '';

if (!$term_id) {
    echo '<div class="alert alert-warning">No active term found.</div>';
    exit;
}

// 🔹 Get section/course/year
$section_code = $course = $year_level = '';
$irregular_sections = [];

if ($is_regular == 1) {
    // Regular
    $stmt = $conn->prepare("
        SELECT sec.section_id, sec.section_code, d.degree_code AS course, sec.year_level
        FROM students_sections ss
        JOIN sections sec ON ss.section_id = sec.section_id
        JOIN degrees d ON sec.degree_id = d.degree_id
        WHERE ss.s_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $sectionRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $section_code = $sectionRow['section_code'] ?? '';
    $course = $sectionRow['course'] ?? '';
    $year_level = $sectionRow['year_level'] ?? '';

} elseif ($is_regular == 2) {
    // Irregular
    $stmt = $conn->prepare("
        SELECT s.year_level, d.degree_code AS course
        FROM students_degrees sd
        JOIN students s ON sd.s_id = s.s_id
        JOIN degrees d ON sd.degree_id = d.degree_id
        WHERE sd.s_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $course = $studentRow['course'] ?? '';
    $year_level = $studentRow['year_level'] ?? '';

    $stmt = $conn->prepare("
        SELECT DISTINCT section_code
        FROM subject_enrollments
        WHERE s_id = ? AND enrollment_status = 'Enrolled'
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $sectionsResult = $stmt->get_result();
    while ($row = $sectionsResult->fetch_assoc()) {
        $irregular_sections[] = $row['section_code'];
    }
    $stmt->close();
    $section_code = implode(', ', $irregular_sections);
}

// 🔹 Unified Study Load Query
if (!empty($section_code)) {
   if ($is_regular == 1) {
    // Regular: all subjects from section, show TBA if schedule missing
    $stmt = $conn->prepare("
        SELECT subj.subject_code, subj.subject_description, subj.units,
               sch.day_of_week AS day, sch.start_time, sch.end_time,
               sch.schedule_group_id, r.room_number AS room,
               CONCAT(
                   t.t_fname, ' ',
                   IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''),
                   t.t_lname,
                   IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
               ) AS teacher_name,
               sec.section_code AS sections_enrolled
        FROM subjects subj
        JOIN sections_schedules sch ON subj.subject_code = sch.subject_code AND sch.term_id = ?
        JOIN sections sec ON sch.section_id = sec.section_id
        LEFT JOIN teachers t ON sch.teacher_id = t.t_id
        LEFT JOIN rooms r ON sch.room_id = r.room_id
        WHERE sec.section_id = (SELECT section_id FROM students_sections WHERE s_id = ? LIMIT 1)
        AND sch.is_active = 1
        ORDER BY subj.subject_code, sch.start_time
    ");
    $stmt->bind_param("ii", $term_id, $student_id);
}
 else {
        // Irregular: only enrolled subjects, aligned with schedule
       // Irregular: fetch enrolled subjects including BSHM
$stmt = $conn->prepare("
    SELECT subj.subject_code, subj.subject_description, subj.units,
           sch.day_of_week AS day, sch.start_time, sch.end_time,
           sch.schedule_group_id, r.room_number AS room,
           se.section_code AS sections_enrolled,
           CONCAT(
               t.t_fname, ' ',
               IF(t.t_mname IS NOT NULL AND t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''),
               t.t_lname,
               IF(t.t_suffix IS NOT NULL AND t.t_suffix != '', CONCAT(' ', t.t_suffix), '')
           ) AS teacher_name
    FROM subject_enrollments se
    LEFT JOIN sections_schedules sch 
         ON se.section_code = sch.section_code 
         AND se.subject_code = sch.subject_code
         AND sch.term_id = ?
         AND sch.is_active = 1
    JOIN subjects subj ON se.subject_code = subj.subject_code
    LEFT JOIN teachers t ON sch.teacher_id = t.t_id
    LEFT JOIN rooms r ON sch.room_id = r.room_id
    WHERE se.s_id = ? 
      AND se.enrollment_status = 'Enrolled'
    ORDER BY subj.subject_code, sch.start_time
");
$stmt->bind_param("ii", $term_id, $student_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = null;
}

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-info">No study load found for this student.</div>';
    exit;
}

// 🔹 Combine schedules and days
$studyload = [];
$total_units = 0;

while ($row = $result->fetch_assoc()) {
    // Default to 'TBA' if schedule info is missing
    $day = !empty($row['day']) ? trim($row['day']) : 'TBA';
    $start = !empty($row['start_time']) ? $row['start_time'] : 'TBA';
    $end = !empty($row['end_time']) ? $row['end_time'] : 'TBA';
    $sections = $row['sections_enrolled'] ?? $section_code;
    $teacher_name = !empty($row['teacher_name']) ? $row['teacher_name'] : 'TBA';
    $room = !empty($row['room']) ? $row['room'] : 'TBA';

    // Unique key using schedule group for merging
    $key = $row['subject_code'] . '|' . $sections . '|' . $start . '|' . $end . '|' . ($row['schedule_group_id'] ?? '0');

    $dayShort = ($day === 'TBA') ? 'TBA' : ucfirst(substr(strtolower($day), 0, 3));
    $formattedTime = ($start === 'TBA' || $end === 'TBA')
        ? 'TBA'
        : date('h:i A', strtotime($start)) . ' - ' . date('h:i A', strtotime($end));

    if (!isset($studyload[$key])) {
        $studyload[$key] = [
            'subject_code' => $row['subject_code'],
            'subject_description' => $row['subject_description'],
            'units' => $row['units'],
            'teacher_name' => $teacher_name,
            'room' => $room,
            'time' => $formattedTime,
            'days' => ($dayShort === 'TBA') ? ['TBA'] : [$dayShort],
            'sections' => $sections
        ];
        $total_units += floatval($row['units']);
    } elseif ($dayShort !== 'TBA' && !in_array($dayShort, $studyload[$key]['days'])) {
        $studyload[$key]['days'][] = $dayShort;
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Study Load</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
    .studyload-container { background: #fff; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); margin: 20px auto; padding: 30px; max-width: 900px; }
    .school-header { text-align: center; border-bottom: 3px solid var(--primary); padding-bottom: 15px; margin-bottom: 25px; }
    .school-header img { height: 100px; margin-bottom: 10px; }
    .school-header h4 { font-weight: 700; color: var(--primary); }
    .school-header p { margin: 0; color: var(--primary); font-size: 14px; }
    .note { font-size: 12px; color: var(--primary); margin-top: 15px; text-align: center; }
    .title { color: var(--primary); }
    .card-header { background-color: #033A70 !important; font-size: 14px; }
   .studyload-container th {
    color: white !important;
}
th{color:#033A70 !important;}
    .studyload-container td { font-size: 12px; }
 @media print {
    /* Hide everything else */
    body * {
        visibility: hidden !important;
    }

    
    /* Show only studyload-container */
    .studyload-container, 
    .studyload-container * {
        visibility: visible !important;
    }
    

    /* Force layout fit to one landscape page */
   .studyload-container {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) scale(0.95); /* Center and slightly scale down */
        width: 95vw !important;
        height: auto !important;
        margin: 0 auto !important;
        padding: 10mm !important;
        background: #fff !important;
        box-shadow: none !important;
        overflow: visible !important;
        page-break-inside: avoid !important;
    }


    .print-btn {
        display: none !important;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        width: 297mm;
        height: 210mm;
        overflow: hidden !important;
    }

    @page {
        size: A4 landscape;
        margin: 5mm;
    }
}

</style>
</head>
<body>
<div class="studyload-container" id="studyload">
    <div class="text-end">
<!-- 🔹 Print Button -->
<button class="btn btn-warning print-btn" onclick="window.print()">
      <i class="bi bi-printer"></i> Print/PDF
</button>


    </div>
    <div class="school-header">
        <img src="../assets/img/attendifylogo.png" alt="School Logo">
        <h4 class="title">COLLEGE SCHOOL</h4>
        <p>1st Sample Street, Sample City</p>
        <p>Tel. (032) 123-456 | Email: attendifysys2025@gmail.com</p>
    </div>

    <h5 class="text-center fw-bold mb-1 title">STUDY LOAD</h5>
    <p class="text-center mb-1">S.Y. <?= htmlspecialchars($term_year); ?> — <?= htmlspecialchars($term_name); ?></p>
    <p class="text-center mb-3">Name: <strong><?= htmlspecialchars($student_name); ?></strong></p>

    <div class="row mb-3">
        <div class="col text-start">
            Course & Year Level: <strong><?= htmlspecialchars($course . ' | ' . $year_level); ?></strong>
        </div>
        <div class="col text-end">
            Section: <strong><?= htmlspecialchars($section_code); ?></strong>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered text-center align-middle">
            <thead class="card-header">
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Units</th>
                    <?php if ($is_regular == 2): ?><th>Section</th><?php endif; ?>
                    <th>Schedule</th>
                    <th>Room</th>
                    <th>Instructor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studyload as $item): 
                    $abbr = '';
                    foreach ($item['days'] as $d) {
                        switch (strtolower($d)) {
                            case 'mon': $abbr .= 'M'; break;
                            case 'tue': $abbr .= 'T'; break;
                            case 'wed': $abbr .= 'W'; break;
                            case 'thu': $abbr .= 'Th'; break;
                            case 'fri': $abbr .= 'F'; break;
                            case 'sat': $abbr .= 'S'; break;
                            case 'sun': $abbr .= 'Su'; break;
                        }
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['subject_code']); ?></td>
                    <td class="text-start"><?= htmlspecialchars($item['subject_description']); ?></td>
                    <td><?= htmlspecialchars($item['units']); ?></td>
                    <?php if ($is_regular == 2): ?><td><?= htmlspecialchars($item['sections']); ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($abbr . ' ' . $item['time']); ?></td>
                    <td><?= htmlspecialchars($item['room']); ?></td>
                    <td><?= htmlspecialchars($item['teacher_name']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="fw-bold">
                    <td colspan="<?= $is_regular == 2 ? 4 : 3 ?>" class="text-end">Total Units:</td>
                    <td><?= number_format($total_units, 1); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="note">
        Note: This document is computer-generated and not the official study load.<br>
        The official version is printed by the Registrar’s Office.
    </p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('downloadPdfBtn');
    btn.addEventListener('click', function() {
        window.print(); // Opens the system print dialog
    });
});
</script>

</body>
</html>