<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    // Validate required fields
    if (
        empty($_POST['section_name']) ||
        empty($_POST['section_code']) ||
        empty($_POST['max_students']) ||
        empty($_POST['degree_id'])
    ) {
        throw new Exception('All fields are required, including degree selection.');
    }

    $section_name = trim($_POST['section_name']);
    $section_code_raw = trim($_POST['section_code']);
    $max_students = (int)$_POST['max_students'];
    $degree_id = (int)$_POST['degree_id'];

    // Validate degree_id
    if ($degree_id <= 0) {
        throw new Exception('Invalid degree selected.');
    }

    /**
     * Helper function:
     * Capitalize the first letter of each word ONLY if the entire string is lowercase.
     */
    function formatInputName($input) {
        return ($input === strtolower($input)) ? ucwords($input) : $input;
    }

    $section_name = formatInputName($section_name);
    $section_code = formatInputName($section_code_raw);

    // ✅ Validation: allow letters, numbers, spaces, and hyphens in section code
    if (!preg_match('/^[A-Za-z0-9\- ]+$/', $section_code)) {
        throw new Exception('Invalid section code. Use letters, numbers, spaces, and hyphens only.');
    }

    /**
     * Detect year level if section code ends with a format like:
     * - "3A", "4B", etc.
     * Example: "BSIT 3A" → year_level = 3
     */
    $year_level = 0;
    if (preg_match('/\b([1-4])[A-Z]\s*$/i', $section_code, $matches)) {
        $year_level = (int)$matches[1];
    }

    // ✅ Check if selected degree_id exists
    $degree_check = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_id = ?");
    $degree_check->bind_param("i", $degree_id);
    $degree_check->execute();
    $degree_result = $degree_check->get_result();

    if ($degree_result->num_rows === 0) {
        throw new Exception('Selected degree does not exist.');
    }
    $degree_check->close();

    // ✅ Check for duplicate section_code
    $check_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_code = ?");
    $check_stmt->bind_param("s", $section_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('Section code already exists.');
    }
    $check_stmt->close();

    // ✅ Insert the new section
    $stmt = $conn->prepare("
        INSERT INTO sections (degree_id, section_code, section_name, year_level, max_students) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issii", $degree_id, $section_code, $section_name, $year_level, $max_students);

    if (!$stmt->execute()) {
        if ($stmt->errno == 1062) {
            throw new Exception('Section code already exists.');
        } else {
            throw new Exception('Failed to add section: ' . $stmt->error);
        }
    }

    $section_id = $conn->insert_id;

   echo json_encode([
    'success' => true,
    'message' => 'Section added successfully',
    'section_id' => $section_id,
    'degree_id' => $degree_id,
    'formatted_section_code' => $section_code,
    'formatted_section_name' => $section_name,
    'max_students' => $max_students,
    'year_level' => $year_level
]);


} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
