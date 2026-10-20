<?php
require __DIR__ . '/config.php';
$user = require_login();
if (!in_array($user['role'], ['teacher','admin'], true)) { header('Location: index.php'); exit; }
$isDark = $user['theme'] === 'dark';

// Считаем сдачи, ожидающие проверки
if ($user['role'] === 'admin') {
    $pendingCount = (int) db()->query("SELECT COUNT(*) FROM submissions WHERE status = 'submitted'")->fetchColumn();
} else {
    $stmt = db()->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.status = 'submitted' AND a.teacher_id = ?");
    $stmt->execute([$user['id']]);
    $pendingCount = (int) $stmt->fetchColumn();
}

// Список групп препода (для селекта "Кому выдать")
$stmt = db()->prepare("
    SELECT g.id, g.name,
           (SELECT COUNT(*) FROM user_groups WHERE group_id = g.id) AS students_cnt
    FROM study_groups g
    JOIN teacher_groups tg ON tg.group_id = g.id AND tg.teacher_id = ?
    ORDER BY g.name
");
$stmt->execute([$user['id']]);
$teacherGroups = $stmt->fetchAll();

// Если админ — он видит ВСЕ группы, не только свои
if ($user['role'] === 'admin') {
    $teacherGroups = db()->query("
        SELECT g.id, g.name,
               (SELECT COUNT(*) FROM user_groups WHERE group_id = g.id) AS students_cnt
        FROM study_groups g ORDER BY g.name
    ")->fetchAll();
}

// Список предметов препода для селекта
$stmt = db()->prepare('SELECT id, name, color FROM subjects WHERE user_id = ? ORDER BY name');
$stmt->execute([$user['id']]);
$teacherSubjects = $stmt->fetchAll();
?>

<html lang="ru" class="<?= $isDark ? 'dark' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Кабинет преподавателя</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: { 50: '#f5f3ff', 100: '#ede9fe', 400: '#a78bfa', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9' } } } } }</script>
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 page-fade-in">
<?= impersonate_banner() ?>

<header class="header-gradient bg-white dark:bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold gradient-text">Кабинет преподавателя</h1>
            <span class="text-xs px-2.5 py-1 rounded-full bg-gradient-to-r from-brand-500 to-brand-600 text-white font-medium shadow-sm">Преподаватель</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="calendar.php" class="nav-pill nav-pill-calendar"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Календарь</a>
            <a href="teacher_stats.php" class="nav-pill nav-pill-stats"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg> Статистика</a>
            <a href="students.php" class="nav-pill nav-pill-students"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> Студенты</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="admin.php" class="nav-pill nav-pill-admin"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M2 20h20l-3-11-5 4-2-9-2 9-5-4z"/></svg> Админка</a>
            <?php endif; ?>
            <button onclick="toggleTheme()" class="icon-pill"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
            <span class="text-sm text-slate-500"><?= e($user['name']) ?></span>
            <button onclick="toggleTheme()" class="icon-pill" title="Переключить тему"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
            <a href="logout.php" class="nav-pill nav-pill-admin">Выйти</a>
        </div>
    </div>
</header>

<!-- Вкладки -->
<div class="max-w-7xl mx-auto px-4 pt-4">
    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg w-fit">
        <button onclick="setTab('assignments')" id="tab-assignments" class="tab-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> Мои задания</button>
        <button onclick="setTab('submissions')" id="tab-submissions" class="tab-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg> На проверку <?php if ($pendingCount > 0): ?><span class="text-xs bg-red-500 text-white rounded-full px-2 py-0.5 pulse-badge"><?= $pendingCount ?></span><?php endif; ?></button>
    </div>
</div>

<main class="max-w-7xl mx-auto px-4 py-4">

    <!-- Вкладка: Мои задания -->
    <section id="pane-assignments">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Созданные задания</h2>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium shadow">+ Создать задание</button>
        </div>
        <div id="assignmentsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </section>

    <!-- Вкладка: На проверку -->
    <section id="pane-submissions" class="hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Сдачи студентов</h2>
            <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                <button onclick="loadSubmissions('submitted')" id="f-submitted" class="px-3 py-1 rounded-md text-sm bg-white dark:bg-slate-900 shadow">На проверку</button>
                <button onclick="loadSubmissions('graded')"    id="f-graded" class="px-3 py-1 rounded-md text-sm">Проверенные</button>
                <button onclick="loadSubmissions('all')"       id="f-all" class="px-3 py-1 rounded-md text-sm">Все</button>
            </div>
        </div>
        <div id="submissionsList" class="space-y-3"></div>
    </section>
</main>

<!-- Модалка создания задания -->
<div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-start justify-between">
            <h2 class="text-xl font-bold">Новое задание</h2>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
        </div>

        <form id="createForm" onsubmit="createAssignment(event)" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Название *</label>
                <input type="text" name="title" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Описание задания</label>
                <textarea name="description" rows="5" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Дедлайн</label>
                    <input type="date" name="deadline_at" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Макс. балл</label>
                    <input type="number" name="grade_max" value="100" min="1" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Предмет</label>
                    <select name="subject_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
                        <option value="">— без предмета —</option>
                        <?php foreach ($teacherSubjects as $subj): ?>
                            <option value="<?= $subj['id'] ?>"><?= e($subj['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="allow_resubmit" checked>
                    Разрешить пересдачу после проверки
                </label>
            </div>

            <!-- Прикрепление файлов -->
            <div class="mt-3">
                <label class="block text-sm font-medium mb-1">📎 Файлы к заданию (методички)</label>
                <label for="createFilesInput" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-900/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-6 h-6 mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        <p class="text-xs text-slate-500 dark:text-slate-400"><span class="font-semibold text-brand-600 dark:text-brand-400">Нажмите</span>, чтобы выбрать файлы</p>
                        <p class="text-[10px] text-slate-400 mt-1" id="fileNamesDisplay">Файлы не выбраны</p>
                    </div>
                    <input type="file" id="createFilesInput" multiple class="hidden" onchange="document.getElementById('fileNamesDisplay').textContent = this.files.length ? Array.from(this.files).map(f => f.name).join(', ') : 'Файлы не выбраны'">
                </label>
            </div>

            <!-- Кому выдать -->
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <label class="block text-sm font-medium mb-2">Кому выдать</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="target_mode" value="all" onchange="onTargetChange()">
                        Всем студентам
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="target_mode" value="group" onchange="onTargetChange()">
                        По группе
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="target_mode" value="my_students" onchange="onTargetChange()">
                        Моим студентам (привязанным)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="target_mode" value="manual" checked onchange="onTargetChange()">
                        Выбрать вручную
                    </label>
                </div>

                <div id="groupInput" class="hidden mt-2">
                    <select name="group_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
                        <option value="">— выбери группу —</option>
                        <?php foreach ($teacherGroups as $tg): ?>
                            <option value="<?= $tg['id'] ?>"><?= e($tg['name']) ?> (<?= (int)$tg['students_cnt'] ?> чел.)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="studentsChecklist" class="hidden mt-2 max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg p-2">
                    <div class="text-xs text-slate-500 mb-1">Студенты:</div>
                    <div id="studentsChecklistInner"></div>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">Создать</button>
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модалка сдачи -->
<div id="subModal" class="hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 id="subTitle" class="text-xl font-bold"></h2>
                <div id="subStudent" class="text-sm text-slate-500"></div>
            </div>
            <button onclick="closeSubModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
        </div>

        <div>
            <h3 class="font-semibold text-sm mb-1">Работа студента</h3>
            <div id="subText" class="text-sm bg-slate-100 dark:bg-slate-900 rounded-lg p-3 whitespace-pre-wrap break-words"></div>
        </div>

        <div>
            <h3 class="font-semibold text-sm mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> Файлы студента</h3>
            <div id="subFiles" class="space-y-1 text-sm"></div>
        </div>

                <!-- Блок файлов преподавателя -->
        <div class="mt-4 border-t border-slate-200 dark:border-slate-700 pt-4">
            <h3 class="font-semibold text-sm mb-2">📎 Файлы задания (методички)</h3>
            <div id="teacherFilesList" class="space-y-1 text-sm mb-2 text-slate-500">Нет файлов</div>
            <label class="text-xs cursor-pointer text-brand-600 hover:text-brand-800 dark:text-brand-400">
                + Прикрепить файл
                <input type="file" class="hidden" onchange="uploadTeacherFile(this)">
            </label>
        </div>

        <div>
            <h3 class="font-semibold text-sm mb-2"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> Обсуждение</h3>
            <div id="subComments" class="space-y-2 mb-3 text-sm"></div>
            <div class="flex gap-2">
                <input type="text" id="subCommentInput" placeholder="Написать..."
                       class="flex-1 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none text-sm">
                <button type="button" onclick="sendSubComment()" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 rounded-lg text-sm">Отправить</button>
            </div>
        </div>

        <div id="gradeForm" class="border-t border-slate-200 dark:border-slate-700 pt-4 space-y-3">
            <h3 class="font-semibold text-sm">Оценка</h3>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs mb-1">Балл</label>
                    <input type="number" id="gradeVal" min="0" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none">
                </div>
                <div class="col-span-2 self-end text-sm text-slate-500">из <span id="gradeMax">100</span></div>
            </div>
            <div>
                <label class="block text-xs mb-1">Комментарий</label>
                <textarea id="gradeComment" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 outline-none"></textarea>
            </div>
            <button onclick="saveGrade()" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Сохранить оценку</button>
        </div>
    </div>
</div>

<script>
let STUDENTS = [];
let ASSIGNMENTS = [];
let SUBMISSIONS = [];
let CURRENT_SUB = null;
let TAB = 'assignments';

function setTab(name) {
    TAB = name;
    document.getElementById('pane-assignments').classList.toggle('hidden', name !== 'assignments');
    document.getElementById('pane-submissions').classList.toggle('hidden', name !== 'submissions');
    document.getElementById('tab-assignments').classList.toggle('active', name === 'assignments');
    document.getElementById('tab-submissions').classList.toggle('active', name === 'submissions');
    if (name === 'submissions') loadSubmissions('submitted');
}

async function loadStudents() {
    const res = await fetch('api.php?action=teacher_students');
    const json = await res.json();
    STUDENTS = json.data || [];
}

async function loadAssignments() {
    // Скелетон пока грузится
    document.getElementById('assignmentsList').innerHTML = `
        <div class="card p-4">
            <div class="skeleton h-5 w-3/4 mb-2"></div>
            <div class="skeleton h-4 w-1/3 mb-3"></div>
            <div class="skeleton h-3 w-full"></div>
        </div>
        <div class="card p-4">
            <div class="skeleton h-5 w-2/3 mb-2"></div>
            <div class="skeleton h-4 w-1/4 mb-3"></div>
            <div class="skeleton h-3 w-full"></div>
        </div>
        <div class="card p-4">
            <div class="skeleton h-5 w-3/4 mb-2"></div>
            <div class="skeleton h-4 w-1/3 mb-3"></div>
            <div class="skeleton h-3 w-full"></div>
        </div>
    `;
    const res = await fetch('api.php?action=teacher_assignments');
    const json = await res.json();
    ASSIGNMENTS = json.data || [];
    renderAssignments();
}

function renderAssignments() {
    const el = document.getElementById('assignmentsList');
    if (!ASSIGNMENTS.length) { el.innerHTML = '<p class="text-slate-500">Заданий пока нет</p>'; return; }
    el.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 stagger-fade';
    el.innerHTML = ASSIGNMENTS.map(a => `
        <div class="card hover-lift glow-hover bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-200 dark:border-slate-700 cursor-pointer" onclick="openAssignmentModal(${a.id})">
            <div class="flex items-start justify-between gap-2 mb-1">
                <div class="font-semibold">${escapeHtml(a.title)}</div>
                <button onclick="deleteAssignment(${a.id})" class="text-red-500 hover:text-red-700 text-sm shrink-0" title="Удалить задание">${icon("trash", 14)}</button>
            </div>
            <div class="text-sm text-slate-500 mb-2">${a.deadline_at ? 'до ' + a.deadline_at : 'без дедлайна'}</div>
            <div class="flex justify-between text-xs text-slate-500">
                <span>${icon("users", 14)} ${a.students_count}</span>
                <span class="${a.pending_count > 0 ? 'text-amber-600 font-semibold' : ''}">${icon("inbox", 14)} ${a.pending_count} на проверке</span>
            </div>
            ${a.students_count > 0 ? `
            <div class="mini-progress"><div class="mini-progress-bar" style="width: ${Math.round(((a.students_count - a.pending_count) / a.students_count) * 100)}%"></div></div>
            ` : ''}
        </div>
    `).join('');
}

// ---- Удаление задания ----
async function deleteAssignment(id) {
    if (!await confirmDialog('Удалить задание? Все сдачи, файлы и комментарии тоже будут удалены.', { title: 'Удаление задания', okText: 'Удалить' })) return;
    const res = await fetch('api.php?action=delete_assignment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    if (res.ok) {
        await loadAssignments();
    } else {
        const j = await res.json();
        showToast(j.error || 'Ошибка удаления', 'error');
    }
}

// =====================================================================
//  Массовая оценка: модалка задания
// =====================================================================
let CURRENT_ASSIGNMENT = null;

async function openAssignmentModal(assignmentId) {
    try {
        const res = await fetch('api.php?action=assignment_detail&id=' + assignmentId);
        const j = await res.json();
        if (!res.ok) { showToast(j.error || 'Ошибка', 'error'); return; }
        CURRENT_ASSIGNMENT = j.data;
        renderAssignmentModal();
        document.getElementById('assignmentModal').classList.remove('hidden');
    } catch (e) {
        showToast('Ошибка загрузки: ' + e.message, 'error');
    }
}

function renderAssignmentModal() {
    const a = CURRENT_ASSIGNMENT;
    document.getElementById('amTitle').textContent = a.title;
    document.getElementById('amMeta').textContent =
        (a.deadline_at ? 'Дедлайн: ' + a.deadline_at + ' · ' : '') +
        'Макс. балл: ' + a.grade_max +
        ' · Студентов: ' + (a.students ? a.students.length : 0);

    const body = document.getElementById('amBody');

    if (!a.students || !a.students.length) {
        body.innerHTML = '<p class="text-slate-500 text-sm">Этому заданию ещё никто не назначен.</p>';
        return;
    }

    const counts = { new: 0, in_progress: 0, submitted: 0, graded: 0 };
    a.students.forEach(s => { counts[s.status] = (counts[s.status] || 0) + 1; });

    body.innerHTML = `
        <div class="flex flex-wrap gap-2 mb-2 text-xs">
            <span class="badge-status badge-status-new">${icon("inbox", 14)} Новых: ${counts.new}</span>
            <span class="badge-status badge-status-progress">${icon("refresh", 14)} В работе: ${counts.in_progress}</span>
            <span class="badge-status badge-status-review">${icon("inbox", 14)} На проверке: ${counts.submitted}</span>
            <span class="badge-status badge-status-graded">${icon("check-circle", 14)} Оценено: ${counts.graded}</span>
        </div>

        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 dark:bg-slate-900 text-left">
                    <tr>
                        <th class="px-3 py-2">Студент</th>
                        <th class="px-3 py-2">Группа</th>
                        <th class="px-3 py-2">Статус</th>
                        <th class="px-3 py-2 w-28">Оценка</th>
                        <th class="px-3 py-2">Комментарий</th>
                        <th class="px-3 py-2 w-20">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    ${a.students.map(s => renderStudentRow(s, a.grade_max)).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderStudentRow(s, gradeMax) {
    const statusLabels = {
        new:         { txt: `${icon("inbox", 14)} Новое`,        cls: 'badge-status badge-status-new' },
        in_progress: { txt: `${icon("refresh", 14)} В работе`,     cls: 'badge-status badge-status-progress' },
        submitted:   { txt: `${icon("inbox", 14)} На проверке`,  cls: 'badge-status badge-status-review' },
        graded:      { txt: `${icon("check-circle", 14)} Оценено`,      cls: 'badge-status badge-status-graded' },
    };
    const st = statusLabels[s.status] || { txt: s.status, cls: 'bg-slate-100' };
    const canGrade = s.status === 'submitted' || s.status === 'graded';

    return `
        <tr class="border-t border-slate-200 dark:border-slate-700 table-row-anim">
            <td class="px-3 py-2">
                <div class="font-medium">${escapeHtml(s.name)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(s.email || '')}</div>
            </td>
            <td class="px-3 py-2 text-slate-500">${s.group_name ? escapeHtml(s.group_name) : '—'}</td>
            <td class="px-3 py-2"><span class="badge ${st.cls}">${st.txt}</span></td>
            <td class="px-3 py-2">
                ${canGrade ? `
                    <div class="flex items-center gap-1">
                        <input type="number" min="0" max="${gradeMax}"
                               class="input input-sm grade-input"
                               data-sub-id="${s.submission_id}"
                               value="${s.grade ?? ''}"
                               placeholder="—">
                        <span class="text-xs text-slate-400">/${gradeMax}</span>
                    </div>
                ` : '<span class="text-xs text-slate-400">—</span>'}
            </td>
            <td class="px-3 py-2">
                ${canGrade ? `
                    <input type="text" class="input input-sm comment-input"
                           data-sub-id="${s.submission_id}"
                           value="${escapeHtml(s.teacher_comment || '')}"
                           placeholder="—">
                ` : '<span class="text-xs text-slate-400">—</span>'}
            </td>
            <td class="px-3 py-2">
                <button onclick="openSubModal(${s.submission_id})"
                        class="btn btn-ghost btn-sm" title="Открыть работу">${icon("eye", 14)}</button>
            </td>
        </tr>
    `;
}

function closeAssignmentModal() {
    document.getElementById('assignmentModal').classList.add('hidden');
    CURRENT_ASSIGNMENT = null;
}

async function saveBulkGrades() {
    if (!CURRENT_ASSIGNMENT) return;

    const items = [];
    document.querySelectorAll('.grade-input').forEach(inp => {
        const subId = parseInt(inp.dataset.subId);
        const grade = parseInt(inp.value);
        if (isNaN(grade)) return;
        const commentInp = document.querySelector('.comment-input[data-sub-id="' + subId + '"]');
        items.push({
            id: subId,
            grade: grade,
            teacher_comment: commentInp ? commentInp.value : ''
        });
    });

    if (!items.length) {
        showToast('Нет оценок для сохранения', 'warning');
        return;
    }

    const btn = document.getElementById('amSaveBtn');
    btn.classList.add('btn-loading');

    try {
        const res = await fetch('api.php?action=bulk_grade', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items })
        });
        const j = await res.json();
        btn.classList.remove('btn-loading');

        if (!res.ok) { showToast(j.error || 'Ошибка', 'error'); return; }

        showToast(icon('check-circle', 14) + ' Сохранено оценок: ' + j.data.updated, 'success', 2500);

        if (j.errors && j.errors.length) {
            console.warn('Ошибки:', j.errors);
            showToast('Пропущено: ' + j.errors.length + ' (см. консоль)', 'warning', 4000);
        }

        await loadAssignments();
        await openAssignmentModal(CURRENT_ASSIGNMENT.id);
    } catch (e) {
        btn.classList.remove('btn-loading');
        showToast('Ошибка: ' + e.message, 'error');
    }
}

async function loadSubmissions(filter) {
    // Скелетон пока грузится
    document.getElementById('submissionsList').innerHTML = `
        <div class="card p-4">
            <div class="skeleton h-5 w-1/3 mb-2"></div>
            <div class="skeleton h-4 w-1/4"></div>
        </div>
        <div class="card p-4">
            <div class="skeleton h-5 w-1/3 mb-2"></div>
            <div class="skeleton h-4 w-1/4"></div>
        </div>
    `;
    ['submitted','graded','all'].forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        el.classList.toggle('bg-white', f === filter);
        el.classList.toggle('dark:bg-slate-900', f === filter);
        el.classList.toggle('shadow', f === filter);
    });
    const res = await fetch('api.php?action=list_submissions&filter=' + filter);
    const json = await res.json();
    SUBMISSIONS = json.data || [];
    renderSubmissions();
}

function renderSubmissions() {
    const el = document.getElementById('submissionsList');
    if (!SUBMISSIONS.length) { el.innerHTML = '<p class="text-slate-500">Сдач нет</p>'; return; }
    el.innerHTML = SUBMISSIONS.map(s => `
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-700 flex justify-between items-center gap-4 cursor-pointer hover:shadow-md"
             onclick="openSubModal(${s.id})">
            <div>
                <div class="font-semibold">${escapeHtml(s.title)}</div>
                <div class="text-sm text-slate-500">${escapeHtml(s.student_name)} ${s.group_name ? '· ' + escapeHtml(s.group_name) : ''}</div>
                ${formatDate(s.submitted_at) ? `<div class="text-xs text-slate-400">Сдано: ${formatDate(s.submitted_at)}</div>` : ''}
            </div>
            <div class="text-right">
                ${s.status === 'graded' ? `<div class="text-emerald-600 font-bold">${s.grade} / ${s.grade_max}</div>` : `<div class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">На проверке</div>`}
            </div>
        </div>
    `).join('');
}

// ---- Создание задания ----
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    renderStudentsChecklist();
    onTargetChange();
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createForm').reset();
}

function onTargetChange() {
    const mode = document.querySelector('input[name="target_mode"]:checked').value;
    document.getElementById('groupInput').classList.toggle('hidden', mode !== 'group');
    document.getElementById('studentsChecklist').classList.toggle('hidden', mode !== 'manual');
}

function renderStudentsChecklist() {
    const inner = document.getElementById('studentsChecklistInner');
    if (!STUDENTS.length) { inner.innerHTML = '<p class="text-xs text-slate-500">Нет студентов</p>'; return; }
    inner.innerHTML = STUDENTS.map(s => `
        <label class="flex items-center gap-2 py-0.5 text-sm">
            <input type="checkbox" class="student-checkbox" value="${s.id}">
            ${escapeHtml(s.name)} <span class="text-xs text-slate-400">${escapeHtml(s.group_name || '')}</span>
        </label>
    `).join('');
}

async function createAssignment(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    const targetMode = fd.get('target_mode');

    const payload = {
        title: fd.get('title'),
        description: fd.get('description'),
        subject_id: fd.get('subject_id') || null,
        deadline_at: fd.get('deadline_at') || null,
        grade_max: parseInt(fd.get('grade_max')) || 100,
        allow_resubmit: form.querySelector('[name="allow_resubmit"]').checked ? 1 : 0,
        target_mode: targetMode,
    };
    if (targetMode === 'group') payload.group_id = parseInt(fd.get('group_id')) || null;
    if (targetMode === 'manual') {
        payload.student_ids = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(c => parseInt(c.value));
    }

    const res = await fetch('api.php?action=create_assignment', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    if (res.ok) {
    const result = await res.json();
    const newAssignmentId = result.data.id; // ID только что созданного задания

    // Загружаем выбранные файлы
    const fileInput = document.getElementById('createFilesInput');
    if (fileInput.files.length > 0) {
        for (let i = 0; i < fileInput.files.length; i++) {
            const fd = new FormData();
            fd.append('owner_type', 'assignment');
            fd.append('owner_id', newAssignmentId);
            fd.append('file', fileInput.files[i]);
            await fetch('upload.php', { method: 'POST', body: fd });
        }
        showToast('Задание и файлы успешно созданы!', 'success');
    } else {
        showToast('Задание создано', 'success');
    }

    fileInput.value = ''; // Очищаем поле
    closeCreateModal();
    await loadAssignments();
}
    else { const j = await res.json(); showToast(j.error || 'Ошибка', 'error'); }
}

// ---- Просмотр сдачи ----
async function openSubModal(subId) {
    const res = await fetch('api.php?action=submission_detail&id=' + subId);
    const json = await res.json();
    if (!res.ok) { showToast(json.error || 'Ошибка', 'error'); return; }
    CURRENT_SUB = json.data;

    document.getElementById('subTitle').textContent = CURRENT_SUB.title;
    document.getElementById('subStudent').textContent = '👤 ' + CURRENT_SUB.student_name + (CURRENT_SUB.group_name ? ' · ' + CURRENT_SUB.group_name : '');
    document.getElementById('subText').textContent = CURRENT_SUB.work_text || '(без текста)';

    const files = CURRENT_SUB.files || [];
    document.getElementById('subFiles').innerHTML = files.length
        ? files.map(f => `<a href="${f.url}" target="_blank" class="block text-brand-600 dark:text-brand-400 hover:underline">${icon("file-text", 14)} ${escapeHtml(f.filename)} <span class="text-xs text-slate-400">(${f.human})</span></a>`).join('')
        : '<span class="text-xs text-slate-400">Нет файлов</span>';

    renderSubComments(CURRENT_SUB.comments || []);

    document.getElementById('gradeVal').value = CURRENT_SUB.grade ?? '';
    document.getElementById('gradeMax').textContent = CURRENT_SUB.grade_max;
    document.getElementById('gradeComment').value = CURRENT_SUB.teacher_comment || '';

    document.getElementById('subModal').classList.remove('hidden');
}

function closeSubModal() {
    document.getElementById('subModal').classList.add('hidden');
    CURRENT_SUB = null;
}

function renderSubComments(rows) {
    const el = document.getElementById('subComments');
    if (!rows.length) { el.innerHTML = '<div class="text-xs text-slate-400">Нет комментариев</div>'; return; }
    el.innerHTML = rows.map(c => `
        <div class="bg-slate-100 dark:bg-slate-900 rounded-lg p-2">
            <div class="flex justify-between text-xs text-slate-500 mb-1">
                <span class="font-medium">${escapeHtml(c.author)}</span>
                <span>${formatDate(c.created_at)}</span>
            </div>
            <div class="text-sm break-words">${escapeHtml(c.body)}</div>
        </div>
    `).join('');
}

async function sendSubComment() {
    if (!CURRENT_SUB) return;
    const input = document.getElementById('subCommentInput');
    const body = input.value.trim();
    if (!body) return;
    const res = await fetch('api.php?action=add_comment', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ submission_id: CURRENT_SUB.id, body })
    });
    if (res.ok) {
        input.value = '';
        const refresh = await fetch('api.php?action=submission_detail&id=' + CURRENT_SUB.id).then(r => r.json());
        CURRENT_SUB = refresh.data;
        renderSubComments(CURRENT_SUB.comments || []);
    }
}

async function saveGrade() {
    if (!CURRENT_SUB) return;
    const grade = parseInt(document.getElementById('gradeVal').value);
    const comment = document.getElementById('gradeComment').value;
    if (isNaN(grade) || grade < 0) { showToast('Введи балл', 'error'); return; }
    const res = await fetch('api.php?action=grade_submission', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: CURRENT_SUB.id, grade, teacher_comment: comment })
    });
    if (res.ok) { closeSubModal(); loadSubmissions('graded'); loadAssignments(); }
    else { const j = await res.json(); showToast(j.error || 'Ошибка', 'error'); }
}

// ---- Тема ----
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    fetch('api.php?action=set_theme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: isDark ? 'dark' : 'light' })
    });
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
}

document.getElementById('subCommentInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendSubComment(); });

(async () => {
    await loadStudents();
    await loadAssignments();
    setTab('assignments');
})();
// --- Загрузка файлов преподом ---
window._currentAssignmentId = null;
const _origOpen = window.openAssignmentModal;
window.openAssignmentModal = async function(id) {
    window._currentAssignmentId = id;
    await _origOpen(id);
    loadTeacherFiles(id);
};

async function loadTeacherFiles(id) {
    const res = await fetch('api.php?action=list_files&owner_type=assignment&owner_id=' + id);
    const j = await res.json();
    const list = document.getElementById('teacherFilesList');
    if (!list) return;
    if (!j.data || !j.data.length) {
        list.innerHTML = '<span class="text-xs text-slate-400">Нет файлов</span>';
        return;
    }
    list.innerHTML = j.data.map(f => `
        <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900 rounded px-2 py-1 mb-1">
            <a href="${f.url}" target="_blank" class="text-brand-600 hover:underline truncate">${f.filename}</a>
            <button onclick="deleteTeacherFile(${f.id})" class="text-red-500 text-xs">✕</button>
        </div>
    `).join('');
}

async function uploadTeacherFile(input) {
    if (!input.files[0] || !window._currentAssignmentId) return;
    const fd = new FormData();
    fd.append('owner_type', 'assignment');
    fd.append('owner_id', window._currentAssignmentId);
    fd.append('file', input.files[0]);
    const res = await fetch('upload.php', { method: 'POST', body: fd });
    if (res.ok) {
        loadTeacherFiles(window._currentAssignmentId);
        showToast('Файл загружен', 'success');
    } else {
        const j = await res.json();
        showToast(j.error || 'Ошибка загрузки', 'error');
    }
    input.value = '';
}

async function deleteTeacherFile(fileId) {
    if (!confirm('Удалить файл?')) return;
    const res = await fetch('api.php?action=delete_file', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: fileId })
    });
    if (res.ok) loadTeacherFiles(window._currentAssignmentId);
}
</script>
<!-- Модалка: студенты задания + массовая оценка -->
<div id="assignmentModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-5xl shadow-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 id="amTitle" class="text-xl font-bold"></h2>
                <div id="amMeta" class="text-sm text-slate-500"></div>
            </div>
            <button onclick="closeAssignmentModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
        </div>

        <div id="amBody" class="space-y-4"></div>

        <div class="flex gap-2 pt-2 border-t border-slate-200 dark:border-slate-700">
            <button onclick="saveBulkGrades()" id="amSaveBtn" class="flex-1 btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block align-[-0.15em] " aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Сохранить все оценки
            </button>
            <button onclick="closeAssignmentModal()" class="btn btn-secondary">Закрыть</button>
        </div>
    </div>
</div>
<script src="assets/app.js"></script>
<script src="assets/icons.js"></script>
</body>
</html>
