<?php
require_once '../../../includes/db.php';
header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['student_id']) || !isset($data['new_section_id'])) {
        throw new Exception('Missing required fields');
    }

    $student_id = (int)$data['student_id'];
    $new_section_id = (int)$data['new_section_id'];
    
    // Start transaction
    $conn->begin_transaction();

    /**
     * 1. Get the student's current section and year level
     */
    $stmt = $conn->prepare("
        SELECT ss.section_id, ss.section_code, s.year_level
        FROM students_sections ss
        INNER JOIN sections s ON ss.section_id = s.section_id
        WHERE ss.s_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $stmt->bind_result($current_section_id, $current_section_code, $current_year_level);

    if (!$stmt->fetch()) {
        throw new Exception('Student not found in any section');
    }
    $stmt->close();

    /**
     * 2. If the current section is the same as the new one, no changes should be made
     */
    if ($current_section_id === $new_section_id) {
        throw new Exception('No changes made. The student is already in this section.');
    }

    /**
     * 3. Get the new section_code and year level
     */
    $stmt = $conn->prepare("SELECT section_code, year_level FROM sections WHERE section_id = ?");
    $stmt->bind_param('i', $new_section_id);
    $stmt->execute();
    $stmt->bind_result($new_section_code, $new_year_level);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid section_id provided');
    }
    $stmt->close();

    /**
     * 4. Check if year levels match
     */
    if ($current_year_level !== $new_year_level) {
        throw new Exception("Transfer denied. Student belongs to Year Level {$current_year_level}, 
            but the target section is Year Level {$new_year_level}.");
    }

    /**
     * 5. Update the student's section
     */
    $update_query = "
        UPDATE students_sections 
        SET section_id = ?, section_code = ?, updated_at = NOW()
        WHERE s_id = ?
    ";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('isi', $new_section_id, $new_section_code, $student_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update student section');
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made. Update did not affect any rows.');
    }
    $stmt->close();


    /**
     * ✅ 6. Update or Create QR Code Record
     * Keep QR section synced with student's new section
     */
    $checkQR = $conn->prepare("SELECT id FROM generatedqrcode WHERE id = ?");
    $checkQR->bind_param('i', $student_id);
    $checkQR->execute();
    $checkQR->store_result();

    if ($checkQR->num_rows > 0) {
        $checkQR->close();

        $updateQR = $conn->prepare("
            UPDATE generatedqrcode 
            SET section = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateQR->bind_param('si', $new_section_code, $student_id);
        $updateQR->execute();
        $updateQR->close();
    } else {
        $checkQR->close();

        // Auto-create QR if missing
        $fetchName = $conn->prepare("SELECT CONCAT(s_fname, ' ', s_lname) AS full_name FROM students WHERE s_id = ?");
        $fetchName->bind_param('i', $student_id);
        $fetchName->execute();
        $fetchName->bind_result($full_name);
        $fetchName->fetch();
        $fetchName->close();

        $randomHash = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $qrCodeText = "STUDENT-{$student_id}-{$randomHash}";

        $insertQR = $conn->prepare("
            INSERT INTO generatedqrcode (id, full_name, section, generated_qrcode, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $insertQR->bind_param('isss', $student_id, $full_name, $new_section_code, $qrCodeText);
        $insertQR->execute();
        $insertQR->close();
    }

    /**
     * 7. Commit transaction
     */
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student transferred successfully and QR code section updated.',
        'section_code' => $new_section_code,
        'old_section_id' => $current_section_id,
        'new_section_id' => $new_section_id
    ]);

} catch (Exception $e) {
    // Rollback if an error occurs during transaction
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
