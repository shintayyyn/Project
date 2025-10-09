<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $t_id = $_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE t_id = ?");
        $stmt->bind_param("i", $t_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $teacher]);
        } else {
            throw new Exception('Teacher not found');
        }

        $stmt->close();
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>