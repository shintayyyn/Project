<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

function getSubjects($conn) {
    // Get the active term_id first
    $active_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $active_term_id = $active_term['term_id'] ?? 0;

    $query = "
        SELECT 
            s.subject_id,
            s.subject_code,
            s.subject_description,
            s.units,
            s.degree_id,
            s.term_id,
            d.degree_code,
            d.degree_name,
            COALESCE(GROUP_CONCAT(CONCAT(t.t_fname, ' ', t.t_lname) SEPARATOR ', '), '') AS teachers,
            CASE
                WHEN at.term_id IS NOT NULL THEN
                    CONCAT('A.Y. ', ay.year_start, '-', ay.year_end, ' | ',
                        CASE
                            WHEN at.semester = 1 THEN '1st Semester'
                            WHEN at.semester = 2 THEN '2nd Semester'
                            WHEN at.semester = 3 THEN 'Summer'
                            ELSE at.semester
                        END
                    )
                ELSE NULL
            END AS term_label
        FROM subjects s
        LEFT JOIN degrees d ON s.degree_id = d.degree_id
        LEFT JOIN subjects_teachers st 
            ON s.subject_id = st.subject_id AND st.term_id = $active_term_id
        LEFT JOIN teachers t ON st.t_id = t.t_id
        LEFT JOIN academic_terms at ON s.term_id = at.term_id
        LEFT JOIN academic_years ay ON at.ay_id = ay.ay_id
        GROUP BY s.subject_id
        ORDER BY s.subject_code
    ";

    $result = $conn->query($query);
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    return $subjects;
}

function sendResponse($status, $message, $subjects = null) {
    ob_clean();
    $response = [
        'status' => $status,
        'message' => $message
    ];
    if ($subjects !== null) {
        $response['subjects'] = $subjects;
    }
    echo json_encode($response);
    exit;
}

try {
    // =======================
    // ADD NEW SUBJECT
    // =======================
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject_code = trim($_POST['subject_code']);
    $subject_description = trim($_POST['subject_description']);
    $units = (int)$_POST['units'];
   $degree_codes = isset($_POST['degree_code']) ? (array)$_POST['degree_code'] : [];

// Debugging
error_log("Received degree codes: " . print_r($degree_codes, true));
 // Expect array of degree codes

    // Validate
    if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6 || empty($degree_codes)) {
        sendResponse('error', 'Invalid input. Please check all fields and select at least one department.');
    }

    // ✅ Get current active term
    $active_term = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    $term_id = $active_term['term_id'] ?? null;

    // Prepare insert statement
    $insert_query = "INSERT INTO subjects (subject_code, subject_description, units, degree_id, term_id) 
                     VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);

    foreach ($degree_codes as $code) {
        // Get degree_id for this degree_code
        $degree_stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_code = ?");
        $degree_stmt->bind_param("s", $code);
        $degree_stmt->execute();
        $degree_result = $degree_stmt->get_result();
        $degree = $degree_result->fetch_assoc();

        if (!empty($degree['degree_id'])) {
            $degree_id = $degree['degree_id'];

            // ✅ Check if subject_code already exists for THIS degree_id
            $check_query = "
                SELECT COUNT(*) as count 
                FROM subjects 
                WHERE subject_code = ? AND degree_id = ?
            ";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("si", $subject_code, $degree_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $row = $check_result->fetch_assoc();

            if ($row['count'] > 0) {
                // Skip insertion if duplicate found for this degree
                continue;
            }

            // ✅ Insert subject
            $stmt->bind_param("ssiii", $subject_code, $subject_description, $units, $degree_id, $term_id);
            $stmt->execute();
        }
    }

    sendResponse('success', 'Subject added successfully.', getSubjects($conn));
}


    // =======================
    // EDIT SUBJECT
    // =======================
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $subject_id = (int)$_POST['subject_id'];
    $subject_code = trim($_POST['subject_code']);
    $subject_description = trim($_POST['subject_description']);
    $units = (int)$_POST['units'];
    $degree_id = (int)$_POST['degree_id']; // This comes from the subject being edited

    if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6) {
        sendResponse('error', 'Invalid input. Please check all fields.');
    }

    // Check for duplicate subject_code + degree_id (excluding current subject_id)
    $check_query = "
        SELECT COUNT(*) as count 
        FROM subjects 
        WHERE subject_code = ? AND degree_id = ? AND subject_id != ?
    ";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("sii", $subject_code, $degree_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        sendResponse('error', 'This subject code already exists for this degree.');
    }

    // Proceed with update
    $update_query = "UPDATE subjects 
                     SET subject_code = ?, subject_description = ?, units = ? 
                     WHERE subject_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssii", $subject_code, $subject_description, $units, $subject_id);

    if ($stmt->execute()) {
        sendResponse('success', 'Subject updated successfully.', getSubjects($conn));
    } else {
        sendResponse('error', 'Database error: ' . $stmt->error);
    }
}


    // =======================
    // GET TEACHERS ASSIGNED TO SUBJECT
    // =======================
    if (isset($_GET['action']) && $_GET['action'] === 'get_teachers') {
        $subject_id = (int)$_GET['subject_id'];

        $query = "SELECT t.t_id, CONCAT(t.t_lname, ', ', t.t_fname) as name 
                  FROM teachers t 
                  INNER JOIN subjects_teachers st ON t.t_id = st.t_id 
                  WHERE st.subject_id = ? 
                  ORDER BY t.t_lname, t.t_fname";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'teachers' => $teachers]);
        exit;
    }

    // =======================
    // DELETE SUBJECT
    // =======================
    if (isset($_POST['delete'])) {
        $subject_id = (int)$_POST['delete'];

        // Delete teacher assignments first
        $delete_assignments = "DELETE FROM subjects_teachers WHERE subject_id = ?";
        $stmt = $conn->prepare($delete_assignments);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        // Delete subject
        $delete_query = "DELETE FROM subjects WHERE subject_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $subject_id);

        if ($stmt->execute()) {
            sendResponse('success', 'Subject deleted successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Database error: ' . $stmt->error);
        }
    }

    sendResponse('error', 'Invalid request.');
} catch (Exception $e) {
    sendResponse('error', 'An error occurred: ' . $e->getMessage());
}
