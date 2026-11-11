(function () {
    'use strict';
    const { createElement: h, useState, useEffect } = wp.element;
    const api = wp.apiFetch;
    function Dashboard({ onOpen, onSection }) {
        const [counts, setCounts] = useState(null);
        const [events, setEvents] = useState(null);
        const [feedback, setFeedback] = useState(null);
        const [error, setError] = useState('');
        useEffect(() => {
            setError('');
            Promise.all([
                api({ path: '/wp/v2/pages?per_page=1&status=any', parse: false }),
                api({ path: '/wp/v2/posts?per_page=1&status=any', parse: false }),
                api({ path: '/wp/v2/' + nutsConfig.catalogType + '?per_page=1&status=any', parse: false }).catch(() => null),
                api({ path: '/wp/v2/comments?per_page=1&status=hold', parse: false }).catch(() => null),
            ]).then(async ([pages, posts, catalog, comments]) => {
                const total = r => Number(r.headers.get('X-WP-Total')) || 0;
                setCounts({
                    pages: total(pages), posts: total(posts),
                    catalog: catalog ? total(catalog) : null,
                    comments: comments ? total(comments) : null,
                });
            }).catch(e => setError(e.message || 'Не удалось загрузить сводку.'));
            if (nutsConfig.canManage) {
                api({ path: '/nuts/v1/feedback' }).then(setFeedback).catch(() => setFeedback([]));
            }
            fetch(nutsCommunityConfig.root + 'notifications', { credentials: 'same-origin', headers: { 'X-WP-Nonce': nutsCommunityConfig.nonce } })
                .then(r => r.ok ? r.json() : Promise.reject()).then(d => setEvents(d.items.slice(0, 5))).catch(() => setEvents([]));
        }, []);
        const checks = [
            ['title', 'Название сайта', 'site'], ['description', 'Описание сайта', 'site'], ['icon', 'Фавикон сайта', 'favicon'],
            ['front', 'Главная страница', 'site'], ['contacts', 'Контакты и реквизиты', 'contacts'], ['appearance', 'Цветовая гамма', 'admin-appearance'],
        ];
        const done = checks.filter(([key]) => nutsConfig.onboarding[key]).length;
        const cards = [
            ['Страницы', counts && counts.pages, 'Контент сайта', 'pages'],
            ['Записи', counts && counts.posts, 'Блог', 'blog'],
            nutsConfig.catalogType !== 'nuts_product' || true ? ['Товары', counts && counts.catalog, 'Каталог товаров', 'catalog'] : null,
            ['Комментарии на проверке', counts && counts.comments, 'Обсуждения', 'system'],
        ].filter(Boolean);
        return h('div', { className: 'nuts-section-grid' },
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Сайт в цифрах'),
                error && h('p', { role: 'alert', className: 'nuts-error' }, error),
                h('div', { className: 'nuts-dash-counts' }, cards.map(([label, value, hint, target]) => h('button', { key: label, type: 'button', className: 'nuts-dash-count', onClick: () => target === 'system' ? onOpen({ label: 'Комментарии', url: nutsConfig.admin + 'edit-comments.php' }) : onSection({ id: target, label, url: null, group: 'Контент' }) },
                    h('strong', null, value === null || value === undefined ? '—' : value), h('span', null, label), h('small', null, hint))))),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Чек-лист администратора'), h('p', null, 'Выполнено ' + done + ' из ' + checks.length + '. Откройте раздел прямо из списка.'),
                h('ul', { className: 'nuts-checklist' }, checks.map(([key, label, target]) => {
                    const ok = nutsConfig.onboarding[key];
                    return h('li', { key }, h('button', { type: 'button', className: ok ? 'nuts-check nuts-check-done' : 'nuts-check', onClick: () => onSection({ id: target === 'favicon' || target === 'admin-appearance' ? target : target, label, url: null }) },
                        h('span', { className: 'nuts-check-icon', 'aria-hidden': true }, ok ? '✓' : ''), h('span', null, label)));
                }))),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Последние события'),
                events === null ? h('p', { role: 'status' }, 'Загружаем…') :
                events.length ? h('ul', { className: 'nuts-event-list' }, events.map(item => h('li', { key: item.id, className: item.severity }, h('a', { href: item.url }, item.title), h('small', null, item.time ? new Date(item.time * 1000).toLocaleString('ru-RU') : '')))) : h('p', null, 'Событий пока нет.'),
                h('a', { className: 'nuts-section-action', href: '#nuts=notifications' }, 'Все уведомления →')),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Заявки с формы'),
                !nutsConfig.canManage ? h('p', null, 'Журнал заявок доступен администратору.') :
                feedback === null ? h('p', { role: 'status' }, 'Загружаем…') :
                feedback.length ? h(wp.element.Fragment, null, h('p', null, 'Всего: ' + feedback.length + ' · непрочитанных: ' + feedback.filter(x => !x.read).length), h('a', { className: 'nuts-section-action', href: '#nuts=feedback' }, 'Открыть журнал →')) :
                h('p', null, 'Заявок нет. Разместите [nuts_form] на сайте — пример в разделе «Конструктор».')),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Быстрые настройки'),
                h('div', { className: 'nuts-section-grid' }, [
                    ['Настройки сайта', 'Название, главная страница, комментарии, обслуживание.', 'site'],
                    ['Контакты и реквизиты', 'Телефон, адрес и соцсети для всего сайта.', 'contacts'],
                    ['Фавикон', 'Значок вкладки браузера.', 'favicon'],
                    ['Перенаправления', 'Старые адреса ведут на новые.', 'redirects'],
                    ['SEO главной', 'Заголовок и описание для поисковиков.', 'seo'],
                ].map(([label, hint, id]) => h('button', { key: id, type: 'button', className: 'nuts-quick', onClick: () => onSection({ id, label, url: null }) }, h('strong', null, label), h('small', null, hint))))));
    }
    window.nutsDashboard = { Dashboard };
})();
