/*
 * Replaces the browser's native <datalist> popup with our own dropdown, app-wide.
 *
 * The native popup clips a long name (supplier, customer, item...) to its own internal width no
 * matter how wide the <input> is. Every <input list="..."> is adopted here: the `list` attribute
 * is moved to data-dl-list (so the browser never opens its popup) and the datalist's <option>s —
 * read fresh each time the dropdown opens, so datalists a page fills in later still work — are
 * shown as wrapped rows. Picking a row sets the value and fires the same `input` and `change`
 * events a native pick does, so each page's existing handlers run unchanged.
 */
(function () {
    'use strict';

    if (window.__datalistDropdown) return;
    window.__datalistDropdown = true;

    var MAX_ROWS = 100;
    var current = null;
    var rows = [];
    var activeIndex = -1;
    var picking = false;

    var style = document.createElement('style');
    style.textContent =
        '#dl-dropdown{position:fixed;z-index:2000;display:none;overflow-y:auto;background:#fff;' +
        'border:1px solid rgba(0,0,0,.15);border-radius:.375rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.15);}' +
        '#dl-dropdown button{display:block;width:100%;text-align:left;background:#fff;border:0;' +
        'border-bottom:1px solid #f1f1f1;padding:.375rem .75rem;cursor:pointer;white-space:normal;' +
        'word-break:break-word;font-size:.8125rem;color:inherit;}' +
        '#dl-dropdown button:last-child{border-bottom:0;}' +
        '#dl-dropdown button:hover,#dl-dropdown button.dl-active{background:#f1f3f5;}' +
        '#dl-dropdown .dl-hint{display:block;font-size:.72rem;color:#8592a3;}';
    document.head.appendChild(style);

    var box = document.createElement('div');
    box.id = 'dl-dropdown';
    box.setAttribute('role', 'listbox');
    document.body.appendChild(box);

    function adopt(input) {
        if (!input || input.dataset.dlList || !input.hasAttribute || !input.hasAttribute('list')) return;
        input.dataset.dlList = input.getAttribute('list');
        input.removeAttribute('list');
    }

    function adoptWithin(node) {
        if (!node || node.nodeType !== 1) return;
        if (node.matches && node.matches('input[list]')) adopt(node);
        if (node.querySelectorAll) node.querySelectorAll('input[list]').forEach(adopt);
    }

    function optionsOf(input) {
        var datalist = document.getElementById(input.dataset.dlList);
        if (!datalist) return [];

        return Array.prototype.map.call(datalist.options, function (option) {
            var hint = option.getAttribute('label') || (option.textContent || '').trim();
            return { value: option.value, hint: hint === option.value ? '' : hint };
        }).filter(function (option) { return option.value !== ''; });
    }

    function position(input) {
        var rect = input.getBoundingClientRect();
        var spaceBelow = window.innerHeight - rect.bottom;
        var spaceAbove = rect.top;
        var above = spaceBelow < 180 && spaceAbove > spaceBelow;
        var width = Math.min(Math.max(rect.width, 260), window.innerWidth - 16);

        box.style.width = width + 'px';
        box.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
        box.style.maxHeight = Math.max(120, Math.min(280, (above ? spaceAbove : spaceBelow) - 12)) + 'px';

        if (above) {
            box.style.top = '';
            box.style.bottom = (window.innerHeight - rect.top + 2) + 'px';
        } else {
            box.style.bottom = '';
            box.style.top = (rect.bottom + 2) + 'px';
        }
    }

    function close() {
        box.style.display = 'none';
        box.textContent = '';
        current = null;
        rows = [];
        activeIndex = -1;
    }

    function render(input) {
        var typed = input.value.trim().toLowerCase();
        var all = optionsOf(input);
        // A field already holding an exact option browses the whole list instead of
        // filtering down to that single entry.
        var exact = typed !== '' && all.some(function (o) { return o.value.toLowerCase() === typed; });
        var list = (typed === '' || exact) ? all : all.filter(function (o) {
            return (o.value + ' ' + o.hint).toLowerCase().indexOf(typed) !== -1;
        });
        list = list.slice(0, MAX_ROWS);

        if (!list.length) {
            close();
            return;
        }

        box.textContent = '';
        list.forEach(function (o, i) {
            var button = document.createElement('button');
            button.type = 'button';
            button.dataset.i = i;

            var label = document.createElement('span');
            label.textContent = o.value;
            button.appendChild(label);

            if (o.hint) {
                var hint = document.createElement('span');
                hint.className = 'dl-hint';
                hint.textContent = o.hint;
                button.appendChild(hint);
            }
            box.appendChild(button);
        });

        rows = list;
        activeIndex = -1;
        current = input;
        position(input);
        box.style.display = 'block';
    }

    function pick(index) {
        var input = current;
        var option = rows[index];
        if (!input || !option) return;

        picking = true;
        input.value = option.value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        picking = false;
        close();
    }

    function highlight(index) {
        var buttons = box.querySelectorAll('button');
        buttons.forEach(function (b) { b.classList.remove('dl-active'); });
        activeIndex = index;
        if (buttons[index]) {
            buttons[index].classList.add('dl-active');
            buttons[index].scrollIntoView({ block: 'nearest' });
        }
    }

    function usable(el) {
        return el instanceof HTMLInputElement && el.dataset.dlList && !el.readOnly && !el.disabled;
    }

    document.addEventListener('focusin', function (e) {
        if (!(e.target instanceof HTMLInputElement)) return;
        adopt(e.target);
        if (usable(e.target)) render(e.target);
    });

    document.addEventListener('input', function (e) {
        if (picking || !usable(e.target) || document.activeElement !== e.target) return;
        render(e.target);
    });

    document.addEventListener('click', function (e) {
        if (usable(e.target) && current !== e.target) render(e.target);
    });

    document.addEventListener('focusout', function (e) {
        var input = e.target;
        if (input !== current) return;
        setTimeout(function () {
            if (current === input && document.activeElement !== input) close();
        }, 150);
    });

    // Capture phase, so Escape closes just this dropdown and doesn't also dismiss a modal behind it.
    document.addEventListener('keydown', function (e) {
        if (!current || e.target !== current) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var next = activeIndex + (e.key === 'ArrowDown' ? 1 : -1);
            highlight(Math.max(0, Math.min(rows.length - 1, next)));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            e.stopPropagation();
            pick(activeIndex);
        } else if (e.key === 'Escape') {
            e.stopPropagation();
            close();
        } else if (e.key === 'Tab') {
            close();
        }
    }, true);

    box.addEventListener('mousedown', function (e) {
        var button = e.target.closest('button[data-i]');
        if (!button) return;
        e.preventDefault();
        pick(parseInt(button.dataset.i, 10));
    });

    window.addEventListener('scroll', function () { if (current) position(current); }, true);
    window.addEventListener('resize', function () { if (current) position(current); });

    adoptWithin(document.body);
    new MutationObserver(function (mutations) {
        mutations.forEach(function (m) { m.addedNodes.forEach(adoptWithin); });
    }).observe(document.body, { childList: true, subtree: true });
})();
