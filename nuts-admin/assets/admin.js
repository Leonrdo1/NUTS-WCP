(function () {
    'use strict';
    const { createElement: h, useState, useEffect } = wp.element;
    const api = wp.apiFetch;
    api.use(api.createNonceMiddleware(nutsConfig.nonce));
    const labels = { publish: 'Опубликована', draft: 'Черновик', pending: 'На проверке', private: 'Личная', future: 'Запланирована' };
    const { WpMenu, isInternal, menu, Collapsible } = window.nutsWorkspace;
    function workspaceFromHash() {
        try {
            if (!location.hash.startsWith('#wp=')) return null;
            const url = new URL(decodeURIComponent(location.hash.slice(4)), nutsConfig.admin).href;
            if (!isInternal(url)) return null;
            return menu.flatMap(g => [g, ...g.children]).find(item => item.url === url) || { label: 'NUTS ПУС', url };
        } catch (_) { return null; }
    }
    const { sections, SectionHub } = window.nutsSections;
    function sectionFromHash() {
        const id = location.hash.startsWith('#nuts=') ? location.hash.slice(6) : 'dashboard';
        return sections.some(s => s.id === id && !s.url) ? id : 'dashboard';
    }
    function scrollToStart() { requestAnimationFrame(() => window.scrollTo({top:0,left:0,behavior:'instant'})); }
    function App() {
        const [sectionId, setSectionId] = useState(sectionFromHash);
        function openSection(item) {
            scrollToStart();
            if (item.url) { openWorkspace(item); return; }
            setWorkspace(null); setFrameUrl(''); setFrameLoading(false); setSectionId(item.id);
            location.hash = item.id === 'pages' ? 'nuts-pages' : 'nuts=' + item.id;
        }

        const [workspace, setWorkspace] = useState(workspaceFromHash);
        const [frameUrl, setFrameUrl] = useState('');
        const [frameLoading, setFrameLoading] = useState(false);
        useEffect(() => { scrollToStart(); }, [sectionId, workspace?.url]);
        function openWorkspace(item) {
            scrollToStart();
            if (!isInternal(item.url)) return;
            setWorkspace(item); setFrameUrl(item.url); setFrameLoading(true);
            location.hash = 'wp=' + encodeURIComponent(item.url);
        }
        function returnToPages() { scrollToStart(); setSectionId('pages'); setWorkspace(null); setFrameUrl(''); setFrameLoading(false); location.hash = 'nuts-pages'; }
        useEffect(() => {
            function restoreWorkspace() {
                scrollToStart();
                setSectionId(sectionFromHash());
                const item = workspaceFromHash();
                setWorkspace(current => current?.url === item?.url ? current : item);
                setFrameUrl(item ? item.url : '');
            }
            window.addEventListener('hashchange', restoreWorkspace);
            return () => window.removeEventListener('hashchange', restoreWorkspace);
        }, []);

        const [pages, setPages] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        const [busy, setBusy] = useState(false);
        const [query, setQuery] = useState('');
        const [editing, setEditing] = useState(null);
        const [title, setTitle] = useState('');
        const [page, setPage] = useState(1);
        const [totalPages, setTotalPages] = useState(1);
        async function load() {
            setLoading(true); setError('');
            try {
                const response = await api({ path: '/wp/v2/pages?context=edit&status=any&per_page=20&page=' + page + '&orderby=modified&order=desc', parse: false });
                setPages(await response.json());
                setTotalPages(Number(response.headers.get('X-WP-TotalPages')) || 1);
            } catch (e) { setError(e.message || 'Не удалось загрузить страницы.'); }
            finally { setLoading(false); }
        }
        useEffect(() => { load(); }, [page]);
        async function save(e) {
            e.preventDefault(); if (!title.trim()) return;
            setBusy(true); setError(''); setNotice('');
            try {
                await api({ path: '/wp/v2/pages' + (editing ? '/' + editing.id : ''), method: 'POST', data: editing ? { title: title.trim() } : { title: title.trim(), status: 'draft' } });
                setNotice(editing ? 'Заголовок сохранён.' : 'Черновик создан. Откройте редактор, чтобы добавить содержание.');
                setEditing(null); setTitle(''); await load();
            } catch (e) { setError(e.message || 'Не удалось сохранить страницу.'); }
            finally { setBusy(false); }
        }
        const visible = pages.filter(p => p.title.raw.toLowerCase().includes(query.toLowerCase()));
        return h('div', { className: 'nuts-shell' },
            h('aside', { className: 'nuts-sidebar' },
                h('div', { className: 'nuts-logo' }, 'NUTS ПУС', h('span', null, 'Панель управления сайтом')),
                h('div', {className:'nuts-site-card','aria-label':'Текущий сайт'},h('strong',{title:nutsConfig.siteTitle},nutsConfig.siteTitle),nutsConfig.siteDescription&&h('span',null,nutsConfig.siteDescription)),
                h('nav', { className: 'nuts-primary-nav', 'aria-label': 'Основные разделы NUTS' }, ['Контент', 'Дизайн', 'Управление'].map(group =>
                    h(Collapsible, { key:group, storageKey:'primary:'+group, defaultOpen:true, className:'nuts-primary-group', summaryClass:'nuts-nav-label nuts-group-heading',title:group.toUpperCase() }, sections.filter(s => s.group === group).map(item => {
                        const active = item.url ? workspace?.url === item.url : !workspace && sectionId === item.id;
                        return h('a', { key: item.id, 'data-section': item.id, className: active ? 'active' : '', 'aria-current': active ? 'page' : undefined, href: item.url ? '#wp=' + encodeURIComponent(item.url) : item.id === 'pages' ? '#nuts-pages' : '#nuts=' + item.id,
                            onClick: e => { if (!e.metaKey && !e.ctrlKey && !e.shiftKey) { e.preventDefault(); openSection(item); } } }, h('span', { className: 'dashicons dashicons-' + item.icon, 'aria-hidden': true }), item.label);
                    })))),
                nutsConfig.capabilities.design && h(Collapsible, {storageKey:'design-system',className:'nuts-design-menu',summaryClass:'nuts-nav-label nuts-group-heading',defaultOpen:sections.some(s=>s.id===sectionId&&s.group==='Дизайн-система')&&!workspace,title:'Дизайн-система'},
                    h('nav',{'aria-label':'Дизайн-система'},sections.filter(s=>s.group==='Дизайн-система').map(item=>h('a',{key:item.id,'data-section':item.id,href:'#nuts='+item.id,className:!workspace&&sectionId===item.id?'active':'','aria-current':!workspace&&sectionId===item.id?'page':undefined,onClick:e=>{if(!e.metaKey&&!e.ctrlKey&&!e.shiftKey){e.preventDefault();openSection(item);}}},item.label)))),
                h(WpMenu, { selected: workspace, onOpen: openWorkspace }),
                h('div', { className: 'nuts-sidebar-bottom' }, 'NUTS ПУС · ' + nutsConfig.versionLabel, h('p', null, 'Панель управления сайтом'))),
            h('main', { id: 'nuts-workspace-main' },
                h('header', null, h('span', null, 'Панель управления / ' + (workspace ? workspace.label : (sections.find(s => s.id === sectionId)?.label || 'Страницы'))), h('a', { href: nutsConfig.home, target: '_blank', rel: 'noopener' }, 'Открыть сайт ↗')),
                workspace ? h('section', { className: 'nuts-workspace' },
                    h('div', { className: 'nuts-workspace-bar' },
                        h('div', null, h('p', { className: 'nuts-eyebrow' }, 'NUTS ПУС'), h('h1', null, workspace.label)),
                        h('a', { href: nutsConfig.admin + 'admin.php?page=nuts-cms#wp=' + encodeURIComponent(frameUrl || workspace.url), target: '_blank', rel: 'noopener' }, 'Открыть отдельно ↗')),
                    frameLoading && h('p', { role: 'status', className: 'nuts-frame-loading' }, 'Загружаем раздел…'),
                    h('iframe', { id: 'nuts-wp-frame', title: 'NUTS ПУС — ' + workspace.label, src: workspace.url,
                        onLoad: e => {
                            setFrameLoading(false);
                            try {
                                const url = e.currentTarget.contentWindow.location.href;
                                if (isInternal(url)) setFrameUrl(url);
                            } catch (_) {}
                        } })) : sectionId !== 'pages' ? h(SectionHub, { id: sectionId, onOpen: openWorkspace, onPages: returnToPages, onSection: openSection }) : h(wp.element.Fragment, null,
                h('section', { className: 'nuts-heading' }, h('div', null, h('p', { className: 'nuts-eyebrow' }, 'КОНТЕНТ САЙТА'), h('h1', null, 'Страницы'), h('p', null, 'Содержание, с которого начинается ваш сайт.')), h('span', { className: 'nuts-badge' }, nutsConfig.versionLabel)),
                error && h('div', { role: 'alert', className: 'nuts-error' }, error, h('button', { onClick: load, disabled: loading }, 'Повторить')),
                notice && h('p', { role: 'status', className: 'nuts-notice' }, notice),
                h('form', { className: 'nuts-create', onSubmit: save }, h('label', { htmlFor: 'nuts-title' }, editing ? 'Изменить заголовок' : 'Новая страница'), h('div', null,
                    h('input', { id: 'nuts-title', value: title, onChange: e => setTitle(e.target.value), placeholder: 'Например, О студии', required: true, disabled: busy }),
                    h('button', { type: 'submit', disabled: busy || !title.trim() }, busy ? 'Сохраняем…' : editing ? 'Сохранить' : '+ Создать черновик'),
                    editing && h('button', { type: 'button', disabled: busy, onClick: () => { setEditing(null); setTitle(''); } }, 'Отмена'))),
                h('section', { className: 'nuts-card', 'aria-busy': loading },
                    h('div', { className: 'nuts-toolbar' }, h('h2', null, 'Страницы сайта'), h('input', { type: 'search', 'aria-label': 'Поиск на текущей странице списка', placeholder: 'Поиск в текущем списке…', value: query, onChange: e => setQuery(e.target.value) })),
                    loading ? h('p', { className: 'nuts-empty', role: 'status' }, 'Загружаем страницы…') : visible.length ? h('div', { className: 'nuts-table-wrap' }, h('table', null,
                        h('thead', null, h('tr', null, ['Название', 'Статус', 'Изменена', 'Действия'].map(x => h('th', { key: x, scope: 'col' }, x)))),
                        h('tbody', null, visible.map(p => h('tr', { key: p.id },
                            h('td', null, h('strong', null, p.title.raw || 'Без названия'), h('small', null, '/' + p.slug)),
                            h('td', null, h('span', { className: 'nuts-status ' + p.status }, labels[p.status] || p.status)),
                            h('td', null, new Intl.DateTimeFormat('ru-RU').format(new Date(p.modified))),
                            h('td', { className: 'nuts-actions' }, h('button', { type: 'button', disabled: busy, onClick: () => { setEditing(p); setTitle(p.title.raw); document.getElementById('nuts-title').focus(); } }, 'Название'), h('a', { href: nutsConfig.admin + 'admin.php?page=nuts-cms#wp=' + encodeURIComponent(nutsConfig.admin + 'post.php?post=' + p.id + '&action=edit'), onClick: e => { if(!e.metaKey&&!e.ctrlKey&&!e.shiftKey){e.preventDefault();openWorkspace({label:p.title.raw || 'Редактор',url:nutsConfig.admin + 'post.php?post=' + p.id + '&action=edit'});} } }, 'Редактор ↗'))))))) : h('p', { className: 'nuts-empty' }, 'Страницы не найдены.'),
                    h('div', { className: 'nuts-pagination' }, h('button', { disabled: loading || page <= 1, onClick: () => setPage(page - 1) }, '← Назад'), h('span', null, page + ' / ' + totalPages), h('button', { disabled: loading || page >= totalPages, onClick: () => setPage(page + 1) }, 'Далее →'))),
                ),
                h('footer', null, 'Здравствуйте, ' + nutsConfig.user + '. ', 'Панель управления сайтом · N ' + nutsConfig.cmsVersion)));
    }
    wp.element.createRoot(document.getElementById('nuts-cms-root')).render(h(App));
})();
