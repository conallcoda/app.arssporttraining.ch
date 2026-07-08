document.addEventListener('alpine:init', () => {
    Alpine.data('cmsLightbox', (config = {}) => ({
        imageUrls: config.imageUrls || [],
        alternateImageUrls: config.alternateImageUrls || [],
        count: config.count || 0,
        currentIndex: 0,
        imageMode: 'primary',
        isOpen: false,

        open(index) {
            if (index == null || index < 0 || index >= this.count) return;
            this.currentIndex = index;
            this.imageMode = 'primary';
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
            this.imageMode = 'primary';
            this.preload();
        },

        next() {
            if (!this.isOpen || this.count <= 1) return;
            this.currentIndex = (this.currentIndex + 1) % this.count;
            this.imageMode = 'primary';
            this.preload();
        },

        hasAlternateImage() {
            return Boolean(this.alternateImageUrls[this.currentIndex]);
        },

        currentImageUrl() {
            if (this.imageMode === 'alternate' && this.hasAlternateImage()) {
                return this.alternateImageUrls[this.currentIndex];
            }

            return this.imageUrls[this.currentIndex];
        },

        toggleImageMode() {
            if (!this.hasAlternateImage()) return;
            this.imageMode = this.imageMode === 'alternate' ? 'primary' : 'alternate';
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
