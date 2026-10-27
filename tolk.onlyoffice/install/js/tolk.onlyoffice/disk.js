(function () {
    'use strict';

    var cfg = window.TolkOO || {};
    var exts = cfg.exts || [];

    // ВАЖНО: атрибуты, в которых интерфейс Диска хранит id файла, зависят от версии модуля disk.
    // Откройте DevTools на странице Диска / в задаче, посмотрите разметку ссылки на файл
    // и при необходимости поправьте эти списки.
    var OBJECT_ATTRS = ['data-object-id', 'data-bx-object-id'];
    var ATTACHED_ATTRS = ['data-attached-object-id', 'data-bx-attached-object-id'];
    var NAME_ATTRS = ['data-title', 'data-name', 'title'];
    var MAX_DEPTH = 6;

    var isDesktop = typeof window.BXDesktopSystem !== 'undefined'
        || /BitrixDesktop/i.test(navigator.userAgent);

    function firstAttr(el, list) {
        for (var i = 0; i < list.length; i++) {
            if (el.hasAttribute && el.hasAttribute(list[i])) {
                return el.getAttribute(list[i]);
            }
        }
        return null;
    }

    function findTarget(node) {
        var start = node;
        for (var depth = 0; node && depth < MAX_DEPTH; depth++, node = node.parentNode) {
            if (!node.getAttribute) continue;
            var attached = parseInt(firstAttr(node, ATTACHED_ATTRS), 10) || 0;
            var object = parseInt(firstAttr(node, OBJECT_ATTRS), 10) || 0;
            if (attached || object) {
                return { el: node, objectId: object, attachedId: attached };
            }
        }
        // Плитка Диска: клик по иконке — берём ссылку с названием из той же карточки.
        var card = start && start.closest && start.closest('.disk-folder-list-item');
        var link = card && card.querySelector('[data-object-id]');
        if (link) {
            return { el: link, objectId: parseInt(link.getAttribute('data-object-id'), 10) || 0, attachedId: 0 };
        }
        return null;
    }

    function extOf(el) {
        var name = firstAttr(el, NAME_ATTRS) || el.textContent || '';
        var m = /\.([a-z0-9]+)\s*$/i.exec(name.trim());
        return m ? m[1].toLowerCase() : '';
    }

    function query(objectId, attachedId) {
        return 'fileId=' + encodeURIComponent(objectId) + '&attachedId=' + encodeURIComponent(attachedId);
    }

    function open(objectId, attachedId) {
        objectId = objectId || 0;
        attachedId = attachedId || 0;
        var oKey = objectId + ":" + attachedId; if (window.__tolkOoLast && window.__tolkOoLast.k === oKey && Date.now() - window.__tolkOoLast.t < 1500) return; window.__tolkOoLast = { k: oKey, t: Date.now() };

        if (!isDesktop) {
            window.open(cfg.editUrl + '?' + query(objectId, attachedId), '_blank');
            return;
        }

        // Клиент Битрикс24: получаем ссылку с одноразовым токеном,
        // чтобы редактор открылся даже во внешнем браузере без входа в портал.
        var sessid = (window.BX && BX.bitrix_sessid) ? BX.bitrix_sessid() : '';
        fetch(cfg.tokenUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sessid=' + encodeURIComponent(sessid) + '&' + query(objectId, attachedId)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.url) {
                    window.open(d.url, '_blank');
                } else {
                    alert((d && d.error) || 'Не удалось открыть документ');
                }
            })
            .catch(function () { alert('Не удалось открыть документ'); });
    }

    window.addEventListener('click', function (e) {
        // С модификаторами (Ctrl/Shift/Alt) оставляем стандартное поведение портала.
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var t = findTarget(e.target);
        if (!t || exts.indexOf(extOf(t.el)) === -1) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        open(t.objectId, t.attachedId);
    }, true);

    window.addEventListener('dblclick', function (e) { if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; var t = findTarget(e.target); if (t && exts.indexOf(extOf(t.el)) !== -1) { e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation(); } }, true);

    // ---------- Меню «Добавить» на Диске ----------

    var CREATE_URL = '/onlyoffice/create.php';

    // Пункты, которые убираем из меню (сравнение по тексту пункта).
    var HIDE_ITEMS = ['Google Docs', 'MS Office Online', 'Office365', 'Программы на компьютере'];

    var CREATE_ITEMS = [
        { type: 'docx', text: 'Документ' },
        { type: 'xlsx', text: 'Таблица' },
        { type: 'pptx', text: 'Презентация' }
    ];

    function createDocument(type, folderId) {
        var sessid = (window.BX && BX.bitrix_sessid) ? BX.bitrix_sessid() : '';
        // В браузере открываем вкладку сразу по клику, иначе её заблокирует защита от всплывающих окон.
        var win = isDesktop ? null : window.open('about:blank', '_blank');

        fetch(CREATE_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sessid=' + encodeURIComponent(sessid)
                + '&type=' + encodeURIComponent(type)
                + '&folderId=' + encodeURIComponent(folderId)
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.objectId) {
                    if (win) win.close();
                    alert((d && d.error) || 'Не удалось создать документ');
                    return;
                }
                if (win) {
                    win.location.href = cfg.editUrl + '?' + query(d.objectId, 0);
                } else {
                    open(d.objectId, 0);
                }
                // Обновляем список файлов, чтобы новый документ появился на Диске.
                setTimeout(function () { window.location.reload(); }, 1500);
            })
            .catch(function () {
                if (win) win.close();
                alert('Не удалось создать документ');
            });
    }

    function makeItem(item, folderId, closePopup) {
        var el = document.createElement('span');
        el.className = 'menu-popup-item menu-popup-no-icon tolk-oo-create';
        el.setAttribute('role', 'menuitem');
        el.setAttribute('tabindex', '-1');
        el.innerHTML = '<span class="menu-popup-item-icon"></span><span class="menu-popup-item-text"></span>';
        el.lastChild.textContent = item.text;
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closePopup();
            createDocument(item.type, folderId);
        });
        return el;
    }

    function patchAddMenu(root) {
        var items = root.querySelector('.menu-popup-items');
        if (!items || items.getAttribute('data-tolk-oo') === 'Y') return;

        var folderInput = root.querySelector('input[name="targetFolderId"]');
        var folderId = folderInput ? parseInt(folderInput.value, 10) : 0;
        if (!folderId) return;
        items.setAttribute('data-tolk-oo', 'Y');

        // Скрываем ненужные пункты.
        Array.prototype.forEach.call(items.querySelectorAll('.menu-popup-item'), function (it) {
            var text = (it.querySelector('.menu-popup-item-text') || it).textContent.trim();
            if (HIDE_ITEMS.indexOf(text) !== -1) {
                it.style.display = 'none';
            }
        });

        var popup = root.closest('.popup-window');
        var closePopup = function () { if (popup) popup.style.display = 'none'; };

        // Добавляем свои пункты в начало меню, перед «Файл».
        var first = items.firstChild;
        CREATE_ITEMS.forEach(function (item) {
            items.insertBefore(makeItem(item, folderId, closePopup), first);
        });
        var sep = document.createElement('span');
        sep.className = 'popup-window-delimiter tolk-oo-create';
        sep.style.cssText = 'display:block;height:1px;margin:4px 0;background:rgba(0,0,0,.1)';
        items.insertBefore(sep, first);
    }

    function scanForAddMenu() {
        var root = document.getElementById('popup-window-content-menu-popup-popupMenuAdd');
        if (root) patchAddMenu(root);
    }

    new MutationObserver(scanForAddMenu).observe(document.documentElement, { childList: true, subtree: true });
    scanForAddMenu();

    // Для отладки из консоли: TolkOO.open(123) или TolkOO.open(0, 456)
    window.TolkOO = window.TolkOO || {};
    window.TolkOO.open = open;
})();
