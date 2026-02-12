import Treeselect from 'treeselectjs';

document.addEventListener('alpine:init', () => {
    Alpine.data('tree_select', () => {
        let instance = null;

        return {
            init() {
                const options = JSON.parse(this.$el.dataset.options || '[]');
                const value = JSON.parse(this.$el.dataset.value || '[]');
                const placeholder = this.$el.dataset.placeholder || '';
                const wireModel = this.$el.dataset.wireModel;

                const container = this.$el.querySelector('[data-tree-select-container]');

                instance = new Treeselect({
                    parentHtmlContainer: container,
                    value: value.length ? value[0] : null,
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
                        const arr = newValue !== null ? [parseInt(newValue)] : [];
                        this.$wire.set(wireModel, arr);
                    },
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
