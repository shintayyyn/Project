<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../includes/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../includes/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

require __DIR__ . '/../../includes/db.php';
$student_id = $_POST['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(["status" => "error", "message" => "Student ID missing"]);
    exit;
}

// Fetch attendance
$stmt = $conn->prepare("SELECT s_id, subject_code, section_code, time_in, time_out, status FROM attendance WHERE s_id = ?");
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

error_log("Sending report to Student ID: $student_id, Email: $recipient");

// Get timestamp
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

// Send email immediately
$sent = false;
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'attendifysys2025@gmail.com';
    $mail->Password   = 'lyhmcgprzmvnojwz';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;
    $mail->SMTPDebug  = 0;
    $mail->Timeout = 60;

    $mail->setFrom('attendifysys2025@gmail.com', 'Attendify');
    $mail->addAddress($recipient);
    $mail->Subject = "Attendance Report - Student $student_id";
    $mail->Body    = "Dear Student,\n\nPlease find attached your attendance report.\nGenerated At: $generatedAt\n\nSummary:\n - Present: $presentCount\n - Absent: $absentCount\n\nRegards,\nAttendify";

    $mail->addStringAttachment($csvContent, "attendance_report_{$student_id}.csv", 'base64', 'text/csv');
    $mail->addStringAttachment($chartContent, "attendance_graph_{$student_id}.png", 'base64', 'image/png');

    $mail->send();
    $sent = true;
} catch (Exception $e) {
    error_log("Mailer Error: {$mail->ErrorInfo}");
}

echo json_encode([
    "status" => "success",
    "message" => "Report generated and email sent.",
    "emailSent" => $sent,
    "generatedAt" => $generatedAt,
    "presentCount" => $presentCount,
    "absentCount" => $absentCount
]);
exit;
