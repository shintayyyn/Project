<?php
session_start();
require_once('../../includes/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];
$entered_otp = preg_replace("/[^0-9]/", "", $_POST['otp']); // digits only

if (empty($entered_otp)) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid OTP format']);
    exit();
}

// Find OTP
$stmt = $conn->prepare("SELECT id, new_email, expires_at 
                        FROM email_verifications 
                        WHERE s_id = ? AND otp_code = ? AND verified = 0
                        ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("is", $student_id, $entered_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['status' => 'danger', 'message' => 'OTP expired']);
    } else {
        // Update student email
        $stmt2 = $conn->prepare("UPDATE students SET s_email = ? WHERE s_id = ?");
        $stmt2->bind_param("si", $row['new_email'], $student_id);
        $stmt2->execute();
        $stmt2->close();

        // Mark OTP as used
        $stmt3 = $conn->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
        $stmt3->bind_param("i", $row['id']);
        $stmt3->execute();
        $stmt3->close();

        // ✅ Optional: Send confirmation email
        require_once("../../includes/mailer.php");
        sendMail($row['new_email'], "Email Updated", "<p>Your email has been updated successfully.</p>");

        echo json_encode(['status' => 'success', 'message' => 'Email updated successfully!']);
    }
} else {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid OTP']);
}
$stmt->close();
