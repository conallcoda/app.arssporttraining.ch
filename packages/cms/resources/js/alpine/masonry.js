document.addEventListener('alpine:init', () => {
    Alpine.data('cmsMasonry', (cardWidth = 220, gap = 4) => ({
        cardWidth,
        gap,
        columnCount: 0,
        columnWidth: 0,
        lastLayoutWidth: 0,
        resizeObserver: null,
        mutationObserver: null,
        layoutScheduled: false,

        init() {
            this.$el.style.position = 'relative';
            this.$el.style.opacity = '0';
            this.$el.style.transition = 'opacity 200ms ease-out';

            this.computeColumns();
            this.prepareItems(this.getItems());

            this.waitForImages(this.getItems()).then(() => {
                this.layout();
                requestAnimationFrame(() => {
                    this.$el.style.opacity = '1';
                });
            });

            this.resizeObserver = new ResizeObserver(() => {
                const width = this.$el.clientWidth;
                if (width === 0 || width === this.lastLayoutWidth) return;
                this.computeColumns();
                this.layout();
            });
            this.resizeObserver.observe(this.$el);

            this.mutationObserver = new MutationObserver((mutations) => {
                let relevant = false;
                for (const m of mutations) {
                    for (const node of m.addedNodes) {
                        if (node.nodeType === 1 && node.matches?.('[data-masonry-item]')) {
                            relevant = true;
                            break;
                        }
                    }
                    if (relevant) break;
                    for (const node of m.removedNodes) {
                        if (node.nodeType === 1 && node.matches?.('[data-masonry-item]')) {
                            relevant = true;
                            break;
                        }
                    }
                    if (relevant) break;
                }
                if (!relevant) return;

                this.prepareItems(this.getItems());
                this.scheduleLayout();
            });
            this.mutationObserver.observe(this.$el, { childList: true });
        },

        destroy() {
            this.resizeObserver?.disconnect();
            this.mutationObserver?.disconnect();
        },

        getItems() {
            return Array.from(this.$el.children).filter(el => el.matches('[data-masonry-item]'));
        },

        computeColumns() {
            const containerWidth = this.$el.clientWidth;
            if (containerWidth === 0) return;
            this.columnCount = Math.max(
                1,
                Math.floor((containerWidth + this.gap) / (this.cardWidth + this.gap)),
            );
            this.columnWidth = (containerWidth - this.gap * (this.columnCount - 1)) / this.columnCount;
        },

        prepareItems(items) {
            for (const item of items) {
                if (item.dataset.masonryReady === '1') continue;
                item.style.position = 'absolute';
                item.style.top = '0';
                item.style.left = '0';
                item.style.width = `${this.columnWidth}px`;
                item.style.opacity = '0';
                item.style.transform = 'translate3d(0, 0, 0)';
                item.style.transition = 'opacity 200ms ease-out';
                item.style.willChange = 'transform';
                item.querySelectorAll('img').forEach(img => { img.loading = 'eager'; });
                item.dataset.masonryReady = '1';
            }
        },

        layout() {
            const items = this.getItems();
            if (items.length === 0) {
                this.$el.style.height = '0px';
                return;
            }
            if (this.columnCount === 0) this.computeColumns();

            this.lastLayoutWidth = this.$el.clientWidth;

            for (const item of items) {
                item.style.width = `${this.columnWidth}px`;
            }

            const heights = items.map(item => item.offsetHeight);

            const columnHeights = new Array(this.columnCount).fill(0);
            const positions = items.map((_, i) => {
                let minCol = 0;
                for (let c = 1; c < this.columnCount; c++) {
                    if (columnHeights[c] < columnHeights[minCol]) minCol = c;
                }
                const x = minCol * (this.columnWidth + this.gap);
                const y = columnHeights[minCol];
                columnHeights[minCol] = y + heights[i] + this.gap;
                return { x, y };
            });

            items.forEach((item, i) => {
                item.style.transform = `translate3d(${positions[i].x}px, ${positions[i].y}px, 0)`;
                if (item.style.opacity !== '1') item.style.opacity = '1';
            });

            const tallest = Math.max(...columnHeights) - this.gap;
            this.$el.style.height = `${Math.max(0, tallest)}px`;
        },

        scheduleLayout() {
            if (this.layoutScheduled) return;
            this.layoutScheduled = true;
            requestAnimationFrame(() => {
                this.layoutScheduled = false;
                this.waitForImages(this.getItems()).then(() => this.layout());
            });
        },

        waitForImages(items) {
            const images = items.flatMap(el => Array.from(el.querySelectorAll('img')));
            const pending = images.filter(img => !img.complete || img.naturalWidth === 0);
            if (pending.length === 0) return Promise.resolve();
            return Promise.all(
                pending.map(img => new Promise(resolve => {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                })),
            );
        },
    }));
});
