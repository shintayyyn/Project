<?php
require_once __DIR__ . '/../../includes/db.php';
session_start();

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['dean_id'])) {
    $response['message'] = 'dean session not found';
    echo json_encode($response);
    exit;
}

$deanId = $_SESSION['dean_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['s_id']) && is_numeric($_POST['s_id'])) {
    $studentId = intval($_POST['s_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // 1️⃣ Fetch student data
        $stmt = $conn->prepare("SELECT * FROM students WHERE s_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentResult = $stmt->get_result();
        $student = $studentResult->fetch_assoc();
        $stmt->close();

        if (!$student) {
            throw new Exception('Student not found or already deleted');
        }

        // 2️⃣ Fetch related sections
        $sections = [];
        $stmt = $conn->prepare("SELECT sec.section_code FROM students_sections ss 
                                JOIN sections sec ON ss.section_id = sec.section_id
                                WHERE ss.s_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $secResult = $stmt->get_result();
        while ($row = $secResult->fetch_assoc()) {
            $sections[] = $row['section_code'];
        }
        $stmt->close();

        // 3️⃣ Fetch related parent(s)
        $parents = [];
        $stmt = $conn->prepare("SELECT p.* FROM parent_student ps 
                                JOIN parents p ON ps.p_id = p.p_id
                                WHERE ps.s_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $parentResult = $stmt->get_result();
        while ($row = $parentResult->fetch_assoc()) {
            $parents[] = $row;
        }
        $stmt->close();

        // 4️⃣ Prepare archive JSON
        $archiveData = [
            'student'  => $student,
            'sections' => $sections,
            'parents'  => $parents
        ];
        $dataJson = json_encode($archiveData, JSON_UNESCAPED_UNICODE);

        // 5️⃣ Insert into archives
        $archiveStmt = $conn->prepare("INSERT INTO archives (table_name, record_id, data, deleted_by) VALUES (?, ?, ?, ?)");
        $tableName = 'students';
        $archiveStmt->bind_param("sisi", $tableName, $studentId, $dataJson, $deanId);

        if (!$archiveStmt->execute()) {
            throw new Exception('Failed to archive student: ' . $archiveStmt->error);
        }
        $archiveStmt->close();

        // 6️⃣ Soft delete student
        $deleteStmt = $conn->prepare("UPDATE students SET is_deleted = 1 WHERE s_id = ?");
        $deleteStmt->bind_param("i", $studentId);
        if (!$deleteStmt->execute()) {
            throw new Exception('Failed to delete student: ' . $deleteStmt->error);
        }
        $deleteStmt->close();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Student archived and deleted successfully';

    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        $response['message'] = $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
