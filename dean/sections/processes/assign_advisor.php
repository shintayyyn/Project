<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['section_id']) || !isset($data['advisor_id'])) {
        throw new Exception('Missing required fields.');
    }

    $section_id = (int)$data['section_id'];
    $advisor_id = (int)$data['advisor_id'];

    if ($section_id <= 0 || $advisor_id <= 0) {
        throw new Exception('Invalid Section or Advisor ID.');
    }

    // ------------------ Fetch active term ------------------
    $term_stmt = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
    if (!$term_stmt || !($term_row = $term_stmt->fetch_assoc())) {
        throw new Exception('No active academic term found.');
    }
    $term_id = (int)$term_row['term_id'];

    /**
     * 1. Check how many sections this advisor is already assigned to in the current term.
     *    Advisors can be assigned to a maximum of 2 sections per term.
     */
    $check_query = "SELECT COUNT(*) AS section_count FROM sections_advisors WHERE t_id = ? AND term_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $advisor_id, $term_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $advisor_sections = (int)$check_result->fetch_assoc()['section_count'];
    $check_stmt->close();

    if ($advisor_sections >= 2) {
        throw new Exception('This advisor is already assigned to 2 sections in the current term.');
    }

    /**
     * 2. Remove any existing advisor from this section in the current term
     *    - A section can only have ONE advisor per term.
     */
    $delete_query = "DELETE FROM sections_advisors WHERE section_id = ? AND term_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $section_id, $term_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    /**
     * 3. Insert the new advisor assignment for the current term
     */
    $insert_query = "INSERT INTO sections_advisors (section_id, t_id, term_id) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iii", $section_id, $advisor_id, $term_id);

    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to assign advisor to this section.');
    }
    $insert_stmt->close();

    /**
     * 4. Fetch advisor details to return as response
     */
    $advisor_query = "SELECT t_id, t_lname, t_fname, t_mname FROM teachers WHERE t_id = ?";
    $advisor_stmt = $conn->prepare($advisor_query);
    $advisor_stmt->bind_param("i", $advisor_id);
    $advisor_stmt->execute();
    $result = $advisor_stmt->get_result();
    $advisor = $result->fetch_assoc();
    $advisor_stmt->close();

    if (!$advisor) {
        throw new Exception('Advisor details could not be retrieved.');
    }

    // Format advisor full name
    $advisor_name = $advisor['t_lname'] . ', ' . $advisor['t_fname'];
    if (!empty($advisor['t_mname'])) {
        $advisor_name .= ' ' . substr($advisor['t_mname'], 0, 1) . '.';
    }

    /**
     * 5. Return success response
     */
    echo json_encode([
        'success' => true,
        'message' => 'Advisor assigned successfully for the current term.',
        'advisor_name' => $advisor_name,
        't_id' => $advisor_id,
        'assigned_sections' => $advisor_sections + 1,
        'term_id' => $term_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
