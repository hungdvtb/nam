/* Presentation and navigation for Dạy PA; chart actions stay in app.js. */
(() => {
    'use strict';
    const icons = {
        swap: '<path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4"/>',
        clock: '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        filter: '<path d="M3 4h18l-7 8v7l-4 2v-9Z"/>',
        refresh: '<path d="M20 7v5h-5M4 17v-5h5M6 6a8 8 0 0 1 13 2M18 18a8 8 0 0 1-13-2"/>',
        plus: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 7v10M7 12h10"/>',
        briefcase: '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 12a22 22 0 0 0 18 0M12 11v4"/>',
        eye: '<path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
        eyeOff: '<path d="m3 3 18 18M10.6 6.2A10.6 10.6 0 0 1 12 6c6.2 0 9.5 6 9.5 6a16 16 0 0 1-3 3.5M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.3 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        previous: '<path d="M5 5v14M18 5 8 12l10 7Z"/>',
        next: '<path d="M19 5v14M6 5l10 7-10 7Z"/>',
        play: '<path d="m8 5 11 7-11 7Z" fill="currentColor" stroke="none"/>',
        pause: '<path d="M8 5v14M16 5v14" stroke-width="3"/>',
        gauge: '<path d="M4 19a10 10 0 1 1 16 0H4ZM12 4v3M5 8l2 2M19 8l-2 2M12 15l4-6"/>',
        crosshair: '<circle cx="12" cy="12" r="6"/><path d="M12 2v6m0 8v6M2 12h6m8 0h6"/>',
        cursor: '<path d="m4 3 15 10-7 1-3 7Z"/>',
        line: '<circle cx="5" cy="19" r="2"/><circle cx="19" cy="5" r="2"/><path d="m7 17 10-10"/>',
        horizontal: '<path d="M3 12h18"/>',
        vertical: '<path d="M12 3v18"/>',
        rectangle: '<rect x="3" y="4" width="18" height="16" rx="1"/>',
        circle: '<ellipse cx="12" cy="12" rx="9" ry="7"/>',
        pencil: '<path d="m15 4 5 5M4 15 15 4a3.5 3.5 0 0 1 5 5L9 20l-6 1Z"/>',
        sellZone: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 10 4 4 4-4"/>',
        buyZone: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m8 14 4-4 4 4"/>',
        up: '<path d="M12 21V3m-7 7 7-7 7 7"/>',
        down: '<path d="M12 3v18m-7-7 7 7 7-7"/>',
        undo: '<path d="m8 3-5 5 5 5M3 8h11a6 6 0 0 1 0 12H9"/>',
        redo: '<path d="m16 3 5 5-5 5m5-5H10a6 6 0 0 0 0 12h5"/>',
        trash: '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        expand: '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/>',
        settings: '<path d="m9 3-1 3-3 1v4l-2 1 2 2v3l3 1 1 3h6l1-3 3-1v-3l2-2-2-1V7l-3-1-1-3Z"/><circle cx="12" cy="12" r="3"/>',
        more: '<circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="20" cy="12" r="1" fill="currentColor"/>',
        sparkles: '<path d="m13 3 3 6 6 3-6 3-3 6-3-6-6-3 6-3ZM4 2v5M1.5 4.5h5" fill="currentColor" stroke="none"/>',
        send: '<path d="m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3"/>',
        paperclip: '<path d="m8 13 7-7a3 3 0 0 1 4 4L9 20a5 5 0 0 1-7-7L13 2m-7 13 9-9"/>',
        check: '<path d="m5 12 4 4L19 6"/>',
        close: '<path d="m5 5 14 14M19 5 5 19"/>',
        bookmark: '<path d="M6 21V3h12v18l-6-4Z"/>',
        moon: '<path d="M20 15A9 9 0 0 1 9 3a9 9 0 1 0 11 12Z"/>',
        fit: '<path d="M3 8V3h5m8 0h5v5M3 16v5h5m13-5v5h-5M8 12h8m-4-4v8"/>',
        eraser: '<path d="m3 13 10-10a2 2 0 0 1 3 0l5 5a2 2 0 0 1 0 3L11 21H7l-4-4a3 3 0 0 1 0-4ZM8 8l9 9M11 21h10"/>',
        ruler: '<path d="m3 16 13-13 5 5L8 21ZM12 7l3 3m-7 1 3 3m-7 1 3 3"/>',
        text: '<path d="M4 6V3h16v3M12 3v18m-4 0h8"/>',
        ray: '<circle cx="4" cy="20" r="2"/><path d="M6 18 20 4m-6 0h6v6"/>',
        first: '<path d="M4 5v14M20 5 10 12l10 7Z"/>',
        last: '<path d="M20 5v14M4 5l10 7-10 7Z"/>',
        start: '<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4Z"/>',
        camera: '<path d="M3 7h4l2-3h6l2 3h4v14H3Z"/><circle cx="12" cy="13" r="4"/>',
        help: '<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 5m0 3v.01"/>',
    };
    const svg = (name) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${icons[name] || icons.more}</svg>`;
    const byId = (id) => document.getElementById(id);

    function initNavigation() {
        const drawer = byId('navigationDrawer');
        const sidebar = byId('appSidebar');
        const reveal = byId('navigationReveal');
        if (!drawer || !sidebar || !reveal) return;
        let hideTimer;
        let pointerInside = false;
        let openedByPointer = false;
        let keyboardNavigation = false;
        const setOpen = (open) => {
            clearTimeout(hideTimer);
            if (!open && sidebar.contains(document.activeElement)) reveal.focus({ preventScroll: true });
            drawer.classList.toggle('is-open', open);
            reveal.setAttribute('aria-expanded', String(open));
            reveal.setAttribute('aria-label', open ? 'Đóng menu điều hướng' : 'Mở menu điều hướng');
            sidebar.inert = !open;
        };
        const deferClose = () => {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                if (!pointerInside && !(keyboardNavigation && drawer.contains(document.activeElement))) setOpen(false);
            }, 220);
        };
        drawer.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'touch') return;
            pointerInside = true;
            openedByPointer = !drawer.classList.contains('is-open');
            setOpen(true);
        });
        drawer.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'touch') return;
            pointerInside = false;
            deferClose();
        });
        reveal.addEventListener('click', (event) => {
            // First pointer click can follow pointerenter; keep that opening usable.
            const open = openedByPointer && event.detail > 0 ? true : !drawer.classList.contains('is-open');
            openedByPointer = false;
            setOpen(open);
        });
        drawer.addEventListener('focusin', (event) => {
            if (event.target !== reveal) setOpen(true);
        });
        drawer.addEventListener('focusout', deferClose);
        drawer.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            reveal.focus();
            setOpen(false);
        });
        sidebar.addEventListener('click', (event) => {
            if (!event.target.closest('.nav-tab')) return;
            reveal.focus({ preventScroll: true });
            setOpen(false);
        });
        document.addEventListener('pointerdown', (event) => {
            keyboardNavigation = false;
            if (!drawer.contains(event.target)) setOpen(false);
        });
        document.addEventListener('keydown', (event) => {
            if (['Tab', 'ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) keyboardNavigation = true;
        });
        setOpen(false);
    }

    function init(adapter) {
        initNavigation();
        const root = document.querySelector('.pa-workspace-v2');
        if (!root) return;
        const toolbar = byId('paIconToolbar');
        const host = toolbar.parentElement;
        const headerSymbolButton = byId('paWorkspaceSymbolButton');
        const headerTimeframeButtons = [...document.querySelectorAll('[data-pa-header-timeframe]')];
        let currentPopover = null;
        let currentTrigger = null;
        let lastState = {};
        let symbolFilterText = '';
        const actionButtons = new Map();
        document.querySelectorAll('[data-pa-icon]').forEach((node) => { node.innerHTML = svg(node.dataset.paIcon); });

        function makeButton(action, icon, label, tone = '') {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `pa-tool-button ${tone ? `is-${tone}` : ''}`;
            button.dataset.paCommand = action;
            button.setAttribute('aria-label', label);
            button.title = label;
            if (action === 'cases') {
                button.setAttribute('aria-controls', 'paTrainingZonesView');
                button.setAttribute('aria-expanded', 'false');
            }
            button.innerHTML = svg(icon);
            if (!actionButtons.has(action)) actionButtons.set(action, []);
            actionButtons.get(action).push(button);
            return button;
        }
        const groups = [
            [['symbol', 'swap', 'Chọn cặp tiền'], ['timeframe', 'clock', 'Đổi khung thời gian'], ['filter', 'filter', 'Lọc vùng giá'], ['refresh', 'refresh', 'Tải lại bộ nhớ']],
            [['new-case', 'plus', 'Tạo ca trade'], ['cases', 'eye', 'Xem danh sách ca'], ['skip-case', 'next', 'Bỏ ca trade này']],
            [['previous', 'previous', 'Lùi nến'], ['play', 'play', 'Chạy tua nến', 'primary'], ['next', 'next', 'Tiến nến'], ['replay', 'gauge', 'Tốc độ và vị trí tua nến']],
            [['tool:cursor', 'crosshair', 'Con trỏ / kéo biểu đồ'], ['tool:trendline', 'line', 'Đường xu hướng (T)'], ['tool:hline', 'horizontal', 'Đường ngang (H)'], ['tool:ellipse', 'rectangle', 'Khoanh vùng gửi AI'], ['tool:brush', 'pencil', 'Bút vẽ']],
            [['zone:supply', 'sellZone', 'Vẽ vùng canh sell', 'sell'], ['zone:demand', 'buyZone', 'Vẽ vùng canh buy', 'buy'], ['tool:long-position', 'up', 'Vị thế mua (L)', 'buy'], ['tool:short-position', 'down', 'Vị thế bán (S)', 'sell'], ['trade-markers', 'eye', 'Ẩn điểm đánh dấu Entry, SL, TP']],
            [['undo', 'undo', 'Hoàn tác (Ctrl+Z)'], ['delete', 'trash', 'Xóa hình vẽ đã chọn'], ['fullscreen', 'expand', 'Toàn màn hình'], ['settings', 'settings', 'Cài đặt biểu đồ'], ['more', 'more', 'Thêm công cụ']],
        ];
        groups.forEach((items) => {
            const group = document.createElement('div');
            group.className = 'pa-tool-group';
            items.forEach((item) => group.appendChild(makeButton(...item)));
            toolbar.appendChild(group);
        });

        const popoverIds = { symbol: 'paSymbolPopover', timeframe: 'paTimeframePopover', filter: 'paFilterPopover', settings: 'paSettingsPopover', more: 'paMorePopover' };
        Object.entries(popoverIds).forEach(([action, id]) => {
            actionButtons.get(action)[0].setAttribute('aria-expanded', 'false');
            actionButtons.get(action)[0].setAttribute('aria-controls', id);
        });
        // Move the original form controls, retaining their IDs, values and handlers.
        const symbolPopover = byId('paSymbolPopover');
        const symbolSearch = byId('paTrainingSymbolSearch');
        const symbolLabel = symbolSearch?.closest('label');
        if (symbolLabel) symbolPopover.appendChild(symbolLabel);
        const symbolChoiceList = document.createElement('div');
        symbolChoiceList.id = 'paSymbolChoiceList';
        symbolChoiceList.className = 'pa-symbol-choice-list';
        symbolChoiceList.setAttribute('role', 'listbox');
        symbolChoiceList.setAttribute('aria-label', 'Danh sách cặp tiền');
        symbolPopover.appendChild(symbolChoiceList);
        byId('paFilterPopover').appendChild(byId('paTrainingZoneFilter').closest('label'));
        const timeframes = byId('paTimeframePopover');
        timeframes.innerHTML = '<strong>Khung thời gian</strong><div class="pa-timeframe-options"></div>';
        ['M1', 'M5', 'M15', 'M30', 'H1', 'H4', 'D1'].forEach((timeframe) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'pa-timeframe-option';
            button.dataset.paTimeframe = timeframe;
            button.textContent = timeframe;
            button.addEventListener('click', () => {
                adapter.timeframe(timeframe);
                closePopover(true);
            });
            timeframes.lastElementChild.appendChild(button);
        });
        const settings = byId('paSettingsPopover');
        settings.innerHTML = '<strong>Cài đặt biểu đồ</strong><div class="pa-settings-fields"></div>';
        ['chartTypeSelect', 'chartMagnetMode'].forEach((id) => {
            const control = byId(id);
            if (!control) return;
            const label = document.createElement('label');
            label.className = 'field';
            const text = document.createElement('span');
            text.textContent = control.getAttribute('aria-label');
            label.append(text, control.cloneNode(true));
            const copy = label.lastElementChild;
            copy.removeAttribute('id');
            copy.dataset.paSetting = id;
            copy.addEventListener('change', () => {
                control.value = copy.value;
                control.dispatchEvent(new Event('change', { bubbles: true }));
            });
            settings.lastElementChild.appendChild(label);
        });
        const caseCountControl = byId('paTrainingCaseCount')?.closest('.pa-case-count-control');
        const newCaseButton = actionButtons.get('new-case')?.[0];
        if (caseCountControl && newCaseButton) {
            caseCountControl.classList.add('pa-toolbar-case-count');
            newCaseButton.after(caseCountControl);
        }
        ['ema20', 'ema50', 'ema200'].forEach((key) => {
            const original = document.querySelector(`[data-chart-toggle="${key}"]`);
            if (!original) return;
            const label = document.createElement('label');
            label.className = 'pa-indicator-option';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.dataset.paIndicator = key;
            input.addEventListener('change', () => {
                original.checked = input.checked;
                original.dispatchEvent(new Event('change', { bubbles: true }));
            });
            label.append(input, key.toUpperCase().replace('EMA', 'EMA '));
            settings.lastElementChild.appendChild(label);
        });
        const more = byId('paMorePopover');
        more.innerHTML = '<strong>Thêm công cụ</strong><div class="pa-extra-tools"></div>';
        [
            ['start-cases', 'start', 'Bắt đầu ca dạy'], ['first', 'first', 'Về nến đầu tiên'], ['last', 'last', 'Về nến mới nhất'],
            ['fit', 'fit', 'Căn vừa biểu đồ'], ['fit-x', 'horizontal', 'Căn vừa chiều ngang'], ['fit-y', 'vertical', 'Căn vừa chiều dọc'],
            ['tool:select', 'cursor', 'Chọn hình vẽ'], ['tool:ray', 'ray', 'Tia xu hướng (R)'], ['tool:measure', 'ruler', 'Đo khoảng cách (M)'],
            ['tool:text', 'text', 'Ghi chú (N)'], ['tool:erase', 'eraser', 'Tẩy hình vẽ (E)'], ['redo', 'redo', 'Làm lại (Ctrl+Y)'],
            ['theme', 'moon', 'Đổi nền sáng / tối'], ['screenshot', 'camera', 'Chụp biểu đồ'], ['clear', 'trash', 'Xóa toàn bộ hình vẽ'],
        ].forEach((item) => more.lastElementChild.appendChild(makeButton(...item)));
        const normalizeSymbol = (value) => String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
        function availableSymbols() {
            const select = byId('paTrainingSymbolSelect');
            const seen = new Set();
            return [...(select?.options || [])]
                .map((option) => String(option.value || option.textContent || '').trim().toUpperCase())
                .filter((symbol) => {
                    const key = normalizeSymbol(symbol);
                    if (!key || seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });
        }
        function chooseSymbol(symbol) {
            const select = byId('paTrainingSymbolSelect');
            if (select) select.value = symbol;
            if (symbolSearch) symbolSearch.value = symbol;
            if (adapter.symbol) adapter.symbol(symbol);
            else select?.dispatchEvent(new Event('change', { bubbles: true }));
            symbolFilterText = '';
            closePopover(true);
        }
        function renderSymbolChoices(activeSymbol = '') {
            const activeKey = normalizeSymbol(activeSymbol);
            const filterKey = normalizeSymbol(symbolFilterText);
            const symbols = availableSymbols().filter((symbol) => !filterKey || normalizeSymbol(symbol).includes(filterKey));
            symbolChoiceList.replaceChildren();
            if (!symbols.length) {
                const empty = document.createElement('p');
                empty.className = 'pa-symbol-choice-empty';
                empty.textContent = 'Chưa có cặp phù hợp.';
                symbolChoiceList.appendChild(empty);
                return;
            }
            symbols.forEach((symbol) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pa-symbol-choice';
                button.setAttribute('role', 'option');
                const active = normalizeSymbol(symbol) === activeKey;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', String(active));
                const dot = document.createElement('span');
                dot.className = 'pa-market-dot';
                dot.setAttribute('aria-hidden', 'true');
                const name = document.createElement('strong');
                name.textContent = symbol;
                const meta = document.createElement('small');
                meta.textContent = 'H4 / M15';
                button.append(dot, name, meta);
                button.addEventListener('click', () => chooseSymbol(symbol));
                symbolChoiceList.appendChild(button);
            });
        }
        symbolSearch?.addEventListener('focus', () => {
            symbolFilterText = '';
            symbolSearch.select();
            renderSymbolChoices(lastState.symbol);
        });
        symbolSearch?.addEventListener('input', () => {
            symbolFilterText = symbolSearch.value;
            renderSymbolChoices(lastState.symbol);
        });
        const status = byId('paTrainingStatus');
        more.appendChild(status);

        // Keep the main row intact on large screens; move secondary icons into
        // the same "more" menu when space is limited, never clip action buttons.
        const adaptiveTools = document.createElement('div');
        adaptiveTools.className = 'pa-extra-tools pa-overflow-tools';
        more.insertBefore(adaptiveTools, more.querySelector('.pa-extra-tools'));
        const toolbarHomes = new Map();
        const overflowOrder = [
            'refresh', 'tool:brush', 'tool:ellipse', 'undo', 'delete', 'previous', 'next',
            'new-case', 'cases', 'fullscreen', 'tool:trendline', 'tool:hline', 'tool:long-position',
            'tool:short-position', 'replay', 'filter', 'settings', 'zone:supply', 'zone:demand',
            'skip-case',
        ];
        overflowOrder.forEach((action) => {
            const button = actionButtons.get(action)?.[0];
            if (!button || !toolbar.contains(button)) return;
            const marker = document.createComment(action);
            button.before(marker);
            toolbarHomes.set(action, { button, marker });
        });
        let toolbarResizeFrame;
        const fitToolbar = () => {
            if (!toolbar.clientWidth) return;
            toolbarHomes.forEach(({ button, marker }) => marker.after(button));
            toolbar.querySelectorAll('.pa-tool-group').forEach((group) => { group.hidden = false; });
            for (const action of overflowOrder) {
                if (toolbar.scrollWidth <= toolbar.clientWidth + 1) break;
                const home = toolbarHomes.get(action);
                if (!home) continue;
                adaptiveTools.appendChild(home.button);
                const group = home.marker.parentElement;
                group.hidden = !group.querySelector('button');
            }
            adaptiveTools.hidden = !adaptiveTools.children.length;
        };
        new ResizeObserver(() => {
            cancelAnimationFrame(toolbarResizeFrame);
            toolbarResizeFrame = requestAnimationFrame(() => { closePopover(); fitToolbar(); });
        }).observe(toolbar);

        function closePopover(restoreFocus = false) {
            if (!currentPopover) return;
            currentPopover.hidden = true;
            currentTrigger?.setAttribute('aria-expanded', 'false');
            if (restoreFocus) currentTrigger?.focus({ preventScroll: true });
            currentPopover = null;
            currentTrigger = null;
        }
        function openPopover(action, trigger) {
            const popover = byId(popoverIds[action]);
            const wasOpen = currentPopover === popover;
            const anchor = trigger || actionButtons.get('more')?.[0];
            if (!popover || !anchor) return;
            closePopover();
            if (wasOpen) return;
            currentPopover = popover;
            currentTrigger = anchor;
            popover.hidden = false;
            anchor.setAttribute('aria-expanded', 'true');
            const hostBox = host.getBoundingClientRect();
            const triggerBox = anchor.getBoundingClientRect();
            const left = Math.max(8, Math.min(triggerBox.left - hostBox.left, host.clientWidth - popover.offsetWidth - 8));
            popover.style.left = `${left}px`;
            popover.querySelector('input, select, button')?.focus({ preventScroll: true });
            sync(lastState);
        }
        headerSymbolButton?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            openPopover('symbol', headerSymbolButton);
        });
        headerTimeframeButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                closePopover();
                adapter.timeframe(button.dataset.paHeaderTimeframe);
            });
        });
        root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-pa-command]');
            if (!button) return;
            const action = button.dataset.paCommand;
            if (popoverIds[action]) {
                openPopover(action, button);
            } else if (action === 'cases') {
                const panel = byId('paTrainingZonesView');
                panel.classList.toggle('is-pa-queue-open');
                const open = panel.classList.contains('is-pa-queue-open');
                button.setAttribute('aria-expanded', String(open));
                button.classList.toggle('is-active', open);
                if (open) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                closePopover();
                adapter.action(action);
            }
        });
        document.addEventListener('pointerdown', (event) => {
            if (currentPopover && !currentPopover.contains(event.target) && !currentTrigger.contains(event.target)) closePopover();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && currentPopover) {
                event.preventDefault();
                event.stopImmediatePropagation();
                closePopover(true);
            }
        }, true);
        toolbar.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const buttons = [...toolbar.querySelectorAll('button:not(:disabled)')];
            const index = buttons.indexOf(document.activeElement);
            if (index < 0) return;
            event.preventDefault();
            event.stopPropagation();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
                : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            buttons[next].focus();
        });

        const actions = root.querySelector('.pa-ai-action-row');
        const aiMore = document.createElement('div');
        aiMore.id = 'paCoachMoreActions';
        aiMore.className = 'pa-coach-more-actions';
        aiMore.hidden = true;
        const aiIcons = ['check', 'close', 'pencil', 'bookmark', 'next', 'help', 'sparkles', 'clock', 'next'];
        [...actions.children].forEach((button, index) => {
            if (button.hasAttribute('data-pa-case-skip')) {
                button.innerHTML = `${svg('next')}<span>Bỏ ca này</span>`;
                button.classList.add('pa-case-skip-visible');
                button.title = 'Bỏ ca này và chuyển sang ca tiếp theo';
                button.setAttribute('aria-label', button.title);
                button.dataset.paActionTitle = 'Bỏ ca này';
            } else {
                button.innerHTML = svg(aiIcons[index]);
            }
            if (index >= 4 && !button.hasAttribute('data-pa-case-skip')) aiMore.appendChild(button);
        });
        root.querySelector('.pa-ai-side-head').appendChild(aiMore);
        const savedCasesButton = makeButton('saved-cases', 'briefcase', 'Ca đã lưu: xem, sửa, xóa');
        root.querySelector('.pa-ai-side-head').insertBefore(savedCasesButton, byId('paCoachMoreBtn'));
        const zonesButton = makeButton('nearby-zones', 'rectangle', 'Bật/tắt vùng vào lệnh gần giá và hai vùng TP');
        zonesButton.setAttribute('aria-pressed', 'true');
        more.querySelector('.pa-extra-tools:not(.pa-overflow-tools)').appendChild(zonesButton);
        const editButton = actions.querySelector('[data-pa-case-decision="weak_case"]');
        if (editButton) {
            editButton.title = 'Kéo chỉnh Entry, SL và TP trên chart';
            editButton.setAttribute('aria-label', editButton.title);
            editButton.dataset.paActionTitle = editButton.title;
            editButton.dataset.paActionHelp = 'Bật con trỏ để kéo trực tiếp 3 đường Entry, SL và TP trên biểu đồ. Thả chuột để cập nhật setup; không cần nhập tọa độ.';
            editButton.dataset.paActionItems = 'RR và giá mới hiển thị ngay trên chart.|Sau khi chỉnh xong, bấm Lưu ca nếu muốn lưu thay đổi.';
        }
        const saveButton = actions.querySelector('[data-pa-session-save]');
        if (saveButton) {
            saveButton.title = 'Lưu ca (không bắt buộc chấm đúng/sai)';
            saveButton.setAttribute('aria-label', saveButton.title);
            saveButton.dataset.paActionHelp = 'Lưu setup hiện tại, bản gốc, hội thoại và chart. Ca đã lưu có thể xem, sửa và xóa trong biểu tượng cặp tài liệu cạnh AI Coach.';
            saveButton.dataset.paActionItems = 'Không chọn kết luận vẫn lưu được như ca trao đổi.|Đã chỉnh setup thì lưu bản chỉnh sửa cùng đề xuất gốc.';
        }
        byId('paTrainingChatLog').after(actions);
        const aiMoreBtn = byId('paCoachMoreBtn');
        aiMoreBtn.addEventListener('click', () => {
            aiMore.hidden = !aiMore.hidden;
            aiMoreBtn.setAttribute('aria-expanded', String(!aiMore.hidden));
        });
        document.addEventListener('pointerdown', (event) => {
            if (!aiMore.hidden && !aiMore.contains(event.target) && !aiMoreBtn.contains(event.target)) {
                aiMore.hidden = true;
                aiMoreBtn.setAttribute('aria-expanded', 'false');
            }
        });
        aiMore.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                aiMore.hidden = true;
                aiMoreBtn.setAttribute('aria-expanded', 'false');
                aiMoreBtn.focus();
            }
        });
        byId('paChatChartContextBtn').addEventListener('click', () => adapter.action('tool:ellipse'));
        const input = byId('paTrainingChatInput');
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = `${Math.min(120, Math.max(44, input.scrollHeight))}px`;
        });
        // Repaint the canvas when its real container changes, including responsive/fullscreen layout.
        let resizeFrame;
        new ResizeObserver(() => {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(() => adapter.resize());
        }).observe(byId('paTrainingChartMount'));

        function sync(snapshot = {}) {
            lastState = snapshot;
            const setText = (id, text) => { const node = byId(id); if (node.textContent !== text) node.textContent = text; };
            setText('paWorkspaceSymbol', snapshot.symbol || 'Chọn cặp tiền');
            const activeTimeframe = snapshot.timeframe || 'H4';
            headerTimeframeButtons.forEach((button) => {
                const active = button.dataset.paHeaderTimeframe === activeTimeframe;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            renderSymbolChoices(snapshot.symbol || '');
            setText('paWorkspacePrice', snapshot.price || '—');
            setText('paCoachContextSymbol', `${snapshot.symbol || '—'} · ${snapshot.timeframe || 'H4'}`);
            setText('paCoachContextLabel', snapshot.hasCase ? 'Ca đang chọn' : 'Chart đang xem');
            actionButtons.forEach((buttons, action) => {
                buttons.forEach((button) => {
                    const isTool = action.startsWith('tool:');
                    const isZone = action.startsWith('zone:');
                    if (isTool || isZone) {
                        const active = isTool ? snapshot.tool === action.slice(5) && !snapshot.zone
                            : snapshot.tool === 'rect' && snapshot.zone === action.slice(5);
                        button.classList.toggle('is-active', active);
                        button.setAttribute('aria-pressed', String(active));
                    }
                    if (action === 'play') {
                        const icon = snapshot.playing ? 'pause' : 'play';
                        if (button.dataset.icon !== icon) { button.innerHTML = svg(icon); button.dataset.icon = icon; }
                        button.title = snapshot.playing ? 'Tạm dừng tua nến' : 'Chạy tua nến';
                        button.setAttribute('aria-label', button.title);
                        button.setAttribute('aria-pressed', String(!!snapshot.playing));
                    }
                    if (['play', 'previous', 'next', 'replay'].includes(action)) button.disabled = !snapshot.hasData;
                    if (action === 'new-case') button.disabled = !!snapshot.loading;
                    if (action === 'trade-markers') {
                        const visible = snapshot.teachingLevelMarkersVisible !== false;
                        button.setAttribute('aria-pressed', String(visible));
                        button.title = visible ? 'Ẩn điểm đánh dấu Entry, SL, TP' : 'Hiện điểm đánh dấu Entry, SL, TP';
                        button.setAttribute('aria-label', button.title);
                        button.classList.toggle('is-active', visible);
                        const icon = visible ? 'eye' : 'eyeOff';
                        if (button.dataset.icon !== icon) { button.innerHTML = svg(icon); button.dataset.icon = icon; }
                    }
                });
            });
            timeframes.querySelectorAll('button').forEach((button) => {
                const active = button.dataset.paTimeframe === snapshot.timeframe;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            settings.querySelectorAll('[data-pa-setting]').forEach((copy) => { copy.value = byId(copy.dataset.paSetting)?.value; });
            settings.querySelectorAll('[data-pa-indicator]').forEach((copy) => { copy.checked = !!snapshot.indicators?.[copy.dataset.paIndicator]; });
        }
        return { sync };
    }
    window.PaWorkspace = { init };
})();
