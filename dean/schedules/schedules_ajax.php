<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_subject_teachers':
            $subject_code = $_POST['subject_code'] ?? '';
            
            if (empty($subject_code)) {
                echo json_encode(['error' => 'Subject code is required']);
                exit;
            }

            try {
                // Get teachers assigned to the subject
                $query = "SELECT DISTINCT 
                            t.t_id,
                            CONCAT(t.t_lname, ', ', t.t_fname, ' ', COALESCE(LEFT(t.t_mname, 1), ''), '.') as teacher_name
                         FROM subjects s
                         LEFT JOIN subjects_teachers st ON s.subject_code = st.subject_code
                         LEFT JOIN teachers t ON st.t_id = t.t_id
                         WHERE s.subject_code = ?";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('s', $subject_code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $teachers = [];
                while ($row = $result->fetch_assoc()) {
                    if ($row['t_id'] !== null) {
                        $teachers[] = $row;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'teachers' => $teachers,
                    'hasTeachers' => count($teachers) > 0
                ]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Failed to fetch teachers: ' . $e->getMessage()]);
            }
            break;
            
        // Add other cases for schedule operations here
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
