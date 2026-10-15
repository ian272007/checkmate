<?php
/*
 * Mail settings for sending OTP emails (PHPMailer + Gmail SMTP).
 *
 * 1. Gumamit ng Gmail account para sa system
 * 2. I-on ang 2-Step Verification sa Google account na 'yon
 * 3. Gumawa ng App Password: https://myaccount.google.com/apppasswords
 * 4. Ilagay sa MAIL_PASSWORD (16 characters, WALANG space)
 */

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_USERNAME',  'francisco.ian@ncst.edu.ph');   // Gmail na pinag-gawan ng App Password
define('MAIL_PASSWORD',  'pbvchrsvhbzkdbqm'); // 16 chars, walang space
define('MAIL_FROM_NAME', 'CheckMate');

// true = ipapakita ang totoong error ng mail sa screen (para sa testing lang)
define('MAIL_DEBUG', true);

// true = para gumana sa XAMPP/localhost na walang CA certificate bundle.
// Gawing false pag nasa totoong server na.
define('MAIL_LOCAL_DEV', true);

// Load PHPMailer (composer o manual download)
$autoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Build one PHPMailer object for a given port + encryption.
 * 587 must use STARTTLS, 465 must use SMTPS (SSL).
 */
function build_mailer($port, $encryption, $toEmail, $toName, $otp, $minutes)
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = $encryption;
    $mail->Port       = $port;
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
    $mail->addAddress($toEmail, $toName);

    $mail->isHTML(true);
    $mail->Subject = 'Your CheckMate verification code';

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeOtp  = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

    $mail->Body = '
    <div style="font-family:Arial,sans-serif;max-width:420px;margin:auto;
                background:#0d1216;color:#f2f2f2;padding:28px;border-radius:12px;">
        <h2 style="margin:0 0 6px;color:#d9a94f;letter-spacing:2px;">CHECKMATE</h2>
        <p style="margin:0 0 18px;color:#8c969e;">Email verification</p>
        <p>Hi ' . $safeName . ',</p>
        <p>Use this code to finish creating your account:</p>
        <p style="font-size:34px;letter-spacing:10px;font-weight:bold;
                  text-align:center;background:#12181d;border:1px solid #263038;
                  border-radius:8px;padding:14px 0;color:#d9a94f;">' . $safeOtp . '</p>
        <p style="color:#8c969e;font-size:12px;">
            This code expires in ' . (int)$minutes . ' minutes.
            If you did not try to sign up, you can ignore this email.
        </p>
    </div>';

    $mail->AltBody = "Your CheckMate verification code is $otp. It expires in " . (int)$minutes . " minutes.";

    return $mail;
}

/**
 * Send the OTP to the given email.
 * Tries 587 (STARTTLS) first, then 465 (SSL) if the first one fails to connect.
 * Returns [true, ""] on success or [false, "error message"] on failure.
 */
function send_otp_email($toEmail, $toName, $otp, $minutes)
{
    $attempts = [
        [587, PHPMailer::ENCRYPTION_STARTTLS],
        [465, PHPMailer::ENCRYPTION_SMTPS],
    ];

    $errors = [];

    foreach ($attempts as $a) {
        $mail = null;
        try {
            $mail = build_mailer($a[0], $a[1], $toEmail, $toName, $otp, $minutes);
            $mail->send();
            return [true, ""];
        } catch (Exception $e) {
            $info = $mail ? $mail->ErrorInfo : $e->getMessage();
            $errors[] = "[port {$a[0]}] " . $info;

            // Kung mali ang password, walang saysay mag-retry sa ibang port
            if (stripos($info, 'authenticate') !== false) {
                break;
            }
        }
    }

    return [false, implode(' | ', $errors)];
}
?>