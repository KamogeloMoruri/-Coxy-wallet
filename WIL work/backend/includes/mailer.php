<?php
// ==========================================================
// Email service (PHPMailer over SMTP)
// Install with: composer require phpmailer/phpmailer
// ==========================================================

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email(string $toEmail, string $toName, string $subject, string $bodyHtml): bool {
    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer is not installed — run "composer require phpmailer/phpmailer" inside backend/. Email not sent.');
        return false;
    }

    $config = require __DIR__ . '/../config/config.php';
    $mailCfg = $config['mail'];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mailCfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailCfg['username'];
        $mail->Password   = $mailCfg['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mailCfg['port'];

        $mail->setFrom($mailCfg['from_email'], $mailCfg['from_name']);
        $mail->addAddress($toEmail, $toName ?: '');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = strip_tags($bodyHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function send_entry_confirmation_email(string $toEmail, string $songTitle): bool {
    $subject = "Your entry \"$songTitle\" was received";
    $body = "
        <h2>You're entered!</h2>
        <p>Thanks for submitting <strong>" . htmlspecialchars($songTitle) . "</strong>
           to the Coxy Wallet AI Music Contest.</p>
        <p>Your 10 ADA entry fee was confirmed and your track is now live for voting.</p>
        <p>Good luck!</p>
    ";
    return send_email($toEmail, '', $subject, $body);
}

function send_contest_results_email(string $toEmail, string $songTitle, int $finalVotes, bool $isWinner): bool {
    $safeTitle = htmlspecialchars($songTitle);
    $subject = $isWinner
        ? "Your track \"$songTitle\" won the contest!"
        : "Contest results for \"$songTitle\"";

    $body = $isWinner
        ? "<h2>Congratulations!</h2><p>Your track <strong>$safeTitle</strong> won this round with $finalVotes votes.</p>"
        : "<h2>Contest results</h2><p>Your track <strong>$safeTitle</strong> finished with $finalVotes votes. Thanks for entering — a new round starts soon.</p>";

    return send_email($toEmail, '', $subject, $body);
}
