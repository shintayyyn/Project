<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    if (empty($_POST['student_id']) || empty($_POST['section_id'])) {
        throw new Exception('Student ID and Section ID are required');
    }

    $student_id = trim($_POST['student_id']);
    $section_id = (int)$_POST['section_id'];

    // Validate student exists and get degree
    $student_query = $conn->prepare("
        SELECT sd.degree_code 
        FROM students_degrees sd 
        WHERE sd.s_id = ? AND sd.status = 'Active'
        LIMIT 1
    ");
    if (!$student_query) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $student_query->bind_param("s", $student_id);
    if (!$student_query->execute()) {
        throw new Exception('Error checking student: ' . $student_query->error);
    }
    $student_result = $student_query->get_result();
    if ($student_result->num_rows === 0) {
        throw new Exception('Student not found or not active');
    }
    $student_degree = $student_result->fetch_assoc()['degree_code'];

    // Validate section exists and get degree
    $section_query = $conn->prepare("
        SELECT section_code, max_students 
        FROM sections 
        WHERE section_id = ?
        LIMIT 1
    ");
    if (!$section_query) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $section_query->bind_param("i", $section_id);
    if (!$section_query->execute()) {
        throw new Exception('Error checking section: ' . $section_query->error);
    }
    $section_result = $section_query->get_result();
    if ($section_result->num_rows === 0) {
        throw new Exception('Section not found');
    }
    $section_data = $section_result->fetch_assoc();
    $section_degree = explode(' ', $section_data['section_code'])[0];

    // Verify degrees match
    if ($student_degree !== $section_degree) {
        throw new Exception('Student can only be assigned to sections in their degree program');
    }

    // Check if section is full
    $count_query = $conn->prepare("
        SELECT COUNT(*) as student_count 
        FROM students_sections 
        WHERE section_id = ?
    ");
    $count_query->bind_param("i", $section_id);
    $count_query->execute();
    $count_result = $count_query->get_result();
    $current_count = $count_result->fetch_assoc()['student_count'];
    
    if ($current_count >= $section_data['max_students']) {
        throw new Exception('Section is already full');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Check if student is already assigned
        $check_query = $conn->prepare("
            SELECT section_id 
            FROM students_sections 
            WHERE s_id = ?
        ");
        $check_query->bind_param("s", $student_id);
        $check_query->execute();
        $check_result = $check_query->get_result();

        // Prepare the appropriate statement
        if ($check_result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE students_sections SET section_id = ? WHERE s_id = ?");
            $stmt->bind_param("is", $section_id, $student_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO students_sections (s_id, section_id) VALUES (?, ?)");
            $stmt->bind_param("si", $student_id, $section_id);
        }

        // Execute the assignment
        if (!$stmt->execute()) {
            throw new Exception('Failed to assign student to section');
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Student assigned successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
