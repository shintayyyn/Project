<?php
require_once '../../includes/db.php';

// Clear any previous output
if (ob_get_level()) ob_end_clean();

// Fetch statistics
$stats = [
    'students' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'],
    'sections' => $conn->query("SELECT COUNT(*) as count FROM sections")->fetch_assoc()['count'],
    'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count']
];

// Fetch section statistics
$section_stats_query = "
    SELECT 
        s.section_code, 
        COUNT(ss.s_id) as student_count,
        s.max_students,
        ROUND((COUNT(ss.s_id) / s.max_students) * 100) as fill_percentage
    FROM sections s
    LEFT JOIN students_sections ss ON s.section_id = ss.section_id
    GROUP BY s.section_id
    ORDER BY fill_percentage DESC";
$section_stats = $conn->query($section_stats_query);

// Fetch all activities
$activities_query = "
    (SELECT 
        s_id as id,
        CONCAT(s_fname, ' ', s_lname) as name,
        'Student' as type,
        'Added new student' as action
    FROM students)
    UNION ALL
    (SELECT 
        t_id as id,
        CONCAT(t_fname, ' ', t_lname) as name,
        'Teacher' as type,
        'Added new teacher' as action
    FROM teachers)
    ORDER BY id DESC";
$activities = $conn->query($activities_query);

// Generate filename
$filename = 'school_statistics_report_' . date('Y-m-d') . '.csv';

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create output
$output = fopen('php://output', 'w');

// Add UTF-8 BOM
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Title and Date
fputcsv($output, ['School Statistics Report']);
fputcsv($output, ['Generated on:', date('F d, Y')]);
fputcsv($output, []); // Empty line

// Overall Statistics
fputcsv($output, ['Overall Statistics']);
fputcsv($output, ['Category', 'Count']);
fputcsv($output, ['Total Students', $stats['students']]);
fputcsv($output, ['Total Teachers', $stats['teachers']]);
fputcsv($output, ['Total Sections', $stats['sections']]);
fputcsv($output, ['Total Subjects', $stats['subjects']]);
fputcsv($output, []); // Empty line

// Section Capacity
fputcsv($output, ['Section Capacity']);
fputcsv($output, ['Section Code', 'Students', 'Capacity', 'Fill Rate (%)']);
while ($section = $section_stats->fetch_assoc()) {
    fputcsv($output, [
        $section['section_code'],
        $section['student_count'],
        $section['max_students'],
        $section['fill_percentage'] . '%'
    ]);
}
fputcsv($output, []); // Empty line

// Activities
fputcsv($output, ['All Activities']);
fputcsv($output, ['Name', 'Type', 'Action', 'ID']);
while ($activity = $activities->fetch_assoc()) {
    fputcsv($output, [
        $activity['name'],
        $activity['type'],
        $activity['action'],
        $activity['id']
    ]);
}

// Close the output
fclose($output);
exit();
?>
