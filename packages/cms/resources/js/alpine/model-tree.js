document.addEventListener('alpine:init', () => {
    Alpine.data('model_tree', () => ({
        collapsed: {},

        isExpanded(id) {
            return !this.collapsed[id];
        },

        toggle(id) {
            this.collapsed[id] = !this.collapsed[id];
        },
    }));
});
