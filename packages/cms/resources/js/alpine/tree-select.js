document.addEventListener('alpine:init', () => {
    Alpine.data('tree_select', () => {
        let instance = null;
        let ignoreNextWatch = false;

        const normalizeValues = (value, multiple) => {
            if (multiple) {
                if (!Array.isArray(value)) {
                    return [];
                }

                return value
                    .filter((item) => item !== null && item !== '' && !Number.isNaN(parseInt(item, 10)))
                    .map((item) => parseInt(item, 10));
            }

            if (value === null || value === '' || value === undefined) {
                return null;
            }

            const parsed = parseInt(value, 10);

            return Number.isNaN(parsed) ? null : parsed;
        };

        return {
            init() {
                const Treeselect = globalThis.Treeselect;

                if (!Treeselect) {
                    console.warn('CMS tree_select requires globalThis.Treeselect to be registered by the consuming app.');
                    return;
                }

                const options = JSON.parse(this.$el.dataset.options || '[]');
                const rawValue = JSON.parse(this.$el.dataset.value || 'null');
                const multiple = this.$el.dataset.multiple === 'true';
                const value = normalizeValues(rawValue, multiple);
                const placeholder = this.$el.dataset.placeholder || '';
                const wireModel = this.$el.dataset.wireModel;
                const clearable = this.$el.dataset.clearable !== 'false';
                const searchable = this.$el.dataset.searchable !== 'false';
                const leafOnly = this.$el.dataset.leafOnly === 'true';

                const container = this.$el.querySelector('[data-tree-select-container]');

                instance = new Treeselect({
                    parentHtmlContainer: container,
                    value: value,
                    options: options,
                    isSingleSelect: !multiple,
                    showTags: true,
                    searchable: searchable,
                    clearable: clearable,
                    placeholder: placeholder,
                    expandSelected: true,
                    disabledBranchNode: leafOnly,
                    iconElements: {
                        cross: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"/></svg>',
                    },
                    inputCallback: (newValue) => {
                        ignoreNextWatch = true;
                        this.$wire.set(wireModel, normalizeValues(newValue, multiple));
                    },
                });

                this.$wire.$watch(wireModel, (newValue) => {
                    if (ignoreNextWatch) {
                        ignoreNextWatch = false;
                        return;
                    }
                    if (instance) {
                        instance.updateValue(normalizeValues(newValue, multiple));
                    }
                });

                container.addEventListener('mousedown', (e) => {
                    if (e.target.closest('.treeselect-input__tags-element') && !e.target.closest('.treeselect-input__tags-cross')) {
                        e.preventDefault();
                        e.stopPropagation();
                        setTimeout(() => {
                            const input = container.querySelector('.treeselect-input__edit');
                            if (input) input.focus();
                        }, 0);
                    }
                }, true);
            },

            destroy() {
                if (instance) {
                    instance.destroy();
                    instance = null;
                }
            }
        };
    });
});
