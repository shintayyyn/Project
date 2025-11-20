<?php
session_start();
require_once('../includes/db.php');
require_once('../includes/mailer.php');
header('Content-Type: application/json');

// -----------------------------
// Detect user type and ID
// -----------------------------
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'parent' && isset($_SESSION['parent_id'])) {
        $user_id = $_SESSION['parent_id'];
        $user_type = 'parent';
    } elseif (in_array($_SESSION['user_type'], ['student','teacher']) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
    } else {
        echo json_encode(['status'=>'danger','message'=>'Unauthorized']);
        exit();
    }
} else {
    echo json_encode(['status'=>'danger','message'=>'Unauthorized']);
    exit();
}

// -----------------------------
// Get OTP
// -----------------------------
$entered_otp = preg_replace("/[^0-9]/","",$_POST['otp'] ?? '');
if (empty($entered_otp)) {
    echo json_encode(['status'=>'danger','message'=>'Invalid OTP format']);
    exit();
}

// -----------------------------
// Find OTP
// -----------------------------
$stmt = $conn->prepare("
    SELECT id, new_email, expires_at 
    FROM email_verifications 
    WHERE user_id=? AND user_type=? AND otp_code=? AND verified=0
    ORDER BY created_at DESC LIMIT 1
");
$stmt->bind_param("iss", $user_id, $user_type, $entered_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // Check if OTP expired
    if (strtotime($row['expires_at']) < time()) {
        echo json_encode(['status'=>'danger','message'=>'OTP expired']);
        exit();
    }

    // Determine user table and email field
    switch ($user_type) {
        case 'teacher': $table='teachers'; $email_field='t_email'; $id_field='t_id'; break;
        case 'student': $table='students'; $email_field='s_email'; $id_field='s_id'; break;
        case 'parent':  $table='parents';  $email_field='p_email'; $id_field='p_id'; break;
    }

  // -----------------------------
// Check if same email exists in email_verifications as verified=1 (already used by someone)
// -----------------------------
$check_verified = $conn->prepare("
    SELECT 1 
    FROM email_verifications
    WHERE new_email=? AND verified=1
    LIMIT 1
");
$check_verified->bind_param("s", $row['new_email']);
$check_verified->execute();
$check_verified_result = $check_verified->get_result();
$check_verified->close();

if ($check_verified_result->num_rows > 0) {
    echo json_encode([
        'status'=>'warning',
        'message'=>'This email is already verified and used by another account.'
    ]);
    exit();
}

// -----------------------------
// Check if same email is already assigned to another user's main account
// -----------------------------
$check_main = $conn->prepare("
    SELECT 1 
    FROM $table 
    WHERE $email_field=? AND $id_field<>?
    LIMIT 1
");
$check_main->bind_param("si", $row['new_email'], $user_id);
$check_main->execute();
$check_main_result = $check_main->get_result();
$check_main->close();

if ($check_main_result->num_rows > 0) {
    echo json_encode([
        'status'=>'warning',
        'message'=>'This email is already used by another account in the system.'
    ]);
    exit();
}


    // -----------------------------
    // Remove any unverified OTPs for this email to allow reuse
    // -----------------------------
    $delete_unverified = $conn->prepare("
        DELETE FROM email_verifications 
        WHERE new_email=? AND verified=0 AND id<>?
    ");
    $delete_unverified->bind_param("si", $row['new_email'], $row['id']);
    $delete_unverified->execute();
    $delete_unverified->close();

    // -----------------------------
    // Update user email
    // -----------------------------
    $stmt2 = $conn->prepare("UPDATE $table SET $email_field=? WHERE $id_field=?");
    $stmt2->bind_param("si", $row['new_email'], $user_id);
    $stmt2->execute();
    $stmt2->close();

    // -----------------------------
    // Mark OTP as verified
    // -----------------------------
    $stmt3 = $conn->prepare("UPDATE email_verifications SET verified=1 WHERE id=?");
    $stmt3->bind_param("i", $row['id']);
    $stmt3->execute();
    $stmt3->close();

    // -----------------------------
    // Send notification
    // -----------------------------
    sendMail($row['new_email'], "Email Updated", "<p>Your email has been updated successfully.</p>");
    echo json_encode(['status'=>'success','message'=>'Email updated successfully!']);

} else {
    echo json_encode(['status'=>'danger','message'=>'Invalid OTP']);
}
$stmt->close();
?>
