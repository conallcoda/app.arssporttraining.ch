document.addEventListener('alpine:init', () => {
    Alpine.data('cms_sort_group', (options = {}) => ({
        groupKey: options.groupKey ?? 'list',
        method: options.method ?? 'reorderCurrentPage',
        grouped: options.grouped ?? false,
        debugEnabled: options.debug ?? true,

        debug(step, detail = {}) {
            if (!this.debugEnabled) {
                return;
            }

            const payload = {
                step,
                groupKey: this.groupKey,
                method: this.method,
                grouped: this.grouped,
                ...detail,
            };

            window.__cmsManualSortDebug = window.__cmsManualSortDebug || [];
            window.__cmsManualSortDebug.push(payload);
            console.debug('[cms sort plugin]', payload);
            window.dispatchEvent(new CustomEvent('cms-manual-sort-debug', { detail: payload }));
        },

        init() {
            this.debug('init');
        },

        orderedKeys() {
            return Array.from(this.$el.querySelectorAll('[data-sort-item-key]'))
                .filter((item) => String(item.dataset.sortGroupKey ?? '') === String(this.groupKey))
                .map((item) => item.dataset.sortItemKey)
                .filter((item) => item !== '');
        },

        async handleSort(item, position) {
            const orderedKeys = this.orderedKeys();

            this.debug('sort', {
                item,
                position,
                orderedKeys,
            });

            try {
                if (this.grouped) {
                    await this.$wire.call(this.method, this.groupKey, orderedKeys);
                } else {
                    await this.$wire.call(this.method, orderedKeys);
                }

                this.debug('sort-complete', {
                    item,
                    position,
                    orderedKeys,
                });
            } catch (error) {
                this.debug('sort-failed', {
                    item,
                    position,
                    orderedKeys,
                    message: error?.message ?? String(error),
                });

                throw error;
            }
        },
    }));
});
