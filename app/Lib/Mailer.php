<?php

declare(strict_types=1);

namespace App\Lib;

use Base;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Thin PHPMailer wrapper. Reads SMTP config from the F3 hive (populated by
 * config.php from .env, editable in the admin panel). All mail goes through
 * send(); a caller gets true on success or a throwable message.
 */
final class Mailer
{
    /** True once a host + from address are configured. */
    public static function configured(?Base $f3 = null): bool
    {
        $f3 ??= Base::instance();
        return $f3->get('SMTP_HOST') !== '' && $f3->get('SMTP_FROM') !== '';
    }

    /**
     * Send one HTML email. Returns [ok(bool), error(string)].
     * $altBody defaults to a plaintext fallback stripped from the HTML.
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $altBody = null): array
    {
        $f3 = Base::instance();
        if (!self::configured($f3)) {
            return [false, 'SMTP yapılandırılmamış (host / gönderen adresi boş).'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $f3->get('SMTP_HOST');
            $mail->Port = (int) $f3->get('SMTP_PORT');
            $mail->CharSet = 'UTF-8';

            $user = (string) $f3->get('SMTP_USER');
            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = (string) $f3->get('SMTP_PASS');
            }

            $secure = strtolower((string) $f3->get('SMTP_SECURE'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom((string) $f3->get('SMTP_FROM'), (string) $f3->get('SMTP_FROM_NAME'));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody ?? trim(strip_tags($htmlBody));

            $mail->send();
            return [true, ''];
        } catch (PHPMailerException $e) {
            return [false, $mail->ErrorInfo ?: $e->getMessage()];
        }
    }
}
