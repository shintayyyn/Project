<?php
require_once __DIR__ . '/../../../includes/db.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['t_id']) && is_numeric($_POST['t_id'])) {
    $teacher_id = $_POST['t_id'];

    // Check if teacher exists
    $check_teacher = $conn->prepare("SELECT t_id FROM teachers WHERE t_id = ?");
    $check_teacher->bind_param('i', $teacher_id);
    $check_teacher->execute();
    $check_teacher->store_result();
    if ($check_teacher->num_rows === 0) {
        $response['message'] = 'Teacher not found';
        echo json_encode($response);
        exit;
    }
    $check_teacher->close();

    $sql = "DELETE FROM teachers WHERE t_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teacher_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Teacher deleted successfully';
    } else {
        $response['message'] = 'Failed to delete teacher';
    }

    $stmt->close();
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
?>
