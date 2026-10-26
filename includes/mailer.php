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

function otp_code(): string
{
    return str_pad((string) random_int(0, 10 ** OTP_LENGTH - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
}

// Creates a fresh code for the pending login and emails it to the user
function send_login_otp(array $user): bool
{
    $code = otp_code();

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

// Emails a code that lets the user set a new password from reset_password.php
function send_password_reset_otp(array $user): bool
{
    $code = otp_code();

    $_SESSION['reset_pending'] = [
        'user_id'  => (int) $user['id'],
        'hash'     => password_hash($code, PASSWORD_DEFAULT),
        'expires'  => time() + OTP_TTL,
        'attempts' => 0,
        'sent_at'  => time(),
    ];

    $minutes = (int) ceil(OTP_TTL / 60);

    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;color:#334155">'
        . '<h2 style="color:#1834b2">' . e(APP_NAME) . '</h2>'
        . '<p>Hi ' . e($user['first_name']) . ',</p>'
        . '<p>We received a request to reset your password. Use this code to set a new one:</p>'
        . '<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#0f172a">' . $code . '</p>'
        . '<p>The code expires in ' . $minutes . ' minutes. If you didn\'t ask to reset your password, you can ignore this email; your password stays the same.</p>'
        . '<p style="font-size:12px;color:#94a3b8">Never share this code with anyone, including IT staff.</p>'
        . '</div>';
    $text = "Hi {$user['first_name']},\n\nYour " . APP_NAME . " password reset code is: {$code}\n\n"
        . "It expires in {$minutes} minutes. If you didn't ask to reset your password, you can ignore this email.";

    return send_mail($user['email'], full_name($user), 'Your password reset code: ' . $code, $html, $text);
}

// Absolute link to a page of this site, for use in emails. Set APP_URL in .env on production.
function app_url(string $path): string
{
    $base = mail_env('APP_URL');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// Tells the requester their ticket was fixed and asks them to confirm. $ticket comes from find_ticket().
function send_ticket_resolved_email(array $ticket, string $note, array $resolvedBy): bool
{
    $ticketNo = ticket_no((int) $ticket['id']);
    $link = app_url('ticket.php?id=' . (int) $ticket['id']);
    $by = full_name($resolvedBy);

    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#334155">'
        . '<h2 style="color:#1834b2">' . e(APP_NAME) . '</h2>'
        . '<p>Hi ' . e($ticket['requester_first_name']) . ',</p>'
        . '<p>Good news! Your concern has been <strong style="color:#059669">resolved</strong>.</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0">'
        . '<tr><td style="padding:6px 0;color:#64748b;width:120px">Ticket</td><td style="padding:6px 0"><strong>' . $ticketNo . '</strong></td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Subject</td><td style="padding:6px 0">' . e($ticket['subject']) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Resolved by</td><td style="padding:6px 0">' . e($by) . '</td></tr>'
        . '</table>'
        . '<p style="margin-bottom:4px;color:#64748b;font-size:14px">What was done:</p>'
        . '<p style="background:#f1f5f9;border-radius:8px;padding:12px;margin-top:0">' . nl2br(e($note)) . '</p>'
        . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#1d53f2;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Confirm the fix</a></p>'
        . '<p style="font-size:13px">Please open the ticket to confirm and rate the support. If the problem is still there, you can reopen it from the same page.</p>'
        . '</div>';
    $text = "Hi {$ticket['requester_first_name']},\n\nGood news! Your concern has been resolved.\n\n"
        . "Ticket: {$ticketNo}\nSubject: {$ticket['subject']}\nResolved by: {$by}\n\nWhat was done:\n{$note}\n\n"
        . "Please confirm the fix and rate the support: {$link}\n"
        . 'If the problem is still there, you can reopen the ticket from the same page.';

    return send_mail(
        $ticket['requester_email'],
        trim($ticket['requester_first_name'] . ' ' . $ticket['requester_last_name']),
        "Resolved: {$ticketNo} - {$ticket['subject']}",
        $html,
        $text
    );
}

// Masks an email for display, e.g. ju******@icct.edu.ph
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = mb_substr($local, 0, 2);
    return $visible . str_repeat('*', max(mb_strlen($local) - 2, 3)) . '@' . $domain;
}
