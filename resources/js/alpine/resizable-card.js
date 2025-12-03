document.addEventListener('alpine:init', () => {
    Alpine.store('cardWidths', {});

    Alpine.data('resizable_card', () => ({
        config: {},
        width: 50,

        init() {
            this.config = JSON.parse(atob(this.$el.getAttribute('data-config')));
            if (this.config.storageKey) {
                const stored = localStorage.getItem(this.config.storageKey);
                if (stored !== null) {
                    const parsed = parseInt(stored, 10);
                    if (!isNaN(parsed) && [25, 33, 50, 66, 75, 100].includes(parsed)) {
                        this.width = parsed;
                    } else {
                        localStorage.removeItem(this.config.storageKey);
                    }
                }
                Alpine.store('cardWidths')[this.config.storageKey] = this.width;
            }
            setTimeout(() => this.dispatchCompactState(), 100);
        },

        setWidth(percent) {
            this.width = percent;
            if (this.config.storageKey) {
                localStorage.setItem(this.config.storageKey, percent.toString());
                Alpine.store('cardWidths')[this.config.storageKey] = percent;
            }
            this.dispatchCompactState();
        },

        getColSpan() {
            switch (this.width) {
                case 25: return 'col-span-3';
                case 33: return 'col-span-4';
                case 50: return 'col-span-6';
                case 66: return 'col-span-8';
                case 75: return 'col-span-9';
                case 100: return 'col-span-12';
                default: return 'col-span-6';
            }
        },

        dispatchCompactState() {
            const compact = this.width < 100;
            Livewire.dispatch('compact-mode-changed', { compact });
            window.dispatchEvent(new CustomEvent('compact-mode-changed', { detail: { compact, width: this.width } }));
        },

        destroy() {}
    }));
});
