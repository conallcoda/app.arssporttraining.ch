document.addEventListener('alpine:init', () => {
    Alpine.data('sortable_items', (fieldName) => ({
        fieldName: fieldName,
        draggingIndex: null,
        dragOverIndex: null,

        handleDragStart(event, index) {
            this.draggingIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', index);

            const row = event.target.closest('[data-item-index]');
            row.classList.add('opacity-50');
        },

        handleDragEnd(event) {
            const row = event.target.closest('[data-item-index]');
            if (row) {
                row.classList.remove('opacity-50');
            }

            this.draggingIndex = null;
            this.dragOverIndex = null;
            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
        },

        handleDragOver(event, index) {
            event.preventDefault();
            if (this.draggingIndex === index) return;

            this.dragOverIndex = index;
            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
            event.target.closest('[data-item-index]')?.classList.add('border-l-4', 'border-l-blue-500');
        },

        handleDragLeave(event) {
            const relatedTarget = event.relatedTarget;
            const currentTarget = event.target.closest('[data-item-index]');
            if (currentTarget && !currentTarget.contains(relatedTarget)) {
                currentTarget.classList.remove('border-l-4', 'border-l-blue-500');
            }
        },

        handleDrop(event, targetIndex) {
            event.preventDefault();
            const sourceIndex = parseInt(event.dataTransfer.getData('text/plain'));

            this.$el.querySelectorAll('[data-item-index]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });

            if (sourceIndex !== targetIndex) {
                this.$wire.reorderRelationshipItem(this.fieldName, sourceIndex, targetIndex);
            }

            this.draggingIndex = null;
            this.dragOverIndex = null;
        }
    }));
});
