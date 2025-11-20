<?php
session_start();
require_once('../../includes/db.php');

header('Content-Type: application/json');

// Security: hide PHP errors from browser
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        // Validate required fields
        $required_fields = ['firstname', 'lastname', 'gender', 'birthdate', 'contact', 'email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst($field) . ' is required');
            }
        }

        // Validate gender
        if (!in_array($_POST['gender'], ['Male', 'Female'])) {
            throw new Exception('Invalid gender');
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Build SQL
        $sql = "UPDATE students 
                SET s_fname = ?, 
                    s_lname = ?, 
                    s_mname = ?, 
                    s_suffix = ?, 
                    s_gender = ?, 
                    s_bdate = ?, 
                    s_cnum = ?, 
                    s_email = ?";
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
        $types = "ssssssss";

        // Include password if provided
        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql .= ", s_password = ?";
            $params[] = $hashedPassword;
            $types .= "s";
        }

        // Add WHERE clause
        $sql .= " WHERE s_id = ?";
        $params[] = $_SESSION['user_id'];
        $types .= "i";

        // Execute prepared statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $conn->commit();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'No changes were made']);
        }

        $stmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error updating student profile: " . $e->getMessage());
        echo json_encode(['status' => 'danger', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
}

$conn->close();
