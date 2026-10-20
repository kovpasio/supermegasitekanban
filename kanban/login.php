<?php
require __DIR__ . '/config.php';
require __DIR__ . '/send_mail.php';

// Если пользователь уже авторизован — на главную
if (current_user()) { header('Location: index.php'); exit; }

$error = '';
$mode  = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $email  = trim($_POST['email'] ?? '');
    $pass   = (string)($_POST['password'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $role   = $_POST['role'] ?? 'student';
    $group  = trim($_POST['group_name'] ?? '');
    $code   = trim($_POST['code'] ?? '');

    if (!in_array($role, ['student','teacher'], true)) $role = 'student';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } 
    // --- ЭТАП 1: ОТПРАВКА КОДА ---
    elseif ($action === 'register' && $code === '') {
        // Проверяем, не занят ли email
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Такой email уже зарегистрирован';
        } else {
            // Генерируем код от 100000 до 999999
            $generated = sprintf("%06d", mt_rand(100000, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Сохраняем в БД (перезаписываем, если уже был)
            db()->prepare('REPLACE INTO email_codes (email, code, expires_at) VALUES (?, ?, ?)')
                ->execute([$email, $generated, $expires]);

            // Отправляем письмо
            if (send_verification_code($email, $generated)) {
                $error = 'Код отправлен на почту. Проверьте ящик (и папку Спам).';
                $mode = 'register_verify';
            } else {
                $error = 'Ошибка отправки письма. Попробуйте позже.';
            }
        }
    } 
    // --- ЭТАП 2: ПРОВЕРКА КОДА И СОЗДАНИЕ ---
    elseif ($action === 'register' && $code !== '') {
        $stmt = db()->prepare('SELECT code, expires_at FROM email_codes WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row || $row['code'] !== $code || strtotime($row['expires_at']) < time()) {
            $error = 'Неверный или просроченный код';
            $mode = 'register_verify';
        } elseif (strlen($pass) < 3) {
            $error = 'Пароль минимум 3 символа';
            $mode = 'register_verify';
        } else {
            // Создаём пользователя
            $stmt = db()->prepare('INSERT INTO users (email, password_hash, name, role, group_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name, $role, $group !== '' ? $group : null]);
            
            // Удаляем код из БД
            db()->prepare('DELETE FROM email_codes WHERE email = ?')->execute([$email]);

            $_SESSION['user_id'] = (int)db()->lastInsertId();
            header('Location: index.php');
            exit;
        }
    } 
    // --- ВХОД ---
    else {
        if (strlen($pass) < 3) {
            $error = 'Пароль минимум 3 символа';
        } else {
            $stmt = db()->prepare('SELECT id, password_hash, blocked FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if (!$u || !password_verify($pass, $u['password_hash'])) {
                $error = 'Неверный email или пароль';
            } elseif ((int)$u['blocked'] === 1) {
                $error = 'Аккаунт заблокирован';
            } else {
                $_SESSION['user_id'] = (int)$u['id'];
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход — Study Kanban</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>

<div class="auth-bg">
    <div class="auth-grid"></div>

    <div class="auth-card">
        <!-- Логотип -->
        <div class="auth-logo">
            <div class="auth-logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
            <h1>Study Kanban</h1>
        </div>
        <p class="auth-subtitle">Управляй своей учёбой</p>

        <!-- Табы (скрываем при вводе кода) -->
        <?php if ($mode !== 'register_verify'): ?>
        <div class="auth-tabs">
            <a href="?mode=login" class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>">Вход</a>
            <a href="?mode=register" class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>">Регистрация</a>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="auth-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="<?= e($mode === 'register_verify' ? 'register' : $mode) ?>">
            
            <?php if ($mode === 'register_verify'): ?>
                <!-- СКРЫТЫЕ ПОЛЯ ДЛЯ 2 ЭТАПА -->
                <input type="hidden" name="email" value="<?= e($_POST['email'] ?? '') ?>">
                <input type="hidden" name="name" value="<?= e($_POST['name'] ?? '') ?>">
                <input type="hidden" name="role" value="<?= e($_POST['role'] ?? 'student') ?>">
                <input type="hidden" name="group_name" value="<?= e($_POST['group_name'] ?? '') ?>">
                <input type="hidden" name="password" value="<?= e($_POST['password'] ?? '') ?>">

                <!-- ПОЛЕ ДЛЯ КОДА -->
                <div class="auth-field">
                    <span class="auth-field-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input type="text" name="code" required class="auth-input" placeholder="Код из письма (6 цифр)" maxlength="6" autofocus>
                </div>
                <p class="text-center text-slate-500 text-sm mb-4">Письмо отправлено на <b><?= e($_POST['email'] ?? '') ?></b></p>
                <button type="submit" class="auth-btn">Подтвердить и войти</button>
                <a href="?mode=register" class="block text-center mt-4 text-sm text-slate-400 hover:text-white">← Назад</a>

            <?php else: ?>
                <!-- ОБЫЧНАЯ РЕГИСТРАЦИЯ / ВХОД -->
                <?php if ($mode === 'register'): ?>
                    <div class="auth-field">
                        <span class="auth-field-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" name="name" required class="auth-input" placeholder="Имя" value="<?= e($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="auth-field">
                        <span class="auth-field-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        </span>
                        <select name="role" class="auth-input" style="appearance:none;cursor:pointer">
                            <option value="student" style="background:#1e293b" <?= ($_POST['role'] ?? 'student') === 'student' ? 'selected' : '' ?>>Студент</option>
                            <option value="teacher" style="background:#1e293b" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Преподаватель</option>
                        </select>
                    </div>

                    <div class="auth-field">
                        <span class="auth-field-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" name="group_name" class="auth-input" placeholder="Группа (необязательно)" value="<?= e($_POST['group_name'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <div class="auth-field">
                    <span class="auth-field-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" name="email" required class="auth-input" placeholder="Email" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="auth-field">
                    <span class="auth-field-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" name="password" required class="auth-input" placeholder="Пароль">
                </div>

                <button type="submit" class="auth-btn">
                    <?php if ($mode === 'register'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Получить код
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Войти
                    <?php endif; ?>
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');</script>
</body>
</html>