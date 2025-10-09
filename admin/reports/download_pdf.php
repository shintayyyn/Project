<?php
require_once '../../includes/db.php';

// Fetch all data first
$stats = [
    'students' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
    'teachers' => $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'],
    'sections' => $conn->query("SELECT COUNT(*) as count FROM sections")->fetch_assoc()['count'],
    'subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count']
];

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

// Create content with fixed-width font formatting
$content = str_repeat("=", 80) . "\n";
$content .= str_pad("SCHOOL STATISTICS REPORT", 80, " ", STR_PAD_BOTH) . "\n";
$content .= str_pad("Generated on: " . date('F d, Y'), 80, " ", STR_PAD_BOTH) . "\n";
$content .= str_repeat("=", 80) . "\n\n";

$content .= "OVERALL STATISTICS\n";
$content .= str_repeat("-", 80) . "\n";
$content .= sprintf("%-20s: %d\n", "Total Students", $stats['students']);
$content .= sprintf("%-20s: %d\n", "Total Teachers", $stats['teachers']);
$content .= sprintf("%-20s: %d\n", "Total Sections", $stats['sections']);
$content .= sprintf("%-20s: %d\n", "Total Subjects", $stats['subjects']);
$content .= "\n";

$content .= "SECTION CAPACITY\n";
$content .= str_repeat("-", 80) . "\n";
$content .= sprintf("%-20s %-15s %-15s %-15s\n", 
    "Section Code", "Students", "Capacity", "Fill Rate");
$content .= str_repeat("-", 80) . "\n";

while ($section = $section_stats->fetch_assoc()) {
    $content .= sprintf("%-20s %-15s %-15s %-15s\n",
        $section['section_code'],
        $section['student_count'],
        $section['max_students'],
        $section['fill_percentage'] . '%'
    );
}
$content .= "\n";

$content .= "ALL ACTIVITIES\n";
$content .= str_repeat("-", 80) . "\n";
$content .= sprintf("%-30s %-15s %-20s %-10s\n",
    "Name", "Type", "Action", "ID");
$content .= str_repeat("-", 80) . "\n";

while ($activity = $activities->fetch_assoc()) {
    $content .= sprintf("%-30s %-15s %-20s %-10s\n",
        $activity['name'],
        $activity['type'],
        $activity['action'],
        $activity['id']
    );
}

// Output as text file
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="school_statistics_report_' . date('Y-m-d') . '.txt"');
echo $content;
?>
