<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $term_id = intval($_POST['term_id']);

    if ($term_id > 0) {
        $conn->begin_transaction();

        try {
            // Step 1: Deactivate all previous terms
            $queryDeactivate = "UPDATE academic_terms SET is_active = 2, updated_at = NOW()";
            if (!$conn->query($queryDeactivate)) {
                throw new Exception('Failed to deactivate other terms.');
            }

            // Step 2: Activate the selected term
            $queryActivate = "UPDATE academic_terms SET is_active = 1, updated_at = NOW() WHERE term_id = ?";
            $stmt = $conn->prepare($queryActivate);
            $stmt->bind_param("i", $term_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to activate selected term.');
            }
            $stmt->close();

            // ✅ Step 2.1: Update sections_advisors
            // Deactivate all current advisors
            $conn->query("UPDATE sections_advisors SET is_active = 2 WHERE is_active = 1");

            // Activate only those that match the new term_id
            $stmt = $conn->prepare("
                UPDATE sections_advisors 
                SET is_active = 1 
                WHERE term_id = ?
            ");
            $stmt->bind_param("i", $term_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update section advisors.');
            }
            $stmt->close();

            // ✅ Step 2.2: Reactivate teachers who are advisors in the new term
            $stmt = $conn->prepare("
                UPDATE teachers t
                JOIN sections_advisors sa ON sa.t_id = t.t_id
                SET t.t_status = 'active'
                WHERE sa.term_id = ? AND sa.is_active = 1
            ");
            $stmt->bind_param("i", $term_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to reactivate teachers linked to new term advisors.');
            }
            $stmt->close();

            // Step 3: Set all other students and teachers to inactive
            $conn->query("UPDATE students SET s_status = 'inactive', enrollment_status = 'Not yet Enrolled' WHERE s_status = 'active'");
            $conn->query("UPDATE teachers SET t_status = 'inactive' WHERE t_status = 'active'");

            // Step 4: Inactivate parents linked to students
            $conn->query("
                UPDATE parents p
                JOIN parent_student ps ON ps.p_id = p.p_id
                JOIN students s ON s.s_id = ps.s_id
                SET p.p_status = 'inactive'
                WHERE p.p_status = 'active'
            ");

            // Step 5: Handle student promotions
            $students = $conn->query("SELECT s_id, year_level FROM students");
            while ($row = $students->fetch_assoc()) {
                $s_id = $row['s_id'];
                $current_year = $row['year_level'];

                // Get last section_code from students_sections
                $prev = $conn->query("
                    SELECT section_code 
                    FROM students_sections 
                    WHERE s_id = $s_id
                    ORDER BY updated_at DESC
                    LIMIT 1
                ")->fetch_assoc();

                $prev_section = $prev['section_code'] ?? '';

                // Extract number from section_code as previous year_level
                if (preg_match('/\d+/', $prev_section, $matches)) {
                    $prev_year = intval($matches[0]);
                } else {
                    $prev_year = 0;
                }

                // Determine enrollment_status
                if ($current_year > $prev_year) {
                    $enroll_status = "Promoted to Year $current_year";
                } else {
                    $enroll_status = "Not yet Enrolled";
                }

                $update = $conn->prepare("UPDATE students SET enrollment_status = ?, s_status = 'inactive' WHERE s_id = ?");
                $update->bind_param("si", $enroll_status, $s_id);
                $update->execute();
                $update->close();
            }

            $conn->commit();

            echo json_encode(['status' => 'success', 'message' => 'Term activated, advisors & teachers updated, and student/parent statuses refreshed.']);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit();
        }

    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid term ID provided.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit();
}
?>
