<?php
// Outgoing email via PHPMailer, and the login OTP flow built on it

require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

// Sends an HTML email. Returns false (and logs why) instead of throwing.
function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text): bool
{
    if (MAIL_USERNAME === '' || MAIL_PASSWORD === '') {
        error_log('Mail not sent: MAIL_USERNAME / MAIL_PASSWORD are not configured in config/mail.php');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout    = 15;
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        return $mail->send();
    } catch (MailException) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Admins sign in with their password only; every other role also needs an emailed code
function requires_login_otp(array $user): bool
{
    return $user['role'] !== 'admin';
}

// Creates a fresh code for the pending login and emails it to the user
function send_login_otp(array $user): bool
{
    $code = str_pad((string) random_int(0, 10 ** OTP_LENGTH - 1), OTP_LENGTH, '0', STR_PAD_LEFT);

    $_SESSION['otp_pending'] = [
        'user_id'  => (int) $user['id'],
        'hash'     => password_hash($code, PASSWORD_DEFAULT),
        'expires'  => time() + OTP_TTL,
        'attempts' => 0,
        'sent_at'  => time(),
    ];

    $name = full_name($user);
    $minutes = (int) ceil(OTP_TTL / 60);

    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#334155">'
        . '<h2 style="color:#1834b2">' . e(APP_NAME) . '</h2>'
        . '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>Use this code to finish logging in:</p>'
        . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#0f172a">' . $code . '</p>'
        . '<p>The code expires in ' . $minutes . ' minutes. If you didn\'t try to log in, change your password and contact the IT Department.</p>'
        . '<p style="font-size:12px;color:#94a3b8">Never share this code with anyone, including IT staff.</p>'
        . '</div>';
    $text = "Hi {$user['first_name']},\n\nYour " . APP_NAME . " login code is: {$code}\n\n"
        . "It expires in {$minutes} minutes. If you didn't try to log in, change your password and contact the IT Department.";

    return send_mail($user['email'], $name, 'Your login code: ' . $code, $html, $text);
}

// Masks an email for display, e.g. ju******@icct.edu.ph
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = mb_substr($local, 0, 2);
    return $visible . str_repeat('*', max(mb_strlen($local) - 2, 3)) . '@' . $domain;
}
