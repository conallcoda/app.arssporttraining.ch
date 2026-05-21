document.addEventListener('alpine:init', () => {
    Alpine.data('cmsLightbox', (config = {}) => ({
        imageUrls: config.imageUrls || [],
        count: config.count || 0,
        currentIndex: 0,
        isOpen: false,

        open(index) {
            if (index == null || index < 0 || index >= this.count) return;
            this.currentIndex = index;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.preload());
        },

        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            document.body.style.overflow = '';
        },

        prev() {
            if (!this.isOpen || this.count <= 1) return;
            this.currentIndex = (this.currentIndex - 1 + this.count) % this.count;
            this.preload();
        },

        next() {
            if (!this.isOpen || this.count <= 1) return;
            this.currentIndex = (this.currentIndex + 1) % this.count;
            this.preload();
        },

        preload() {
            if (this.count <= 1) return;
            const neighbors = [
                (this.currentIndex + 1) % this.count,
                (this.currentIndex - 1 + this.count) % this.count,
            ];
            for (const i of neighbors) {
                const url = this.imageUrls[i];
                if (!url) continue;
                const img = new Image();
                img.src = url;
            }
        },
    }));
});
