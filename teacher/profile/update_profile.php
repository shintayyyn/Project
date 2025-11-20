<?php
session_start();
require_once('../../includes/db.php');  // Fixed path to db.php

// Set JSON header first, before any output
header('Content-Type: application/json');

// Disable error display in output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log errors instead of displaying them
ini_set('log_errors', 1);
error_log("Processing update_profile request");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug log
        error_log("POST data received: " . print_r($_POST, true));
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

   // Validate required fields (exclude department and status)
$required_fields = ['firstname', 'lastname', 'gender', 'birthdate', 'contact', 'email'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        throw new Exception("$field is required");
    }
}

// Prepare SQL fields without department or status
$sql_fields = "t_fname = ?, t_lname = ?, t_mname = ?, t_suffix = ?,
    t_gender = ?, t_bdate = ?, t_cnum = ?, t_email = ?";

$params = [
    $_POST['firstname'],
    $_POST['lastname'],
    $_POST['middlename'] ?? '',
    $_POST['suffix'] ?? '',
    $_POST['gender'],
    $_POST['birthdate'],
    $_POST['contact'],
    $_POST['email']
];

$types = "ssssssss"; // 8 fields

// If password is provided
if (!empty($_POST['password'])) {
    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $sql_fields .= ", t_password = ?";
    $types .= "s";
    $params[] = $hashed_password;
}

// Add user ID at the end
$params[] = $_SESSION['user_id'];
$types .= "i";

$sql = "UPDATE teachers SET " . $sql_fields . " WHERE t_id = ?";

        // Debug log
        error_log("SQL Query: " . $sql);
        error_log("Parameters: " . print_r($params, true));
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Consider both affected rows and password change
        $changes_made = $stmt->affected_rows > 0 || !empty($_POST['password']);

        if ($changes_made) {
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'No changes were made']);
        }

    } catch (Exception $e) {
        error_log("Error in update_profile.php: " . $e->getMessage());
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