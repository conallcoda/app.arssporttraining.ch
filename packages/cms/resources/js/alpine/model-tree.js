document.addEventListener('alpine:init', () => {
    Alpine.data('model_tree', (config = {}) => ({
        expanded: {},
        expandableKeys: Array.isArray(config.expandableKeys) ? config.expandableKeys.map((key) => String(key)) : [],
        defaultExpandedKeys: Array.isArray(config.defaultExpandedKeys) ? config.defaultExpandedKeys.map((key) => String(key)) : null,

        init() {
            if (this.defaultExpandedKeys === null) {
                return;
            }

            const defaultExpandedSet = new Set(this.defaultExpandedKeys);

            this.expandableKeys.forEach((key) => {
                this.expanded[key] = defaultExpandedSet.has(key);
            });
        },

        isExpanded(id) {
            const key = String(id);

            if (Object.hasOwn(this.expanded, key)) {
                return this.expanded[key];
            }

            return this.defaultExpandedKeys === null;
        },

        toggle(id) {
            const key = String(id);
            this.expanded[key] = !this.isExpanded(key);
        },

        collapseAll() {
            this.expandableKeys.forEach((key) => {
                this.expanded[key] = false;
            });
        },

        expandAll() {
            this.expandableKeys.forEach((key) => {
                this.expanded[key] = true;
            });
        },
    }));
});
