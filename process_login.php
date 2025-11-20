<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();

require_once 'includes/db.php';
ob_clean();

// Allow cross-origin requests if needed
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request method']));
}

  // 🔹 Get current active term_id
        $term_query = $conn->query("SELECT term_id FROM academic_terms WHERE is_active = 1 LIMIT 1");
        $term = $term_query->fetch_assoc();
        $current_term_id = $term ? (int)$term['term_id'] : 0;

$login_id     = trim($_POST['login_id'] ?? '');
$password     = $_POST['password'] ?? '';
$device_token = $_POST['device_token'] ?? ''; // ✅ Device identifier

if (empty($login_id) || empty($password)) {
    echo json_encode(['error' => 'Please fill in all fields']);
    exit();
}

// Normalize device token
$device_token = trim(strtolower($device_token));

// Detect device type
$userAgent   = strtolower($_SERVER['HTTP_USER_AGENT']);
$device_type = 'Laptop/Desktop';
if (preg_match('/mobile/i', $userAgent)) $device_type = 'Phone';
elseif (preg_match('/tablet/i', $userAgent)) $device_type = 'Tablet';

try {

    // ================= ADMIN LOGIN =================
    if (
        strtolower($login_id) === 'admin' || 
        (
            strtoupper(substr($login_id, 0, 1)) === 'A' &&
            !filter_var($login_id, FILTER_VALIDATE_EMAIL)
        )
    ) {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE idcode = ? OR username = ? LIMIT 1");
        $stmt->bind_param("ss", $login_id, $login_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['error' => 'Invalid admin ID or password']);
            exit();
        }

        $admin = $result->fetch_assoc();
       if ((isset($admin['password']) && password_verify($password, $admin['password'])) || 
    $password === $admin['password']) {

    $_SESSION['user_id']   = $admin['admin_id'];
    $_SESSION['idcode']    = $admin['idcode'];
    $_SESSION['user_type'] = 'admin';

    // ✅ Admin-only active term check
    $term_check = $conn->query("SELECT COUNT(*) AS count_active FROM academic_terms WHERE is_active = 1");
    $term_row   = $term_check->fetch_assoc();

    if ($term_row['count_active'] == 0) {
        // No active term, redirect admin to manage_terms.php
        echo json_encode([
            'success'  => true,
            'redirect' => '../manage_terms.php' // adjust path if login script is in admin folder
        ]);
        exit();
    }

    // Normal redirect if an active term exists
    echo json_encode(['success' => true, 'redirect' => 'admin/dashboard.php']);
    exit();
}

    }

    // ================= EMAIL LOGIN =================
    if (filter_var($login_id, FILTER_VALIDATE_EMAIL)) {

      

        // 🔹 Fetch student info with status check
        $stmt = $conn->prepare("
            SELECT st.*, 
                   COALESCE(ss.is_Mayor, 0) AS is_Mayor
            FROM students st
            LEFT JOIN students_sections ss 
                ON st.s_id = ss.s_id AND ss.term_id = ?
            WHERE st.s_email = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $current_term_id, $login_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();

            // 🚫 Block inactive students
            if (strtolower($student['s_status']) !== 'active') {
                echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
                exit();
            }

       if ((isset($student['s_password']) && password_verify($password, $student['s_password'])) ||
        $password === $student['s_password']) {

    $s_id     = $student['s_id'];
    $idcode   = $student['idcode'];
    $is_mayor = isset($student['is_Mayor']) && $student['is_Mayor'] == 1;

    // ✅ Extract prefix before last dash
    $prefix = substr($device_token, 0, strrpos($device_token, '-'));

    // 🚫 BLOCK if another record exists with same prefix but different student
    $stmt_check_prefix = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM approved_devices 
        WHERE mac_address LIKE CONCAT(?, '%') AND student_id != ?
    ");
    $stmt_check_prefix->bind_param("si", $prefix, $s_id);
    $stmt_check_prefix->execute();
    $prefix_result = $stmt_check_prefix->get_result()->fetch_assoc();

    if ($prefix_result['count'] > 0) {
        echo json_encode(['error' => 'Login blocked. This device is already registered by another student.']);
        exit();
    }

    // ✅ Check if this student already has a device record
    $stmt2 = $conn->prepare("SELECT student_id, status, mac_address FROM approved_devices WHERE student_id = ?");
    $stmt2->bind_param("i", $s_id);
    $stmt2->execute();
    $device_result = $stmt2->get_result();

    if ($device_result->num_rows > 0) {
        // Existing device found
        $device = $device_result->fetch_assoc();
        $stored_token = trim(strtolower($device['mac_address']));

        // Block if not same device (unless Mayor)
        if ($stored_token !== $device_token && !$is_mayor) {
            echo json_encode(['error' => 'Login blocked. This account is already registered to another device.']);
            exit();
        }

        // Block if not yet approved
        if ($device['status'] !== 'approved' && !$is_mayor) {
            echo json_encode(['error' => 'Your device is not approved yet.']);
            exit();
        }

        // ✅ Allow login (their registered device)
        $_SESSION['user_id']   = $s_id;
        $_SESSION['idcode']    = $idcode;
        $_SESSION['user_type'] = 'student';
        echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
        exit();

    } else {
        // 🧩 FIRST-TIME DEVICE REGISTRATION (no device yet)
// normalize device token
$device_token = trim(strtolower($device_token));

// ensure token exists
if (empty($device_token)) {
    echo json_encode(['error' => 'Missing device token']);
    exit();
}

// safe prefix extraction
$dashPos = strrpos($device_token, '-');
$prefix = ($dashPos !== false) ? substr($device_token, 0, $dashPos) : $device_token;
$prefix = trim(strtolower($prefix));

// Only do prefix checks if prefix is reasonably long and user is not mayor
if (!$is_mayor && strlen($prefix) >= 8) {
    $stmt_check_prefix = $conn->prepare("
        SELECT COUNT(*) AS count 
        FROM approved_devices 
        WHERE mac_address LIKE CONCAT(?, '%') AND student_id != ?
    ");
    $stmt_check_prefix->bind_param("si", $prefix, $s_id);
    $stmt_check_prefix->execute();
    $prefix_result = $stmt_check_prefix->get_result()->fetch_assoc();

    if ($prefix_result['count'] > 0) {
        echo json_encode(['error' => 'Login blocked. This device is already registered by another student.']);
        exit();
    }
}

// exact mac check (always do this)
$stmt_check = $conn->prepare("SELECT student_id FROM approved_devices WHERE mac_address = ?");
$stmt_check->bind_param("s", $device_token);
$stmt_check->execute();
$exists = $stmt_check->get_result()->fetch_assoc();
if ($exists && $exists['student_id'] != $s_id) {
    echo json_encode(['error' => 'Login blocked. This device is already registered to another student.']);
    exit();
}


        // ✅ Register new device
        $status = $is_mayor ? 'approved' : 'pending';
        $device_name = $student['s_fname'] . ' ' . $student['s_lname'];

        $stmt4 = $conn->prepare("
            INSERT INTO approved_devices 
            (student_id, term_id, device_name, mac_address, device_type, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt4->bind_param("iissss", $s_id, $current_term_id, $device_name, $device_token, $device_type, $status);
        $stmt4->execute();

        if ($status === 'approved') {
            $_SESSION['user_id']   = $s_id;
            $_SESSION['idcode']    = $idcode;
            $_SESSION['user_type'] = 'student';
            echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
        } else {
            echo json_encode(['error' => 'This device is not yet approved. Waiting for approval.']);
        }
        exit();
    }
            } else {
                echo json_encode(['error' => 'Invalid password']);
                exit();
            }
        }

        // --- TEACHER EMAIL LOGIN ---
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE t_email = ? LIMIT 1");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();

            if ((isset($teacher['t_password']) && password_verify($password, $teacher['t_password'])) ||
                $password === $teacher['t_password']) {

                $stmt2 = $conn->prepare("SELECT COUNT(*) FROM degrees WHERE dean_id = ?");
                $stmt2->bind_param("i", $teacher['t_id']);
                $stmt2->execute();
                $stmt2->bind_result($degree_count);
                $stmt2->fetch();
                $stmt2->close();

                $is_dean_flag = (int)$teacher['is_dean'];
                $is_dean = ($is_dean_flag === 1 || $degree_count > 0);

                $_SESSION['t_id']       = $teacher['t_id'];
                $_SESSION['user_id']    = $teacher['t_id'];
                $_SESSION['idcode']     = $teacher['idcode'];
                $_SESSION['user_type']  = 'teacher';
                $_SESSION['is_dean']    = $is_dean;

                echo json_encode(['success' => true, 'redirect' => 'teacher/dashboard.php']);
                exit();
            } else {
                echo json_encode(['error' => 'Invalid password']);
                exit();
            }
        }

        // --- PARENT EMAIL LOGIN ---
        $stmt = $conn->prepare("SELECT * FROM parents WHERE p_email = ? LIMIT 1");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $parent = $result->fetch_assoc();

            // 🚫 Block inactive parents
            if (strtolower($parent['p_status']) !== 'active') {
                echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
                exit();
            }

            if ((isset($parent['p_password']) && password_verify($password, $parent['p_password'])) ||
                $password === $parent['p_password']) {

                $_SESSION['user_id']   = $parent['p_id'];
                $_SESSION['idcode']    = $parent['idcode'];
                $_SESSION['user_type'] = 'parent';
                $_SESSION['parent_id'] = $parent['p_id'];
                echo json_encode(['success' => true, 'redirect' => 'parent/dashboard.php']);
                exit();
            } else {
                echo json_encode(['error' => 'Invalid password']);
                exit();
            }
        }
         echo json_encode(['error' => 'Email not found']);
        exit();

    }

    // ================= ID PREFIX LOGIN (Fallback for Students) =================
 elseif (preg_match('/^S\d+$/i', $login_id)) {
$stmt = $conn->prepare("
    SELECT st.*, 
           COALESCE(ss.is_Mayor, 0) AS is_Mayor
    FROM students st
    LEFT JOIN students_sections ss 
        ON st.s_id = ss.s_id AND ss.term_id = ?
    WHERE st.idcode = ? OR st.s_email = ?
    LIMIT 1
");
$stmt->bind_param("iss", $current_term_id, $login_id, $login_id);
$stmt->execute();
$result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Invalid student ID or password']);
        exit();
    }

    $student = $result->fetch_assoc();

    // 🚫 Block if student account is inactive
    if (isset($student['s_status']) && strtolower($student['s_status']) === 'inactive') {
        echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
        exit();
    }

    // ✅ Continue if password matches
    if ((isset($student['s_password']) && password_verify($password, $student['s_password'])) ||
        $password === $student['s_password']) {

        $s_id     = $student['s_id'];
        $idcode   = $student['idcode'];
        $is_mayor = isset($student['is_Mayor']) && $student['is_Mayor'] == 1;

        // ✅ Extract prefix before last dash
        $prefix = substr($device_token, 0, strrpos($device_token, '-'));

        // 🚫 BLOCK if another record exists with same prefix but different student
        $stmt_check_prefix = $conn->prepare("
            SELECT COUNT(*) AS count 
            FROM approved_devices 
            WHERE mac_address LIKE CONCAT(?, '%') AND student_id != ?
        ");
        $stmt_check_prefix->bind_param("si", $prefix, $s_id);
        $stmt_check_prefix->execute();
        $prefix_result = $stmt_check_prefix->get_result()->fetch_assoc();

        if ($prefix_result['count'] > 0) {
            echo json_encode(['error' => 'Login blocked. This device is already registered by another student.']);
            exit();
        }

        // ✅ Check if this student already has a device record
        $stmt2 = $conn->prepare("SELECT student_id, status, mac_address FROM approved_devices WHERE student_id = ?");
        $stmt2->bind_param("i", $s_id);
        $stmt2->execute();
        $device_result = $stmt2->get_result();

        if ($device_result->num_rows > 0) {
            // Existing device found
            $device = $device_result->fetch_assoc();
            $stored_token = trim(strtolower($device['mac_address']));

            // Block if not same device (unless Mayor)
            if ($stored_token !== $device_token && !$is_mayor) {
                echo json_encode(['error' => 'Login blocked. This account is already registered to another device.']);
                exit();
            }

            // Block if not yet approved
            if ($device['status'] !== 'approved' && !$is_mayor) {
                echo json_encode(['error' => 'Your device is not approved yet.']);
                exit();
            }

            // ✅ Allow login (their registered device)
            $_SESSION['user_id']   = $s_id;
            $_SESSION['idcode']    = $idcode;
            $_SESSION['user_type'] = 'student';
            echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
            exit();

        } else {
            // 🧩 FIRST-TIME DEVICE REGISTRATION (no device yet)

            // Check if prefix used by another student
            $stmt_check_prefix2 = $conn->prepare("
                SELECT COUNT(*) AS count 
                FROM approved_devices 
                WHERE mac_address LIKE CONCAT(?, '%') AND student_id != ?
            ");
            $stmt_check_prefix2->bind_param("si", $prefix, $s_id);
            $stmt_check_prefix2->execute();
            $prefix_result2 = $stmt_check_prefix2->get_result()->fetch_assoc();

            if ($prefix_result2['count'] > 0) {
                echo json_encode(['error' => 'Login blocked. Another student already registered this device.']);
                exit();
            }

            // Check exact MAC address reuse
            $stmt_check = $conn->prepare("SELECT student_id FROM approved_devices WHERE mac_address = ?");
            $stmt_check->bind_param("s", $device_token);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->fetch_assoc();

            if ($exists && $exists['student_id'] != $s_id) {
                echo json_encode(['error' => 'Login blocked. This device is already registered to another student.']);
                exit();
            }

            // ✅ Register new device
            $status = $is_mayor ? 'approved' : 'pending';
            $device_name = $student['s_fname'] . ' ' . $student['s_lname'];

            $stmt4 = $conn->prepare("
                INSERT INTO approved_devices 
                (student_id, term_id, device_name, mac_address, device_type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt4->bind_param("iissss", $s_id, $current_term_id, $device_name, $device_token, $device_type, $status);
            $stmt4->execute();

            if ($status === 'approved') {
                $_SESSION['user_id']   = $s_id;
                $_SESSION['idcode']    = $idcode;
                $_SESSION['user_type'] = 'student';
                echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
            } else {
                echo json_encode(['error' => 'This device is not yet approved. Waiting for approval.']);
            }
            exit();
        }
    } else {
        echo json_encode(['error' => 'Invalid password']);
        exit();
    }
}

    // ================= TEACHER LOGIN BY ID =================
    elseif (preg_match('/^T\d+$/i', $login_id)) {
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE idcode = ?");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['error' => 'Invalid teacher ID or password']);
            exit();
        }

        $teacher = $result->fetch_assoc();

        if ((isset($teacher['t_password']) && password_verify($password, $teacher['t_password'])) || 
            $password === $teacher['t_password']) {

            $stmt2 = $conn->prepare("SELECT COUNT(*) FROM degrees WHERE dean_id = ?");
            $stmt2->bind_param("i", $teacher['t_id']);
            $stmt2->execute();
            $stmt2->bind_result($degree_count);
            $stmt2->fetch();
            $stmt2->close();

            $is_dean_flag = (int)$teacher['is_dean'];

            $_SESSION['t_id']       = $teacher['t_id'];    
            $_SESSION['user_id']    = $teacher['t_id']; 
            $_SESSION['idcode']     = $teacher['idcode'];  

            if ($is_dean_flag === 1 || $degree_count > 0) {
                $_SESSION['is_dean']   = true;
                $_SESSION['user_type'] = 'teacher';
                echo json_encode([
                    'success'          => true,
                    'choose_role'      => true,
                    'dean_redirect'    => 'dean/dashboard.php',
                    'teacher_redirect' => 'teacher/dashboard.php'
                ]);
            } else {
                $_SESSION['is_dean']   = false;
                $_SESSION['user_type'] = 'teacher';
                echo json_encode(['success' => true, 'redirect' => 'teacher/dashboard.php']);
            }
            exit();

        } else {
            echo json_encode(['error' => 'Invalid password']);
            exit();
        }
    }

  // ================= PARENT LOGIN =================
elseif (preg_match('/^P\d+$/i', $login_id)) {
    $stmt = $conn->prepare("SELECT * FROM parents WHERE idcode = ?");
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Invalid parent ID or password']);
        exit();
    }

    $parent = $result->fetch_assoc();

    // 🚫 Check if parent account is inactive (if you have a 'status' column)
    if (isset($parent['p_status']) && strtolower($parent['p_status']) === 'inactive') {
        echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
        exit();
    }

    // ✅ Proceed if password matches
    if ((isset($parent['p_password']) && password_verify($password, $parent['p_password'])) ||
        $password === $parent['p_password']) {
        
        $p_id   = $parent['p_id'];
        $idcode = $parent['idcode'];

        $_SESSION['user_id']   = $p_id;
        $_SESSION['idcode']    = $idcode;
        $_SESSION['user_type'] = 'parent';
        $_SESSION['parent_id'] = $p_id;

        echo json_encode(['success' => true, 'redirect' => 'parent/dashboard.php']);
        exit();
    } else {
        echo json_encode(['error' => 'Invalid password']);
        exit();
    }
}

    else {
        echo json_encode(['error' => 'Unrecognized ID format']);
        exit();
    }


} catch (Exception $e) {
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}


ob_end_flush();
exit();
?>