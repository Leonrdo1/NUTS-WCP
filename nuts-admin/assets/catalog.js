(function () {
    'use strict';
    const { createElement: h, useState, useEffect } = wp.element;
    const api = wp.apiFetch;
    function Messages({ error, notice }) {
        return h(wp.element.Fragment, null,
            error && h('p', { role: 'alert', className: 'nuts-error' }, error),
            notice && h('p', { role: 'status', className: 'nuts-notice' }, notice));
    }
    function useLoad(path) {
        const [data, setData] = useState(null);
        const [error, setError] = useState('');
        async function load() { setError(''); try { setData(await api({ path })); } catch (e) { setError(e.message || 'Не удалось загрузить данные.'); } }
        useEffect(() => { load(); }, []);
        return { data, setData, error, setError, load };
    }
    function Types() {
        const r = useLoad('/nuts/v1/catalog-types');
        const [draft, setDraft] = useState({ name: '', singular: '', slug: '' });
        const [busy, setBusy] = useState(false);
        const [notice, setNotice] = useState('');
        if (!r.data) return h('article', { className: 'nuts-section-tile' }, h('p', { role: r.error ? 'alert' : 'status' }, r.error || 'Загружаем разделы…'));
        const types = r.data.types || [];
        async function save(e) {
            e.preventDefault(); setBusy(true); setNotice(''); r.setError('');
            try {
                const d = await api({ path: '/nuts/v1/catalog-types', method: 'POST', data: draft });
                r.setData(d); setDraft({ name: '', singular: '', slug: '' });
                setNotice('Раздел «' + draft.name + '» создан. Он появился в меню «Каталог» и в системном меню после обновления страницы.');
            } catch (e2) { r.setError(e2.message || 'Не удалось создать раздел.'); }
            finally { setBusy(false); }
        }
        async function remove(slug, name) {
            if (!window.confirm('Удалить раздел «' + name + '»? Его записи останутся в базе, но раздел исчезнет из панели.')) return;
            setBusy(true); r.setError('');
            try { r.setData(await api({ path: '/nuts/v1/catalog-types', method: 'POST', data: { action: 'delete', slug } })); setNotice('Раздел удалён.'); }
            catch (e2) { r.setError(e2.message || 'Не удалось удалить раздел.'); }
            finally { setBusy(false); }
        }
        return h('div', { className: 'nuts-section-grid' },
            h('form', { className: 'nuts-section-tile', onSubmit: save },
                h('h2', null, 'Новый раздел'),
                h('p', null, 'Создайте собственный раздел каталога — как инфоблок: например «Портфолио», «Команда», «Объекты». У раздела будет список записей, категории, изображения и REST-доступ.'),
                h('label', { className: 'nuts-field' }, h('span', null, 'Название, множественное число'), h('input', { value: draft.name, onChange: e => setDraft({ ...draft, name: e.target.value }), placeholder: 'например: Портфолио', required: true, disabled: busy })),
                h('label', { className: 'nuts-field' }, h('span', null, 'Название, единственное число'), h('input', { value: draft.singular, onChange: e => setDraft({ ...draft, singular: e.target.value }), placeholder: 'например: Работа', disabled: busy })),
                h('label', { className: 'nuts-field' }, h('span', null, 'Системное имя (латиница)'), h('input', { value: draft.slug, onChange: e => setDraft({ ...draft, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '') }), placeholder: 'например: portfolio', disabled: busy }), h('small', null, 'От 2 до 30 символов: a–z, 0–9, дефис. Используется в адресах и REST.')),
                h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: busy }, busy ? 'Создаём…' : 'Создать раздел')),
                h(Messages, { error: r.error, notice })),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Созданные разделы'),
                types.length ? h('ul', { className: 'nuts-event-list' }, types.map(t => h('li', { key: t.slug },
                    h('div', null, h('strong', null, t.name), ' ', t.singular && t.singular !== t.name && h('small', null, '(ед.: ' + t.singular + ')'), h('small', null, 'system name: ' + t.slug)),
                    h('div', { className: 'nuts-section-actions' },
                        h('a', { className: 'nuts-section-action', href: nutsConfig.admin + 'edit.php?post_type=' + t.slug, target: '_blank', rel: 'noopener' }, 'Открыть список ↗'),
                        h('button', { type: 'button', disabled: busy, onClick: () => remove(t.slug, t.name) }, 'Удалить'))))) : h('p', null, 'Своих разделов пока нет. Создайте первый — форма слева.'),
                h('p', { className: 'nuts-section-note' }, 'Базовые разделы «Товары», «Услуги» и «Предложения» являются частью панели и не отображаются в этом списке.')));
    }
    function Offers() {
        const offers = useLoad('/wp/v2/nuts_offer?per_page=50&status=any&orderby=modified&order=desc');
        const items = useLoad('/nuts/v1/catalog-items');
        const [title, setTitle] = useState('');
        const [busy, setBusy] = useState(false);
        const [notice, setNotice] = useState('');
        const [error, setError] = useState('');
        const [openId, setOpenId] = useState(0);
        const [draft, setDraft] = useState({});
        async function create(e) {
            e.preventDefault(); if (!title.trim()) return;
            setBusy(true); setError(''); setNotice('');
            try {
                await api({ path: '/wp/v2/nuts_offer', method: 'POST', data: { title: title.trim(), status: 'draft' } });
                setTitle(''); setNotice('Предложение создано. Настройте состав ниже.');
                offers.load();
            } catch (e2) { setError(e2.message || 'Не удалось создать предложение.'); }
            finally { setBusy(false); }
        }
        function toggleItem(offer, kind, id) {
            const key = offer.id;
            const current = draft[key] !== undefined ? draft[key] : (offer.meta && offer.meta._nuts_offer_items) || [];
            const exists = current.some(x => x.t === kind && x.id === id);
            const next = exists ? current.filter(x => !(x.t === kind && x.id === id)) : [...current, { t: kind, id }];
            setDraft({ ...draft, [key]: next });
        }
        async function saveItems(offer) {
            setBusy(true); setError('');
            const key = offer.id;
            const value = draft[key] !== undefined ? draft[key] : (offer.meta && offer.meta._nuts_offer_items) || [];
            try {
                await api({ path: '/wp/v2/nuts_offer/' + offer.id, method: 'POST', data: { meta: { _nuts_offer_items: value } } });
                const d = { ...draft }; delete d[key]; setDraft(d);
                setNotice('Состав предложения «' + (offer.title.raw || 'Без названия') + '» сохранён.');
                offers.load();
            } catch (e2) { setError(e2.message || 'Не удалось сохранить состав.'); }
            finally { setBusy(false); }
        }
        const groups = items.data || [];
        return h('div', { className: 'nuts-section-grid' },
            h('form', { className: 'nuts-section-tile', onSubmit: create },
                h('h2', null, 'Новое предложение'),
                h('p', null, 'Предложение — это набор товаров и услуг: пакет, комплект, акция. Создайте карточку и отметьте, что в неё входит.'),
                h('label', { className: 'nuts-field' }, h('span', null, 'Название предложения'), h('input', { value: title, onChange: e => setTitle(e.target.value), placeholder: 'например: Стартовый пакет', required: true, disabled: busy })),
                h('div', { className: 'nuts-section-actions' }, h('button', { type: 'submit', disabled: busy || !title.trim() }, busy ? 'Создаём…' : 'Создать предложение')),
                h(Messages, { error, notice })),
            h('article', { className: 'nuts-section-tile' },
                h('h2', null, 'Предложения'),
                items.error && h('p', { role: 'alert', className: 'nuts-error' }, items.error),
                offers.error && h('p', { role: 'alert', className: 'nuts-error' }, offers.error),
                !offers.data && !offers.error && h('p', { role: 'status' }, 'Загружаем…'),
                offers.data && !offers.data.length && h('p', null, 'Предложений пока нет.'),
                offers.data && offers.data.map(o => {
                    const selected = draft[o.id] !== undefined ? draft[o.id] : (o.meta && o.meta._nuts_offer_items) || [];
                    const dirty = draft[o.id] !== undefined;
                    const head = h('div', { className: 'nuts-offer-head' },
                        h('strong', null, o.title.raw || 'Без названия'),
                        h('small', null, (o.status === 'publish' ? 'Опубликовано' : 'Черновик') + ' · позиций: ' + selected.length),
                        h('div', { className: 'nuts-section-actions' },
                            h('button', { type: 'button', onClick: () => setOpenId(openId === o.id ? 0 : o.id) }, openId === o.id ? 'Свернуть' : 'Состав'),
                            dirty && h('button', { type: 'button', disabled: busy, onClick: () => saveItems(o) }, 'Сохранить состав')));
                    let body = null;
                    if (openId === o.id) {
                        body = h('div', { className: 'nuts-offer-body' }, groups.map(g => {
                            const list = g.items.length
                                ? g.items.map(it => h('label', { key: g.kind + it.id, className: 'nuts-toggle nuts-offer-item' },
                                    h('input', { type: 'checkbox', checked: selected.some(x => x.t === g.kind && x.id === it.id), onChange: () => toggleItem(o, g.kind, it.id), disabled: busy }),
                                    h('span', null, it.title, it.status !== 'publish' ? h('small', null, ' · черновик') : null)))
                                : h('p', { className: 'nuts-section-note' }, 'Пока пусто — добавьте записи в соответствующий раздел.');
                            return h('div', { key: g.kind, className: 'nuts-offer-group' }, h('h3', null, g.label), list);
                        }));
                    }
                    return h('div', { key: o.id, className: 'nuts-offer-card' }, head, body);
                })));
    }
    window.nutsCatalog = { Types, Offers };
})();
