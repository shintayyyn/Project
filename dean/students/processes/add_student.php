<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../includes/db.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'dean') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $conn->begin_transaction();

    // -----------------------------
    // 1. Validate Required Fields
    // -----------------------------
    $required_fields = [
        's_fname', 's_lname', 's_gender', 's_bdate',
        's_cnum', 's_address', 's_email', 'degree_id'
    ];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Student " . ucfirst(str_replace('s_', '', $field)) . " is required");
        }
    }

    if (!filter_var($_POST['s_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid student email');
    }

    if (!empty($_POST['p_email']) && !filter_var($_POST['p_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid parent email');
    }

    // -----------------------------
    // 2. Assign Variables
    // -----------------------------
    $s_fname   = trim($_POST['s_fname']);
    $s_lname   = trim($_POST['s_lname']);
    $s_mname   = !empty($_POST['s_mname']) ? trim($_POST['s_mname']) : null;
    $s_suffix  = !empty($_POST['s_suffix']) ? trim($_POST['s_suffix']) : null;

    // Normalize gender to match ENUM ('Male','Female','Other')
    $s_gender_raw = strtolower(trim($_POST['s_gender'] ?? 'other'));
    if (in_array($s_gender_raw, ['male', 'm'])) {
        $s_gender = 'Male';
    } elseif (in_array($s_gender_raw, ['female', 'f'])) {
        $s_gender = 'Female';
    } else {
        $s_gender = 'Other';
    }

    $s_bdate   = $_POST['s_bdate'];
    $s_cnum    = $_POST['s_cnum'];
    $s_address = $_POST['s_address'];
    $s_email   = $_POST['s_email'];
    $s_status  = $_POST['s_status'] ?? 'inactive';
    $degree_id = intval($_POST['degree_id']);
    $year_level = intval($_POST['year_level'] ?? 1);

    // ✅ Solo handling
    $is_solo = intval($_POST['is_solo'] ?? 1); // 1 = Living with Parent, 2 = Solo
    $remarks = ($is_solo === 2) ? 'Solo (No Parent)' : 'Living with Parents/Guardians';

    // ✅ Regularity: 1 = Regular, 2 = Irregular
    $is_regular = (isset($_POST['is_irregular']) && $_POST['is_irregular'] == "2") ? 2 : 1;

    // ✅ Generate password
    $raw_password = $_POST['s_password'] ?? (
        strtolower(substr($s_fname, 0, 1)) .
        strtolower(str_replace(' ', '', $s_lname)) .
        date("mdY", strtotime($s_bdate))
    );
    $s_password = $raw_password;

    // -----------------------------
    // 3. Check Duplicate Student
    // -----------------------------
    $check_student = $conn->prepare("
        SELECT s_id 
        FROM students 
        WHERE s_fname=? AND s_lname=? AND s_mname<=>? AND s_suffix<=>? AND s_bdate=?
    ");
    $check_student->bind_param("sssss", $s_fname, $s_lname, $s_mname, $s_suffix, $s_bdate);
    $check_student->execute();
    $check_student->store_result();
    if ($check_student->num_rows > 0) {
        throw new Exception('Student is already enrolled.');
    }
    $check_student->close();

    // -----------------------------
    // 4. Enforce Unique Student Email
    // -----------------------------
    $check_email = $conn->prepare("SELECT s_id FROM students WHERE s_email=?");
    $check_email->bind_param("s", $s_email);
    $check_email->execute();
    $check_email->store_result();
    if ($check_email->num_rows > 0) {
        throw new Exception('Student email is already in use.');
    }
    $check_email->close();

    // -----------------------------
    // 5. Insert Student Record
    // -----------------------------
    $term_id = $conn->query("SELECT term_id FROM academic_terms WHERE is_active=1 LIMIT 1")
                    ->fetch_assoc()['term_id'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO students 
        (s_fname, s_lname, s_mname, s_suffix, s_gender, s_bdate, s_cnum, s_address, 
         s_email, s_status, s_password, year_level, is_regular, is_solo, remarks, term_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssssssiissi",
        $s_fname, $s_lname, $s_mname, $s_suffix,
        $s_gender, $s_bdate, $s_cnum, $s_address,
        $s_email, $s_status, $s_password,
        $year_level, $is_regular, $is_solo, $remarks, $term_id
    );
    $stmt->execute();
    $s_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Generate Student ID Code
    $year_stmt = $conn->prepare("SELECT YEAR(s_created_at) AS yr FROM students WHERE s_id=?");
    $year_stmt->bind_param("i", $s_id);
    $year_stmt->execute();
    $year = $year_stmt->get_result()->fetch_assoc()['yr'];
    $year_stmt->close();

    $student_idcode = "S{$year}" . str_pad($s_id, 4, "0", STR_PAD_LEFT);
    $upd = $conn->prepare("UPDATE students SET idcode=? WHERE s_id=?");
    $upd->bind_param("si", $student_idcode, $s_id);
    $upd->execute();
    $upd->close();

    // -----------------------------
    // 6. Insert Student Degree
    // -----------------------------
    $deg_stmt = $conn->prepare("SELECT degree_code FROM degrees WHERE degree_id=?");
    $deg_stmt->bind_param("i", $degree_id);
    $deg_stmt->execute();
    $degree_code = $deg_stmt->get_result()->fetch_assoc()['degree_code'] ?? '';
    $deg_stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO students_degrees 
        (s_id, degree_id, degree_code, s_fname, s_lname, s_mname, s_gender, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
    ");
    $stmt->bind_param("iisssss", $s_id, $degree_id, $degree_code, $s_fname, $s_lname, $s_mname, $s_gender);
    $stmt->execute();
    $stmt->close();

    // -----------------------------
    // 7. Handle Parent Information
    // -----------------------------
    $p_id = null;
    $parent_email = null;
    $parent_fullname = null;

    if ($is_solo === 2) {
        // ✅ Solo student: create dummy parent
        $p_fname   = '(No Parent)';
        $p_lname   = 'Solo';
        $p_mname   = '';
        $p_suffix  = '';
        $p_gender  = 'Other'; // Changed from 'N/A' to match ENUM
        $p_bdate   = $s_bdate;
        $p_cnum    = $s_cnum;
        $p_address = $s_address;
        $p_email   = "solo_" . uniqid('', true) . "@dummy.local";
        $p_status  = 'active';
        $p_password= $s_password;

        $stmt = $conn->prepare("
            INSERT INTO parents 
            (p_fname, p_lname, p_mname, p_suffix, p_gender, p_bdate, p_cnum, p_address, p_email, p_status, p_password)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssssssss",
            $p_fname, $p_lname, $p_mname, $p_suffix,
            $p_gender, $p_bdate, $p_cnum, $p_address,
            $p_email, $p_status, $p_password
        );
        $stmt->execute();
        $p_id = $stmt->insert_id;
        $stmt->close();

        $parent_fullname = 'Solo (No Parent)';
        $parent_email = $p_email;

    } elseif (!empty($_POST['existing_parent_id'])) {
        // ✅ Link to existing parent
        $p_id = intval($_POST['existing_parent_id']);
        $stmt = $conn->prepare("
            SELECT CONCAT(p_lname, ', ', p_fname, ' ', COALESCE(p_mname,'')) AS fullname, p_email 
            FROM parents WHERE p_id=? LIMIT 1
        ");
        $stmt->bind_param("i", $p_id);
        $stmt->execute();
        $stmt->bind_result($parent_fullname, $parent_email);
        $stmt->fetch();
        $stmt->close();

    } else {
        // ✅ Create new parent
        $p_fname  = trim($_POST['p_fname']);
        $p_lname  = trim($_POST['p_lname']);
        $p_mname  = !empty($_POST['p_mname']) ? trim($_POST['p_mname']) : '';
        $p_suffix = !empty($_POST['p_suffix']) ? trim($_POST['p_suffix']) : '';

        // Normalize gender to match ENUM ('Male','Female','Other')
        $p_gender_raw = strtolower(trim($_POST['p_gender'] ?? 'other'));
        if (in_array($p_gender_raw, ['male', 'm'])) {
            $p_gender = 'Male';
        } elseif (in_array($p_gender_raw, ['female', 'f'])) {
            $p_gender = 'Female';
        } else {
            $p_gender = 'Other';
        }

        $p_bdate  = $_POST['p_bdate'];
        $p_cnum   = $_POST['p_cnum'];
        $p_address= $_POST['p_address'];
        $p_email  = $_POST['p_email'];
        $p_status = $_POST['p_status'] ?? 'active';

        // Ensure parent email is unique
        $check_parent_email = $conn->prepare("SELECT p_id FROM parents WHERE p_email=?");
        $check_parent_email->bind_param("s", $p_email);
        $check_parent_email->execute();
        $check_parent_email->store_result();
        if ($check_parent_email->num_rows > 0) {
            throw new Exception('Parent email is already in use.');
        }
        $check_parent_email->close();

        $stmt = $conn->prepare("
            INSERT INTO parents 
            (p_fname, p_lname, p_mname, p_suffix, p_gender, p_bdate, p_cnum, p_address, p_email, p_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssssss",
            $p_fname, $p_lname, $p_mname, $p_suffix,
            $p_gender, $p_bdate, $p_cnum, $p_address, $p_email, $p_status
        );
        $stmt->execute();
        $p_id = $stmt->insert_id;
        $stmt->close();

        $parent_fullname = trim($p_lname . ', ' . $p_fname . ($p_mname ? ' ' . $p_mname : ''));
        $parent_email = $p_email;
    }

    // -----------------------------
    // 8. Generate Parent ID Code
    // -----------------------------
    $year_stmt = $conn->prepare("SELECT YEAR(p_created_at) AS yr FROM parents WHERE p_id=?");
    $year_stmt->bind_param("i", $p_id);
    $year_stmt->execute();
    $year = $year_stmt->get_result()->fetch_assoc()['yr'] ?? date('Y');
    $year_stmt->close();

    $parent_idcode = "P{$year}" . str_pad($p_id, 4, "0", STR_PAD_LEFT);
    $upd = $conn->prepare("UPDATE parents SET idcode=? WHERE p_id=?");
    $upd->bind_param("si", $parent_idcode, $p_id);
    $upd->execute();
    $upd->close();

    // -----------------------------
    // 9. Link Parent and Student
    // -----------------------------
    $link_check = $conn->prepare("SELECT COUNT(*) FROM parent_student WHERE p_id=? AND s_id=?");
    $link_check->bind_param("ii", $p_id, $s_id);
    $link_check->execute();
    $link_check->bind_result($link_count);
    $link_check->fetch();
    $link_check->close();

    if ($link_count == 0) {
        $link_stmt = $conn->prepare("INSERT INTO parent_student (p_id, s_id, term_id) VALUES (?, ?, ?)");
        $link_stmt->bind_param("iii", $p_id, $s_id, $term_id);
        $link_stmt->execute();
        $link_stmt->close();
    }

    // -----------------------------
    // Commit
    // -----------------------------
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $is_solo === 2 ? 'Student added successfully as Solo.' :
                    (!empty($_POST['existing_parent_id']) ? 'Student linked to existing parent.' : 'Student and parent added successfully.'),
        'data' => [
            's_id' => $s_id,
            'p_id' => $p_id,
            'idcode' => $student_idcode,
            's_fname' => $s_fname,
            's_mname' => $s_mname,
            's_lname' => $s_lname,
            's_suffix' => $s_suffix,
            's_gender' => $s_gender,
            's_bdate' => $s_bdate,
            's_age' => date('Y') - date('Y', strtotime($s_bdate)),
            's_cnum' => $s_cnum,
            's_address' => $s_address,
            's_email' => $s_email,
            's_status' => $s_status,
            'degree_id' => $degree_id,
            'degree_code' => $degree_code,
            'year_level' => $year_level,
            'is_regular' => $is_regular,
            'is_solo' => $is_solo,
            'remarks' => $remarks,
            'term_id' => $term_id,
            'parent_email' => $parent_email,
            'parent_fullname' => $parent_fullname,
            'plaintext_password' => $raw_password
        ]
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
