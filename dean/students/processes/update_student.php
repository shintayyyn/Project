<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_id = filter_var($_POST['s_id'], FILTER_VALIDATE_INT);
    $s_fname = $_POST['s_fname'] ?? '';
    $s_lname = $_POST['s_lname'] ?? '';
    $s_mname = $_POST['s_mname'] ?? '';
    $s_suffix = $_POST['s_suffix'] ?? '';
    $s_gender = $_POST['s_gender'] ?? '';
    $s_bdate = $_POST['s_bdate'] ?? null;
    $s_cnum = $_POST['s_cnum'] ?? '';
    $s_address = $_POST['s_address'] ?? '';
    $s_email = !empty($_POST['s_email']) ? filter_var($_POST['s_email'], FILTER_VALIDATE_EMAIL) : null;
    $s_status = $_POST['s_status'] ?? '';
    $degree_id = $_POST['degree_id'] ?? null;

    if (!$s_id) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid student ID']));
    }

    if ($s_email === false && !empty($_POST['s_email'])) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid email format']));
    }

    // Calculate age
    $age = null;
    if (!empty($s_bdate)) {
        $birthDate = new DateTime($s_bdate);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

    $conn->begin_transaction();

    try {
        // --- Update main student record with updated_at ---
        $stmt = $conn->prepare("
            UPDATE students SET 
                s_fname = ?, s_lname = ?, s_mname = NULLIF(?, ''), 
                s_suffix = NULLIF(?, ''), s_gender = ?, s_bdate = ?, 
                s_age = ?, s_cnum = NULLIF(?, ''), s_address = NULLIF(?, ''), 
                s_email = NULLIF(?, ''), s_status = ?, updated_at = NOW()
            WHERE s_id = ?
        ");
        $stmt->bind_param(
            "ssssssissssi",
            $s_fname, $s_lname, $s_mname, $s_suffix,
            $s_gender, $s_bdate, $age, $s_cnum, $s_address,
            $s_email, $s_status, $s_id
        );
        $stmt->execute();
        $stmt->close();

        // --- Handle degree ---
        if ($degree_id) {
            // Get degree code
            $deg_stmt = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id = ?");
            $deg_stmt->bind_param("i", $degree_id);
            $deg_stmt->execute();
            $deg_result = $deg_stmt->get_result();
            $deg_data = $deg_result->fetch_assoc();
            $degree_code = $deg_data['degree_code'] ?? null;
            $deg_stmt->close();

            if ($degree_code) {
                // Check if student has active degree
                $chk_stmt = $conn->prepare("
                    SELECT sd_id FROM students_degrees 
                    WHERE s_id = ? AND status = 'Active' 
                    ORDER BY enrollment_date DESC LIMIT 1
                ");
                $chk_stmt->bind_param("i", $s_id);
                $chk_stmt->execute();
                $res_chk = $chk_stmt->get_result();
                $existingDeg = $res_chk->fetch_assoc();
                $chk_stmt->close();

                if ($existingDeg) {
                    // Update existing with updated_at
                    $upd_stmt = $conn->prepare("
                        UPDATE students_degrees 
                        SET degree_id = ?, degree_code = ?, updated_at = NOW()
                        WHERE sd_id = ?
                    ");
                    $upd_stmt->bind_param("isi", $degree_id, $degree_code, $existingDeg['sd_id']);
                    $upd_stmt->execute();
                    $upd_stmt->close();
                } else {
                    // Insert new with updated_at
                    $ins_stmt = $conn->prepare("
                        INSERT INTO students_degrees (s_id, degree_id, degree_code, status, updated_at) 
                        VALUES (?, ?, ?, 'Active', NOW())
                    ");
                    $ins_stmt->bind_param("iis", $s_id, $degree_id, $degree_code);
                    $ins_stmt->execute();
                    $ins_stmt->close();
                }
            }
        }

        $conn->commit();

        // --- Fetch updated student ---
    $stmt2 = $conn->prepare("
            SELECT 
                s.*, sd.degree_code, ss.section_id, sec.section_code,
                CONCAT(
                    p.p_fname, ' ',
                    COALESCE(CONCAT(p.p_mname, ' '), ''),
                    p.p_lname,
                    COALESCE(CONCAT(' ', p.p_suffix), '')
                ) AS parent_fullname
            FROM students s
            LEFT JOIN (SELECT * FROM students_degrees WHERE status = 'Active') sd ON s.s_id = sd.s_id
            LEFT JOIN students_sections ss ON s.s_id = ss.s_id
            LEFT JOIN sections sec ON ss.section_id = sec.section_id
            LEFT JOIN parent_student ps ON s.s_id = ps.s_id
            LEFT JOIN parents p ON ps.p_id = p.p_id
            WHERE s.s_id = ? LIMIT 1
        ");
        $stmt2->bind_param("i", $s_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $student = $res2->fetch_assoc();
        $stmt2->close();

        $student['age'] = !empty($student['s_bdate']) ? (int)date_diff(date_create($student['s_bdate']), date_create('today'))->y : null;
        $student['parent_fullname'] = !empty($student['parent_fullname']) ? $student['parent_fullname'] : 'No record';

        echo json_encode(['status' => 'success', 'message' => 'Student updated successfully.', 'data' => $student]);
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
?>
