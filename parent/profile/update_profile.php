<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../../includes/db.php');

// Check session
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit();
}

$parent_id = $_SESSION['parent_id'];

// JSON response header
header('Content-Type: application/json');

// Disable error display in output, log instead
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
error_log("Processing update_profile request (Parent)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log
        error_log("POST data received (Parent): " . print_r($_POST, true));
        
        // ✅ CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }

        // ✅ Required fields
        $required_fields = ['firstname', 'lastname', 'gender', 'birthdate', 'contact', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("$field is required");
            }
        }

        // ✅ Gender validation
        if (!in_array($_POST['gender'], ['Male', 'Female', 'Other'])) {
            throw new Exception('Invalid gender');
        }

        // ✅ Status validation
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }

        // ✅ Email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Parameters
        $params = [
            $_POST['firstname'],
            $_POST['lastname'],
            $_POST['middlename'] ?? '',
            $_POST['suffix'] ?? '',
            $_POST['gender'],
            $_POST['birthdate'],
            $_POST['contact'],
            $_POST['email'],
            $status
        ];
        
        $types = "sssssssss";
        $sql_fields = "p_fname = ?, p_lname = ?, p_mname = ?, p_suffix = ?,
                       p_gender = ?, p_bdate = ?, p_cnum = ?, p_email = ?, p_status = ?";

        // ✅ Password update if provided
        if (!empty($_POST['password'])) {
            $sql_fields .= ", p_password = ?";
            $types .= "s";
            $params[] = $_POST['password']; // 🔐 hash recommended
        }

        // ✅ Append WHERE clause (use parent_id)
        $params[] = $parent_id;
        $types .= "i";

        $sql = "UPDATE parents SET " . $sql_fields . " WHERE p_id = ?";

        // Debugging logs
        error_log("SQL Query (Parent): " . $sql);
        error_log("Parameters (Parent): " . print_r($params, true));
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // ✅ Detect if update happened
        $changes_made = $stmt->affected_rows > 0 || !empty($_POST['password']);

        if ($changes_made) {
            echo json_encode(['status' => 'success', 'message' => 'Parent profile updated successfully']);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'No changes were made.']);
        }

    } catch (Exception $e) {
        error_log("Error in update_profile (Parent): " . $e->getMessage());
        echo json_encode(['status' => 'danger', 'message' => $e->getMessage()]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
    exit();
}

echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
exit();
