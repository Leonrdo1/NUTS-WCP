(function () {
    'use strict';
    const { createElement: h, useState, useEffect } = wp.element;
    const api = wp.apiFetch;
    function useResource(path) {
        const [data, setData] = useState(null);
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        async function load() { setError(''); try { setData(await api({ path })); } catch (e) { setError(e.message || 'Не удалось загрузить данные.'); } }
        useEffect(() => { load(); }, []);
        return { data, setData, busy, setBusy, error, notice, setNotice, load };
    }
    function Messages({ error, notice }) {
        return h(wp.element.Fragment, null,
            error && h('p', { role: 'alert', className: 'nuts-error' }, error),
            notice && h('p', { role: 'status', className: 'nuts-notice' }, notice));
    }
    function Redirects() {
        const r = useResource('/nuts/v1/redirects');
        const [items, setItems] = useState(null);
        useEffect(() => { if (r.data && !items) setItems(r.data.map(x => ({ ...x }))); }, [r.data]);
        if (!items) return h('article', { className: 'nuts-section-tile' }, h('p', { role: r.error ? 'alert' : 'status' }, r.error || 'Загружаем перенаправления…'));
        async function save() {
            r.setBusy(true); r.setError(''); r.setNotice('');
            try { const saved = await api({ path: '/nuts/v1/redirects', method: 'POST', data: { items } }); setItems(saved.map(x => ({ ...x }))); r.setNotice('Перенаправления сохранены.'); }
            catch (e) { r.setError(e.message || 'Не удалось сохранить.'); }
            finally { r.setBusy(false); }
        }
        const dirty = JSON.stringify(items) !== JSON.stringify(r.data);
        return h('article', { className: 'nuts-section-tile' },
            h('h2', null, 'Перенаправления'),
            h('p', null, 'Старые адреса ведут на новые. Полезно при переименовании страниц и переносе сайта. До 200 записей.'),
            h('table', { className: 'nuts-table-wrap nuts-mini-table' }, h('thead', null, h('tr', null, ['Откуда', 'Куда', 'Код', ''].map((x, i) => h('th', { key: i, scope: 'col' }, x)))),
                h('tbody', null, items.map((item, i) => h('tr', { key: i },
                    h('td', null, h('input', { value: item.from, placeholder: '/staryj-adr', onChange: e => setItems(items.map((x, j) => j === i ? { ...x, from: e.target.value } : x)), disabled: r.busy })),
                    h('td', null, h('input', { value: item.to, placeholder: '/novyj-adr или https://…', onChange: e => setItems(items.map((x, j) => j === i ? { ...x, to: e.target.value } : x)), disabled: r.busy })),
                    h('td', null, h('select', { value: item.code, onChange: e => setItems(items.map((x, j) => j === i ? { ...x, code: Number(e.target.value) } : x)), disabled: r.busy }, [301, 302, 307, 308].map(c => h('option', { key: c, value: c }, c)))),
                    h('td', null, h('button', { type: 'button', onClick: () => setItems(items.filter((_, j) => j !== i)), disabled: r.busy, 'aria-label': 'Удалить строку' }, '✕')))))),
            h('div', { className: 'nuts-section-actions' },
                h('button', { type: 'button', onClick: () => setItems([...items, { from: '', to: '', code: 301 }]), disabled: r.busy }, '+ Добавить'),
                h('button', { type: 'button', onClick: save, disabled: r.busy || !dirty }, r.busy ? 'Сохраняем…' : 'Сохранить перенаправления')),
            h(Messages, { error: r.error, notice: r.notice }));
    }
    function SeoHub() {
        const r = useResource('/nuts/v1/seo-home');
        const [draft, setDraft] = useState(null);
        useEffect(() => { if (r.data && !draft) setDraft({ ...r.data }); }, [r.data]);
        if (!draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: 'status' }, 'Загружаем…'));
        return h('form', { className: 'nuts-section-tile', onSubmit: async e => { e.preventDefault(); r.setBusy(true); r.setError(''); try { const v = await api({ path: '/nuts/v1/seo-home', method: 'POST', data: draft }); setDraft({ ...v }); r.setNotice('Сохранено.'); } catch (e2) { r.setError(e2.message || 'Не удалось сохранить.'); } finally { r.setBusy(false); } } },
            h('h2', null, 'SEO главной страницы'),
            h('p', null, 'Заголовок вкладки и описание для поисковиков. Для остальных страниц мета-поля хранятся в карточке страницы (ключи _nuts_meta_title и _nuts_meta_description); собственный экран редактора появится в следующем выпуске.'),
            h('label', { className: 'nuts-field' }, h('span', null, 'Заголовок (title)'), h('input', { value: draft.title, onChange: e => setDraft({ ...draft, title: e.target.value }), disabled: r.busy, maxLength: 70 }), h('small', null, (70 - draft.title.length) + ' символов осталось')),
            h('label', { className: 'nuts-field' }, h('span', null, 'Описание (description)'), h('textarea', { value: draft.description, onChange: e => setDraft({ ...draft, description: e.target.value }), rows: 3, disabled: r.busy, maxLength: 160 }), h('small', null, (160 - draft.description.length) + ' символов осталось')),
            h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: r.busy }, r.busy ? 'Сохраняем…' : 'Сохранить')),
            h(Messages, { error: r.error, notice: r.notice }));
    }
    function Feedback() {
        const r = useResource('/nuts/v1/feedback');
        const [busyId, setBusyId] = useState(0);
        if (!r.data) return h('article', { className: 'nuts-section-tile' }, h('p', { role: r.error ? 'alert' : 'status' }, r.error || 'Загружаем заявки…'));
        async function act(id, action) {
            setBusyId(id);
            r.setError('');
            try {
                if (action === 'delete') { await api({ path: '/nuts/v1/feedback/' + id, method: 'POST', data: { action } }); r.setData(r.data.filter(x => x.id !== id)); }
                else r.setData(await api({ path: '/nuts/v1/feedback/' + id, method: 'POST', data: { action } }));
            } catch (e) { r.setError(e.message || 'Не удалось выполнить действие.'); }
            finally { setBusyId(0); }
        }
        const unread = r.data.filter(x => !x.read).length;
        return h('article', { className: 'nuts-section-tile' },
            h('h2', null, 'Заявки с формы'), h('p', null, 'Сообщения, отправленные через форму [nuts_form] на сайте. Копия каждой заявки уходит на почту сайта.'),
            unread > 0 && h('p', { className: 'nuts-notice' }, 'Непрочитанных: ' + unread),
            r.data.length ? h('ul', { className: 'nuts-event-list' }, r.data.map(x => h('li', { key: x.id, className: x.read ? '' : 'nuts-entry-unread' },
                h('div', null, h('strong', null, x.name), ' ', x.email && h('a', { href: 'mailto:' + x.email }, x.email), h('small', null, new Date(x.time * 1000).toLocaleString('ru-RU'))),
                h('p', null, x.message),
                h('div', { className: 'nuts-section-actions' },
                    !x.read && h('button', { type: 'button', disabled: busyId === x.id, onClick: () => act(x.id, 'read') }, 'Отметить прочитанной'),
                    h('button', { type: 'button', disabled: busyId === x.id, onClick: () => act(x.id, 'delete') }, 'Удалить'))))) : h('p', null, 'Заявок пока нет. Разместите [nuts_form] на странице контактов.'),
            h(Messages, { error: r.error, notice: r.notice }));
    }
    function MediaTools() {
        const r = useResource('/nuts/v1/media-unused');
        const [replaceId, setReplaceId] = useState(0);
        const [message, setMessage] = useState('');
        if (!r.data) return h('article', { className: 'nuts-section-tile' }, h('p', { role: r.error ? 'alert' : 'status' }, r.error || 'Проверяем медиатеку…'));
        async function replace(id, file) {
            setReplaceId(id); setMessage(''); r.setError('');
            try {
                const body = new FormData(); body.append('file', file);
                await fetch(nutsConfig.nutsRest + 'media/' + id + '/replace', { method: 'POST', headers: { 'X-WP-Nonce': nutsConfig.nonce }, body, credentials: 'same-origin' });
                setMessage('Файл заменён. Адрес и все размеры обновлены.');
            } catch (e) { r.setError('Не удалось заменить файл.'); }
            finally { setReplaceId(0); }
        }
        return h('article', { className: 'nuts-section-tile' },
            h('h2', null, 'Порядок в медиатеке'),
            h('p', null, r.data.note),
            message && h('p', { role: 'status', className: 'nuts-notice' }, message),
            h(Messages, { error: r.error }),
            r.data.items.length ? h('ul', { className: 'nuts-media-list' }, r.data.items.map(x => h('li', { key: x.id },
                x.thumb ? h('img', { src: x.thumb, alt: '', width: 48, height: 48 }) : h('span', { className: 'nuts-media-empty' }, 'файл'),
                h('div', null, h('strong', null, x.title || 'Без названия'), h('small', null, new Date(x.date * 1000).toLocaleDateString('ru-RU'), ' · ', h('a', { href: x.url, target: '_blank', rel: 'noopener' }, 'открыть'))),
                h('label', { className: 'nuts-replace' }, replaceId === x.id ? 'Замена…' : 'Заменить файл', h('input', { type: 'file', accept: 'image/*', style: { display: 'none' }, disabled: replaceId === x.id, onChange: e => { if (e.target.files[0]) replace(x.id, e.target.files[0]); } }))))) : h('p', null, 'Возможно неиспользуемых изображений не найдено.'));
    }
    function ExportImport() {
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        async function importFile(file) {
            setError(''); setNotice('');
            try {
                const text = await file.text();
                const result = await api({ path: '/nuts/v1/import', method: 'POST', data: { data: text } });
                setNotice(result.applied.length ? 'Применено: ' + result.applied.join(', ') + '. Обновите страницу, чтобы увидеть изменения гаммы.' : 'В файле не найдено применимых настроек.');
            } catch (e) { setError(e.message || 'Не удалось импортировать файл.'); }
        }
        return h('article', { className: 'nuts-section-tile' },
            h('h2', null, 'Экспорт и импорт настроек'),
            h('p', null, 'Гамма админки, контакты, перенаправления и SEO главной — одним JSON-файлом. Изображения и контент не включаются.'),
            h('div', { className: 'nuts-section-actions' },
                h('a', { className: 'button button-secondary', href: nutsConfig.nutsRest + 'export&_wpnonce=' + nutsConfig.nonce, download: true }, 'Скачать экспорт'),
                h('label', { className: 'nuts-replace button button-secondary' }, 'Импорт из файла', h('input', { type: 'file', accept: 'application/json', style: { display: 'none' }, onChange: e => { if (e.target.files[0]) importFile(e.target.files[0]); e.target.value = ''; } }))),
            h(Messages, { error, notice }));
    }
    function Installer() {
        return h('article', { className: 'nuts-section-tile' },
            h('h2', null, 'Установщик стартовой сборки'),
            h('p', null, 'Соберите однофайловый установщик NUTS ПУС и разверните такую же сборку на любом пустом хостинге за пару минут: WordPress установится автоматически, NUTS ПУС активируется, сайт будет скрыт от поисковиков, вход — через /cabinet/.'),
            h('ol', null, [
                'На новом хостинге создайте базу данных (панель хостинга, раздел «Базы данных»).',
                'Загрузите скачанный файл в корень нового сайта — по FTP или через файловый менеджер панели.',
                'Откройте в браузере адрес вида site.ru/nuts-installer.php и заполните форму: реквизиты базы, название сайта, логин и пароль администратора.',
                'Через минуту сборка готова: входите через /cabinet/ с указанными реквизитами.',
            ].map((s, i) => h('li', { key: i, style: { margin: '6px 0', color: '#555' } }, s))),
            h('div', { className: 'nuts-section-actions' },
                h('a', { className: 'nuts-section-action', href: nutsConfig.nutsRest + 'installer&_wpnonce=' + nutsConfig.nonce, download: true }, 'Скачать nuts-installer.php')),
            h('p', { className: 'nuts-section-note' }, 'Внутрь файла упакован текущий плагин N ' + nutsConfig.cmsVersion + ' и цвет акцента вашей гаммы. Размер — около 200 КБ. WordPress установщик скачает сам с ru.wordpress.org; если хостинг без внешнего доступа — загрузите архив WordPress рядом с установщиком, он его подхватит. Файл работает только с правами администратора этой панели (ссылка содержит одноразовый ключ сессии).'));
    }
    function PanelUpdate() {
        const [data, setData] = useState(null);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        const [busy, setBusy] = useState(false);
        const [draft, setDraft] = useState(null);
        useEffect(() => { load(); }, []);
        async function load() {
            setError('');
            try { const d = await api({ path: '/nuts/v1/update' }); setData(d); setDraft({ repo: d.repo, auto: d.auto }); }
            catch (e) { setError(e.message || 'Не удалось проверить обновления.'); }
        }
        async function save(e) {
            e.preventDefault(); setBusy(true); setError(''); setNotice('');
            try { const d = await api({ path: '/nuts/v1/update', method: 'POST', data: draft }); setData(d); setDraft({ repo: d.repo, auto: d.auto }); setNotice('Настройки сохранены. Проверка обновлений выполнена заново.'); }
            catch (e2) { setError(e2.message || 'Не удалось сохранить.'); }
            finally { setBusy(false); }
        }
        if (!data || !draft) return h('article', { className: 'nuts-section-tile' }, h('p', { role: error ? 'alert' : 'status' }, error || 'Проверяем канал обновлений…'), error && h('button', { type: 'button', onClick: load }, 'Повторить'));
        return h('div', { className: 'nuts-section-grid' },
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Обновление панели'),
                h('p', null, 'Панель следит за релизами GitHub-репозитория и обновляется штатным механизмом WordPress: скачивание, проверка архива и замена файлов — как у обычных плагинов.'),
                data.error ? h('p', { role: 'alert', className: 'nuts-error' }, 'Не удалось получить данные о релизе. Проверьте имя репозитория и доступность api.github.com с хостинга.') :
                data.latest ? h('dl', null,
                    h('div', { className: 'nuts-status-row' }, h('dt', null, 'Установлена версия'), h('dd', null, 'N ' + data.current)),
                    h('div', { className: 'nuts-status-row' }, h('dt', null, 'Последний релиз'), h('dd', null, 'N ' + data.latest)),
                    h('div', { className: 'nuts-status-row' }, h('dt', null, 'Состояние'), h('dd', null, data.update_available ? 'Доступна новая версия' : 'Установлена актуальная версия'))) :
                    h('p', null, 'Репозиторий обновлений не настроен. Укажите его в настройках ниже.'),
                data.update_available && h('div', { className: 'nuts-section-actions' },
                    h('a', { className: 'nuts-section-action', href: data.update_url }, 'Обновить до N ' + data.latest),
                    h('a', { className: 'nuts-section-action', href: data.release_url, target: '_blank', rel: 'noopener' }, 'Что нового ↗')),
                h(Messages, { error, notice })),
            h('form', { className: 'nuts-section-tile', onSubmit: save },
                h('h2', null, 'Канал обновлений'),
                h('p', null, 'Создайте публичный репозиторий на GitHub, разместите в разделе Releases архив вида NUTS-PUS-Nx.y.z.zip и укажите репозиторий здесь. Панель проверяет новые релизы раз в 6 часов.'),
                h('label', { className: 'nuts-field' }, h('span', null, 'Репозиторий GitHub'), h('input', { value: draft.repo, onChange: e => setDraft({ ...draft, repo: e.target.value.trim() }), placeholder: 'например: nuts-studio/nuts-pus', disabled: busy })),
                h('label', { className: 'nuts-toggle' }, h('input', { type: 'checkbox', checked: !!draft.auto, onChange: e => setDraft({ ...draft, auto: e.target.checked }), disabled: busy }), h('span', { className: 'nuts-toggle-switch', 'aria-hidden': true }), h('span', null, h('strong', null, 'Автоматическое обновление'), h('small', null, 'Панель обновится сама, как только выйдет новый релиз (механизм автообновлений WordPress).'))),
                h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: busy }, busy ? 'Сохраняем…' : 'Сохранить и проверить')),
                h('p', { className: 'nuts-section-note' }, 'Перед обновлением рекомендуется резервная копия файлов и базы. Локальные правки кода плагина будут перезаписаны файлами из релиза.')));
    }
    function ToolsHub({ id }) {
        if (id === 'redirects') return h(Redirects);
        if (id === 'seo') return h(SeoHub);
        if (id === 'feedback') return h(Feedback);
        if (id === 'media-tools') return h(MediaTools);
        if (id === 'export') return h(ExportImport);
        if (id === 'installer') return h(Installer);
        if (id === 'update') return h(PanelUpdate);
        return h('article', { className: 'nuts-section-tile' }, h('p', { role: 'alert' }, 'Раздел недоступен.'));
    }
    window.nutsTools = { ToolsHub };
})();
