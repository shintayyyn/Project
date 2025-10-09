<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();

require_once 'includes/db.php';
ob_clean();
header('Content-Type: application/json');

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request method']));
}

$login_id = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($login_id) || empty($password)) {
    echo json_encode(['error' => 'Please fill in all fields']);
    exit();
}

// Device identification for students
$device_ip = $_SERVER['REMOTE_ADDR'];
$device_hostname = gethostbyaddr($device_ip);
$mac_address = md5($device_ip . '-' . $device_hostname);

// Detect device type
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
$device_type = 'Laptop/Desktop';
if (preg_match('/mobile/i', $userAgent)) $device_type = 'Phone';
elseif (preg_match('/tablet/i', $userAgent)) $device_type = 'Tablet';

try {
    // ================= ADMIN LOGIN =================
if (strtolower($login_id) === 'admin' || strtoupper(substr($login_id,0,1)) === 'A') {
    // Lookup by ID or username
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
        echo json_encode(['success' => true, 'redirect' => 'admin/dashboard.php']);
        exit();
    } else {
        echo json_encode(['error' => 'Invalid password']);
        exit();
    }
}

    // ================= EMAIL LOGIN FIRST =================
    if (filter_var($login_id, FILTER_VALIDATE_EMAIL)) {
        // --- STUDENT EMAIL ---
        $stmt = $conn->prepare("SELECT st.*, ss.is_Mayor
            FROM students st
            LEFT JOIN students_sections ss ON st.s_id = ss.s_id
            WHERE st.s_email = ?
            LIMIT 1");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            if ((isset($student['s_password']) && password_verify($password, $student['s_password'])) ||
                $password === $student['s_password']) {

                $s_id     = $student['s_id'];
                $idcode   = $student['idcode'];
                $is_mayor = isset($student['is_Mayor']) && $student['is_Mayor'] == 1;

                // Device check
                $stmt2 = $conn->prepare("SELECT student_id, status FROM approved_devices WHERE mac_address = ?");
                $stmt2->bind_param("s", $mac_address);
                $stmt2->execute();
                $device_result = $stmt2->get_result();

                if ($device_result->num_rows > 0) {
                    $device = $device_result->fetch_assoc();
                    if ($device['student_id'] != $s_id) {
                        echo json_encode(['error' => 'This device is already registered to another student']);
                        exit();
                    }
                    if ($device['status'] !== 'approved') {
                        echo json_encode(['error' => 'Your device is not approved yet.']);
                        exit();
                    }

                    $_SESSION['user_id']   = $s_id;
                    $_SESSION['idcode']    = $idcode;
                    $_SESSION['user_type'] = 'student';
                    echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
                    exit();
                } else {
                    // First-time device registration
                    $status = $is_mayor ? 'approved' : 'pending';
                    $stmt4 = $conn->prepare("INSERT INTO approved_devices
                        (student_id, device_name, mac_address, device_type, status, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())");
                    $device_name = $student['s_fname'] . ' ' . $student['s_lname'];
                    $stmt4->bind_param("issss", $s_id, $device_name, $mac_address, $device_type, $status);
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

        // --- TEACHER EMAIL ---
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE t_email = ? LIMIT 1");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            if ((isset($teacher['t_password']) && password_verify($password, $teacher['t_password'])) ||
                $password === $teacher['t_password']) {

                // Dean info
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
                        'success' => true,
                        'choose_role' => true,
                        'dean_redirect' => 'dean/dashboard.php',
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

        // --- PARENT EMAIL ---
        $stmt = $conn->prepare("SELECT * FROM parents WHERE p_email = ? LIMIT 1");
        $stmt->bind_param("s", $login_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $parent = $result->fetch_assoc();
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
        // No match for this email
        echo json_encode(['error' => 'Email not found']);
        exit();
    }

    // ================= ID PREFIX LOGIN (Fallback) =================
    elseif (preg_match('/^S\d+$/i', $login_id)) {
       // ================= STUDENT LOGIN =================
    $stmt = $conn->prepare("
        SELECT st.*, ss.is_Mayor
        FROM students st
        LEFT JOIN students_sections ss ON st.s_id = ss.s_id
        WHERE st.idcode = ? OR st.s_email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $login_id, $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Invalid student ID or password']);
        exit();
    }

    $student = $result->fetch_assoc();

    // Accept hashed or plain password
    if ((isset($student['s_password']) && password_verify($password, $student['s_password'])) ||
        $password === $student['s_password']) {

        $s_id     = $student['s_id'];      // real FK
        $idcode   = $student['idcode'];    // login code
        $is_mayor = isset($student['is_Mayor']) && $student['is_Mayor'] == 1;

        // Device check
        $stmt2 = $conn->prepare("SELECT student_id, status FROM approved_devices WHERE mac_address = ?");
        $stmt2->bind_param("s", $mac_address);
        $stmt2->execute();
        $device_result = $stmt2->get_result();

        if ($device_result->num_rows > 0) {
            $device = $device_result->fetch_assoc();
            if ($device['student_id'] != $s_id) {
                echo json_encode(['error' => 'This device is already registered to another student']);
                exit();
            }
            if ($device['status'] !== 'approved') {
                echo json_encode(['error' => 'Your device is not approved yet.']);
                exit();
            }

            $_SESSION['user_id']   = $s_id;
            $_SESSION['idcode']    = $idcode;
            $_SESSION['user_type'] = 'student';
            echo json_encode(['success' => true, 'redirect' => 'student/dashboard.php']);
            exit();
        } else {
            // First-time device registration
            $status = $is_mayor ? 'approved' : 'pending';
            $stmt4 = $conn->prepare("
                INSERT INTO approved_devices 
                (student_id, device_name, mac_address, device_type, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $device_name = $student['s_fname'] . ' ' . $student['s_lname'];
            $stmt4->bind_param("issss", $s_id, $device_name, $mac_address, $device_type, $status);
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
    
    elseif (preg_match('/^T\d+$/i', $login_id)) {
       $stmt = filter_var($login_id, FILTER_VALIDATE_EMAIL)
        ? $conn->prepare("SELECT * FROM teachers WHERE t_email = ?")
        : $conn->prepare("SELECT * FROM teachers WHERE idcode = ?");
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Invalid teacher ID or password']);
        exit();
    }

    $teacher = $result->fetch_assoc();

    // Verify password
    if ((isset($teacher['t_password']) && password_verify($password, $teacher['t_password'])) || 
        $password === $teacher['t_password']) {

        // --- Fetch dean-related info first ---
        $stmt2 = $conn->prepare("SELECT COUNT(*) FROM degrees WHERE dean_id = ?");
        $stmt2->bind_param("i", $teacher['t_id']);
        $stmt2->execute();
        $stmt2->bind_result($degree_count);
        $stmt2->fetch();
        $stmt2->close();

        $is_dean_flag = (int)$teacher['is_dean']; // 1 if teacher is also a dean

        // --- Save session ---
        $_SESSION['t_id']       = $teacher['t_id'];    
        $_SESSION['user_id']    = $teacher['t_id']; // optional duplicate
        $_SESSION['idcode']     = $teacher['idcode'];  

        if ($is_dean_flag === 1 || $degree_count > 0) {
            $_SESSION['is_dean']   = true;     // can access dean dashboard
            $_SESSION['user_type'] = 'teacher'; // default is teacher, will switch if chooses dean
            // Ask user to choose role
            echo json_encode([
                'success'          => true,
                'choose_role'      => true,
                'dean_redirect'    => 'dean/dashboard.php',
                'teacher_redirect' => 'teacher/dashboard.php'
            ]);
        } else {
            $_SESSION['is_dean']   = false;
            $_SESSION['user_type'] = 'teacher';
            // Just redirect to teacher dashboard
            echo json_encode([
                'success'  => true,
                'redirect' => 'teacher/dashboard.php'
            ]);
        }

        exit();
    } else {
        echo json_encode(['error' => 'Invalid password']);
        exit();
    }
    }

    elseif (preg_match('/^P\d+$/i', $login_id)) {
        // Parent ID login 
         $stmt = filter_var($login_id, FILTER_VALIDATE_EMAIL)
        ? $conn->prepare("SELECT * FROM parents WHERE p_email = ?")
        : $conn->prepare("SELECT * FROM parents WHERE idcode = ?");
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Invalid parent ID or password']);
        exit();
    }

    $parent = $result->fetch_assoc();

    if ((isset($parent['p_password']) && password_verify($password, $parent['p_password'])) || 
        $password === $parent['p_password']) {
        
        $p_id   = $parent['p_id'];     // real FK
        $idcode = $parent['idcode'];   // login code

        $_SESSION['user_id']   = $p_id;
        $_SESSION['idcode']    = $idcode;
        $_SESSION['user_type'] = 'parent';
        $_SESSION['parent_id'] = $p_id;   // ✅ FIXED

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
