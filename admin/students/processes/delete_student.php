<?php
require_once __DIR__ . '/../../../includes/db.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['s_id']) && is_numeric($_POST['s_id'])) {
    $student_id = $_POST['s_id'];

    // Check if student exists
    $check_student = $conn->prepare("SELECT s_id FROM students WHERE s_id = ?");
    $check_student->bind_param('i', $student_id);
    $check_student->execute();
    $check_student->store_result();
    if ($check_student->num_rows === 0) {
        $response['message'] = 'Student not found';
        echo json_encode($response);
        exit;
    }
    $check_student->close();

    $sql = "DELETE FROM students WHERE s_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $student_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Student deleted successfully';
    } else {
        $response['message'] = 'Failed to delete student';
    }

    $stmt->close();
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
?>
