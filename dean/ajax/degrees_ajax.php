<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$action = $_POST['action'] ?? '';
$response = ['success' => false];

try {
    // ✅ Fetch the active term ID
    $term_stmt = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
    $active_term_id = ($term_stmt && $term_stmt->num_rows > 0) ? $term_stmt->fetch_assoc()['term_id'] : null;

    switch ($action) {

        // =====================
        // ADD DEGREE
        // =====================
        case 'add':
            $degree_code = trim($_POST['degree_code'] ?? '');
            $degree_name = trim($_POST['degree_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $dean_id = !empty($_POST['dean_id']) ? intval($_POST['dean_id']) : null;

            if (empty($degree_code) || empty($degree_name)) {
                throw new Exception('Degree code and name are required');
            }

            // Check if degree code already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM degrees WHERE degree_code = ?");
            $check_stmt->bind_param("s", $degree_code);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();

            if ($count > 0) {
                throw new Exception('Degree code already exists');
            }

            // Insert new degree (include term_id)
            $stmt = $conn->prepare("
                INSERT INTO degrees (degree_code, degree_name, description, dean_id, term_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $degree_code, $degree_name, $description, $dean_id, $active_term_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $response = [
                'success' => true,
                'message' => 'Degree added successfully',
                'degree' => [
                    'degree_code' => $degree_code,
                    'degree_name' => $degree_name,
                    'description' => $description,
                    'dean_id' => $dean_id,
                    'term_id' => $active_term_id
                ]
            ];
            break;

        // =====================
        // EDIT DEGREE
        // =====================
        case 'edit':
            $degree_code = $_POST['degree_code'] ?? '';
            $degree_name = $_POST['degree_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $original_code = $_POST['original_code'] ?? '';
            $new_dean_id = !empty($_POST['dean_id']) ? intval($_POST['dean_id']) : null;

            if (empty($degree_code) || empty($degree_name) || empty($original_code)) {
                throw new Exception('Required fields are missing');
            }

            // Check for duplicate degree code
            if ($degree_code !== $original_code) {
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM degrees WHERE degree_code = ?");
                $check_stmt->bind_param("s", $degree_code);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();

                if ($count > 0) {
                    throw new Exception('New degree code already exists');
                }
            }

            // Fetch previous dean_id
            $stmt_old = $conn->prepare("SELECT dean_id FROM degrees WHERE degree_code = ?");
            $stmt_old->bind_param("s", $original_code);
            $stmt_old->execute();
            $stmt_old->bind_result($old_dean_id);
            $stmt_old->fetch();
            $stmt_old->close();

            $conn->begin_transaction();

            // Update degree (include updated_at)
            $stmt = $conn->prepare("
                UPDATE degrees 
                SET degree_code = ?, degree_name = ?, description = ?, dean_id = ?, term_id = ?, updated_at = NOW()
                WHERE degree_code = ?
            ");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssiss", $degree_code, $degree_name, $description, $new_dean_id, $active_term_id, $original_code);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            // Update dean flags
            if (!empty($old_dean_id) && $old_dean_id !== $new_dean_id) {
                $stmt_reset = $conn->prepare("UPDATE teachers SET is_dean = 0 WHERE t_id = ?");
                $stmt_reset->bind_param("i", $old_dean_id);
                $stmt_reset->execute();
                $stmt_reset->close();
            }

            if (!empty($new_dean_id)) {
                $stmt_set = $conn->prepare("UPDATE teachers SET is_dean = 1 WHERE t_id = ?");
                $stmt_set->bind_param("i", $new_dean_id);
                $stmt_set->execute();
                $stmt_set->close();
            }

            $conn->commit();

            $response = [
                'success' => true,
                'message' => 'Degree updated successfully',
                'degree' => [
                    'degree_code' => $degree_code,
                    'degree_name' => $degree_name,
                    'description' => $description,
                    'dean_id' => $new_dean_id,
                    'term_id' => $active_term_id
                ]
            ];
            break;

        // =====================
        // DELETE DEGREE
        // =====================
        case 'delete':
            $degree_code = $_POST['degree_code'] ?? '';

            if (empty($degree_code)) {
                throw new Exception('Degree code is required');
            }

            $conn->begin_transaction();

            // Prevent deletion if degree has sections
            $stmt = $conn->prepare("SELECT COUNT(*) FROM degrees_sections WHERE degree_code = ?");
            $stmt->bind_param("s", $degree_code);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                throw new Exception('Cannot delete degree with existing sections');
            }

            // Delete the degree
            $stmt = $conn->prepare("DELETE FROM degrees WHERE degree_code = ?");
            $stmt->bind_param("s", $degree_code);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $conn->commit();

            $response = [
                'success' => true,
                'message' => 'Degree deleted successfully'
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
