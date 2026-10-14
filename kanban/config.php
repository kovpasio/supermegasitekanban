<?php
// =====================================================================
//  Настройки приложения и подключение к MySQL.
// =====================================================================

declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'kanban';
const DB_USER = 'kanban';
const DB_PASS = 'kanban123';

// Куда складываем загруженные файлы
const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_MAX_SIZE = 10 * 1024 * 1024; // 10 МБ

// ---- Подключение к БД ----
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---- JSON-ответ ----
function json_response(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Экранирование ----
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---- Текущий юзер ----
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, email, name, role, group_name, theme, blocked FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user && (int)$user['blocked'] === 1) {
            session_destroy();
            $user = null;
        }
    }
    return $user;
}

// ---- Требуется вход (страница) ----
function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

// ---- Требуется вход (API) ----
function require_login_api(): array {
    $u = current_user();
    if (!$u) json_response(['error' => 'Unauthorized'], 401);
    return $u;
}

// ---- Требуется роль ----
function require_role(string ...$roles): array {
    $u = require_login_api();
    if (!in_array($u['role'], $roles, true)) {
        json_response(['error' => 'Forbidden'], 403);
    }
    return $u;
}

// ---- Проверка роли без исключения ----
function has_role(array $user, string ...$roles): bool {
    return in_array($user['role'], $roles, true);
}

// ---- Лейбл роли (ЭТА ФУНКЦИЯ ПРОПАЛА) ----
function role_label(string $role): string {
    return match ($role) {
        'student' => 'Студент',
        'teacher' => 'Преподаватель',
        'admin'   => 'Админ',
        default   => $role,
    };
}

// ---- Размер файла по-человечески ----
function human_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' Б';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' КБ';
    return round($bytes / 1024 / 1024, 1) . ' МБ';
}

// =====================================================================
//  Impersonate — админ может "зайти как" другой пользователь
// =====================================================================

function is_impersonating(): bool {
    return !empty($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] !== (int)($_SESSION['user_id'] ?? 0);
}

function impersonate(int $userId): void {
    // Если мы уже impersonate — сохраняем оригинального админа
    if (!isset($_SESSION['admin_id'])) {
        $_SESSION['admin_id'] = $_SESSION['user_id'] ?? null;
    }
    $_SESSION['user_id'] = $userId;
}

function impersonate_stop(): void {
    if (!empty($_SESSION['admin_id'])) {
        $_SESSION['user_id'] = $_SESSION['admin_id'];
        unset($_SESSION['admin_id']);
    }
}

// HTML-плашка сверху, если админ "зашёл как"
function impersonate_banner(): string {
    if (!is_impersonating()) return '';
    $realId = (int)$_SESSION['admin_id'];
    $stmt = db()->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$realId]);
    $admin = $stmt->fetch();
    $adminName = $admin['name'] ?? 'Админ';
    return '<div style="background:#ef4444;color:white;text-align:center;padding:6px 12px;font-size:13px;font-family:system-ui,sans-serif;">'
         . '👑 Режим просмотра от лица пользователя. Вы вошли как админ: <b>' . e($adminName) . '</b>. '
         . '<a href="impersonate.php?action=stop" style="color:white;text-decoration:underline;font-weight:600;">← Вернуться под админом</a>'
         . '</div>';
}

// =====================================================================
//  SVG-иконки для PHP-шаблонов (для замены эмодзи)
//  Использование: ic('trash', 16)
// =====================================================================

function ic(string $name, int $size = 16, string $extraClass = ''): string {
    static $paths = [
        'check'          => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x'              => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'x-circle'       => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'alert'          => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info'           => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'plus'           => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'trash'          => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'edit'           => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'save'           => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'eye'            => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'upload'         => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'file'           => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'file-text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'folder'         => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'paperclip'      => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'book'           => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'graduation'     => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'           => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'crown'          => '<path d="M2 20h20l-3-11-5 4-2-9-2 9-5-4z"/>',
        'calendar'       => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'inbox'          => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'send'           => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'bar-chart'      => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'pie-chart'      => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'trending-up'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'sun'            => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon'           => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'message-circle' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'star'           => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'flame'          => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'zap'            => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'target'         => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'filter'         => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'shield'         => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    ];

    $path = $paths[$name] ?? '';
    if ($path === '') return '';
    $cls = 'inline-block align-[-0.15em] ' . $extraClass;
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($cls) . '" aria-hidden="true">' . $path . '</svg>';
}
