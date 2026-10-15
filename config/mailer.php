<?php
// Gumagamit ng settings at PHPMailer loader mula sa mail_config.php
// (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_NAME, MAIL_LOCAL_DEV)
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Magpadala ng kahit anong HTML email.
 * Susubukan ang port 587 (STARTTLS), tapos 465 (SSL) kung pumalya.
 */
function sendMail(string $to, string $subject, string $htmlBody): bool
{
    $attempts = [
        [587, PHPMailer::ENCRYPTION_STARTTLS],
        [465, PHPMailer::ENCRYPTION_SMTPS],
    ];

    foreach ($attempts as $a) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = $a[1];
            $mail->Port       = $a[0];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;

            if (MAIL_LOCAL_DEV) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer error [port {$a[0]}]: " . $mail->ErrorInfo);

            // Kung mali ang password, walang saysay mag-retry sa ibang port
            if (stripos($mail->ErrorInfo, 'authenticate') !== false) {
                break;
            }
        }
    }
    return false;
}