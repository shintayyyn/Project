<?php
require_once '../../../includes/db.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$section_id = isset($_POST['section_id']) ? trim($_POST['section_id']) : null;
$section_code = isset($_POST['section_code']) ? trim($_POST['section_code']) : null;
$year_level = isset($_POST['year_level']) ? (int)$_POST['year_level'] : null;

// Validate required fields
if (!$section_id || !$section_code || !$year_level) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate section code format
if (!preg_match('/^[A-Z]+ [1-4][A-Z]$/', $section_code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid section code format']);
    exit;
}

// Validate year level
if ($year_level < 1 || $year_level > 4) {
    echo json_encode(['success' => false, 'message' => 'Invalid year level']);
    exit;
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
        echo json_encode(['success' => false, 'message' => 'Section code already exists']);
        exit;
    }

    // Update section
    $update_query = "UPDATE sections SET section_code = ?, year_level = ? WHERE section_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $section_code, $year_level, $section_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update section");
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'section_code' => $section_code,
        'year_level' => $year_level
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
$conn->close();