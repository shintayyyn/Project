<?php
require_once '../../../includes/db.php';
header('Content-Type: application/json');

$parent_id = $_GET['parent_id'] ?? 0;

$children_sql = "
    SELECT st.s_fname, st.s_lname, s.section_code, ps.term_id, 
           at.semester, ay.year_start, ay.year_end, yl.level_name
    FROM parent_student ps
    LEFT JOIN students st ON ps.s_id = st.s_id
    LEFT JOIN students_sections s ON ps.s_id = s.s_id
    LEFT JOIN academic_terms at ON ps.term_id = at.term_id
    LEFT JOIN academic_years ay ON at.ay_id = ay.ay_id
    LEFT JOIN year_levels yl ON st.year_level = yl.id
    WHERE ps.p_id = $parent_id
";

$result = $conn->query($children_sql);
$children = [];
if($result && $result->num_rows > 0){
    while($c = $result->fetch_assoc()){
        $term_label = ($c['year_start'] && $c['year_end'] && $c['semester'])
            ? "A.Y. {$c['year_start']}-{$c['year_end']} | {$c['semester']}"
            : '-';
        $year_level = !empty($c['level_name']) ? $c['level_name'] : '-';
        $children[] = [
            'name' => "{$c['s_fname']} {$c['s_lname']}",
            'section' => $c['section_code'] ?? 'Not Assigned',
            'term' => $term_label,
            'year_level' => $year_level
        ];
    }
}

echo json_encode(['success' => true, 'children' => $children]);
