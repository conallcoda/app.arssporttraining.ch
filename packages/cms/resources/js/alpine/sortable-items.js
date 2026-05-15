document.addEventListener('alpine:init', () => {
    const createInfiniteCollection = (options = {}) => ({
        items: [],
        nextOffset: 0,
        hasMore: false,
        loadingInitial: false,
        loadingMore: false,
        sentinelKey: 0,

        async resetAndLoad() {
            if (this.loadingInitial) {
                return;
            }

            this.loadingInitial = true;

            try {
                const payload = await options.loadPage.call(this, 0);
                this.items = Array.isArray(payload?.records) ? payload.records : [];
                this.nextOffset = Number(payload?.nextOffset ?? this.items.length);
                this.hasMore = Boolean(payload?.hasMore);
                this.bumpSentinel();
            } finally {
                this.loadingInitial = false;
            }
        },

        async loadMore() {
            if (this.loadingInitial || this.loadingMore || !this.hasMore) {
                return;
            }

            this.loadingMore = true;

            try {
                const payload = await options.loadPage.call(this, this.nextOffset);
                const nextRecords = Array.isArray(payload?.records) ? payload.records : [];

                this.items = [...this.items, ...nextRecords];
                this.nextOffset = Number(payload?.nextOffset ?? this.items.length);
                this.hasMore = Boolean(payload?.hasMore);
                this.bumpSentinel();
            } finally {
                this.loadingMore = false;
            }
        },

        bumpSentinel() {
            this.sentinelKey += 1;
        },
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
            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
        },

        handleDragOver(event, index) {
            event.preventDefault();
            if (this.draggingIndex === index) return;

            this.dragOverIndex = index;
            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
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

            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });

            if (sourceIndex !== targetIndex) {
                this.$wire[this.reorderAction](this.fieldName, sourceIndex, targetIndex);
            }

            this.draggingIndex = null;
            this.dragOverIndex = null;
        }
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

    Alpine.data('relationship_selector_modal', (config) => ({
        ...createInfiniteCollection({
            async loadPage(offset) {
                return await this.$wire.relationshipSelectorClientPage(
                    this.fieldName,
                    this.search,
                    offset,
                    this.limit,
                    this.selectedItems.map((entry) => entry.item),
                );
            },
        }),
        fieldName: config.fieldName,
        modalName: config.modalName,
        limit: config.limit ?? 40,
        applyAction: config.applyAction ?? 'applyRelationshipSelectorClientState',
        schemaDefaults: config.schemaDefaults ?? {},
        valueAttribute: config.valueAttribute ?? 'id',
        loading: false,
        saving: false,
        initialized: false,
        activeTab: 'results',
        search: '',
        searchDebounce: null,
        selectedItems: [],
        selectedListElement: null,

        async open() {
            if (this.loading) {
                return;
            }

            this.loading = true;

            try {
                const payload = await this.$wire.relationshipSelectorClientInitialState(this.fieldName, this.limit);
                this.selectedItems = Array.isArray(payload?.selectedItems) ? payload.selectedItems : [];
                this.schemaDefaults = payload?.schemaDefaults ?? this.schemaDefaults;
                this.limit = Number(payload?.limit ?? this.limit);
                this.activeTab = 'results';
                this.search = '';
                this.initialized = true;
                this.selectedListElement = null;
                this.items = Array.isArray(payload?.results?.records) ? payload.results.records : [];
                this.nextOffset = Number(payload?.results?.nextOffset ?? this.items.length);
                this.hasMore = Boolean(payload?.results?.hasMore);
                this.bumpSentinel();
                Flux.modal(this.modalName).show();
            } finally {
                this.loading = false;
            }
        },

        cancel() {
            this.search = '';
            this.activeTab = 'results';
            Flux.modal(this.modalName).close();
        },

        selectedIds() {
            return this.selectedItems
                .map((entry) => String(entry?.item?.[this.valueAttribute] ?? ''))
                .filter((value) => value !== '');
        },

        isSelected(recordKey) {
            return this.selectedIds().includes(String(recordKey));
        },

        setTab(tab) {
            this.activeTab = tab;

            if (tab === 'selected') {
                this.$nextTick(() => this.registerSelectedList());
            }
        },

        queueSearch() {
            window.clearTimeout(this.searchDebounce);
            this.searchDebounce = window.setTimeout(() => {
                this.refreshResults();
            }, 250);
        },

        async refreshResults() {
            if (!this.initialized) {
                return;
            }

            await this.resetAndLoad();
        },

        toggleRecord(record) {
            const key = String(record?.key ?? '');

            if (key === '') {
                return;
            }

            const existingIndex = this.selectedItems.findIndex((entry) => String(entry?.item?.[this.valueAttribute] ?? '') === key);

            if (existingIndex !== -1) {
                this.selectedItems.splice(existingIndex, 1);
                this.selectedItems = this.selectedItems.map((entry, index) => ({
                    ...entry,
                    item: {
                        ...entry.item,
                        sort: index,
                    },
                }));

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

            if (this.activeTab === 'selected') {
                this.$nextTick(() => this.registerSelectedList());
            }
        },

        selectedRows() {
            return this.selectedItems;
        },

        resultRows() {
            return this.items;
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
                .map((entry, index) => ({
                    ...entry,
                    item: {
                        ...entry.item,
                        sort: index,
                    },
                }));
        },

        async save() {
            if (this.saving) {
                return;
            }

            this.saving = true;

            try {
                await this.$wire[this.applyAction](
                    this.fieldName,
                    this.selectedItems.map((entry, index) => ({
                        ...entry.item,
                        sort: index,
                    })),
                );
            } finally {
                this.saving = false;
            }
        },
    }));
});
