<?php
require_once __DIR__ . '/../../includes/db.php';
session_start();

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Admin session not found';
    echo json_encode($response);
    exit;
}

$adminId = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id']) && is_numeric($_POST['archive_id'])) {
    $archiveId = intval($_POST['archive_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // 1️⃣ Fetch archive record
        $stmt = $conn->prepare("SELECT * FROM archives WHERE id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();
        $result = $stmt->get_result();
        $archive = $result->fetch_assoc();
        $stmt->close();

        if (!$archive) {
            throw new Exception('Archive record not found');
        }

        // 2️⃣ Decode JSON data
        $archiveData = json_decode($archive['data'], true);
        if (!$archiveData || !isset($archiveData['student'])) {
            throw new Exception('Invalid archive data');
        }

        $student = $archiveData['student'];

        // 3️⃣ Restore student
        $stmt = $conn->prepare("
            INSERT INTO students (idcode, s_fname, s_lname, s_email, s_status, is_deleted)
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->bind_param(
            "sssss",
            $student['idcode'],
            $student['s_fname'],
            $student['s_lname'],
            $student['s_email'],
            $student['s_status']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to restore student: ' . $stmt->error);
        }
        $newStudentId = $conn->insert_id;
        $stmt->close();

        // 4️⃣ Restore sections
        if (isset($archiveData['sections']) && is_array($archiveData['sections'])) {
            foreach ($archiveData['sections'] as $sectionCode) {
                // Get section_id
                $stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_code = ?");
                $stmt->bind_param("s", $sectionCode);
                $stmt->execute();
                $sec = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($sec) {
                    $stmt2 = $conn->prepare("INSERT INTO students_sections (s_id, section_id) VALUES (?, ?)");
                    $stmt2->bind_param("ii", $newStudentId, $sec['section_id']);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }

        // 5️⃣ Restore parent relationships
        if (isset($archiveData['parents']) && is_array($archiveData['parents'])) {
            foreach ($archiveData['parents'] as $parent) {
                $stmt = $conn->prepare("INSERT INTO parent_student (p_id, s_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $parent['p_id'], $newStudentId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // 6️⃣ Optionally delete archive after restore
        $stmt = $conn->prepare("DELETE FROM archives WHERE id = ?");
        $stmt->bind_param("i", $archiveId);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Student restored successfully';

    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
