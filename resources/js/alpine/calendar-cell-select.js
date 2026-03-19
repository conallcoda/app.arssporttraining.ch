document.addEventListener('alpine:init', () => {
    let activeId = null;

    Alpine.data('calendar_cell_select', (config = {}) => ({
        type: config.type || 'category',
        categoryId: config.categoryId || null,
        persistKey: config.persistKey || null,
        _instanceId: (config.type || 'category') + '-' + (config.categoryId || 'notes'),
        expanded: false,
        programExpanded: {},
        anchorIdx: null,
        anchorDate: null,
        endIdx: null,
        endDate: null,
        contextMenu: false,
        contextMenuX: 0,
        contextMenuY: 0,

        init() {
            if (this.persistKey) {
                const stored = localStorage.getItem('_x_' + this.persistKey);
                if (stored !== null) {
                    try {
                        this.expanded = JSON.parse(stored);
                    } catch (e) {}
                }
                this.$watch('expanded', (val) => {
                    localStorage.setItem('_x_' + this.persistKey, JSON.stringify(val));
                });
            }

            this._onDocumentClick = (e) => {
                if (activeId !== this._instanceId || this.anchorIdx === null) {
                    return;
                }
                if (this.contextMenu && e.target.closest('[x-show="contextMenu"]')) {
                    return;
                }
                const container = document.querySelector('[data-cell-select-id="' + this._instanceId + '"]');
                if (container && !container.contains(e.target)) {
                    this.clearSelection();
                }
            };
            document.addEventListener('click', this._onDocumentClick, true);
        },

        destroy() {
            if (this._onDocumentClick) {
                document.removeEventListener('click', this._onDocumentClick, true);
            }
        },

        _clearOtherInstance() {
            console.log('[cs] _clearOtherInstance', {
                activeId,
                myId: this._instanceId,
                same: activeId === this._instanceId,
            });
            if (activeId && activeId !== this._instanceId) {
                const otherEl = document.querySelector('[data-cell-select-id="' + activeId + '"]');
                if (otherEl) {
                    const otherData = Alpine.$data(otherEl);
                    console.log('[cs] clearing other instance', { otherHasClear: !!otherData?.clearSelection });
                    if (otherData && otherData.clearSelection) {
                        otherData.clearSelection();
                    }
                }
            }
            activeId = this._instanceId;
        },

        selectCell(idx, date, event) {
            console.log('[cs] === selectCell ===', {
                idx,
                date,
                shiftKey: event.shiftKey,
                button: event.button,
                anchorIdx: this.anchorIdx,
                anchorDate: this.anchorDate,
                endIdx: this.endIdx,
                endDate: this.endDate,
                type: this.type,
                categoryId: this.categoryId,
                thisElId: this.$el?.id,
                thisElTag: this.$el?.tagName,
                activeIdSame: activeId === this._instanceId,
                activeId: activeId,
                instanceId: this._instanceId,
            });

            this._clearOtherInstance();
            this.contextMenu = false;

            const shiftCondition = event.shiftKey && this.anchorIdx !== null;
            console.log('[cs] shift condition:', shiftCondition, 'shiftKey:', event.shiftKey, 'anchorIdx:', this.anchorIdx);

            if (shiftCondition) {
                this.endIdx = idx;
                this.endDate = date;
                console.log('[cs] SET END', { endIdx: this.endIdx, endDate: this.endDate });
            } else {
                this.anchorIdx = idx;
                this.anchorDate = date;
                this.endIdx = null;
                this.endDate = null;
                console.log('[cs] SET ANCHOR', { anchorIdx: this.anchorIdx, anchorDate: this.anchorDate });
            }

            console.log('[cs] final state', {
                anchorIdx: this.anchorIdx,
                anchorDate: this.anchorDate,
                endIdx: this.endIdx,
                endDate: this.endDate,
            });
        },

        showContextMenu(event, idx, date) {
            event.preventDefault();
            console.log('[cs] === showContextMenu ===', {
                idx, date,
                anchorIdx: this.anchorIdx,
                anchorDate: this.anchorDate,
                endIdx: this.endIdx,
                endDate: this.endDate,
            });
            this._clearOtherInstance();

            if (this.anchorIdx === null) {
                this.anchorIdx = idx;
                this.anchorDate = date;
            }

            this.contextMenuX = event.clientX;
            this.contextMenuY = event.clientY;
            this.contextMenu = true;
        },

        clearSelection() {
            console.log('[cs] === clearSelection ===', {
                anchorIdx: this.anchorIdx,
                endIdx: this.endIdx,
                stack: new Error().stack.split('\n').slice(1, 4).map(s => s.trim()).join(' | '),
            });
            this.anchorIdx = null;
            this.anchorDate = null;
            this.endIdx = null;
            this.endDate = null;
            this.contextMenu = false;
            if (activeId === this._instanceId) {
                activeId = null;
            }
        },

        performAction() {
            console.log('[cs] === performAction ===', {
                anchorDate: this.anchorDate,
                endDate: this.endDate,
                anchorIdx: this.anchorIdx,
                endIdx: this.endIdx,
                type: this.type,
                categoryId: this.categoryId,
            });

            if (!this.anchorDate) {
                console.log('[cs] performAction: no anchorDate, aborting');
                return;
            }

            const dates = [this.anchorDate, this.endDate].filter(Boolean).sort();
            const startDate = dates[0];
            const endDate = dates.length > 1 && dates[1] !== dates[0] ? dates[1] : null;

            console.log('[cs] performAction calling wire', { startDate, endDate });

            if (this.type === 'notes') {
                if (endDate) {
                    this.$wire.openBlockRange(startDate, endDate);
                } else {
                    this.$wire.openBlock(startDate);
                }
            } else {
                if (endDate) {
                    this.$wire.openCategoryBlockRange(startDate, endDate, this.categoryId);
                } else {
                    this.$wire.openCategoryBlock(startDate, this.categoryId);
                }
            }

            this.clearSelection();
        },
    }));
});
