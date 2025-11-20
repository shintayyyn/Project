<?php
(error_reporting(1));
require_once '../../../includes/db.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$section_id   = isset($_POST['section_id']) ? trim($_POST['section_id']) : null;
$section_code = isset($_POST['section_code']) ? trim($_POST['section_code']) : null;
$year_level   = isset($_POST['year_level']) ? (int)$_POST['year_level'] : null;
$max_students = isset($_POST['max_students']) ? (int)$_POST['max_students'] : null;

// Validate required fields
if (!$section_id || !$section_code || !$year_level || !$max_students) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate year level range
if ($year_level < 1 || $year_level > 4) {
    echo json_encode([
        'success' => false,
        'field' => 'year_level',
        'message' => 'Invalid year level (must be between 1 and 4)'
    ]);
    exit;
}

// Check if section_code has embedded year (like "BSIT 3A")
$pattern = '/^([A-Z]+)\s([1-4])([A-Z])$/';
if (preg_match($pattern, $section_code, $matches)) {
    $codeYear = (int)$matches[2];
    if ($codeYear !== $year_level) {
        echo json_encode([
            'success' => false,
            'field' => 'year_level',
            'message' => "Year level ($year_level) does not match section code ($section_code)"
        ]);
        exit;
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if section code already exists (excluding current section)
    $check_query = "SELECT section_id FROM sections WHERE section_code = ? AND section_id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $section_code, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'field' => 'section_code',
            'message' => 'Section code already exists'
        ]);
        exit;
    }

    // Update section
    $update_query = "UPDATE sections SET section_code = ?, year_level = ?, max_students = ? WHERE section_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("siii", $section_code, $year_level, $max_students, $section_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update section");
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'section_code' => $section_code,
        'year_level' => $year_level,
        'max_students' => $max_students
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
$conn->close();
