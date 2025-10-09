<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

function getSubjects($conn) {
    $query = "SELECT s.*, GROUP_CONCAT(CONCAT(t.t_fname, ' ', t.t_lname) SEPARATOR ', ') as teachers 
              FROM subjects s 
              LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id 
              LEFT JOIN teachers t ON st.t_id = t.t_id 
              GROUP BY s.subject_id 
              ORDER BY s.subject_code";
    $result = $conn->query($query);
    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    return $subjects;
}

function sendResponse($status, $message, $subjects = null) {
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
    // Add new subject
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $subject_code = trim($_POST['subject_code']);
        $subject_description = trim($_POST['subject_description']);
        $units = (int)$_POST['units'];

        // Validate input
        if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6) {
            sendResponse('error', 'Invalid input. Please check all fields.');
        }

        // Check for duplicate subject code
        $check_query = "SELECT COUNT(*) as count FROM subjects WHERE subject_code = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            sendResponse('error', 'Subject code already exists.');
        }

        // Insert new subject
        $insert_query = "INSERT INTO subjects (subject_code, subject_description, units) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssi", $subject_code, $subject_description, $units);
        
        if ($stmt->execute()) {
            sendResponse('success', 'Subject added successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Database error: ' . $stmt->error);
        }
    }

    // Edit subject
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $subject_id = (int)$_POST['subject_id'];
        $subject_code = trim($_POST['subject_code']);
        $subject_description = trim($_POST['subject_description']);
        $units = (int)$_POST['units'];

        // Validate input
        if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6) {
            sendResponse('error', 'Invalid input. Please check all fields.');
        }

        // Check for duplicate subject code (excluding current subject)
        $check_query = "SELECT COUNT(*) as count FROM subjects WHERE subject_code = ? AND subject_id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("si", $subject_code, $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            sendResponse('error', 'Subject code already exists.');
        }

        // Update subject
        $update_query = "UPDATE subjects SET subject_code = ?, subject_description = ?, units = ? WHERE subject_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssii", $subject_code, $subject_description, $units, $subject_id);
        
        if ($stmt->execute()) {
            sendResponse('success', 'Subject updated successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Database error: ' . $stmt->error);
        }
    }

    // Assign teacher to subject
    if (isset($_POST['action']) && $_POST['action'] === 'assign_teacher') {
        $subject_id = (int)$_POST['subject_id'];
        $subject_code = trim($_POST['subject_code']);
        $teacher_id = (int)$_POST['teacher_id'];

        // Get teacher details
        $teacher_query = "SELECT t_fname, t_lname, t_mname FROM teachers WHERE t_id = ?";
        $stmt = $conn->prepare($teacher_query);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $teacher = $stmt->get_result()->fetch_assoc();

        if (!$teacher) {
            sendResponse('error', 'Invalid teacher selected.');
        }

        // Check if assignment already exists
        $check_query = "SELECT COUNT(*) as count FROM subjects_teachers WHERE subject_id = ? AND t_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            sendResponse('error', 'Teacher is already assigned to this subject.');
        }

        // Insert assignment
        $insert_query = "INSERT INTO subjects_teachers (subject_id, subject_code, t_id, t_fname, t_lname, t_mname) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isisss", $subject_id, $subject_code, $teacher_id, 
                         $teacher['t_fname'], $teacher['t_lname'], $teacher['t_mname']);
        
        if ($stmt->execute()) {
            sendResponse('success', 'Teacher assigned successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Database error: ' . $stmt->error);
        }
    }

    // Get teachers for a subject
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
        
        echo json_encode(['status' => 'success', 'teachers' => $teachers]);
        exit;
    }

    // Unassign teacher from subject
    if (isset($_POST['action']) && $_POST['action'] === 'unassign_teacher') {
        $subject_id = (int)$_POST['subject_id'];
        $teacher_id = (int)$_POST['teacher_id'];

        // Check if the assignment exists
        $check_query = "SELECT COUNT(*) as count FROM subjects_teachers WHERE subject_id = ? AND t_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] === 0) {
            sendResponse('error', 'Teacher is not assigned to this subject.');
        }

        // Delete the assignment
        $delete_query = "DELETE FROM subjects_teachers WHERE subject_id = ? AND t_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        
        if ($stmt->execute()) {
            sendResponse('success', 'Teacher unassigned successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Failed to unassign teacher: ' . $stmt->error);
        }
    }

    // Delete subject
    if (isset($_POST['delete'])) {
        $subject_id = (int)$_POST['delete'];

        // First delete from subjects_teachers
        $delete_assignments = "DELETE FROM subjects_teachers WHERE subject_id = ?";
        $stmt = $conn->prepare($delete_assignments);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        // Then delete the subject
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
