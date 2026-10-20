<?php
// mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/vendor/autoload.php';

/**
 * Отправка письма через SMTP Beget
 */
function send_email(string $to, string $subject, string $body): bool {
    $mail = new PHPMailer(true);

    try {
        // Настройки сервера
        $mail->isSMTP();
        $mail->Host       = 'smtp.beget.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'student9@absolut-production.ru';
        $mail->Password   = 'jMNyW*5WBtJP'; // Осторожно, там звёздочка!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // От кого
        $mail->setFrom('student9@absolut-production.ru', 'Study Kanban');
        // Кому
        $mail->addAddress($to);

        // Контент
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Ошибка отправки: " . $mail->ErrorInfo);
        return false;
    }
}
