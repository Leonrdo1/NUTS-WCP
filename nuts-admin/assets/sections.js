(function () {
    'use strict';
    const { createElement: h } = wp.element;
    const a = nutsConfig.admin;
    const caps = nutsConfig.capabilities;
    const sections = [
        { id: 'dashboard', label: 'Сегодня', group: 'Контент', icon: 'chart-pie', allowed: true },
        ...[['news','Новости панели','megaphone'],['notifications','Уведомления','bell'],['developer','Страница разработчика','admin-home'],['help','Справка','editor-help']].map(([id,label,icon])=>({id,label,icon,group:id==='news'?'Контент':'Управление',allowed:true})),
        { id: 'catalog', label: 'Товары', group: 'Каталог', icon: 'products', url: a + 'edit.php?post_type=' + nutsConfig.catalogType, allowed: caps.content },
        { id: 'services', label: 'Услуги', group: 'Каталог', icon: 'admin-tools', url: a + 'edit.php?post_type=nuts_service', allowed: caps.content },
        { id: 'offers', label: 'Предложения', group: 'Каталог', icon: 'money-alt', allowed: caps.content },
        { id: 'catalog-types', label: 'Конструктор разделов', group: 'Каталог', icon: 'block-default', allowed: caps.settings },
        ...(nutsConfig.customTypes || []).map(t => ({ id: 'ct-' + t.slug, label: t.name, group: 'Каталог', icon: 'category', url: a + 'edit.php?post_type=' + t.slug, allowed: caps.content })),
        { id: 'blog', label: 'Блог', group: 'Контент', icon: 'edit', url: a + 'edit.php', allowed: caps.content },
        { id: 'pages', label: 'Страницы', group: 'Контент', icon: 'admin-page', allowed: true },
        { id: 'media', label: 'Медиатека', group: 'Контент', icon: 'format-image', url: a + 'upload.php', allowed: caps.content },
        { id: 'menus', label: 'Меню сайта', group: 'Контент', icon: 'menu', url: a + (nutsConfig.blockTheme ? 'site-editor.php?path=/navigation' : 'nav-menus.php'), allowed: caps.design },
        { id: 'builder', label: 'Конструктор', group: 'Дизайн', icon: 'layout', allowed: true },
        ...window.nutsDesign.items.map(([id,label,icon]) => ({id,label,icon,group:'Дизайн-система',allowed:id==='favicon'?caps.settings:caps.design})),
        { id: 'status', label: 'Состояние', group: 'Управление', icon: 'dashboard', allowed: caps.settings },
        { id: 'site', label: 'Настройки сайта', group: 'Управление', icon: 'admin-settings', allowed: caps.settings },
        { id: 'contacts', label: 'Контакты', group: 'Управление', icon: 'phone', allowed: caps.settings },
        { id: 'redirects', label: 'Перенаправления', group: 'Управление', icon: 'random', allowed: caps.settings },
        { id: 'seo', label: 'SEO', group: 'Управление', icon: 'search', allowed: caps.settings },
        { id: 'feedback', label: 'Заявки', group: 'Управление', icon: 'email', allowed: caps.settings },
        { id: 'media-tools', label: 'Медиатека: порядок', group: 'Управление', icon: 'images-alt2', allowed: caps.settings },
        { id: 'export', label: 'Экспорт настроек', group: 'Управление', icon: 'download', allowed: caps.settings },
        { id: 'installer', label: 'Установщик сборки', group: 'Управление', icon: 'update', allowed: caps.settings },
        { id: 'update', label: 'Обновление панели', group: 'Управление', icon: 'cloud-upload', allowed: caps.settings },
        { id: 'admin-appearance', label: 'Гамма админки', group: 'Управление', icon: 'color-picker', allowed: caps.settings },
        { id: 'users', label: 'Пользователи', group: 'Управление', icon: 'admin-users', url: a + 'users.php', allowed: caps.users },
        { id: 'plugins', label: 'Плагины', group: 'Управление', icon: 'admin-plugins', url: a + 'plugins.php', allowed: caps.plugins },
        { id: 'settings', label: 'Все настройки', group: 'Управление', icon: 'admin-settings', allowed: caps.settings }
    ].filter(s => s.allowed);
    const styleTarget = nutsConfig.blockTheme ? a + 'site-editor.php?path=/styles' : a + 'customize.php';
    const settings = [
        ['Общие', 'Название сайта, адрес, язык и часовой пояс.', 'options-general.php'],
        ['Написание', 'Настройки публикации записей.', 'options-writing.php'],
        ['Чтение', 'Главная страница и отображение записей.', 'options-reading.php'],
        ['Обсуждение', 'Комментарии, уведомления и модерация.', 'options-discussion.php'],
        ['Медиафайлы', 'Размеры изображений и параметры загрузки.', 'options-media.php'],
        ['Постоянные ссылки', 'Структура адресов страниц и записей.', 'options-permalink.php'],
        ['Конфиденциальность', 'Страница политики конфиденциальности.', 'options-privacy.php'],
        ['Обновления', 'Версии NUTS ПУС, тем и плагинов.', 'update-core.php']
    ];
    function SectionHub({ id, onOpen, onPages, onSection }) {
        const section = sections.find(s => s.id === id);
        if (!section) return h('p', { role: 'alert' }, 'Раздел недоступен.');
        function action(label, url) { return h('a', { className: 'nuts-section-action', href: nutsConfig.admin + 'admin.php?page=nuts-cms#wp=' + encodeURIComponent(url), onClick: e => { if(!e.metaKey && !e.ctrlKey && !e.shiftKey) { e.preventDefault(); onOpen({label, url}); } } }, label + ' →'); }
        const palette = nutsConfig.designSettings?.color?.palette || {};
        const colors = Object.values(palette).flat().filter(c => c && typeof c.color === 'string');
        const families = nutsConfig.designSettings?.typography?.fontFamilies || {};
        const fonts = Object.values(families).flat().filter(f => f && f.name);
        return h('section', { className: 'nuts-section-hub' },
            h('div', { className: 'nuts-heading' }, h('div', null, h('p', { className: 'nuts-eyebrow' }, section.group.toUpperCase()), h('h1', null, section.label)), h('span', { className: 'nuts-badge' }, nutsConfig.versionLabel)),
            id === 'dashboard' ? h(window.nutsDashboard.Dashboard, { onOpen, onSection }) :
            id === 'site' ? h(window.nutsSiteSettings.SiteHub) :
            id === 'contacts' ? h(window.nutsSiteSettings.Contacts) :
            id === 'offers' ? h(window.nutsCatalog.Offers) :
            id === 'catalog-types' ? h(window.nutsCatalog.Types) :
            ['redirects', 'seo', 'feedback', 'media-tools', 'export'].includes(id) ? h(window.nutsTools.ToolsHub, { id }) :
            ['news','notifications','developer','help'].includes(id) ? h(window.nutsCommunity.Hub,{id}) :
            id === 'status' ? h(window.nutsStatus.Status,{onOpen}) :
            id === 'admin-appearance' ? h(window.nutsDesign.Appearance) :
            section.group === 'Дизайн-система' && !['colors','fonts','styles'].includes(id) ? h(window.nutsDesign.DesignHub, {id,onOpen}) :
            id === 'settings' ? h('div', { className: 'nuts-section-grid' }, h('article', {className:'nuts-section-tile'}, h('h2',null,'Цветовая гамма админки'),h('p',null,'Готовые схемы и собственные цвета интерфейса NUTS.'),h('a',{href:'#nuts=admin-appearance',className:'nuts-section-action'},'Настроить гамму →')), h('article',{className:'nuts-section-tile'},h('h2',null,'Системный вид'),h('p',null,'Стандартный интерфейс управления ядром.'),h('a',{className:'nuts-section-action',href:a},'Открыть системный вид →')), settings.map(([label, description, path]) => h('article', { className: 'nuts-section-tile', key: path }, h('h2', null, label), h('p', null, description), action('Открыть', a + path)))) :
            id === 'builder' ? h('article', { className: 'nuts-section-tile' }, h('span', { className: 'nuts-feature-status' }, 'Основа: редактор блоков NUTS ПУС'), h('h2', null, 'Создание и редактирование страниц'), h('p', null, 'Выберите существующую страницу или создайте новую в редакторе NUTS ПУС. Собственный холст NUTS и импорт Figma ещё не реализованы.'), h('div', { className: 'nuts-section-actions' }, h('button', { onClick: onPages }, 'Выбрать страницу'), action('Создать страницу', a + 'post-new.php?post_type=page'))) :
            h(wp.element.Fragment, null,
                h(window.nutsDesign.DesignHub, {id,onOpen}),
                h('article', { className: 'nuts-section-tile' }, h('h2', null, id === 'colors' ? 'Палитра всего сайта' : id === 'fonts' ? 'Типографика сайта' : 'Единое оформление сайта'), h('p', null, id === 'colors' ? 'Цвета темы для текста, фонов, ссылок и акцентов.' : id === 'fonts' ? 'Семейства шрифтов, начертания и размеры текста.' : 'Настройки цветов, типографики, блоков и расположения элементов.'), h('p', { className: 'nuts-section-note' }, nutsConfig.blockTheme ? 'Изменения пока выполняются в общем редакторе стилей NUTS ПУС. Выберите там нужную вкладку.' : 'Доступные настройки определяются активной темой NUTS ПУС.'), action('Открыть настройки оформления', styleTarget)),
                id === 'colors' && h('article', { className: 'nuts-section-tile' }, h('h2', null, 'Текущая палитра темы'), colors.length ? h('div', { className: 'nuts-palette' }, colors.map((c, i) => h('div', { className: 'nuts-color', key: i }, h('span', { className: 'nuts-color-sample', style: { backgroundColor: c.color }, 'aria-hidden': true }), h('strong', null, c.name || c.slug), h('small', null, c.color)))) : h('p', null, 'Тема не предоставила палитру. Откройте настройки оформления.')),
                id === 'fonts' && h('article', { className: 'nuts-section-tile' }, h('h2', null, 'Шрифты, объявленные темой'), fonts.length ? h('div', { className: 'nuts-fonts' }, fonts.map((f, i) => h('div', { key: i }, h('strong', null, f.name), h('p', null, f.fontFamily)))) : h('p', null, 'Тема не предоставила список шрифтов. Откройте настройки оформления.')),
                id === 'styles' && h('div', { className: 'nuts-section-grid' }, ['Цвета и фоны', 'Типографика', 'Отступы и блоки'].map(label => h('article', { className: 'nuts-section-tile', key: label }, h('h2', null, label), action('Настроить в NUTS ПУС', styleTarget))))));
    }
    window.nutsSections = { sections, SectionHub };
})();
