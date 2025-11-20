<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

try {
    if (empty($_POST['t_id'])) {
        throw new Exception('Teacher ID is required');
    }

    $teacher_id = filter_var($_POST['t_id'], FILTER_VALIDATE_INT);
    if ($teacher_id === false) {
        throw new Exception('Invalid teacher ID format');
    }

    $stmt = $conn->prepare("DELETE FROM teachers WHERE t_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("i", $teacher_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete teacher: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No teacher found with the given ID');
    }

    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Teacher deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
