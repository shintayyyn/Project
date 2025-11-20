<?php
require_once __DIR__ . '/../../../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Teacher authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$teacher_id   = $_SESSION['user_id'];
$section_id   = isset($_GET['section_id']) ? (int)$_GET['section_id'] : '';
$month        = $_GET['month'] ?? date('Y-m');
$subject_code = $_GET['subject_code'] ?? '';
$filter_term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : null; // ✅ Filter by term_id
$semester     = $_GET['semester'] ?? '';

// ✅ Fetch ALL academic terms (active first)
$terms_result = $conn->query("
    SELECT 
        t.term_id,
        y.year_start,
        y.year_end,
        t.semester,
        t.is_active
    FROM academic_terms t
    INNER JOIN academic_years y ON t.ay_id = y.ay_id
    ORDER BY t.is_active DESC, t.term_id DESC
");
$terms = $terms_result->fetch_all(MYSQLI_ASSOC);

if (empty($terms)) {
    die(json_encode(['error' => 'No academic terms found.']));
}

// ✅ Determine which term(s) to include
if ($filter_term_id) {
    // Only show selected term
    $terms = array_filter($terms, fn($t) => $t['term_id'] == $filter_term_id);
} else {
    // Default to all terms (active first)
    usort($terms, fn($a, $b) => $b['is_active'] <=> $a['is_active']);
}

$startDate = $month . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

$result = [];

foreach ($terms as $term) {
    $term_id = $term['term_id'];
    $academic_term = 'A.Y. ' . $term['year_start'] . '-' . $term['year_end'] . ' | ' . $term['semester'];

    // ----------------------
    // Fetch teacher's section schedules per term
    // ----------------------
    $schedule_sql = "
        SELECT s.section_id, s.section_name, ss.subject_code, ss.day_of_week, ss.start_time, ss.end_time
        FROM sections_schedules ss
        INNER JOIN sections s ON ss.section_id = s.section_id
        WHERE ss.teacher_id = ? AND ss.term_id = ?
    ";
    $params = [$teacher_id, $term_id];
    $types = "ii";

    if ($subject_code) {
        $schedule_sql .= " AND ss.subject_code = ?";
        $params[] = $subject_code;
        $types .= "s";
    }
    if ($section_id) {
        $schedule_sql .= " AND ss.section_id = ?";
        $params[] = $section_id;
        $types .= "i";
    }

    $stmt = $conn->prepare($schedule_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $sched_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($sched_rows)) continue;

    $section_subject_map = [];
    $section_days_map = [];
    foreach ($sched_rows as $row) {
        $section_subject_map[$row['section_id']][] = $row['subject_code'];
        $day_full = ucfirst(strtolower($row['day_of_week']));
        $time_range = date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time']));
        $section_days_map[$row['subject_code']][] = $day_full . " ({$time_range})";
    }
    foreach ($section_subject_map as $sid => $subs) {
        $section_subject_map[$sid] = array_unique($subs);
    }

    // ----------------------
    // Fetch students (Regular + Irregular)
    // ----------------------
    $all_section_ids = array_keys($section_subject_map);
    if (empty($all_section_ids)) continue;

    $placeholders = implode(',', array_fill(0, count($all_section_ids), '?'));
    $types = str_repeat('i', count($all_section_ids));

    // Regular students
    $stmt = $conn->prepare("
        SELECT ss.section_id, s.s_id,
               CONCAT(
                   s.s_lname, IF(s.s_suffix!='', CONCAT(' ', s.s_suffix), ''), ', ',
                   s.s_fname, IF(s.s_mname!='', CONCAT(' ', LEFT(s.s_mname,1), '.'), '')
               ) AS student_name,
               'Regular' AS student_type
        FROM students_sections ss
        JOIN students s ON ss.s_id = s.s_id
        WHERE ss.section_id IN ($placeholders) AND ss.term_id = ?
        ORDER BY ss.section_id, s.s_lname, s.s_fname
    ");
    $stmt->bind_param($types . 'i', ...array_merge($all_section_ids, [$term_id]));
    $stmt->execute();
    $regular_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Irregular students
    $stmt = $conn->prepare("
        SELECT sec.section_id, se.s_id, se.subject_code,
               CONCAT(
                   s.s_lname, IF(s.s_suffix!='', CONCAT(' ', s.s_suffix), ''), ', ',
                   s.s_fname, IF(s.s_mname!='', CONCAT(' ', LEFT(s.s_mname,1), '.'), '')
               ) AS student_name,
               'Irregular' AS student_type
        FROM subject_enrollments se
        JOIN students s ON se.s_id = s.s_id
        JOIN sections sec ON se.section_code = sec.section_code
        JOIN sections_schedules ss ON ss.section_id = sec.section_id AND ss.subject_code = se.subject_code
        WHERE ss.teacher_id = ? AND ss.term_id = ? AND se.term_id = ? AND se.enrollment_status = 'Enrolled'
        " . ($subject_code ? " AND se.subject_code = ?" : "") . "
    ");
    if ($subject_code) {
        $stmt->bind_param("iiis", $teacher_id, $term_id, $term_id, $subject_code);
    } else {
        $stmt->bind_param("iii", $teacher_id, $term_id, $term_id);
    }
    $stmt->execute();
    $irregular_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Merge both types
    $students = [];
    foreach ($regular_students as $r) {
        $students[$r['s_id'].'_'.$r['section_id'].'_Regular'] = $r;
    }
    foreach ($irregular_students as $ir) {
        $key = $ir['s_id'].'_'.$ir['section_id'].'_Irregular';
        if (!isset($students[$key])) $students[$key] = $ir;
    }

    // ----------------------
    // Fetch attendance per student per subject
    // ----------------------
    foreach ($students as $stu) {
        $s_id = $stu['s_id'];
        $sec_id = $stu['section_id'];
        $subjects = $stu['student_type'] === 'Irregular'
            ? [$stu['subject_code'] ?? $subject_code]
            : ($section_subject_map[$sec_id] ?? []);

        if ($subject_code) {
            $subjects = array_filter($subjects, fn($subj) => $subj === $subject_code);
        }

        foreach ($subjects as $subj) {
            if (!$subj) continue;

            $att_sql = "
                SELECT status
                FROM attendance
                WHERE s_id = ? 
                  AND subject_code = ?
                  AND term_id = ?
                  AND DATE(time_in) BETWEEN ? AND ?
            ";
            $att_params = [$s_id, $subj, $term_id, $startDate, $endDate];
            $att_types = "isiss";

            $stmt = $conn->prepare($att_sql);
            $stmt->bind_param($att_types, ...$att_params);
            $stmt->execute();
            $att_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $present = $late = $absent = 0;
            foreach ($att_rows as $row) {
                $status = strtoupper(trim($row['status']));
                if ($status === 'PRESENT') $present++;
                elseif ($status === 'LATE') $late++;
                elseif ($status === 'ABSENT') $absent++;
            }

            $days = implode(' | ', $section_days_map[$subj] ?? []);
            $result[] = [
                's_id' => $s_id,
                'student_name' => $stu['student_name'],
                'subject_code' => $subj,
                'student_type' => $stu['student_type'],
                'days_of_week' => $days,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'academic_term' => $academic_term
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($result);
?>
