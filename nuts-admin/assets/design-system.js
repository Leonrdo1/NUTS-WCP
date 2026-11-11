(function () {
    'use strict';
    const {createElement:h,useState,useEffect}=wp.element;
    const presets=nutsConfig.appearancePresets;
    function contrast(hex) {
        const rgb=hex.slice(1).match(/../g).map(x=>parseInt(x,16)/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4);
        const l=rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;
        return (l+.05)/.05 >= 1.05/(l+.05) ? '#202020' : '#ffffff';
    }
    function applyAppearance(value) {
        const root=document.documentElement;
        ['accent','sidebar','background'].forEach(k=>root.style.setProperty('--nuts-'+k,value[k]));
        root.style.setProperty('--nuts-on-accent',contrast(value.accent));
        root.style.setProperty('--nuts-on-sidebar',contrast(value.sidebar));
        root.style.setProperty('--nuts-on-background',contrast(value.background));
        const frame=document.getElementById('nuts-wp-frame');
        try { if(frame?.contentDocument) ['accent','sidebar','background','on-accent','on-sidebar','on-background'].forEach(k=>frame.contentDocument.documentElement.style.setProperty('--nuts-'+k,root.style.getPropertyValue('--nuts-'+k))); } catch(_) {}
    }
    applyAppearance(nutsConfig.appearance);
    function Appearance() {
        const [saved,setSaved]=useState(nutsConfig.appearance);
        const [draft,setDraft]=useState(nutsConfig.appearance);
        const [busy,setBusy]=useState(false);const [message,setMessage]=useState('');const [error,setError]=useState('');
        const dirty=JSON.stringify(draft)!==JSON.stringify(saved);
        useEffect(()=>{applyAppearance(draft);},[draft]);
        useEffect(()=>()=>applyAppearance(nutsConfig.appearance),[]);
        async function save(e){e.preventDefault();setBusy(true);setError('');setMessage('');try{const value=await wp.apiFetch({path:'/nuts/v1/admin-appearance',method:'POST',data:draft});nutsConfig.appearance=value;setSaved(value);setDraft(value);setMessage('Цветовая гамма сохранена.');}catch(e){setError(e.message||'Не удалось сохранить.');}finally{setBusy(false);}}
        return h('form',{onSubmit:save,className:'nuts-section-tile'},h('h2',null,'Цветовая гамма админки'),h('p',null,'Общая схема NUTS для пользователей этой CMS. Предпросмотр применяется сразу, сохранение — по кнопке.'),
            h('fieldset',{className:'nuts-scheme-options',disabled:busy},h('legend',null,'Готовые схемы'),Object.entries(presets).map(([id,p])=>h('label',{key:id,className:'nuts-scheme-option'},h('input',{type:'radio',name:'nuts-scheme',value:id,checked:draft.preset===id,onChange:()=>{setDraft({preset:id,...p});setMessage('');}}),h('span',{className:'nuts-scheme-swatch',style:{background:p.sidebar,borderColor:p.accent}},h('i',{style:{background:p.accent}})),h('span',null,p.name)))),
            h('fieldset',{className:'nuts-custom-colors',disabled:busy},h('legend',null,'Своя палитра'),[['accent','Акцент'],['sidebar','Боковое меню'],['background','Фон рабочей области']].map(([key,label])=>h('label',{key},h('span',null,label),h('input',{type:'color','aria-label':label,value:draft[key],onChange:e=>{setDraft({...draft,preset:'custom',[key]:e.target.value});setMessage('');}}),h('code',null,draft[key])))),
            h('div',{className:'nuts-section-actions'},h('button',{type:'submit',disabled:busy||!dirty},busy?'Сохраняем…':'Сохранить гамму'),h('button',{type:'button',disabled:busy||!dirty,onClick:()=>{setDraft(saved);setMessage('');setError('');}},'Отменить изменения')),
            message&&h('p',{role:'status',className:'nuts-notice'},message),error&&h('p',{role:'alert',className:'nuts-error'},error));
    }
    const items=[['design-system','Обзор','screenoptions'],['favicon','Фавикон','format-image'],['colors','Глобальные цвета','art'],['fonts','Шрифты','editor-textcolor'],['forms','Формы','feedback'],['elements','Стандартные элементы','button'],['styles','CSS и стили','admin-appearance'],['templates','Шаблоны','welcome-widgets-menus'],['block-kits','Наборы блоков','block-default'],['figma-import','Импорт Figma','download']];
    function DesignHub({id,onOpen}) {
        const target=nutsConfig.admin+(nutsConfig.blockTheme?'site-editor.php?path=/styles':'customize.php');
        function link(label,url){return h('a',{className:'nuts-section-action',href:nutsConfig.admin+'admin.php?page=nuts-cms#wp='+encodeURIComponent(url),onClick:e=>{if(!e.ctrlKey&&!e.metaKey&&!e.shiftKey){e.preventDefault();onOpen({label,url});}}},label+' →');}
        const panels={
            'design-system':['Единая система оформления','Цвета, типографика, формы, элементы и шаблоны сайта собраны в одном месте. Здесь же появятся библиотеки блоков и импорт из Figma.'],
            forms:['Формы сайта','Место для общих стилей полей, подписей, ошибок и кнопок отправки. Ниже — образец элементов; подключение форм и сохранение их настроек будут добавлены отдельно.'],
            elements:['Кнопки, ссылки и карточки','Кнопки, ссылки, карточки и поля — основа будущей библиотеки компонентов. Пока показаны образцы; редактирование доступных стилей блоков выполняется в NUTS ПУС.'],
            templates:['Шаблоны страниц','Управление шаблонами страниц, шапкой и подвалом активной темы.'],
            'block-kits':['Библиотеки секций','Здесь появятся готовые секции, собственные библиотеки и наборы, подготовленные в Figma. Импорт наборов пока не подключён.'],
            'figma-import':['Импорт из Figma','Будущий импорт дизайна в редактируемые блоки: выбор макета, сопоставление компонентов и предварительный просмотр. Подключение Figma и загрузка файлов пока не реализованы.']
        };
        const panel=panels[id];
        return h('div',null,h('nav',{className:'nuts-design-tabs','aria-label':'Настройки дизайн-системы'},items.map(([key,label])=>h('a',{key,href:'#nuts='+key,'aria-current':id===key?'page':undefined},label))),
            id==='favicon'&&h(window.nutsIdentity.Favicon),
            panel&&h('article',{className:'nuts-section-tile'},h('h2',null,panel[0]),h('p',null,panel[1]),['forms','block-kits','figma-import'].includes(id)&&h('span',{className:'nuts-feature-status'},'Подготовлено место · функция в разработке'),
                id==='templates'&&link('Открыть шаблоны NUTS ПУС',nutsConfig.admin+(nutsConfig.blockTheme?'site-editor.php?path=/template':'themes.php')),
                id==='elements'&&link('Редактировать стили блоков',target)),
            id==='design-system'&&h('div',{className:'nuts-section-grid'},items.slice(1).map(([key,label])=>h('article',{className:'nuts-section-tile',key},h('h2',null,label),h('a',{className:'nuts-section-action',href:'#nuts='+key},'Открыть →')))),
            ['forms','elements'].includes(id)&&h('article',{className:'nuts-section-tile nuts-element-samples'},h('h2',null,'Образцы элементов'),h('p',null,'Предпросмотр компонентов. Эта форма не отправляет данные.'),h('label',{htmlFor:'nuts-sample-name'},'Имя'),h('input',{id:'nuts-sample-name',placeholder:'Анна',readOnly:true}),h('label',{htmlFor:'nuts-sample-email'},'Электронная почта'),h('input',{id:'nuts-sample-email',placeholder:'name@example.com',readOnly:true}),h('div',{className:'nuts-section-actions'},h('button',{type:'button',disabled:true},'Пример кнопки отправки'),h('a',{href:'#nuts=colors'},'Пример ссылки'))));
    }
    window.nutsDesign={Appearance,DesignHub,items,applyAppearance};
})();
