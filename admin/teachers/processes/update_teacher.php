<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// === Authorization Check ===
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'title' => 'Unauthorized', 'message' => 'Access denied.']);
    exit;
}

require_once __DIR__ . '/../../../includes/db.php';

// === Validate Request ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'title' => 'Invalid Request', 'message' => 'Only POST allowed.']);
    exit;
}

// === Collect Inputs ===
$t_id         = $_POST['t_id'] ?? null;
$t_fname      = trim($_POST['t_fname'] ?? '');
$t_lname      = trim($_POST['t_lname'] ?? '');
$t_mname      = trim($_POST['t_mname'] ?? '');
$t_suffix     = trim($_POST['t_suffix'] ?? '');
$t_gender     = trim($_POST['t_gender'] ?? '');
$t_bdate      = trim($_POST['t_bdate'] ?? '');
$t_cnum       = trim($_POST['t_cnum'] ?? '');
$t_email      = trim($_POST['t_email'] ?? '');
$t_department = $_POST['t_department'] ?? null;
$t_status     = trim($_POST['t_status'] ?? '');

// === Required field validation ===
if (empty($t_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'title' => 'Validation Error', 'message' => 'Teacher ID is required.']);
    exit;
}

// === Age calculation if birthdate is provided ===
$age = null;
if (!empty($t_bdate)) {
    try {
        $birthDate = new DateTime($t_bdate);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'title' => 'Invalid Date', 'message' => 'Invalid birthdate.']);
        exit;
    }
}

try {
    // === Build dynamic UPDATE ===
    $fields = [];
    $params = [];
    $types  = "";

    if ($t_fname !== "") { $fields[] = "t_fname = ?"; $params[] = $t_fname; $types .= "s"; }
    if ($t_lname !== "") { $fields[] = "t_lname = ?"; $params[] = $t_lname; $types .= "s"; }
    $fields[] = "t_mname = NULLIF(?, '')"; $params[] = $t_mname; $types .= "s";
    $fields[] = "t_suffix = NULLIF(?, '')"; $params[] = $t_suffix; $types .= "s";
    if ($t_gender !== "") { $fields[] = "t_gender = ?"; $params[] = $t_gender; $types .= "s"; }
    if ($t_bdate !== "") { 
        $fields[] = "t_bdate = ?"; $params[] = $t_bdate; $types .= "s"; 
        $fields[] = "t_age = ?";   $params[] = $age;     $types .= "i"; 
    }
    if ($t_cnum !== "") { $fields[] = "t_cnum = ?"; $params[] = $t_cnum; $types .= "s"; }
    $fields[] = "t_email = NULLIF(?, '')"; $params[] = $t_email; $types .= "s";
    if (!is_null($t_department)) { $fields[] = "t_department = ?"; $params[] = $t_department; $types .= "i"; }
    if ($t_status !== "") { $fields[] = "t_status = ?"; $params[] = $t_status; $types .= "s"; }

    if (empty($fields)) {
        // Nothing to update
        $sql = "SELECT t.*, d.degree_code 
                FROM teachers t 
                LEFT JOIN degrees d ON t.t_department = d.degree_id 
                WHERE t.t_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $t_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'title'   => 'No Changes',
            'message' => 'No changes were made.',
            'data'    => $data
        ]);
        exit;
    }

    $sql = "UPDATE teachers SET " . implode(", ", $fields) . " WHERE t_id = ?";
    $params[] = $t_id;
    $types   .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // === Always fetch latest data for consistency ===
    $stmt = $conn->prepare("
        SELECT t.*, d.degree_code 
        FROM teachers t 
        LEFT JOIN degrees d ON t.t_department = d.degree_id
        WHERE t.t_id = ?
    ");
    $stmt->bind_param("i", $t_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($affected === 0) {
        echo json_encode([
            'success' => true,
            'title'   => 'No Changes',
            'message' => 'No changes were made.',
            'data'    => $data
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'title'   => 'Success!',
            'message' => 'Teacher details updated successfully.',
            'data'    => $data
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'title'   => 'Server Error',
        'message' => $e->getMessage()
    ]);
}
