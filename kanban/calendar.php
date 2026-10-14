<?php
require __DIR__ . '/config.php';
$user = require_login();
$isDark = $user['theme'] === 'dark';

// Месяц: ?m=YYYY-MM, по умолчанию текущий
$monthParam = $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) $monthParam = date('Y-m');

[$year, $month] = array_map('intval', explode('-', $monthParam));
$firstDay  = sprintf('%04d-%02d-01', $year, $month);
$daysInMon = (int) date('t', strtotime($firstDay));
$lastDay   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMon);

// Предыдущий / следующий месяц для навигации
$prev = date('Y-m', strtotime($firstDay . ' -1 month'));
$next = date('Y-m', strtotime($firstDay . ' +1 month'));

// Достаём задания, попадающие в этот месяц
if ($user['role'] === 'student') {
    $stmt = db()->prepare('
        SELECT a.id, a.title, a.deadline_at, a.grade_max,
               s.status, s.grade,
               u.name AS teacher_name
        FROM assignments a
        JOIN submissions s ON s.assignment_id = a.id
        JOIN users u ON u.id = a.teacher_id
        WHERE s.student_id = ? AND a.deadline_at BETWEEN ? AND ?
        ORDER BY a.deadline_at
    ');
    $stmt->execute([$user['id'], $firstDay, $lastDay]);
} elseif ($user['role'] === 'teacher') {
    $stmt = db()->prepare('
        SELECT a.id, a.title, a.deadline_at, a.grade_max,
               NULL AS status, NULL AS grade,
               NULL AS teacher_name
        FROM assignments a
        WHERE a.teacher_id = ? AND a.deadline_at BETWEEN ? AND ?
        ORDER BY a.deadline_at
    ');
    $stmt->execute([$user['id'], $firstDay, $lastDay]);
} else {
    $stmt = db()->prepare('
        SELECT a.id, a.title, a.deadline_at, a.grade_max,
               NULL AS status, NULL AS grade,
               u.name AS teacher_name
        FROM assignments a
        JOIN users u ON u.id = a.teacher_id
        WHERE a.deadline_at BETWEEN ? AND ?
        ORDER BY a.deadline_at
    ');
    $stmt->execute([$firstDay, $lastDay]);
}
$rows = $stmt->fetchAll();

// Группируем по дате
$byDate = [];
foreach ($rows as $r) {
    $byDate[$r['deadline_at']][] = $r;
}

// Строим сетку месяца: первый день недели (1=Пн)
$startWeekday = (int) date('N', strtotime($firstDay)); // 1..7
$weekdays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
$monthNames = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
    5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
    9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
$monthTitle = $monthNames[$month] . ' ' . $year;
?>
<!DOCTYPE html>
<html lang="ru" class="<?= $isDark ? 'dark' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Календарь — Study Kanban</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: { 50: '#f5f3ff', 100: '#ede9fe', 400: '#a78bfa', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9' } } } } }</script>
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 page-fade-in">
<?= impersonate_banner() ?>

<header class="header-gradient bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-xl font-bold text-brand-600 dark:text-brand-400"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Календарь дедлайнов</h1>
        <div class="flex items-center gap-3">
            <a href="<?= $user['role'] === 'student' ? 'student.php' : ($user['role'] === 'teacher' ? 'teacher.php' : 'admin.php') ?>"
               class="nav-pill nav-pill-export">← Назад</a>
            <a href="logout.php" class="nav-pill nav-pill-admin">Выйти</a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">

    <!-- Навигация по месяцам -->
    <div class="flex items-center justify-between mb-4 bg-white dark:bg-slate-800 rounded-xl px-4 py-3 shadow-sm border border-slate-200 dark:border-slate-700">
        <a href="?m=<?= $prev ?>" class="nav-pill nav-pill-export">← <?= htmlspecialchars(date('M', strtotime($prev . '-01'))) ?></a>
        <div class="text-lg font-semibold"><?= $monthTitle ?></div>
        <a href="?m=<?= $next ?>" class="nav-pill nav-pill-export"><?= htmlspecialchars(date('M', strtotime($next . '-01'))) ?> →</a>
    </div>

    <!-- Сетка -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="grid grid-cols-7 bg-slate-100 dark:bg-slate-900 text-center text-xs font-semibold text-slate-600 dark:text-slate-400">
            <?php foreach ($weekdays as $wd): ?>
                <div class="py-2"><?= $wd ?></div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-7">
            <?php
            $totalCells = $startWeekday - 1 + $daysInMon;
            $rows = ceil($totalCells / 7);
            $day = 1 - ($startWeekday - 1);

            for ($cell = 0; $cell < $rows * 7; $cell++):
                $isCurrentMonth = ($day >= 1 && $day <= $daysInMon);
                $dateStr = $isCurrentMonth ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
                $today = date('Y-m-d') === $dateStr ? 'bg-brand-50 dark:bg-brand-900/20' : '';
                $items = $isCurrentMonth && isset($byDate[$dateStr]) ? $byDate[$dateStr] : [];
            ?>
                <div class="min-h-[100px] border border-slate-100 dark:border-slate-700 p-1.5 <?= $today ?>">
                    <?php if ($isCurrentMonth): ?>
                        <div class="text-xs font-semibold <?= date('Y-m-d') === $dateStr ? 'text-brand-600 dark:text-brand-400' : 'text-slate-500' ?> mb-1">
                            <?= $day ?>
                        </div>
                        <?php foreach ($items as $it): ?>
                            <div class="text-[10px] mb-0.5 px-1 py-0.5 rounded truncate
                                <?php
                                if ($it['status'] === 'graded') echo 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
                                elseif ($it['status'] === 'submitted') echo 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
                                else echo 'bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-300';
                                ?>"
                                title="<?= e($it['title']) ?>">
                                <?= e(mb_substr($it['title'], 0, 20)) ?><?= mb_strlen($it['title']) > 20 ? '…' : '' ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php
                $day++;
            endfor;
            ?>
        </div>
    </div>

    <!-- Легенда -->
    <div class="mt-4 flex gap-4 text-xs text-slate-500 flex-wrap">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-brand-200"></span> В работе</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-200"></span> Сдано на проверку</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-emerald-200"></span> Оценено</span>
    </div>
</main>

<script src="assets/app.js"></script>
<script src="assets/icons.js"></script>
</body>
</html>
