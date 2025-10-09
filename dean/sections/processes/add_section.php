<?php
require_once __DIR__ . '/../../../includes/db.php';
session_start();
header('Content-Type: application/json');

try {
    // Validate input
    if (empty($_POST['section_name']) || empty($_POST['section_code']) || empty($_POST['max_students'])) {
        throw new Exception('All fields are required');
    }

    if (!isset($_SESSION['t_id'])) {
        throw new Exception('Unauthorized: Dean not logged in');
    }

    $dean_id = $_SESSION['t_id'];
    $section_name = trim($_POST['section_name']);
    $section_code = trim($_POST['section_code']);
    $max_students = (int)$_POST['max_students'];

    // Extract year level from section code (e.g., BSIT 1A => 1)
    if (!preg_match('/^[A-Z]+ ([1-4])[A-Z]$/', $section_code, $matches)) {
        throw new Exception('Invalid section code format');
    }
    $year_level = $matches[1];

    // Fetch degree_id assigned to this dean (assumes 1-to-1 relation; modify if many)
    $degree_stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE dean_id = ?");
    $degree_stmt->bind_param("i", $dean_id);
    $degree_stmt->execute();
    $degree_result = $degree_stmt->get_result();

    if ($degree_result->num_rows === 0) {
        throw new Exception('No degree assigned to this dean.');
    }

    $degree = $degree_result->fetch_assoc();
    $degree_id = $degree['degree_id'];

    // Check for duplicate section code
    $check_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_code = ?");
    $check_stmt->bind_param("s", $section_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Section code already exists');
    }

    // Insert the new section with assigned degree_id
    $stmt = $conn->prepare("INSERT INTO sections (section_code, section_name, year_level, max_students, degree_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiii", $section_code, $section_name, $year_level, $max_students, $degree_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to add section: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Section added successfully',
        'section_id' => $conn->insert_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
