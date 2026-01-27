document.addEventListener('alpine:init', () => {
    Alpine.data('sortable_programs', () => ({
        draggingId: null,
        dragOverId: null,

        handleDragStart(event, id) {
            this.draggingId = id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', id);

            const fieldset = event.target.closest('[data-program-id]');
            fieldset.classList.add('border-dashed', 'opacity-50');

            const clone = fieldset.cloneNode(true);
            clone.style.position = 'absolute';
            clone.style.top = '-9999px';
            clone.style.left = '-9999px';
            clone.style.width = `${fieldset.offsetWidth}px`;
            clone.style.backgroundColor = document.documentElement.classList.contains('dark') ? '#27272a' : '#ffffff';
            clone.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.3)';
            clone.style.borderStyle = 'solid';
            clone.style.opacity = '1';
            document.body.appendChild(clone);

            event.dataTransfer.setDragImage(clone, event.offsetX + 20, event.offsetY + 20);

            requestAnimationFrame(() => {
                clone.remove();
            });
        },

        handleDragEnd(event) {
            const fieldset = event.target.closest('[data-program-id]');
            if (fieldset) {
                fieldset.classList.remove('border-dashed', 'opacity-50');
            }

            this.draggingId = null;
            this.dragOverId = null;
            document.querySelectorAll('[data-program-id]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
        },

        handleDragOver(event, id) {
            event.preventDefault();
            if (this.draggingId === id) return;

            this.dragOverId = id;
            document.querySelectorAll('[data-program-id]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
            event.target.closest('[data-program-id]')?.classList.add('border-l-4', 'border-l-blue-500');
        },

        handleDragLeave(event) {
            const relatedTarget = event.relatedTarget;
            const currentTarget = event.target.closest('[data-program-id]');
            if (currentTarget && !currentTarget.contains(relatedTarget)) {
                currentTarget.classList.remove('border-l-4', 'border-l-blue-500');
            }
        },

        handleDrop(event, targetId) {
            event.preventDefault();
            const sourceId = parseInt(event.dataTransfer.getData('text/plain'));

            document.querySelectorAll('[data-program-id]').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });

            if (sourceId !== targetId) {
                this.$wire.reorderPrograms(sourceId, targetId);
            }

            this.draggingId = null;
            this.dragOverId = null;
        }
    }));
});
