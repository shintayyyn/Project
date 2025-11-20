<?php
session_start();
require_once(__DIR__ . '/../../includes/db.php');

if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

$parent_id = $_SESSION['parent_id'];
$child_id = $_GET['child_id'] ?? 0;
if (!$child_id) {
    echo '<div class="alert alert-warning">Invalid child ID.</div>';
    exit;
}

// 🔹 Get child's info
$stmt = $conn->prepare("SELECT s_fname, s_lname, is_regular FROM students WHERE s_id = ?");
$stmt->bind_param("i", $child_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo '<div class="alert alert-danger">Child not found.</div>';
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
    $stmt->bind_param("i", $child_id);
    $stmt->execute();
    $sectionRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $section_code = $sectionRow['section_code'] ?? '';
    $course = $sectionRow['course'] ?? '';
    $year_level = $sectionRow['year_level'] ?? '';
} else {
    // Irregular
    $stmt = $conn->prepare("
        SELECT s.year_level, d.degree_code AS course
        FROM students_degrees sd
        JOIN students s ON sd.s_id = s.s_id
        JOIN degrees d ON sd.degree_id = d.degree_id
        WHERE sd.s_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $child_id);
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
    $stmt->bind_param("i", $child_id);
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
        $stmt = $conn->prepare("
            SELECT subj.subject_code, subj.subject_description, subj.units,
                   sch.day_of_week AS day, sch.start_time, sch.end_time,
                   r.room_number AS room,
                   CONCAT(t.t_fname, ' ', IF(t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''), t.t_lname, IF(t.t_suffix != '', CONCAT(' ', t.t_suffix), '')) AS teacher_name
            FROM sections_schedules sch
            JOIN subjects subj ON sch.subject_code = subj.subject_code
            LEFT JOIN teachers t ON sch.teacher_id = t.t_id
            LEFT JOIN rooms r ON sch.room_id = r.room_id
            WHERE sch.section_id = (SELECT section_id FROM students_sections WHERE s_id = ? LIMIT 1)
              AND sch.term_id = ? AND sch.is_active = 1
            ORDER BY subj.subject_code, sch.start_time
        ");
        $stmt->bind_param("ii", $child_id, $term_id);
    } else {
        $stmt = $conn->prepare("
            SELECT subj.subject_code, subj.subject_description, subj.units,
                   sch.day_of_week AS day, sch.start_time, sch.end_time,
                   r.room_number AS room,
                   se.section_code AS sections_enrolled,
                   CONCAT(t.t_fname, ' ', IF(t.t_mname != '', CONCAT(LEFT(t.t_mname,1), '. '), ''), t.t_lname, IF(t.t_suffix != '', CONCAT(' ', t.t_suffix), '')) AS teacher_name
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
        $stmt->bind_param("ii", $term_id, $child_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = null;
}

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-info">No study load found for this child.</div>';
    exit;
}

// 🔹 Combine schedules and days
$studyload = [];
$total_units = 0;
while ($row = $result->fetch_assoc()) {
    $day = !empty($row['day']) ? trim($row['day']) : 'TBA';
    $start = !empty($row['start_time']) ? $row['start_time'] : 'TBA';
    $end = !empty($row['end_time']) ? $row['end_time'] : 'TBA';
    $sections = $row['sections_enrolled'] ?? $section_code;
    $teacher_name = !empty($row['teacher_name']) ? $row['teacher_name'] : 'TBA';
    $room = !empty($row['room']) ? $row['room'] : 'TBA';

    $key = $row['subject_code'] . '|' . $sections . '|' . $start . '|' . $end;

    $dayShort = ($day === 'TBA') ? 'TBA' : ucfirst(substr(strtolower($day), 0, 3));
    $formattedTime = ($start === 'TBA' || $end === 'TBA') ? 'TBA' : date('h:i A', strtotime($start)) . ' - ' . date('h:i A', strtotime($end));

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

// 🔹 HTML output (same as student version)
// Use the same print-ready table & formatting as your student-style version.
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Study Load</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <style>
         
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .studyload-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin: 40px auto;
            padding: 30px;
            max-width: 900px;
        }
        .school-header {
            text-align: center;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .school-header img {
            height: 100px;
            margin-bottom: 10px;
        }
        .school-header h4 {
            font-weight: 700;
            color: --primary;
        }
        .school-header p {
            margin: 0;
            color: var(--primary);
            font-size: 14px;
        }
    
        .note {
            font-size: 12px;
            color: var(--primary);
            margin-top: 15px;
            text-align: center;
        }
        .title{
            color: var(--primary);
        }
        .headers{
             background: var(--primary);
        }
    </style>
</head>
<body>

<div class="studyload-container">
    <div class="school-header">
        <img src="../assets/img/attendifylogo.png" alt="School Logo">
        <h4 class="title">COLLEGE SCHOOL</h4>
        <p>1st Sample Street, Sample City, Sample</p>
        <p>Tel. No. (032) 123-456 | Email: attendifysys2025@gmail.com</p>
    </div>

  <h5 class="text-center fw-bold mb-1 title">STUDY LOAD</h5>
<p class="text-center mb-1">S.Y. <?= htmlspecialchars($term_year); ?> — <?= htmlspecialchars($term_name); ?></p>
<p class="text-center mb-3">
    Name: <strong><?= htmlspecialchars($student_name); ?></strong><br>
  </p>
  <div class="row">
    <div class="col text-start">
      Course & Year: <strong><?= htmlspecialchars($course . ' ' . $year_level); ?></strong><br>
    </div>
    <div class="col text-end">
    Section: <strong><?= htmlspecialchars($section_code); ?></strong>

    </div>

  </div>

  <div class="table-responsive">
    <table class="table table-bordered text-center align-middle">
        <thead class="headers">
            <tr class="text-white">
                <th>Code</th>
                <th>Subject</th>
                <th>Units</th>
                <th>Schedule</th>
                <th>Room</th>
                <th>Instructor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($studyload as $item): ?>
                <?php
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
                    <td><?= htmlspecialchars($abbr . ' ' . $item['time']); ?></td>
                    <td><?= htmlspecialchars($item['room']); ?></td>
                    <td><?= htmlspecialchars($item['teacher_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td colspan="2" class="text-end">Total Units:</td>
                <td><?= number_format($total_units, 1); ?></td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
</div>


    <p class="note">
        Note: This document is computer-generated and not the official study load. <br>
        The official study load is printed by the Registrar’s Office.
    </p>
</div>
</body>
</html>
