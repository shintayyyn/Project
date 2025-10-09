<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json');

/**
 * Fetch all subjects with degree info and assigned teachers
 */
function getSubjects($conn) {
    $subjects = [];
    $query = "SELECT 
        s.*, 
        d.degree_code, 
        d.degree_name,
        GROUP_CONCAT(CONCAT(t.t_fname, ' ', t.t_lname) SEPARATOR ', ') as teachers 
    FROM subjects s 
    LEFT JOIN degrees d ON s.degree_id = d.degree_id
    LEFT JOIN subjects_teachers st ON s.subject_id = st.subject_id 
    LEFT JOIN teachers t ON st.t_id = t.t_id 
    GROUP BY s.subject_id 
    ORDER BY s.subject_code";

    if ($result = $conn->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    return $subjects;
}

/**
 * Standard JSON response helper
 */
function sendResponse($status, $message, $subjects = null) {
    ob_clean();
    $response = ['status' => $status, 'message' => $message];
    if ($subjects !== null) $response['subjects'] = $subjects;
    echo json_encode($response);
    exit;
}

try {
    // ================== Add Subject ==================
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_description = trim($_POST['subject_description'] ?? '');
        $units = (int)($_POST['units'] ?? 0);
        $degree_code = trim($_POST['degree_code'] ?? '');
        $degree_id = null;

        if ($degree_code) {
            $stmt = $conn->prepare("SELECT degree_id FROM degrees WHERE degree_code = ?");
            if (!$stmt) sendResponse('error', 'Failed to fetch degree.');
            $stmt->bind_param("s", $degree_code);
            $stmt->execute();
            $degree = $stmt->get_result()->fetch_assoc();
            $degree_id = $degree['degree_id'] ?? null;
        }

        if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6) {
            sendResponse('error', 'Invalid input. Please check all fields.');
        }

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE subject_code = ?");
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row['count'] > 0) sendResponse('error', 'Subject code already exists.');

        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_description, units, degree_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $subject_code, $subject_description, $units, $degree_id);
        if ($stmt->execute()) {
            sendResponse('success', 'Subject added successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Failed to add subject. Please try again.');
        }
    }

    // ================== Edit Subject ==================
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $subject_code = trim($_POST['subject_code'] ?? '');
        $subject_description = trim($_POST['subject_description'] ?? '');
        $units = (int)($_POST['units'] ?? 0);

        if (empty($subject_code) || empty($subject_description) || $units < 1 || $units > 6) {
            sendResponse('error', 'Invalid input. Please check all fields.');
        }

        // Check if subject has related attendance records
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM attendance WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row['count'] > 0) {
            sendResponse('error', 'Cannot update subject because it has attendance records.');
        }

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE subject_code = ? AND subject_id != ?");
        $stmt->bind_param("si", $subject_code, $subject_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row['count'] > 0) sendResponse('error', 'Subject code already exists.');

        $stmt = $conn->prepare("UPDATE subjects SET subject_code = ?, subject_description = ?, units = ? WHERE subject_id = ?");
        $stmt->bind_param("ssii", $subject_code, $subject_description, $units, $subject_id);
        if ($stmt->execute()) {
            sendResponse('success', 'Subject updated successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Failed to update subject. Please try again.');
        }
    }

    // ================== Delete Subject ==================
    if (isset($_POST['delete'])) {
        $subject_id = (int)$_POST['delete'];

        // Check if subject has attendance records
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM attendance WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row['count'] > 0) {
            sendResponse('error', 'Cannot delete subject because it has attendance records.');
        }

        // Remove assigned teachers first
        $stmt = $conn->prepare("DELETE FROM subjects_teachers WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->bind_param("i", $subject_id);
        if ($stmt->execute()) {
            sendResponse('success', 'Subject deleted successfully.', getSubjects($conn));
        } else {
            sendResponse('error', 'Failed to delete subject. Please try again.');
        }
    }

    // ================== Assign/Unassign Teachers, Get Teachers ==================
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_teacher':
                $subject_id = (int)($_POST['subject_id'] ?? 0);
                $subject_code = trim($_POST['subject_code'] ?? '');
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);

                $stmt = $conn->prepare("SELECT t_fname, t_lname, t_mname FROM teachers WHERE t_id = ?");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $teacher = $stmt->get_result()->fetch_assoc();
                if (!$teacher) sendResponse('error', 'Invalid teacher selected.');

                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects_teachers WHERE subject_id = ? AND t_id = ?");
                $stmt->bind_param("ii", $subject_id, $teacher_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row['count'] > 0) sendResponse('error', 'Teacher is already assigned.');

                $stmt = $conn->prepare("INSERT INTO subjects_teachers (subject_id, subject_code, t_id, t_fname, t_lname, t_mname) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isisss", $subject_id, $subject_code, $teacher_id, $teacher['t_fname'], $teacher['t_lname'], $teacher['t_mname']);
                if ($stmt->execute()) sendResponse('success', 'Teacher assigned successfully.', getSubjects($conn));
                else sendResponse('error', 'Failed to assign teacher. Please try again.');
                break;

            case 'unassign_teacher':
                $subject_id = (int)($_POST['subject_id'] ?? 0);
                $teacher_id = (int)($_POST['teacher_id'] ?? 0);

                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects_teachers WHERE subject_id = ? AND t_id = ?");
                $stmt->bind_param("ii", $subject_id, $teacher_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row['count'] === 0) sendResponse('error', 'Teacher is not assigned.');

                $stmt = $conn->prepare("DELETE FROM subjects_teachers WHERE subject_id = ? AND t_id = ?");
                $stmt->bind_param("ii", $subject_id, $teacher_id);
                if ($stmt->execute()) sendResponse('success', 'Teacher unassigned successfully.', getSubjects($conn));
                else sendResponse('error', 'Failed to unassign teacher. Please try again.');
                break;
        }
    }

    // ================== Get Teachers via GET ==================
    if (isset($_GET['action']) && $_GET['action'] === 'get_teachers') {
        $subject_id = (int)($_GET['subject_id'] ?? 0);
        $stmt = $conn->prepare("SELECT t.t_id, CONCAT(t.t_lname, ', ', t.t_fname) as name FROM teachers t INNER JOIN subjects_teachers st ON t.t_id = st.t_id WHERE st.subject_id = ? ORDER BY t.t_lname, t.t_fname");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $teachers = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $teachers[] = $row;
        sendResponse('success', 'Teachers retrieved.', $teachers);
    }

    sendResponse('error', 'Invalid request.');
} catch (Exception $e) {
    sendResponse('error', 'An unexpected error occurred. Please try again.');
}

ob_clean();
echo json_encode($response);
exit;
