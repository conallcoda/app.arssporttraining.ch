document.addEventListener('alpine:init', () => {
    Alpine.data('schedule_grid', () => ({
        draggedWeekId: null,
        draggedDay: null,
        draggedSlot: null,
        draggedProgramId: null,
        isDraggingOver: null,
        isDropDisallowed: false,
        draggedCell: null,

        init() {
        },

        handleDragStart(event) {
            const el = event.currentTarget;
            this.draggedWeekId = el.dataset.weekId;
            this.draggedDay = parseInt(el.dataset.day, 10);
            this.draggedSlot = el.dataset.slot;
            this.draggedProgramId = parseInt(el.dataset.programId, 10);
            this.draggedCell = this.draggedWeekId + '-' + this.draggedDay + '-' + this.draggedSlot;
            event.dataTransfer.effectAllowed = 'move';
        },

        handleDragOver(event) {
            const el = event.currentTarget;
            const targetWeekId = el.dataset.weekId;
            const targetDay = parseInt(el.dataset.day, 10);
            const targetSlot = el.dataset.slot;

            const targetCell = targetWeekId + '-' + targetDay + '-' + targetSlot;
            this.isDraggingOver = targetCell;

            const isSameCell = this.draggedCell === targetCell;
            this.isDropDisallowed = isSameCell;

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
            this.draggedCell = null;
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
        },

        handleDrop(event) {
            const el = event.currentTarget;
            const targetWeekId = el.dataset.weekId;
            const targetDay = parseInt(el.dataset.day, 10);
            const targetSlot = el.dataset.slot;
            const targetProgramId = el.dataset.programId ? parseInt(el.dataset.programId, 10) : null;

            if (this.isDropDisallowed || !this.draggedWeekId) {
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

            if (targetProgramId !== null) {
                this.$wire.dispatch('program-swap', {
                    week1Id: this.draggedWeekId,
                    day1: this.draggedDay,
                    slot1: this.draggedSlot,
                    week2Id: targetWeekId,
                    day2: targetDay,
                    slot2: targetSlot
                });
            } else {
                this.$wire.dispatch('program-move', {
                    weekId: this.draggedWeekId,
                    fromDay: this.draggedDay,
                    fromSlot: this.draggedSlot,
                    toDay: targetDay,
                    toSlot: targetSlot
                });
            }

            this.handleDragEnd();
        },

        getCellId(weekId, day, slot) {
            return weekId + '-' + day + '-' + slot;
        },

        destroy() {
        }
    }));
});
