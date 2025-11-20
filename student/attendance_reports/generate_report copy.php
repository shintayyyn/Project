<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

$student_id = $_POST['student_id'] ?? null;
if (!$student_id) {
    echo json_encode(["status" => "error", "message" => "Student ID missing"]);
    exit;
}

// Fetch attendance
$stmt = $conn->prepare("
    SELECT s_id, subject_code, section_code, time_in, time_out, status 
    FROM attendance 
    WHERE s_id = ?
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$attendance = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$attendance) {
    echo json_encode(["status" => "error", "message" => "No attendance records found."]);
    exit;
}

// Fetch student email
$stmt2 = $conn->prepare("SELECT s_email FROM students WHERE s_id = ?");
$stmt2->bind_param("s", $student_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$student = $res2->fetch_assoc();
$recipient = $student['s_email'] ?? null;
$stmt2->close();

if (!$recipient) {
    echo json_encode(["status" => "error", "message" => "Student email not found"]);
    exit;
}

// Check if email is verified
$stmt3 = $conn->prepare("
    SELECT verified 
    FROM email_verifications 
    WHERE user_id = ? AND user_type = 'student' AND new_email = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt3->bind_param("is", $student_id, $recipient);
$stmt3->execute();
$res3 = $stmt3->get_result();
$email_verification = $res3->fetch_assoc();
$stmt3->close();

if (!$email_verification || !$email_verification['verified']) {
    echo json_encode([
        "status" => "warning",
        "message" => "Email is not yet verified, kindly verify it before sending the report."
    ]);
    exit;
}

// Generate timestamp
$timeResult = $conn->query("SELECT NOW() AS generated_time");
$timeRow = $timeResult->fetch_assoc();
$generatedAt = date("M d, Y h:i A", strtotime($timeRow['generated_time']));

// Count Present & Absent
$presentCount = $absentCount = 0;
foreach ($attendance as $row) {
    $status = strtolower($row['status']);
    if ($status === "present") $presentCount++;
    elseif ($status === "absent") $absentCount++;
}

// Generate CSV in memory
$csv = fopen('php://temp', 'r+');
fputcsv($csv, ["Attendance Report for Student ID: $student_id"]);
fputcsv($csv, ["Generated At: $generatedAt"]);
fputcsv($csv, ["Total Present: $presentCount", "Total Absent: $absentCount"]);
fputcsv($csv, []);
fputcsv($csv, ["Student ID", "Subject", "Section", "Date", "Time", "Status"]);

foreach ($attendance as $row) {
    $date = $row['time_in'] ? date('M d, Y', strtotime($row['time_in'])) : 'N/A';
    $time = ($row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-') . " - " .
            ($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-');
    fputcsv($csv, [$row['s_id'], $row['subject_code'], $row['section_code'], $date, $time, $row['status']]);
}
rewind($csv);
$csvContent = stream_get_contents($csv);
fclose($csv);

// Generate Pie Chart in memory
$width = 400; $height = 400;
$image = imagecreate($width, $height);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);
$green = imagecolorallocate($image, 0, 200, 0);
$red   = imagecolorallocate($image, 200, 0, 0);

$total = max(1, $presentCount + $absentCount);
$presentAngle = round(($presentCount / $total) * 360);
$absentAngle = 360 - $presentAngle;

imagefilledarc($image, $width/2, $height/2, 300, 300, 0, $presentAngle, $green, IMG_ARC_PIE);
imagefilledarc($image, $width/2, $height/2, 300, 300, $presentAngle, 360, $red, IMG_ARC_PIE);

imagestring($image, 5, 10, 10, "Attendance Report", $black);
imagestring($image, 4, 20, 350, "Present: $presentCount", $green);
imagestring($image, 4, 200, 350, "Absent: $absentCount", $red);

ob_start();
imagepng($image);
$chartContent = ob_get_clean();
imagedestroy($image);

// Send email using existing mailer.php
// Send email using existing mailer.php
$mailResult = sendMail(
    $recipient,
    "Attendance Report - Student $student_id",
    "Dear Student,<br><br>Please find attached your attendance report.<br>Generated At: $generatedAt<br><br>Summary:<br> - Present: $presentCount<br> - Absent: $absentCount<br><br>Regards,<br>Attendify",
    [$csvContent, $chartContent],
    ["attendance_report_{$student_id}.csv", "attendance_graph_{$student_id}.png"],
    true
);

if (!$mailResult['success']) {
    // Log the detailed error
    error_log("SendMail Error: " . ($mailResult['error'] ?? 'Unknown error') . " | Exception: " . ($mailResult['exception'] ?? 'None'));
}

// Return response
echo json_encode([
    "status" => "success",
    "message" => "Report generated and email sent.",
    "emailSent" => $mailResult['success'],
    "generatedAt" => $generatedAt,
    "presentCount" => $presentCount,
    "absentCount" => $absentCount
]);

exit;
