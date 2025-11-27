document.addEventListener('alpine:init', () => {
    Alpine.data('resizable_card', () => ({
        config: {},
        expanded: true,

        init() {
            this.config = JSON.parse(atob(this.$el.getAttribute('data-config')));
            if (this.config.storageKey) {
                const stored = localStorage.getItem(this.config.storageKey);
                if (stored !== null) {
                    this.expanded = stored === 'true';
                }
            }
            this.$nextTick(() => this.dispatchCompactState());
        },

        toggle() {
            this.expanded = !this.expanded;
            if (this.config.storageKey) {
                localStorage.setItem(this.config.storageKey, this.expanded.toString());
            }
            this.dispatchCompactState();
        },

        dispatchCompactState() {
            const compact = !this.expanded;
            Livewire.dispatch('compact-mode-changed', { compact });
        },

        destroy() {}
    }));
});
