(function () {
    'use strict';
    const { createElement: h, useState, useEffect } = wp.element;
    const api = wp.apiFetch;
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    function useSettings(path) {
        const [data, setData] = useState(null);
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        async function load() { setError(''); try { setData(await api({ path })); } catch (e) { setError(e.message || 'Не удалось загрузить данные.'); } }
        useEffect(() => { load(); }, []);
        async function save(payload, done) {
            setBusy(true); setError(''); setNotice('');
            try { const value = await api({ path, method: 'POST', data: payload }); setData(value); setNotice('Сохранено.'); if (done) done(value); }
            catch (e) { setError(e.message || 'Не удалось сохранить.'); }
            finally { setBusy(false); }
        }
        return { data, setData, busy, error, notice, load, save };
    }
    function Field({ label, hint, children }) {
        return h('label', { className: 'nuts-field' }, h('span', null, label), children, hint && h('small', null, hint));
    }
    function Messages({ error, notice }) {
        return h(wp.element.Fragment, null,
            error && h('p', { role: 'alert', className: 'nuts-error' }, error),
            notice && h('p', { role: 'status', className: 'nuts-notice' }, notice));
    }
    function Site() {
        const s = useSettings('/nuts/v1/site-settings');
        const [draft, setDraft] = useState(null);
        useEffect(() => { if (s.data && !draft) setDraft({ title: s.data.title, description: s.data.description, admin_email: s.data.admin_email, timezone: s.data.timezone, language: s.data.language }); }, [s.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: s.error ? 'alert' : 'status' }, s.error || 'Загружаем настройки…'), s.error && h('button', { onClick: s.load }, 'Повторить'));
        const dirty = draft.timezone !== s.data.timezone || draft.language !== s.data.language || draft.title !== s.data.title || draft.description !== s.data.description || draft.admin_email !== s.data.admin_email;
        return h('form', { className: 'nuts-section-tile', onSubmit: e => { e.preventDefault(); s.save({ title: draft.title, description: draft.description, admin_email: draft.admin_email, timezone: draft.timezone, language: draft.language, discussions: s.data.discussions, front: s.data.front }); } },
            h('h2', null, 'Сайт'),
            h('p', null, 'Основные данные, которые отображаются на страницах и в письмах сайта.'),
            h('div', { className: 'nuts-form-grid' },
                h(Field, { label: 'Название сайта' }, h('input', { value: draft.title, onChange: e => setDraft({ ...draft, title: e.target.value }), required: true, disabled: s.busy })),
                h(Field, { label: 'Электронная почта администратора', hint: 'Сюда приходят уведомления и заявки с форм.' }, h('input', { type: 'email', value: draft.admin_email, onChange: e => setDraft({ ...draft, admin_email: e.target.value }), required: true, disabled: s.busy })),
                h(Field, { label: 'Описание сайта' }, h('input', { value: draft.description, onChange: e => setDraft({ ...draft, description: e.target.value }), disabled: s.busy })),
                h(Field, { label: 'Часовой пояс' }, h('select', { value: draft.timezone, onChange: e => setDraft({ ...draft, timezone: e.target.value }), disabled: s.busy },
                    h('option', { value: '' }, 'Выберите часовой пояс'),
                    (s.data.timezones || []).map(x => h('option', { key: x, value: x }, x.replace(/_/g, ' '))),
                    !s.data.timezones.includes(draft.timezone) && draft.timezone && h('option', { value: draft.timezone }, draft.timezone))),
                h(Field, { label: 'Язык сайта' }, h('select', { value: draft.language, onChange: e => setDraft({ ...draft, language: e.target.value }), disabled: s.busy },
                    (s.data.languages || []).map(x => h('option', { key: x, value: x }, x))))),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: s.busy || !dirty }, s.busy ? 'Сохраняем…' : 'Сохранить'), tz && draft.timezone !== tz ? h('button', { type: 'button', disabled: s.busy, onClick: () => setDraft({ ...draft, timezone: tz }) }, 'Использовать пояс устройства (' + tz + ')') : null),
            h(Messages, { error: s.error, notice: s.notice }));
    }
    function Discussion() {
        const s = useSettings('/nuts/v1/site-settings');
        const [draft, setDraft] = useState(null);
        useEffect(() => { if (s.data && !draft) setDraft({ ...s.data.discussions }); }, [s.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: 'status' }, 'Загружаем настройки…'));
        const keys = [['default_comment_status', 'Разрешить комментарии', 'Новые материалы можно комментировать по умолчанию.'], ['comment_registration', 'Только для зарегистрированных', 'Комментировать смогут только пользователи с учётной записью.'], ['comment_moderation', 'Премодерация', 'Комментарий появится после проверки администратором.'], ['close_comments_for_old_posts', 'Закрывать старые материалы', 'Комментарии отключаются через 14 дней после публикации.']];
        return h('form', { className: 'nuts-section-tile', onSubmit: e => { e.preventDefault(); s.save({ title: s.data.title, description: s.data.description, admin_email: s.data.admin_email, timezone: s.data.timezone, language: s.data.language, discussions: draft, front: s.data.front }); } },
            h('h2', null, 'Комментарии'),
            h('p', null, 'Управление обсуждениями на сайте одними переключателями.'),
            keys.map(([key, label, hint]) => h('label', { key, className: 'nuts-toggle' }, h('input', { type: 'checkbox', checked: !!draft[key], onChange: e => setDraft({ ...draft, [key]: e.target.checked }), disabled: s.busy }), h('span', { className: 'nuts-toggle-switch', 'aria-hidden': true }), h('span', null, h('strong', null, label), h('small', null, hint)))),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: s.busy }, s.busy ? 'Сохраняем…' : 'Сохранить')),
            h(Messages, { error: s.error, notice: s.notice }));
    }
    function FrontPage() {
        const s = useSettings('/nuts/v1/site-settings');
        const [pages, setPages] = useState([]);
        const [draft, setDraft] = useState(null);
        useEffect(() => {
            if (!s.data || draft) return;
            setDraft({ ...s.data.front });
            api({ path: '/wp/v2/pages?per_page=100&status=publish&orderby=title&order=asc' }).then(list => setPages(list)).catch(() => setPages([]));
        }, [s.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: 'status' }, 'Загружаем настройки…'));
        function pageSelect(value, onChange, allowEmpty) {
            return h('select', { value, onChange, disabled: s.busy },
                allowEmpty && h('option', { value: 0 }, '— Не выбрана —'),
                pages.map(p => h('option', { key: p.id, value: p.id }, p.title.rendered || 'Без названия')));
        }
        return h('form', { className: 'nuts-section-tile', onSubmit: e => { e.preventDefault(); s.save({ title: s.data.title, description: s.data.description, admin_email: s.data.admin_email, timezone: s.data.timezone, language: s.data.language, discussions: s.data.discussions, front: draft }); } },
            h('h2', null, 'Главная страница'),
            h('p', null, 'Что посетитель видит, открывая адрес сайта.'),
            h('div', { className: 'nuts-choice' },
                h('label', null, h('input', { type: 'radio', name: 'nuts-front', checked: draft.show_on_front === 'posts', onChange: () => setDraft({ ...draft, show_on_front: 'posts' }), disabled: s.busy }), h('span', null, h('strong', null, 'Последние записи'), h('small', null, 'Лента новостей сайта в обратном порядке.'))),
                h('label', null, h('input', { type: 'radio', name: 'nuts-front', checked: draft.show_on_front === 'page', onChange: () => setDraft({ ...draft, show_on_front: 'page', page_on_front: draft.page_on_front || (pages[0] ? pages[0].id : 0) }), disabled: s.busy }), h('span', null, h('strong', null, 'Статическая страница'), h('small', null, 'Обычная страница, которую вы создали и оформили.')))),
            draft.show_on_front === 'page' && h('div', { className: 'nuts-form-grid' },
                h(Field, { label: 'Главная страница' }, pageSelect(draft.page_on_front, e => setDraft({ ...draft, page_on_front: Number(e.target.value) }), false)),
                h(Field, { label: 'Страница записей', hint: 'Адрес, где показывается лента новостей, например /blog.' }, pageSelect(draft.page_for_posts, e => setDraft({ ...draft, page_for_posts: Number(e.target.value) }), true))),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: s.busy }, s.busy ? 'Сохраняем…' : 'Сохранить')),
            h(Messages, { error: s.error, notice: s.notice }));
    }
    function Maintenance() {
        const s = useSettings('/nuts/v1/maintenance');
        const [draft, setDraft] = useState(null);
        useEffect(() => { if (s.data && !draft) setDraft({ ...s.data }); }, [s.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: 'status' }, 'Загружаем…'));
        return h('form', { className: 'nuts-section-tile' + (draft.enabled ? ' nuts-maintenance-on' : ''), onSubmit: e => { e.preventDefault(); s.save(draft); } },
            h('h2', null, 'Режим обслуживания'),
            h('p', null, 'Гости увидят заглушку с ответом 503 «Сервис недоступен». Авторизованные пользователи с правами редактирования продолжают работать как обычно.'),
            h('label', { className: 'nuts-toggle' }, h('input', { type: 'checkbox', checked: draft.enabled, onChange: e => setDraft({ ...draft, enabled: e.target.checked }), disabled: s.busy }), h('span', { className: 'nuts-toggle-switch', 'aria-hidden': true }), h('span', null, h('strong', null, 'Сайт на обслуживании'), h('small', null, 'Включите, пока вносите важные изменения.'))),
            draft.enabled && h('div', { className: 'nuts-form-grid' },
                h(Field, { label: 'Заголовок заглушки' }, h('input', { value: draft.title, onChange: e => setDraft({ ...draft, title: e.target.value }), disabled: s.busy })),
                h(Field, { label: 'Сообщение' }, h('textarea', { value: draft.message, onChange: e => setDraft({ ...draft, message: e.target.value }), rows: 3, disabled: s.busy }))),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: s.busy }, s.busy ? 'Сохраняем…' : draft.enabled ? 'Включить обслуживание' : 'Сохранить')),
            h(Messages, { error: s.error, notice: s.notice }));
    }
    function SiteHub() {
        return h('div', { className: 'nuts-section-grid' }, h(Site), h(FrontPage), h(Discussion), h(Maintenance));
    }
    function Contacts() {
        const s = useSettings('/nuts/v1/contacts');
        const [draft, setDraft] = useState(null);
        useEffect(() => { if (s.data && !draft) setDraft({ ...s.data }); }, [s.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: s.error ? 'alert' : 'status' }, s.error || 'Загружаем контакты…'), s.error && h('button', { onClick: s.load }, 'Повторить'));
        const groups = [['phone', 'Телефон', 'email', 'Электронная почта', 'address', 'Адрес', 'working_hours', 'Часы работы'], ['vk', 'ВКонтакте (полный адрес https://…)', 'telegram', 'Telegram (https://t.me/…)', 'whatsapp', 'WhatsApp (https://wa.me/…)', 'youtube', 'YouTube (https://youtube.com/…)']];
        return h('form', { className: 'nuts-section-tile', onSubmit: e => { e.preventDefault(); s.save(draft); } },
            h('h2', null, 'Контакты и реквизиты'),
            h('p', null, 'Одно место для телефона в шапке, адреса в подвале и соцсетей. Значения выводятся в теме коротким кодом, например [nuts_contacts field="phone"].'),
            h('div', { className: 'nuts-form-grid' }, groups.flatMap(fields => fields.map((x, i) => i % 2 ? null : h(Field, { key: x, label: fields[i + 1] }, h('input', { value: draft[x], onChange: e => setDraft({ ...draft, [x]: e.target.value }), disabled: s.busy, placeholder: x === 'email' ? 'name@example.com' : '' }))).filter(Boolean))),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: s.busy }, s.busy ? 'Сохраняем…' : 'Сохранить контакты')),
            h(Messages, { error: s.error, notice: s.notice }));
    }
    window.nutsSiteSettings = { SiteHub, Contacts };
})();
