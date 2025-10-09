<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../includes/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../includes/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../includes/PHPMailer/src/SMTP.php';

require __DIR__ . '/../../includes/db.php'; // ✅ DB connection

// Get current timestamp from MySQL
$timeResult = $conn->query("SELECT NOW() AS generated_time");
$generatedAt = ($timeResult && $timeResult->num_rows > 0)
    ? date("M d, Y h:i A", strtotime($timeResult->fetch_assoc()['generated_time']))
    : date("M d, Y h:i A");

// ✅ Get all students with valid email
$students = $conn->query("SELECT s_id, s_email FROM students WHERE s_email IS NOT NULL");

if (!$students || $students->num_rows === 0) {
    echo "No students with email found.\n";
    exit;
}

while ($student = $students->fetch_assoc()) {
    $student_id = $student['s_id'];
    $recipient  = $student['s_email'];

    // ✅ Fetch attendance for current month
    $stmt = $conn->prepare("
        SELECT s_id, subject_code, section_code, time_in, time_out, status 
        FROM attendance 
        WHERE s_id = ? 
        AND MONTH(time_in) = MONTH(CURRENT_DATE())
        AND YEAR(time_in) = YEAR(CURRENT_DATE())
        ORDER BY subject_code, time_in
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$attendance) {
        echo "No attendance records for student {$student_id} this month.\n";
        continue; // skip to next student
    }

    // ✅ Count per subject
    $subjectSummary = [];
    foreach ($attendance as $row) {
        $subj = $row['subject_code'];
        if (!isset($subjectSummary[$subj])) {
            $subjectSummary[$subj] = ["present" => 0, "absent" => 0];
        }
        if (strtolower($row['status']) === "present") {
            $subjectSummary[$subj]['present']++;
        } elseif (strtolower($row['status']) === "absent") {
            $subjectSummary[$subj]['absent']++;
        }
    }

    // ✅ Generate CSV in memory
    $csv = fopen('php://temp', 'r+');
    fputcsv($csv, ["Attendance Report for Student ID: $student_id"]);
    fputcsv($csv, ["Month: " . date("F Y")]);
    fputcsv($csv, ["Generated At: $generatedAt"]);
    fputcsv($csv, []);

    // === SUMMARY PER SUBJECT ===
    fputcsv($csv, ["========================="]);
    fputcsv($csv, ["SUMMARY PER SUBJECT"]);
    fputcsv($csv, ["========================="]);
    fputcsv($csv, ["Subject Code", "Present", "Absent"]);
    foreach ($subjectSummary as $subj => $counts) {
        fputcsv($csv, [$subj, $counts['present'], $counts['absent']]);
    }
    fputcsv($csv, []);

    // === DETAILED ATTENDANCE ===
    foreach ($subjectSummary as $subj => $counts) {
        fputcsv($csv, ["========================="]);
        fputcsv($csv, ["DETAILED ATTENDANCE - $subj"]);
        fputcsv($csv, ["========================="]);
        fputcsv($csv, ["Date", "Time In", "Time Out", "Status"]);

        foreach ($attendance as $row) {
            if ($row['subject_code'] === $subj) {
                $date = $row['time_in'] ? date('M d, Y', strtotime($row['time_in'])) : 'N/A';
                $timeIn  = $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-';
                $timeOut = $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-';
                fputcsv($csv, [$date, $timeIn, $timeOut, $row['status']]);
            }
        }
        fputcsv($csv, []); // separator
    }

    rewind($csv);
    $csvContent = stream_get_contents($csv);
    fclose($csv);

    // ✅ Send Email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'attendifysys2025@gmail.com';
        $mail->Password   = 'lyhmcgprzmvnojwz'; // ⚠️ App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('attendifysys2025@gmail.com', 'Attendify');
        $mail->addAddress($recipient);
        $mail->Subject = "Monthly Attendance Report - " . date("F Y");
        $mail->Body    = "Dear Student,\n\nPlease find attached your monthly attendance report for " 
                       . date("F Y") . ".\n\nGenerated At: $generatedAt\n\nRegards,\nAttendify";

        $mail->addStringAttachment(
            $csvContent,
            "attendance_report_{$student_id}_" . date("F_Y") . ".csv",
            'base64',
            'text/csv'
        );

        $mail->send();
        echo "Report sent to {$recipient} (Student ID: {$student_id}).\n";
    } catch (Exception $e) {
        echo "Failed to send to {$recipient}: {$mail->ErrorInfo}\n";
    }
}

echo "All reports processed.\n";
