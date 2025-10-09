
<?php
require_once __DIR__ . '/../../../includes/db.php';

header('Content-Type: application/json');

try {
    $stats_query = "SELECT s.section_code, COUNT(ss.s_id) as student_count 
                    FROM sections s 
                    LEFT JOIN students_sections ss ON s.section_code = ss.section_code 
                    GROUP BY s.section_code";
    $result = $conn->query($stats_query);
    
    $stats = [];
    while($row = $result->fetch_assoc()) {
        $stats[] = [
            'section_code' => $row['section_code'],
            'student_count' => $row['student_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}