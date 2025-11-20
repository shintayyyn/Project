<?php
require_once __DIR__ . '/../../../includes/db.php';
header('Content-Type: application/json');

try {
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

    if ($degree_id <= 0) throw new Exception('Invalid degree selected.');

    function formatInputName($input) {
        return ($input === strtolower($input)) ? ucwords($input) : $input;
    }

    $section_name = formatInputName($section_name);
    $section_code = formatInputName($section_code_raw);

    if (!preg_match('/^[A-Za-z0-9\- ]+$/', $section_code)) {
        throw new Exception('Invalid section code. Use letters, numbers, spaces, and hyphens only.');
    }

    $year_level = 0;
    if (preg_match('/\b([1-4])[A-Z]\s*$/i', $section_code, $matches)) {
        $year_level = (int)$matches[1];
    }

    $degree_stmt = $conn->prepare("SELECT degree_id, degree_name, degree_code FROM degrees WHERE degree_id = ?");
    $degree_stmt->bind_param("i", $degree_id);
    $degree_stmt->execute();
    $degree_result = $degree_stmt->get_result();
    if ($degree_result->num_rows === 0) throw new Exception('Selected degree does not exist.');
    $degree_data = $degree_result->fetch_assoc();
    $degree_stmt->close();

    $check_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_code = ?");
    $check_stmt->bind_param("s", $section_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) throw new Exception('Section code already exists.');
    $check_stmt->close();

    // ------------------ Fetch active term and academic year ------------------
    $term_stmt = $conn->query("
        SELECT at.term_id, at.semester, ay.year_start, ay.year_end
        FROM academic_terms at
        LEFT JOIN academic_years ay ON at.ay_id = ay.ay_id
        WHERE at.is_active = 1
        LIMIT 1
    ");
    if (!$term_stmt || !($term_row = $term_stmt->fetch_assoc())) {
        throw new Exception('Cannot add section: no active term found.');
    }
    $term_id = (int)$term_row['term_id'];
    $semester = $term_row['semester'];
    $year_start = $term_row['year_start'];
    $year_end = $term_row['year_end'];

    $term_display = (!empty($year_start) && !empty($year_end) && !empty($semester))
        ? "A.Y. $year_start-$year_end | $semester"
        : '-';

    $stmt = $conn->prepare("
        INSERT INTO sections (degree_id, section_code, section_name, year_level, max_students, term_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issiii", $degree_id, $section_code, $section_name, $year_level, $max_students, $term_id);
    if (!$stmt->execute()) throw new Exception('Failed to add section: ' . $stmt->error);
    $section_id = $conn->insert_id;

    $check_ds = $conn->prepare("
        SELECT * FROM degrees_sections 
        WHERE degree_id = ? AND section_id = ? AND term_id = ? AND semester = ?
    ");
    $check_ds->bind_param("iiis", $degree_id, $section_id, $term_id, $semester);
    $check_ds->execute();
    $ds_result = $check_ds->get_result();

    if ($ds_result->num_rows === 0) {
        $ds_stmt = $conn->prepare("
            INSERT INTO degrees_sections 
            (degree_id, section_id, term_id, semester, degree_code, degree_name, section_code, section_name, year_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ds_stmt->bind_param(
            "iiisssssi",
            $degree_id,
            $section_id,
            $term_id,
            $semester,
            $degree_data['degree_code'],
            $degree_data['degree_name'],
            $section_code,
            $section_name,
            $year_level
        );
        if (!$ds_stmt->execute()) {
            throw new Exception('Failed to insert into degrees_sections: ' . $ds_stmt->error);
        }
        $ds_stmt->close();
    }
    $check_ds->close();

    echo json_encode([
        'success' => true,
        'message' => 'Section added successfully',
        'section_id' => $section_id,
        'degree_id' => $degree_id,
        'formatted_section_code' => $section_code,
        'formatted_section_name' => $section_name,
        'max_students' => $max_students,
        'year_level' => $year_level,
        'term_id' => $term_id,
        'semester' => $semester,
        'term_display' => $term_display,
        'degree_name' => $degree_data['degree_name'],
        'degree_code' => $degree_data['degree_code']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
