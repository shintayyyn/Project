<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendMail($to, $subject, $body, $attachment = null, $attachmentName = '', $isString = false) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'attendifysys2025@gmail.com';
        $mail->Password = 'lyhmcgprzmvnojwz';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Sender and recipient
        $mail->setFrom('attendifysys2025@gmail.com', 'Attendify');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // --- Attach file if provided ---
        if ($attachment) {
            if ($isString) {
                $mail->addStringAttachment($attachment, $attachmentName);
            } else {
                $mail->addAttachment($attachment, $attachmentName ?: basename($attachment));
            }
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
