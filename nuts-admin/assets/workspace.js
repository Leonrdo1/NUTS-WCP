(function () {
    'use strict';
    const { createElement: h, useState } = wp.element;
    const translations = {
        'Dashboard': 'Консоль', 'Home': 'Главная', 'Updates': 'Обновления',
        'Posts': 'Записи', 'All Posts': 'Все записи', 'Add Post': 'Добавить запись', 'Add New Post': 'Добавить запись', 'Categories': 'Рубрики', 'Tags': 'Метки',
        'Media': 'Медиатека', 'Library': 'Библиотека', 'Add Media File': 'Добавить медиафайл', 'Add New Media File': 'Добавить медиафайл',
        'Pages': 'Страницы', 'All Pages': 'Все страницы', 'Add Page': 'Добавить страницу', 'Add New Page': 'Добавить страницу', 'Comments': 'Комментарии',
        'Appearance': 'Внешний вид', 'Themes': 'Темы', 'Editor': 'Редактор', 'Customize': 'Настроить', 'Widgets': 'Виджеты', 'Menus': 'Меню', 'Theme File Editor': 'Редактор файлов темы',
        'Plugins': 'Плагины', 'Installed Plugins': 'Установленные плагины', 'Add Plugin': 'Добавить плагин', 'Add New Plugin': 'Добавить плагин', 'Plugin File Editor': 'Редактор файлов плагинов',
        'Users': 'Пользователи', 'All Users': 'Все пользователи', 'Add User': 'Добавить пользователя', 'Add New User': 'Добавить пользователя', 'Profile': 'Профиль',
        'Tools': 'Инструменты', 'Available Tools': 'Доступные инструменты', 'Import': 'Импорт', 'Export': 'Экспорт', 'Site Health': 'Здоровье сайта', 'Export Personal Data': 'Экспорт персональных данных', 'Erase Personal Data': 'Удаление персональных данных',
        'Settings': 'Настройки', 'General': 'Общие', 'Writing': 'Написание', 'Reading': 'Чтение', 'Discussion': 'Обсуждение', 'Permalinks': 'Постоянные ссылки', 'Privacy': 'Конфиденциальность'
    };
    function itemFromAnchor(anchor) {
        const label = anchor.querySelector('.wp-menu-name') || anchor;
        const copy = label.cloneNode(true);
        copy.querySelectorAll('.update-plugins,.awaiting-mod,.screen-reader-text').forEach(n => n.remove());
        const original = copy.textContent.trim().replace(/\s+/g, ' ');
        const badge = label.querySelector('.update-plugins,.awaiting-mod');
        const badgeCopy = badge ? badge.cloneNode(true) : null;
        if (badgeCopy) badgeCopy.querySelectorAll('.screen-reader-text').forEach(n => n.remove());
        const numericCount = badgeCopy ? (badgeCopy.textContent.match(/\d+/) || ['0'])[0] : '0';
        return { label: translations[original] || original, original, url: anchor.href, count: Number(numericCount) > 0 ? numericCount : '' };
    }
    // Read NUTS ПУС's rendered menu: it already resolves plugin URLs and user capabilities.
    const menu = Array.from(document.querySelectorAll('#adminmenu > li')).flatMap(node => {
        const anchor = node.querySelector(':scope > a');
        if (!anchor || node.id === 'toplevel_page_nuts-cms' || node.id === 'collapse-menu') return [];
        const item = itemFromAnchor(anchor);
        if (!item.label || !/^https?:$/.test(new URL(item.url).protocol)) return [];
        item.children = Array.from(node.querySelectorAll('.wp-submenu > li > a')).map(itemFromAnchor);
        return [item];
    });
    function isInternal(url) {
        try { const u = new URL(url); const admin = new URL(nutsConfig.admin); return u.origin === admin.origin && u.pathname.startsWith(admin.pathname) && u.searchParams.get('page') !== 'nuts-cms'; }
        catch (_) { return false; }
    }
    function Collapsible({storageKey,title,defaultOpen=false,forceOpen=false,className='',summaryClass='',children}) {
        const key='nuts-menu:v1:'+nutsConfig.userId+':'+nutsConfig.admin+':'+storageKey;
        const [open,setOpen]=useState(()=>{try{const v=localStorage.getItem(key);return v===null?defaultOpen:v==='1';}catch(_){return defaultOpen;}});
        function toggle(e){e.preventDefault();const next=!open;setOpen(next);try{localStorage.setItem(key,next?'1':'0');}catch(_){}}
        return h('details',{className:'nuts-collapsible '+className,open:forceOpen||open,'data-menu-group':storageKey},h('summary',{className:summaryClass,onClick:toggle},title),children);
    }
    function WpMenu({ selected, onOpen }) {
        const [search, setSearch] = useState('');
        const matches = item => (item.label + ' ' + item.original).toLowerCase().includes(search.toLowerCase());
        function link(item) {
            const active = selected && selected.url === item.url;
            return h('a', { key: item.url, href: isInternal(item.url) ? nutsConfig.admin + 'admin.php?page=nuts-cms#wp=' + encodeURIComponent(item.url) : item.url, className: active ? 'selected' : '', 'aria-current': active ? 'page' : undefined,
                onClick: e => { if (isInternal(item.url) && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) { e.preventDefault(); onOpen(item); } },
                target: isInternal(item.url) ? undefined : '_blank', rel: 'noopener' },
                h('span', null, item.label), item.count && h('span', { className: 'nuts-menu-count' }, item.count));
        }
        return h(Collapsible, { storageKey:'system', className:'nuts-wp-menu', summaryClass:'nuts-nav-label nuts-group-heading', defaultOpen:Boolean(selected), title:'Системный вид' },
            h('label', { className: 'screen-reader-text', htmlFor: 'nuts-menu-search' }, 'Найти раздел NUTS ПУС'),
            h('input', { id: 'nuts-menu-search', type: 'search', placeholder: 'Найти раздел…', value: search, onChange: e => setSearch(e.target.value) }),
            h('nav', { 'aria-label': 'Разделы NUTS ПУС' }, menu.map(group => {
                const wholeGroup = matches(group);
                const children = wholeGroup ? group.children : group.children.filter(matches);
                if (!wholeGroup && !children.length) return null;
                return h('div', { key: group.url, className: 'nuts-wp-group' },
                    group.children.length ? h(Collapsible, {storageKey:'system:'+group.url,forceOpen:!!search,title:h(wp.element.Fragment,null,group.label,group.count&&h('span',{className:'nuts-menu-count'},group.count))}, children.map(link)) : link(group));
            })),
            search && !menu.some(g => matches(g) || g.children.some(matches)) && h('p', { className: 'nuts-menu-empty' }, 'Раздел не найден.'));
    }
    window.nutsWorkspace = { WpMenu, isInternal, menu, Collapsible };
})();
