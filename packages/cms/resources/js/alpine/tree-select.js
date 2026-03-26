import Treeselect from 'treeselectjs';

document.addEventListener('alpine:init', () => {
    Alpine.data('tree_select', () => {
        let instance = null;
        let ignoreNextWatch = false;

        return {
            init() {
                const options = JSON.parse(this.$el.dataset.options || '[]');
                const rawValue = this.$el.dataset.value;
                const value = rawValue ? parseInt(rawValue) : null;
                const placeholder = this.$el.dataset.placeholder || '';
                const wireModel = this.$el.dataset.wireModel;

                const container = this.$el.querySelector('[data-tree-select-container]');

                instance = new Treeselect({
                    parentHtmlContainer: container,
                    value: value,
                    options: options,
                    isSingleSelect: true,
                    showTags: true,
                    searchable: true,
                    clearable: true,
                    placeholder: placeholder,
                    expandSelected: true,
                    iconElements: {
                        cross: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"/></svg>',
                    },
                    inputCallback: (newValue) => {
                        ignoreNextWatch = true;
                        this.$wire.set(wireModel, newValue !== null ? parseInt(newValue) : null);
                    },
                });

                this.$wire.$watch(wireModel, (newValue) => {
                    if (ignoreNextWatch) {
                        ignoreNextWatch = false;
                        return;
                    }
                    if (instance) {
                        const parsed = newValue !== null && newValue !== '' ? parseInt(newValue) : null;
                        instance.updateValue(parsed);
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
