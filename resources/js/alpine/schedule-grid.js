document.addEventListener('alpine:init', () => {
    Alpine.data('schedule_grid', () => ({
        draggedWeekId: null,
        draggedDay: null,
        draggedSlot: null,
        draggedProgramId: null,
        isDraggingOver: null,
        isDropDisallowed: false,
        draggedProgram: null,

        init() {
        },

        handleDragStart(event) {
            const el = event.currentTarget;
            this.draggedWeekId = el.dataset.weekId;
            this.draggedDay = parseInt(el.dataset.day, 10);
            this.draggedSlot = parseInt(el.dataset.slot, 10);
            this.draggedProgramId = parseInt(el.dataset.programId, 10);
            this.draggedProgram = this.draggedWeekId + '-' + this.draggedDay + '-' + this.draggedSlot + '-' + this.draggedProgramId;
            event.dataTransfer.effectAllowed = 'move';
        },

        handleDragOver(event) {
            const el = event.currentTarget;
            const targetWeekId = el.dataset.weekId;
            const targetDay = parseInt(el.dataset.day, 10);
            const targetSlot = parseInt(el.dataset.slot, 10);

            const targetCell = targetWeekId + '-' + targetDay + '-' + targetSlot;
            this.isDraggingOver = targetCell;

            const sourceCell = this.draggedWeekId + '-' + this.draggedDay + '-' + this.draggedSlot;
            this.isDropDisallowed = sourceCell === targetCell;

            event.dataTransfer.dropEffect = this.isDropDisallowed ? 'none' : 'move';
        },

        handleDragLeave() {
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
        },

        handleDragEnd() {
            this.draggedWeekId = null;
            this.draggedDay = null;
            this.draggedSlot = null;
            this.draggedProgramId = null;
            this.draggedProgram = null;
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
        },

        handleDrop(event) {
            const el = event.currentTarget;
            const targetWeekId = el.dataset.weekId;
            const targetDay = parseInt(el.dataset.day, 10);
            const targetSlot = parseInt(el.dataset.slot, 10);

            if (this.isDropDisallowed || !this.draggedWeekId || !this.draggedProgramId) {
                this.handleDragEnd();
                return;
            }

            const isSameCell = this.draggedWeekId === targetWeekId &&
                              this.draggedDay === targetDay &&
                              this.draggedSlot === targetSlot;

            if (isSameCell) {
                this.handleDragEnd();
                return;
            }

            this.$wire.dispatch('schedule-event', {
                type: 'move-single-program',
                data: {
                    programId: this.draggedProgramId,
                    fromWeekId: this.draggedWeekId,
                    fromDay: this.draggedDay,
                    fromSlot: this.draggedSlot,
                    toWeekId: targetWeekId,
                    toDay: targetDay,
                    toSlot: targetSlot
                }
            });

            this.handleDragEnd();
        },

        destroy() {
        }
    }));
});
