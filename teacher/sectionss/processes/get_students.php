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

try {
    // Verify teacher has access to this section
    $access_check = "SELECT 1 FROM sections_schedules WHERE teacher_id = ? AND section_id = ? LIMIT 1";
    $stmt = $conn->prepare($access_check);
    $stmt->bind_param("ii", $teacher_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Access denied to this section');
    }

    // Get students in the section
    $students_query = "SELECT 
                        ss.s_id,
                        ss.s_gender as gender,
                        CONCAT(
                            ss.s_fname,
                            CASE 
                                WHEN ss.s_mname IS NOT NULL AND ss.s_mname != '' 
                                THEN CONCAT(' ', LEFT(ss.s_mname, 1), '. ')
                                ELSE ' '
                            END,
                            ss.s_lname,
                            CASE 
                                WHEN ss.s_suffix IS NOT NULL AND ss.s_suffix != '' 
                                THEN CONCAT(' ', ss.s_suffix)
                                ELSE ''
                            END
                        ) as name
                      FROM students_sections ss
                      WHERE ss.section_id = ?
                      ORDER BY ss.s_lname, ss.s_fname";

    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $section_id);
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $students = [];
    
    while ($row = $result->fetch_assoc()) {
    $students[] = [
        'id' => $row['s_id'], // <-- Add this
        'name' => trim(preg_replace('/\s+/', ' ', $row['name'])),
        'gender' => ucfirst(strtolower($row['gender']))
    ];
}


    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'students' => $students,
        'debug' => [
            'section_id' => $section_id,
            'teacher_id' => $teacher_id,
            'student_count' => count($students)
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
            'teacher_id' => $teacher_id
        ]
    ]);
}
