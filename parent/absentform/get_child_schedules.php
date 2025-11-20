<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

// ✅ Check if student ID is provided
if (!isset($_GET['s_id'])) {
    echo json_encode([]);
    exit();
}

$s_id = intval($_GET['s_id']);

// ✅ Get active term
$termQuery = $conn->query("
    SELECT term_id 
    FROM academic_terms 
    WHERE is_active = 1 
    LIMIT 1
");
$termRow = $termQuery->fetch_assoc();
$active_term_id = $termRow['term_id'] ?? null;

if (!$active_term_id) {
    echo json_encode(['error' => 'No active term found']);
    exit();
}

// ✅ Get student's section(s)
$section_stmt = $conn->prepare("
    SELECT section_id 
    FROM students_sections 
    WHERE s_id = ?
");
$section_stmt->bind_param("i", $s_id);
$section_stmt->execute();
$section_result = $section_stmt->get_result();

$sections = [];
while ($row = $section_result->fetch_assoc()) {
    $sections[] = $row['section_id'];
}

if (empty($sections)) {
    echo json_encode([]);
    exit();
}

// ✅ Build placeholders for IN clause
$placeholders = implode(',', array_fill(0, count($sections), '?'));
$types = str_repeat('i', count($sections)) . 'i'; // +1 for term_id

// ✅ SQL: Include schedule_group_id and join necessary tables
$sql = "
    SELECT 
        ss.ss_id,
        ss.section_id,
        ss.schedule_group_id,
        sec.section_code,
        ss.subject_code,
        subj.subject_description,
        subj.units,
        COALESCE(CONCAT(
            t.t_fname, ' ',
            IF(t.t_mname != '' AND t.t_mname IS NOT NULL, CONCAT(LEFT(t.t_mname, 1), '. '), ''),
            t.t_lname,
            IF(t.t_suffix != '' AND t.t_suffix IS NOT NULL, CONCAT(' ', t.t_suffix), '')
        ), '') AS teacher_name,
        COALESCE(ss.day_of_week, '') AS day_of_week,
        COALESCE(ss.start_time, '') AS start_time,
        COALESCE(ss.end_time, '') AS end_time,
        COALESCE(r.room_number, '') AS room_number,
        COALESCE(ss.status, '') AS status,
        COALESCE(ss.description, '') AS description
    FROM sections_schedules ss
    JOIN sections sec ON ss.section_id = sec.section_id
    JOIN subjects subj ON ss.subject_code = subj.subject_code
    LEFT JOIN teachers t ON ss.teacher_id = t.t_id
    LEFT JOIN rooms r ON ss.room_id = r.room_id
    WHERE ss.section_id IN ($placeholders)
      AND ss.term_id = ?
    ORDER BY ss.subject_code, ss.start_time
";

// ✅ Prepare & execute
$schedule_stmt = $conn->prepare($sql);
$params = array_merge($sections, [$active_term_id]);
$schedule_stmt->bind_param($types, ...$params);
$schedule_stmt->execute();
$result = $schedule_stmt->get_result();

// ✅ Merge by schedule_group_id (or fallback to unique time+subject)
$merged = [];

while ($row = $result->fetch_assoc()) {
    $groupKey = $row['schedule_group_id'] ?: (
        $row['section_id'] . '|' . $row['subject_code'] . '|' . 
        $row['start_time'] . '|' . $row['end_time']
    );

    // Convert full day to abbreviation
   // ✅ Convert any day format (Mon, Monday, M, etc.) to standard abbreviation
$day = strtolower(trim($row['day_of_week']));
$abbr = match (true) {
    in_array($day, ['monday', 'mon', 'm']) => 'M',
    in_array($day, ['tuesday', 'tue', 't']) => 'T',
    in_array($day, ['wednesday', 'wed', 'w']) => 'W',
    in_array($day, ['thursday', 'thu', 'thur', 'th']) => 'Th',
    in_array($day, ['friday', 'fri', 'f']) => 'F',
    in_array($day, ['saturday', 'sat', 's']) => 'S',
    in_array($day, ['sunday', 'sun', 'su']) => 'Su',
    default => ''
};

    if (!isset($merged[$groupKey])) {
        $merged[$groupKey] = [
            'ss_id' => $row['ss_id'], // ✅ include actual schedule ID
            'section_id' => $row['section_id'],
            'section_code' => $row['section_code'],
            'subject_code' => $row['subject_code'],
            'subject_description' => $row['subject_description'],
            'units' => $row['units'],
            'teacher_name' => $row['teacher_name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'room_number' => $row['room_number'],
            'status' => $row['status'],
            'description' => $row['description'],
            'days' => [],
            'schedule_group_id' => $row['schedule_group_id']
        ];
    }

    if ($abbr && !in_array($abbr, $merged[$groupKey]['days'])) {
        $merged[$groupKey]['days'][] = $abbr;
    }
}

// ✅ Sort days logically (M→T→W→Th→F→S→Su)
$dayOrder = ['M','T','W','Th','F','S','Su'];

foreach ($merged as &$item) {
    usort($item['days'], function($a, $b) use ($dayOrder) {
        return array_search($a, $dayOrder) <=> array_search($b, $dayOrder);
    });
    $item['days'] = implode('', $item['days']);
}

echo json_encode(array_values($merged), JSON_PRETTY_PRINT);
?>
