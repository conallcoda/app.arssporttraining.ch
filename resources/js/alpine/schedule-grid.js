document.addEventListener('alpine:init', () => {
    Alpine.data('schedule_grid', () => ({
        draggedWeekId: null,
        draggedDay: null,
        draggedSlot: null,
        draggedProgramId: null,
        isDraggingOver: null,
        isDropDisallowed: false,
        draggedProgram: null,
        dropTargetKey: null,
        dropTargetProgramId: null,
        dropPosition: null,

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
            const td = event.currentTarget;
            const targetWeekId = td.dataset.weekId;
            const targetDay = parseInt(td.dataset.day, 10);
            const targetSlot = parseInt(td.dataset.slot, 10);

            const targetCell = targetWeekId + '-' + targetDay + '-' + targetSlot;
            const sourceCell = this.draggedWeekId + '-' + this.draggedDay + '-' + this.draggedSlot;

            this.isDraggingOver = targetCell;

            const programEl = event.target.closest('[data-program-id]');
            if (programEl && programEl.closest('td') === td) {
                const hoveredProgramId = parseInt(programEl.dataset.programId, 10);
                const hoveredKey = targetCell + '-' + hoveredProgramId;

                if (hoveredKey !== this.draggedProgram) {
                    const rect = programEl.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    this.dropTargetKey = hoveredKey;
                    this.dropTargetProgramId = hoveredProgramId;
                    this.dropPosition = event.clientY < midY ? 'before' : 'after';
                    this.isDropDisallowed = false;
                } else {
                    this.dropTargetKey = null;
                    this.dropTargetProgramId = null;
                    this.dropPosition = null;
                    this.isDropDisallowed = true;
                }
            } else {
                this.dropTargetKey = null;
                this.dropTargetProgramId = null;
                this.dropPosition = null;
                this.isDropDisallowed = (sourceCell === targetCell);
            }

            event.dataTransfer.dropEffect = this.isDropDisallowed ? 'none' : 'move';
        },

        handleDragLeave() {
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
            this.dropTargetKey = null;
            this.dropTargetProgramId = null;
            this.dropPosition = null;
        },

        handleDragEnd() {
            this.draggedWeekId = null;
            this.draggedDay = null;
            this.draggedSlot = null;
            this.draggedProgramId = null;
            this.draggedProgram = null;
            this.isDraggingOver = null;
            this.isDropDisallowed = false;
            this.dropTargetKey = null;
            this.dropTargetProgramId = null;
            this.dropPosition = null;
        },

        handleDrop(event) {
            const td = event.currentTarget;
            const targetWeekId = td.dataset.weekId;
            const targetDay = parseInt(td.dataset.day, 10);
            const targetSlot = parseInt(td.dataset.slot, 10);

            if (this.isDropDisallowed || !this.draggedWeekId || !this.draggedProgramId) {
                this.handleDragEnd();
                return;
            }

            const isSameCell = this.draggedWeekId === targetWeekId &&
                              this.draggedDay === targetDay &&
                              this.draggedSlot === targetSlot;

            if (isSameCell) {
                if (this.dropTargetProgramId !== null && this.dropPosition) {
                    this.$wire.dispatch('schedule-event', {
                        type: 'reorder-slot-program',
                        data: {
                            weekId: this.draggedWeekId,
                            day: this.draggedDay,
                            slot: this.draggedSlot,
                            programId: this.draggedProgramId,
                            targetProgramId: this.dropTargetProgramId,
                            position: this.dropPosition
                        }
                    });
                }
            } else {
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
            }

            this.handleDragEnd();
        },

        destroy() {
        }
    }));
});
