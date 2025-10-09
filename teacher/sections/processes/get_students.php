<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if (!$section_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid section ID']);
    exit();
}

// Get active term_id
$term_result = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
if ($term_result && $term_result->num_rows > 0) {
    $term_row = $term_result->fetch_assoc();
    $term_id = $term_row['term_id'];
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No active term found']);
    exit();
}

try {
    // Verify teacher has access to this section during active term
    $access_check = "SELECT 1 FROM sections_schedules WHERE teacher_id = ? AND section_id = ? AND term_id = ? LIMIT 1";
    $stmt = $conn->prepare($access_check);
    $stmt->bind_param("iii", $teacher_id, $section_id, $term_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Access denied to this section for the current term');
    }

  
// Get students in the section for the active term
$students_query = "
    SELECT 
        st.idcode,
        st.s_gender AS gender,
        st.is_regular,
        CONCAT(
            st.s_lname,
            CASE WHEN st.s_suffix IS NOT NULL AND st.s_suffix != '' THEN CONCAT(' ', st.s_suffix) ELSE '' END,
            ', ',
            st.s_fname,
            CASE WHEN st.s_mname IS NOT NULL AND st.s_mname != '' THEN CONCAT(' ', LEFT(st.s_mname, 1), '.') ELSE '' END
        ) AS name
    FROM students_sections ss
    INNER JOIN students st ON ss.s_id = st.s_id
    WHERE ss.section_id = ? AND ss.term_id = ?

    UNION

    SELECT 
        st.idcode,
        st.s_gender AS gender,
        st.is_regular,
        CONCAT(
            st.s_lname,
            CASE WHEN st.s_suffix IS NOT NULL AND st.s_suffix != '' THEN CONCAT(' ', st.s_suffix) ELSE '' END,
            ', ',
            st.s_fname,
            CASE WHEN st.s_mname IS NOT NULL AND st.s_mname != '' THEN CONCAT(' ', LEFT(st.s_mname, 1), '.') ELSE '' END
        ) AS name
    FROM subject_enrollments se
    INNER JOIN students st ON se.s_id = st.s_id
    WHERE se.section_code = (
            SELECT sec.section_code 
            FROM sections sec 
            WHERE sec.section_id = ?
          )
      AND se.subject_id IN (
            SELECT ss.subject_id 
            FROM sections_schedules ss 
            WHERE ss.section_id = ? AND ss.term_id = ?
          )
      AND se.term_id = ?
      AND st.is_regular = 2
      AND se.enrollment_status = 'Enrolled'
";

$stmt = $conn->prepare($students_query);
if (!$stmt) {
    throw new Exception('Query preparation failed: ' . $conn->error);
}

// 🔹 Bind parameters
$stmt->bind_param("iiiiii", $section_id, $term_id, $section_id, $section_id, $term_id, $term_id);

$stmt->execute();
$result = $stmt->get_result();

$students = [];
$male_count = 0;
$female_count = 0;

while ($row = $result->fetch_assoc()) {
    $gender = ucfirst(strtolower($row['gender']));
    
    if ($gender === 'Male') {
        $male_count++;
    } elseif ($gender === 'Female') {
        $female_count++;
    }

    $students[] = [
        'id'         => $row['idcode'],
        'name'       => trim(preg_replace('/\s+/', ' ', $row['name'])),
        'gender'     => $gender,
        'is_regular' => ($row['is_regular'] == 1 ? 'Regular' : 'Irregular')
    ];
}


header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'term_id' => $term_id,
    'students' => $students,
    'counts' => [
        'total' => count($students),
        'male' => $male_count,
        'female' => $female_count
    ],
    'debug' => [
        'section_id' => $section_id,
        'teacher_id' => $teacher_id
    ]
]);

} catch (Exception $e) {
    error_log('Student list error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load students',
        'debug_message' => $e->getMessage(),
        'debug' => [
            'section_id' => $section_id,
            'teacher_id' => $teacher_id,
            'term_id' => $term_id
        ]
    ]);
}
?>
