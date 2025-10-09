<?php
session_start();
require_once('../../includes/db.php');


// Set JSON header first, before any output
header('Content-Type: application/json');

// Disable error display in output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log errors instead of displaying them
ini_set('log_errors', 1);
error_log("Processing student profile update request");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Debug log
        error_log("POST data received: " . print_r($_POST, true));
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        // Validate required fields
        $required_fields = ['firstname', 'lastname', 'gender', 'birthdate', 'contact', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("$field is required");
            }
        }

        // Validate gender enum
        if (!in_array($_POST['gender'], ['Male', 'Female'])) {
            throw new Exception('Invalid gender');
        }

        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // First, get current student data
        $stmt = $conn->prepare("SELECT s_fname, s_lname, s_mname, s_gender FROM students WHERE s_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_data = $result->fetch_assoc();
        $stmt->close();

        // Create a temporary table to store the old references
        $conn->query("CREATE TEMPORARY TABLE temp_student_refs AS 
            SELECT s_fname, s_lname, s_mname, s_gender 
            FROM students_degrees 
            WHERE s_fname = '{$current_data['s_fname']}' 
            AND s_lname = '{$current_data['s_lname']}' 
            AND s_mname = '{$current_data['s_mname']}' 
            AND s_gender = '{$current_data['s_gender']}'");

        // Drop the foreign key constraint temporarily
        $conn->query("ALTER TABLE students_degrees 
            DROP FOREIGN KEY fk_s_lname_s_fname_s_mname_s_gender");

        // Update the students table
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
        $sql_fields = "s_fname = ?, s_lname = ?, s_mname = ?, s_suffix = ?,
            s_gender = ?, s_bdate = ?, s_cnum = ?, s_email = ?";

        // If password is provided, add it to the query
        if (!empty($_POST['password'])) {
            $sql_fields .= ", s_password = ?";
            $types .= "s";
            $params[] = $_POST['password'];
        }

        // Add user ID at the end
        $params[] = $_SESSION['user_id'];
        $types .= "i";

        $sql = "UPDATE students SET " . $sql_fields . " WHERE s_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Update students_degrees with new values
        $conn->query("UPDATE students_degrees sd 
            INNER JOIN temp_student_refs tr 
            ON sd.s_fname = tr.s_fname 
            AND sd.s_lname = tr.s_lname 
            AND sd.s_mname = tr.s_mname 
            AND sd.s_gender = tr.s_gender 
            SET sd.s_fname = '{$_POST['firstname']}',
                sd.s_lname = '{$_POST['lastname']}',
                sd.s_mname = '" . ($_POST['middlename'] ?? '') . "',
                sd.s_gender = '{$_POST['gender']}'");

        // Recreate the foreign key constraint
        $conn->query("ALTER TABLE students_degrees 
            ADD CONSTRAINT fk_s_lname_s_fname_s_mname_s_gender 
            FOREIGN KEY (s_lname, s_fname, s_mname, s_gender) 
            REFERENCES students (s_lname, s_fname, s_mname, s_gender)");

        // Drop temporary table
        $conn->query("DROP TEMPORARY TABLE IF EXISTS temp_student_refs");

        // Consider both affected rows and password change
        $changes_made = $stmt->affected_rows > 0 || !empty($_POST['password']);
        
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        if ($changes_made) {
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['status' => 'info', 'message' => 'No changes were made']);
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Ensure foreign key constraint is restored on error
        try {
            $conn->query("ALTER TABLE students_degrees 
                ADD CONSTRAINT fk_s_lname_s_fname_s_mname_s_gender 
                FOREIGN KEY (s_lname, s_fname, s_mname, s_gender) 
                REFERENCES students (s_lname, s_fname, s_mname, s_gender)");
        } catch (Exception $constraintError) {
            error_log("Error restoring constraint: " . $constraintError->getMessage());
        }
        
        error_log("Error in profile update: " . $e->getMessage());
        echo json_encode(['status' => 'danger', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method']);
}

$conn->close();
