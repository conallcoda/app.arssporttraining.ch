document.addEventListener('alpine:init', () => {
    const emptyPage = () => ({
        records: [],
        nextOffset: 0,
        hasMore: false,
    });

    Alpine.data('sortable_items', (fieldName, reorderAction = 'reorderRelationshipItem') => ({
        fieldName: fieldName,
        reorderAction: reorderAction,
        draggingIndex: null,
        dragOverIndex: null,

        handleDragStart(event, index) {
            this.draggingIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index);

            const row = event.target.closest('[data-item-index]');
            row.classList.add('opacity-50');
        },

        handleDragEnd(event) {
            const row = event.target.closest('[data-item-index]');
            if (row) {
                row.classList.remove('opacity-50');
            }

            this.draggingIndex = null;
            this.dragOverIndex = null;
            this.$el.querySelectorAll('[data-item-index]').forEach((el) => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
        },

        handleDragOver(event, index) {
            event.preventDefault();
            if (this.draggingIndex === index) return;

            this.dragOverIndex = index;
            this.$el.querySelectorAll('[data-item-index]').forEach((el) => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
            event.target.closest('[data-item-index]')?.classList.add('border-l-4', 'border-l-blue-500');
        },

        handleDragLeave(event) {
            const relatedTarget = event.relatedTarget;
            const currentTarget = event.target.closest('[data-item-index]');
            if (currentTarget && !currentTarget.contains(relatedTarget)) {
                currentTarget.classList.remove('border-l-4', 'border-l-blue-500');
            }
        },

        handleDrop(event, targetIndex) {
            event.preventDefault();
            const sourceIndex = parseInt(event.dataTransfer.getData('text/plain'));

            this.$el.querySelectorAll('[data-item-index]').forEach((el) => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });

            if (sourceIndex !== targetIndex) {
                this.$wire[this.reorderAction](this.fieldName, sourceIndex, targetIndex);
            }

            this.draggingIndex = null;
            this.dragOverIndex = null;
        },
    }));

    Alpine.data('relationship_selector_sort_group', (fieldName) => ({
        fieldName,
        localOrder: [],
        listElement: null,

        registerSelectedList() {
            const nextListElement = this.$refs.selectedList;

            if (!nextListElement) {
                return;
            }

            this.listElement = nextListElement;

            this.$nextTick(() => this.applyOrderToDom());
        },

        domKeys() {
            if (!this.listElement) {
                return [];
            }

            return Array.from(this.listElement.querySelectorAll('[data-sort-item-key]'))
                .map((item) => item.dataset.sortItemKey)
                .filter((item) => item !== '');
        },

        mergedOrder(domKeys) {
            const persistedKeys = this.localOrder.filter((key) => domKeys.includes(key));
            const appendedKeys = domKeys.filter((key) => !persistedKeys.includes(key));

            return [...persistedKeys, ...appendedKeys];
        },

        orderedKeys() {
            if (!this.listElement || !this.listElement.isConnected) {
                return [...this.localOrder];
            }

            return this.mergedOrder(this.domKeys());
        },

        syncOrderFromDom() {
            this.localOrder = this.orderedKeys();
        },

        applyOrderToDom() {
            if (!this.listElement) {
                return;
            }

            const rows = Array.from(this.listElement.querySelectorAll('[data-sort-item-key]'));

            if (rows.length === 0) {
                this.localOrder = [];

                return;
            }

            const order = this.mergedOrder(rows.map((row) => row.dataset.sortItemKey));
            const rowsByKey = new Map(rows.map((row) => [row.dataset.sortItemKey, row]));
            const currentOrder = rows.map((row) => row.dataset.sortItemKey);

            if (currentOrder.length === order.length && currentOrder.every((key, index) => key === order[index])) {
                this.localOrder = order;

                return;
            }

            order.forEach((key) => {
                const row = rowsByKey.get(key);

                if (row) {
                    this.listElement.appendChild(row);
                }
            });

            this.localOrder = order;
        },

        handleSort() {
            this.syncOrderFromDom();
        },
    }));

    Alpine.data('relationship_selector_modal', (config = {}) => ({
        fieldName: config.fieldName,
        modalName: config.modalName,
        limit: Number(config.limit ?? 40),
        applyAction: config.applyAction ?? 'applyRelationshipSelectorClientState',
        schemaDefaults: config.schemaDefaults ?? {},
        valueAttribute: config.valueAttribute ?? 'id',
        lists: Array.isArray(config.lists) && config.lists.length > 0 ? config.lists : [
            {
                key: 'results',
                label: 'Results',
                rows: 'resultRows',
                loader: { type: 'default', method: null },
                searchable: true,
                searchPlaceholder: 'Search...',
                rowAction: { type: 'local', name: 'toggleRecord', passSelectedItems: false, passModalState: false },
                sortable: false,
                sortKey: null,
                selectedState: 'isSelected',
                emptyText: 'No matches found.',
                button: {
                    visible: true,
                    action: { type: 'local', name: 'toggleRecord', passSelectedItems: false, passModalState: false },
                    defaultLabel: 'Select',
                    selectedLabel: 'Selected',
                    defaultColor: 'zinc',
                    selectedColor: 'blue',
                    icon: null,
                    iconOnly: false,
                    visibleField: null,
                },
                badge: null,
                itemFields: [],
                panelFields: [],
            },
            {
                key: 'selected',
                label: 'Selected',
                rows: 'selectedRows',
                loader: null,
                searchable: false,
                searchPlaceholder: 'Search...',
                rowAction: {},
                sortable: true,
                sortKey: 'rowSortKey',
                selectedState: 'always',
                emptyText: 'No items selected yet.',
                button: {
                    visible: true,
                    action: { type: 'local', name: 'toggleRecord', passSelectedItems: false, passModalState: false },
                    defaultLabel: 'Selected',
                    selectedLabel: 'Selected',
                    defaultColor: 'blue',
                    selectedColor: 'blue',
                    icon: null,
                    iconOnly: false,
                    visibleField: null,
                },
                badge: { mode: 'selected-count' },
                itemFields: [],
                panelFields: [],
            },
        ],
        activeListKey: '',
        activeTab: '',
        search: '',
        searchDebounce: null,
        selectedItems: [],
        selectedListElement: null,
        modalState: {},
        modalErrors: {},
        listStates: {},
        loading: false,
        saving: false,
        initialized: false,
        items: [],
        nextOffset: 0,
        hasMore: false,
        loadingInitial: false,
        loadingMore: false,
        sentinelKey: 0,

        init() {
            this.activeListKey = this.lists[0]?.key ?? '';
            this.activeTab = this.activeListKey;
            this.resetListStates();
            this.modalState = this.modalStateDefaults();
            this.modalErrors = {};
        },

        createListState() {
            return {
                items: [],
                nextOffset: 0,
                hasMore: false,
                loadingInitial: false,
                loadingMore: false,
                sentinelKey: 0,
                loaded: false,
                lastQuery: null,
            };
        },

        resetListStates() {
            this.listStates = {};

            this.lists.forEach((list) => {
                this.listStates[list.key] = this.createListState();
            });

            this.syncLegacyState();
        },

        modalStateDefaults() {
            return this.lists
                .flatMap((list) => Array.isArray(list?.panelFields) ? list.panelFields : [])
                .reduce((defaults, field) => {
                    if (field?.key) {
                        defaults[field.key] = field.default ?? '';
                    }

                    return defaults;
                }, {});
        },

        normalizeGroup(group) {
            if (group === null || group === undefined) {
                return null;
            }

            const normalized = String(group).trim();

            return normalized === '' ? null : normalized;
        },

        compareGroups(left, right) {
            const normalizedLeft = this.normalizeGroup(left);
            const normalizedRight = this.normalizeGroup(right);

            if (normalizedLeft === normalizedRight) {
                return 0;
            }

            if (normalizedLeft === null) {
                return -1;
            }

            if (normalizedRight === null) {
                return 1;
            }

            return normalizedLeft.localeCompare(normalizedRight, undefined, {
                numeric: true,
                sensitivity: 'base',
            });
        },

        normalizeSelectedItemsFromCurrentOrder(items = this.selectedItems) {
            const groupCounters = new Map();

            const prepared = items.map((entry) => {
                const group = this.normalizeGroup(entry?.item?.group);
                const counterKey = group ?? '__ungrouped__';
                const nextSort = groupCounters.get(counterKey) ?? 0;

                groupCounters.set(counterKey, nextSort + 1);

                return {
                    ...entry,
                    item: {
                        ...entry.item,
                        group,
                        sort: nextSort,
                    },
                };
            });

            return [...prepared].sort((left, right) => {
                const groupComparison = this.compareGroups(left?.item?.group, right?.item?.group);

                if (groupComparison !== 0) {
                    return groupComparison;
                }

                return Number(left?.item?.sort ?? 0) - Number(right?.item?.sort ?? 0);
            });
        },

        listConfig(listKey) {
            return this.lists.find((list) => list.key === listKey) ?? null;
        },

        listState(listKey) {
            if (!this.listStates[listKey]) {
                this.listStates[listKey] = this.createListState();
            }

            return this.listStates[listKey];
        },

        defaultLoaderListKey() {
            return this.lists.find((list) => list?.loader?.type === 'default')?.key ?? this.defaultResultsListKey();
        },

        defaultResultsListKey() {
            return this.lists.find((list) => list?.rows === 'resultRows')?.key ?? (this.lists[0]?.key ?? '');
        },

        selectedRowsListKey() {
            return this.lists.find((list) => list?.rows === 'selectedRows')?.key ?? (this.lists[0]?.key ?? '');
        },

        initialActiveListKey(hasSelectedItems = this.selectedItems.length > 0) {
            return hasSelectedItems ? this.selectedRowsListKey() : (this.lists[0]?.key ?? '');
        },

        syncLegacyState() {
            this.activeTab = this.activeListKey;

            const legacyListKey = this.defaultResultsListKey();
            const legacyState = this.listState(legacyListKey);

            this.items = [...legacyState.items];
            this.nextOffset = legacyState.nextOffset;
            this.hasMore = legacyState.hasMore;
            this.loadingInitial = legacyState.loadingInitial;
            this.loadingMore = legacyState.loadingMore;
            this.sentinelKey = legacyState.sentinelKey;
        },

        syncActiveTabSelection() {
            const tabsRoot = this.$refs?.tabs;

            if (!tabsRoot) {
                return;
            }

            const tabElements = Array.from(tabsRoot.querySelectorAll('[data-flux-tab][name]'));

            tabElements.forEach((tab) => {
                if (tab.getAttribute('name') === this.activeListKey) {
                    tab.setAttribute('selected', '');
                } else {
                    tab.removeAttribute('selected');
                }
            });
        },

        async open() {
            if (this.loading) {
                return;
            }

            this.loading = true;

            try {
                const payload = await this.$wire.relationshipSelectorClientInitialState(this.fieldName, this.limit);

                this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder(Array.isArray(payload?.selectedItems) ? payload.selectedItems : []);
                this.schemaDefaults = payload?.schemaDefaults ?? this.schemaDefaults;
                this.limit = Number(payload?.limit ?? this.limit);
                this.search = '';
                this.activeListKey = typeof payload?.initialListKey === 'string' && payload.initialListKey !== ''
                    ? payload.initialListKey
                    : this.initialActiveListKey();
                this.activeTab = this.activeListKey;
                this.modalState = {
                    ...this.modalStateDefaults(),
                    ...(payload?.modalState ?? {}),
                };
                this.modalErrors = {};
                this.initialized = true;
                this.selectedListElement = null;
                this.resetListStates();

                const defaultLoaderKey = this.defaultLoaderListKey();

                if (defaultLoaderKey && payload?.results) {
                    this.setListPage(defaultLoaderKey, payload.results, true, '');
                }

                await this.ensureActiveListLoaded();
                Flux.modal(this.modalName).show();
                await this.$nextTick();
                this.syncActiveTabSelection();
            } finally {
                this.loading = false;
            }
        },

        cancel() {
            this.search = '';
            this.activeListKey = this.initialActiveListKey();
            this.activeTab = this.activeListKey;
            this.selectedItems = [];
            this.modalState = this.modalStateDefaults();
            this.modalErrors = {};
            Flux.modal(this.modalName).close();
        },

        async setActiveList(listKey) {
            this.activeListKey = listKey;
            this.activeTab = listKey;
            this.syncLegacyState();
            await this.$nextTick();
            this.syncActiveTabSelection();
            await this.ensureActiveListLoaded();
        },

        setTab(tab) {
            this.setActiveList(tab);
        },

        currentListSearchable() {
            return Boolean(this.listConfig(this.activeListKey)?.searchable);
        },

        currentSearchPlaceholder() {
            return this.listConfig(this.activeListKey)?.searchPlaceholder ?? 'Search...';
        },

        panelFieldsFor(listKey) {
            return Array.isArray(this.listConfig(listKey)?.panelFields) ? this.listConfig(listKey).panelFields : [];
        },

        modalStateFieldValue(field) {
            if (!field?.key) {
                return '';
            }

            return this.modalState?.[field.key] ?? '';
        },

        modalStateFieldError(field) {
            if (!field?.key) {
                return '';
            }

            return this.modalErrors?.[field.key] ?? '';
        },

        modalStateFieldInvalid(field) {
            return this.modalStateFieldError(field) !== '';
        },

        updateModalStateField(field, value) {
            if (!field?.key) {
                return;
            }

            const nextValue = value === '' && field.clearable ? null : value;

            this.modalState = {
                ...this.modalState,
                [field.key]: nextValue,
            };

            if (this.modalErrors?.[field.key]) {
                const nextErrors = { ...this.modalErrors };
                delete nextErrors[field.key];
                this.modalErrors = nextErrors;
            }
        },

        isEmptyModalStateValue(value) {
            if (value === null || value === undefined) {
                return true;
            }

            if (Array.isArray(value)) {
                return value.length === 0;
            }

            return String(value).trim() === '';
        },

        validateActivePanelFields() {
            const nextErrors = {};

            this.panelFieldsFor(this.activeListKey).forEach((field) => {
                if (!field?.key || !field?.required) {
                    return;
                }

                if (this.isEmptyModalStateValue(this.modalStateFieldValue(field))) {
                    nextErrors[field.key] = 'This field is required.';
                }
            });

            this.modalErrors = nextErrors;

            return Object.keys(nextErrors).length === 0;
        },

        async ensureActiveListLoaded() {
            const list = this.listConfig(this.activeListKey);

            if (!list?.loader) {
                return;
            }

            const state = this.listState(list.key);
            const expectedQuery = list.searchable ? this.search : '';

            if (state.loaded && state.lastQuery === expectedQuery) {
                return;
            }

            await this.refreshList(list.key);
        },

        queueSearch() {
            if (!this.currentListSearchable()) {
                return;
            }

            window.clearTimeout(this.searchDebounce);
            this.searchDebounce = window.setTimeout(() => {
                this.refreshList(this.activeListKey);
            }, 250);
        },

        async refreshResults() {
            if (!this.initialized) {
                return;
            }

            await this.refreshList(this.defaultResultsListKey());
        },

        async refreshList(listKey = this.activeListKey) {
            const list = this.listConfig(listKey);

            if (!list?.loader) {
                return;
            }

            const state = this.listState(listKey);

            if (state.loadingInitial) {
                return;
            }

            state.loadingInitial = true;
            this.syncLegacyState();

            try {
                const payload = await this.fetchListPage(listKey, 0);
                this.setListPage(listKey, payload, true, list.searchable ? this.search : '');
            } finally {
                state.loadingInitial = false;
                this.syncLegacyState();
            }
        },

        async loadMore(listKey = this.activeListKey) {
            const list = this.listConfig(listKey);
            const state = this.listState(listKey);

            if (!list?.loader || state.loadingInitial || state.loadingMore || !state.hasMore) {
                return;
            }

            state.loadingMore = true;
            this.syncLegacyState();

            try {
                const payload = await this.fetchListPage(listKey, state.nextOffset);
                this.setListPage(listKey, payload, false, list.searchable ? this.search : '');
            } finally {
                state.loadingMore = false;
                this.syncLegacyState();
            }
        },

        async fetchListPage(listKey, offset) {
            const list = this.listConfig(listKey);

            if (!list?.loader) {
                return emptyPage();
            }

            const selectedItems = this.selectedItems.map((entry) => entry.item);
            const query = list.searchable ? this.search : '';

            if (list.loader.type === 'default') {
                return await this.$wire.relationshipSelectorClientPage(
                    this.fieldName,
                    query,
                    offset,
                    this.limit,
                    selectedItems,
                );
            }

            if (list.loader.type === 'wire' && list.loader.method) {
                return await this.$wire[list.loader.method](
                    this.fieldName,
                    listKey,
                    query,
                    offset,
                    this.limit,
                    selectedItems,
                );
            }

            return emptyPage();
        },

        setListPage(listKey, payload, replace = false, query = null) {
            const state = this.listState(listKey);
            const nextRecords = Array.isArray(payload?.records) ? payload.records : [];

            state.items = replace ? nextRecords : [...state.items, ...nextRecords];
            state.nextOffset = Number(payload?.nextOffset ?? state.items.length);
            state.hasMore = Boolean(payload?.hasMore);
            state.loaded = true;
            state.lastQuery = query;
            state.sentinelKey += 1;

            this.syncLegacyState();
        },

        rowsFor(listKey) {
            const list = this.listConfig(listKey);

            if (!list) {
                return [];
            }

            return list.rows === 'selectedRows' ? this.selectedItems : this.listState(listKey).items;
        },

        resultRows() {
            return this.rowsFor(this.defaultResultsListKey());
        },

        selectedRows() {
            return this.selectedItems;
        },

        rowRecord(listKey, row) {
            return this.isSelectedRowsList(listKey) ? (row?.record ?? {}) : row;
        },

        rowColumns(listKey, row) {
            const record = this.rowRecord(listKey, row);

            return record?.views?.[listKey]?.columns ?? record?.columns ?? [];
        },

        rowKey(listKey, row) {
            if (this.isSelectedRowsList(listKey)) {
                return String(row?.item?._key ?? row?.record?.key ?? '');
            }

            return String(row?.key ?? '');
        },

        rowTemplateKey(listKey, row) {
            return `${listKey}-${this.rowKey(listKey, row)}`;
        },

        isSelectedRowsList(listKey) {
            return this.listConfig(listKey)?.rows === 'selectedRows';
        },

        selectedIds() {
            return this.selectedItems
                .map((entry) => String(entry?.item?.[this.valueAttribute] ?? ''))
                .filter((value) => value !== '');
        },

        isSelected(recordKey) {
            return this.selectedIds().includes(String(recordKey));
        },

        isRowSelected(listKey, row) {
            const state = this.listConfig(listKey)?.selectedState ?? 'never';

            if (state === 'always') {
                return true;
            }

            if (state === 'never') {
                return false;
            }

            return this.isSelected(this.rowRecord(listKey, row)?.key);
        },

        rowButtonVisible(listKey, row) {
            const button = this.listConfig(listKey)?.button;

            if (!button?.visible) {
                return false;
            }

            if (typeof button.visibleField === 'string' && button.visibleField !== '') {
                return Boolean(this.rowRecord(listKey, row)?.[button.visibleField]);
            }

            return true;
        },

        rowButtonLabel(listKey, row) {
            const button = this.listConfig(listKey)?.button;

            if (!button) {
                return '';
            }

            return this.isRowSelected(listKey, row) ? button.selectedLabel : button.defaultLabel;
        },

        rowButtonIconOnly(listKey, row) {
            return Boolean(this.listConfig(listKey)?.button?.iconOnly);
        },

        rowButtonColor(listKey, row) {
            const button = this.listConfig(listKey)?.button;

            if (!button) {
                return 'zinc';
            }

            return this.isRowSelected(listKey, row) ? button.selectedColor : button.defaultColor;
        },

        rowButtonClasses(listKey, row) {
            if (this.rowButtonIconOnly(listKey, row)) {
                const color = this.rowButtonColor(listKey, row);

                return [
                    'inline-flex items-center justify-center rounded-md p-3 transition-colors',
                    this.iconButtonColorClasses(color),
                ].join(' ');
            }

            const color = this.rowButtonColor(listKey, row);

            return [
                'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap shadow-sm transition-colors',
                this.badgeColorClasses(color),
            ].join(' ');
        },

        iconButtonColorClasses(color) {
            return {
                red: 'text-red-600 hover:bg-red-500/10 hover:text-red-500 dark:text-red-500 dark:hover:bg-red-500/10 dark:hover:text-red-400',
                zinc: 'text-zinc-400 hover:bg-white/5 hover:text-zinc-100 dark:text-zinc-500 dark:hover:text-zinc-300',
            }[color] ?? 'text-zinc-400 hover:bg-white/5 hover:text-zinc-100 dark:text-zinc-500 dark:hover:text-zinc-300';
        },

        badgeColorClasses(color) {
            return {
                zinc: 'text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40 hover:bg-zinc-400/25 dark:hover:bg-zinc-400/50',
                red: 'text-red-700 dark:text-red-200 bg-red-400/20 dark:bg-red-400/40 hover:bg-red-400/30 dark:hover:bg-red-400/50',
                orange: 'text-orange-700 dark:text-orange-200 bg-orange-400/20 dark:bg-orange-400/40 hover:bg-orange-400/30 dark:hover:bg-orange-400/50',
                amber: 'text-amber-700 dark:text-amber-200 bg-amber-400/25 dark:bg-amber-400/40 hover:bg-amber-400/40 dark:hover:bg-amber-400/50',
                yellow: 'text-yellow-800 dark:text-yellow-200 bg-yellow-400/25 dark:bg-yellow-400/40 hover:bg-yellow-400/40 dark:hover:bg-yellow-400/50',
                lime: 'text-lime-800 dark:text-lime-200 bg-lime-400/25 dark:bg-lime-400/40 hover:bg-lime-400/35 dark:hover:bg-lime-400/50',
                green: 'text-green-800 dark:text-green-200 bg-green-400/20 dark:bg-green-400/40 hover:bg-green-400/30 dark:hover:bg-green-400/50',
                emerald: 'text-emerald-800 dark:text-emerald-200 bg-emerald-400/20 dark:bg-emerald-400/40 hover:bg-emerald-400/30 dark:hover:bg-emerald-400/50',
                teal: 'text-teal-800 dark:text-teal-200 bg-teal-400/20 dark:bg-teal-400/40 hover:bg-teal-400/30 dark:hover:bg-teal-400/50',
                cyan: 'text-cyan-800 dark:text-cyan-200 bg-cyan-400/20 dark:bg-cyan-400/40 hover:bg-cyan-400/30 dark:hover:bg-cyan-400/50',
                sky: 'text-sky-800 dark:text-sky-200 bg-sky-400/20 dark:bg-sky-400/40 hover:bg-sky-400/30 dark:hover:bg-sky-400/50',
                blue: 'text-blue-800 dark:text-blue-200 bg-blue-400/20 dark:bg-blue-400/40 hover:bg-blue-400/30 dark:hover:bg-blue-400/50',
                indigo: 'text-indigo-700 dark:text-indigo-200 bg-indigo-400/20 dark:bg-indigo-400/40 hover:bg-indigo-400/30 dark:hover:bg-indigo-400/50',
                violet: 'text-violet-700 dark:text-violet-200 bg-violet-400/20 dark:bg-violet-400/40 hover:bg-violet-400/30 dark:hover:bg-violet-400/50',
                purple: 'text-purple-700 dark:text-purple-200 bg-purple-400/20 dark:bg-purple-400/40 hover:bg-purple-400/30 dark:hover:bg-purple-400/50',
                fuchsia: 'text-fuchsia-700 dark:text-fuchsia-200 bg-fuchsia-400/20 dark:bg-fuchsia-400/40 hover:bg-fuchsia-400/30 dark:hover:bg-fuchsia-400/50',
                pink: 'text-pink-700 dark:text-pink-200 bg-pink-400/20 dark:bg-pink-400/40 hover:bg-pink-400/30 dark:hover:bg-pink-400/50',
                rose: 'text-rose-700 dark:text-rose-200 bg-rose-400/20 dark:bg-rose-400/40 hover:bg-rose-400/30 dark:hover:bg-rose-400/50',
            }[color] ?? 'text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40 hover:bg-zinc-400/25 dark:hover:bg-zinc-400/50';
        },

        rowItemFields(listKey) {
            return Array.isArray(this.listConfig(listKey)?.itemFields) ? this.listConfig(listKey).itemFields : [];
        },

        rowItemFieldValue(listKey, row, itemField) {
            if (!this.isSelectedRowsList(listKey)) {
                return '';
            }

            const value = row?.item?.[itemField?.key];

            return value ?? '';
        },

        updateRowItemField(listKey, row, itemField, value) {
            if (!this.isSelectedRowsList(listKey) || !itemField?.key) {
                return;
            }

            const rowKey = String(row?.item?._key ?? '');

            if (rowKey === '') {
                return;
            }

            const entryIndex = this.selectedItems.findIndex((entry) => String(entry?.item?._key ?? '') === rowKey);

            if (entryIndex === -1) {
                return;
            }

            const nextValue = value === '' && itemField.clearable ? null : value;
            const currentEntry = this.selectedItems[entryIndex];

            this.selectedItems.splice(entryIndex, 1, {
                ...currentEntry,
                item: {
                    ...currentEntry.item,
                    [itemField.key]: nextValue,
                },
            });

            this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder();
        },

        isListSortable(listKey) {
            return Boolean(this.listConfig(listKey)?.sortable);
        },

        showRowMoveControls(listKey) {
            return this.isListSortable(listKey) && this.isSelectedRowsList(listKey);
        },

        canMoveRow(listKey, rowIndex, direction) {
            if (!this.showRowMoveControls(listKey)) {
                return false;
            }

            const targetIndex = Number(rowIndex) + Number(direction);
            const rows = this.rowsFor(listKey);

            if (targetIndex < 0 || targetIndex >= rows.length) {
                return false;
            }

            return this.compareGroups(rows[rowIndex]?.item?.group, rows[targetIndex]?.item?.group) === 0;
        },

        moveRow(listKey, rowIndex, direction) {
            if (!this.canMoveRow(listKey, rowIndex, direction)) {
                return;
            }

            if (!this.isSelectedRowsList(listKey)) {
                return;
            }

            const sourceIndex = Number(rowIndex);
            const targetIndex = sourceIndex + Number(direction);
            const nextItems = [...this.selectedItems];
            const [moved] = nextItems.splice(sourceIndex, 1);

            nextItems.splice(targetIndex, 0, moved);

            this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder(nextItems);
        },

        isListLoading(listKey) {
            const state = this.listState(listKey);

            return (this.loading && this.activeListKey === listKey) || state.loadingInitial;
        },

        listHasMore(listKey) {
            return Boolean(this.listState(listKey).hasMore);
        },

        listBadgeValue(listKey) {
            const badgeMode = this.listConfig(listKey)?.badge?.mode;

            if (badgeMode === 'selected-count') {
                return this.selectedItems.length;
            }

            if (badgeMode === 'row-count') {
                return this.rowsFor(listKey).length;
            }

            return '';
        },

        async handleRowClick(listKey, row) {
            await this.runAction(this.listConfig(listKey)?.rowAction, listKey, row);
        },

        async handleButtonClick(listKey, row) {
            await this.runAction(this.listConfig(listKey)?.button?.action, listKey, row);
        },

        async runAction(action, listKey, row) {
            if (!action?.name) {
                return;
            }

            if (action.type === 'wire') {
                await this.invokeWireAction(action, listKey, row);

                return;
            }

            if (typeof this[action.name] === 'function') {
                await this[action.name](this.rowRecord(listKey, row), listKey, row);
            }
        },

        async invokeWireAction(action, listKey, row) {
            const args = [
                this.fieldName,
                listKey,
                this.rowRecord(listKey, row),
            ];

            if (action.passSelectedItems ?? true) {
                args.push(this.selectedItems.map((entry) => entry.item));
            }

            if (action.passModalState ?? false) {
                args.push(this.modalState);
            }

            const response = await this.$wire[action.name](...args);

            await this.hydrateActionResponse(response);
        },

        async hydrateActionResponse(response) {
            if (!response || typeof response !== 'object') {
                return;
            }

            if (Array.isArray(response.selectedItems)) {
                this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder(response.selectedItems);
            }

            if (response.modalState && typeof response.modalState === 'object') {
                this.modalState = {
                    ...this.modalState,
                    ...response.modalState,
                };
            }

            if (response.modalErrors && typeof response.modalErrors === 'object') {
                this.modalErrors = response.modalErrors;
            }

            if (typeof response.activeListKey === 'string' && response.activeListKey !== '') {
                this.activeListKey = response.activeListKey;
                this.activeTab = this.activeListKey;
            }

            if (Array.isArray(response.refreshLists)) {
                for (const listKey of response.refreshLists) {
                    await this.refreshList(String(listKey));
                }
            }

            this.syncLegacyState();
            await this.$nextTick();
            this.syncActiveTabSelection();

            if (response.closeModal) {
                this.search = '';
                this.activeListKey = this.initialActiveListKey();
                this.activeTab = this.activeListKey;
                this.selectedItems = [];
                this.modalState = this.modalStateDefaults();
                this.modalErrors = {};
                Flux.modal(this.modalName).close();
            }
        },

        toggleRecord(record) {
            const key = String(record?.key ?? '');

            if (key === '') {
                return;
            }

            const existingIndex = this.selectedItems.findIndex((entry) => String(entry?.item?.[this.valueAttribute] ?? '') === key);

            if (existingIndex !== -1) {
                this.selectedItems.splice(existingIndex, 1);
                this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder();

                return;
            }

            this.selectedItems.push({
                item: {
                    ...this.schemaDefaults,
                    [this.valueAttribute]: record.key,
                    _key: `item_${crypto.randomUUID()}`,
                    sort: this.selectedItems.length,
                },
                record,
            });

            this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder();

            if (this.activeListKey === this.selectedRowsListKey()) {
                this.$nextTick(() => this.registerSelectedList());
            }
        },

        registerSelectedList() {
            const nextListElement = this.$refs.selectedList;

            if (!nextListElement) {
                return;
            }

            this.selectedListElement = nextListElement;
        },

        handleSelectedSort() {
            if (!this.selectedListElement) {
                return;
            }

            const orderedKeys = Array.from(this.selectedListElement.querySelectorAll('[data-sort-item-key]'))
                .map((element) => element.dataset.sortItemKey)
                .filter((value) => value !== '');
            const itemsByKey = new Map(this.selectedItems.map((entry) => [String(entry.item._key ?? ''), entry]));

            this.selectedItems = orderedKeys
                .map((key) => itemsByKey.get(String(key)))
                .filter(Boolean)
                .map((entry) => ({ ...entry }));

            this.selectedItems = this.normalizeSelectedItemsFromCurrentOrder(this.selectedItems);
        },

        async save() {
            if (this.saving) {
                return;
            }

            if (!this.validateActivePanelFields()) {
                return;
            }

            this.saving = true;

            try {
                await this.$wire[this.applyAction](
                    this.fieldName,
                    this.normalizeSelectedItemsFromCurrentOrder().map((entry) => ({
                        ...entry.item,
                    })),
                    this.modalState,
                );
            } finally {
                this.saving = false;
            }
        },
    }));
});
