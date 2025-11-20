document.addEventListener('alpine:init', () => {
    Alpine.data('navigation_tree', () => ({
        expanded: {},

        isExpanded(uuid) {
            return this.expanded[uuid] !== false;
        },

        toggle(uuid) {
            this.expanded[uuid] = !this.isExpanded(uuid);
        },

        expandAll() {
            this.expanded = {};
        },

        collapseAll() {
            this.expanded = { preventAll: true };
        }
    }));
});
