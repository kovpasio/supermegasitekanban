<?php
function send_verification_code(string $email, string $code): bool {
    $subject = 'Код подтверждения Study Kanban';
    $message = "Здравствуйте!\n\nВаш код подтверждения: $code\n\nОн действует 15 минут.";

    // От кого (на Beget лучше использовать почту с того же домена)
    $from = 'student9@absolut-production.ru';

    $headers  = "From: Study Kanban <$from>\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Отключаем ошибки, чтобы mail() не выводил предупреждения
    return @mail($email, $subject, $message, $headers);
}
