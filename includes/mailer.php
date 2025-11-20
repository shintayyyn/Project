<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendMail($to, $subject, $body, $attachments = null, $attachmentNames = '', $isString = false) {
    $mail = new PHPMailer(true);

    try {
        // ✅ Validate recipient email
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Invalid or empty recipient email.'
            ];
        }

        // ✅ SMTP Debug
        $mail->SMTPDebug = 0; // 0 = off, 2 = detailed
        $mail->Debugoutput = 'error_log';

        // ✅ SMTP Server configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'attendifysys2025@gmail.com';
        $mail->Password   = 'lyhmcgprzmvnojwz'; // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ✅ SSL options
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ]
        ];

        // Sender and recipient
        $mail->setFrom('attendifysys2025@gmail.com', 'Attendify System');
        $mail->addReplyTo('attendifysys2025@gmail.com', 'Attendify Support');
        $mail->addAddress($to);

        // ✅ Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        // ✅ Handle attachments
        if ($attachments) {
            // Make everything an array for uniform handling
            if (!is_array($attachments)) $attachments = [$attachments];
            if (!is_array($attachmentNames)) $attachmentNames = [$attachmentNames];

            foreach ($attachments as $i => $att) {
                $name = $attachmentNames[$i] ?? 'attachment_' . ($i + 1);
                if ($isString) {
                    $mail->addStringAttachment($att, $name);
                } else {
                    $mail->addAttachment($att, $name);
                }
            }
        }

        // ✅ Attempt to send
        if (!$mail->send()) {
            return [
                'success' => false,
                'error' => $mail->ErrorInfo
            ];
        }

        return [
            'success' => true,
            'message' => "Email successfully sent to $to"
        ];

    } catch (Exception $e) {
        // 🔁 SSL fallback
        if (strpos($e->getMessage(), 'certificate verify failed') !== false) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ];
            try {
                $mail->send();
                return ['success' => true, 'message' => "Email sent (SSL fallback) to $to"];
            } catch (Exception $innerEx) {
                return [
                    'success' => false,
                    'error' => $mail->ErrorInfo,
                    'exception' => $innerEx->getMessage()
                ];
            }
        }

        return [
            'success' => false,
            'error' => $mail->ErrorInfo,
            'exception' => $e->getMessage()
        ];
    }
}
?>
