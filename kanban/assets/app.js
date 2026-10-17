// =====================================================================
//  Study Kanban — общий клиентский JS
// =====================================================================

(function () {
    'use strict';

    // -------- Контейнер для тостов --------
    function ensureToastContainer() {
        let el = document.getElementById('toastContainer');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toastContainer';
            document.body.appendChild(el);
        }
        return el;
    }

    // -------- Иконки для типов --------
    const ICONS = {
        success: '✅',
        error:   '⛔',
        info:    'ℹ️',
        warning: '⚠️',
    };

    /**
     * Показать всплывающее уведомление.
     * @param {string} message
     * @param {'success'|'error'|'info'|'warning'} type
     * @param {number} duration — мс, 0 = не закрывать
     */
    function showToast(message, type = 'info', duration = 3000) {
        const container = ensureToastContainer();

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<span style="font-size:16px;line-height:1">' + (ICONS[type] || '•') + '</span>' +
            '<span style="flex:1">' + escapeHtml(message) + '</span>' +
            '<button type="button" style="opacity:.5;cursor:pointer;background:none;border:none;color:inherit;font-size:16px;line-height:1;padding:0 4px">×</button>';

        const closeBtn = toast.querySelector('button');
        const remove = () => {
            toast.classList.add('toast-out');
            setTimeout(() => toast.remove(), 200);
        };
        closeBtn.addEventListener('click', remove);

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(remove, duration);
        }
        return remove;
    }

    // -------- Экранирование --------
    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    // -------- Плавное появление модалок --------
    // Автоматически вешаем классы на .fixed модалки при открытии/закрытии
    // Работает через MutationObserver — не надо менять код везде
    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            if (m.type !== 'attributes' || m.attributeName !== 'class') continue;
            const el = m.target;
            if (!el.classList.contains('fixed')) continue;

            const isHidden = el.classList.contains('hidden');
            const backdrop = el;

            if (!isHidden && !backdrop.dataset.animated) {
                backdrop.dataset.animated = '1';
                backdrop.classList.add('modal-backdrop');
                // Первый div внутри — панель
                const panel = el.firstElementChild;
                if (panel) panel.classList.add('modal-panel');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.fixed.inset-0').forEach(el => {
            observer.observe(el, { attributes: true, attributeFilter: ['class'] });
        });
    });

    // -------- Красивый confirm-диалог --------
    function confirmDialog(message, opts = {}) {
        const title = opts.title || 'Подтверждение';
        const okText = opts.okText || 'OK';
        const cancelText = opts.cancelText || 'Отмена';
        const danger = opts.danger !== false;

        return new Promise((resolve) => {
            const backdrop = document.createElement('div');
            backdrop.className = 'fixed inset-0 bg-black/50 z-[9998] flex items-center justify-center p-4';
            backdrop.innerHTML = `
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm p-6 modal-panel">
                    <h3 class="text-lg font-bold mb-2 text-slate-900 dark:text-slate-100">${escapeHtml(title)}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mb-5 whitespace-pre-wrap">${escapeHtml(message)}</p>
                    <div class="flex gap-2">
                        <button type="button" data-act="ok" class="btn ${danger ? 'btn-danger' : 'btn-primary'} flex-1">${escapeHtml(okText)}</button>
                        <button type="button" data-act="cancel" class="btn btn-secondary flex-1">${escapeHtml(cancelText)}</button>
                    </div>
                </div>
            `;

            function close(result) {
                backdrop.classList.add('opacity-0');
                setTimeout(() => {
                    backdrop.remove();
                    document.removeEventListener('keydown', onKey);
                    resolve(result);
                }, 150);
            }

            function onKey(e) {
                if (e.key === 'Escape') close(false);
                if (e.key === 'Enter')  close(true);
            }

            backdrop.addEventListener('click', (e) => {
                const act = e.target.closest('[data-act]');
                if (act) close(act.dataset.act === 'ok');
                else if (e.target === backdrop) close(false);
            });

            document.addEventListener('keydown', onKey);
            document.body.appendChild(backdrop);

            setTimeout(() => backdrop.querySelector('[data-act="ok"]').focus(), 50);
        });
    }

    // -------- Экспорт в window --------
    // Закрытие модалок с анимацией
    // Перехватываем класс hidden и делаем плавный выход
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-close-modal], [onclick*="classList.add(\'hidden\')"]');
        // Просто добавим плавность через CSS-transition (уже есть)
    }, true);

    window.showToast = showToast;
    window.escapeHtml = escapeHtml;
    window.confirmDialog = confirmDialog;

    // -------- Перехватываем alert() и заменяем на toast? --------
    // Осторожно: не будем, чтобы не сломать confirm() для удаления.
    // Пусть каждый файл сам вызывает showToast() где надо.
})();

// =====================================================================
//  Форматирование дат в JS (аналог fmt_date из PHP)
// =====================================================================
(function () {
    'use strict';

    const MONTHS = ['янв', 'фев', 'мар', 'апр', 'май', 'июн',
                    'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    /**
     * Форматирует "2026-10-17 06:37:10" → "17 окт, 06:37"
     * Или "Сегодня, 06:37" / "Вчера, 06:37"
     */
    function formatDate(datetime, opts = {}) {
        if (!datetime) return '—';
        const d = new Date(datetime.replace(' ', 'T'));
        if (isNaN(d.getTime())) return datetime;

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const diffDays = Math.round((today - target) / 86400000);

        const time = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

        if (diffDays === 0)  return 'Сегодня, ' + time;
        if (diffDays === 1)  return 'Вчера, ' + time;

        const day = d.getDate();
        const month = MONTHS[d.getMonth()];
        if (d.getFullYear() === now.getFullYear()) {
            return day + ' ' + month + ', ' + time;
        }
        return day + ' ' + month + ' ' + d.getFullYear() + ', ' + time;
    }

    window.formatDate = formatDate;
})();
