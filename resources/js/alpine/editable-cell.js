document.addEventListener('alpine:init', () => {
    Alpine.data('editable_cell', () => ({
        editing: false,
        editValue: '',
        mask: '',
        cellField: '',
        cellEditType: '',
        cellWeek: 0,
        cellSet: 0,

        init() {
            this.mask = this.$el.getAttribute('data-mask') || '';
            this.cellField = this.$el.getAttribute('data-field') || '';
            this.cellEditType = this.$el.getAttribute('data-edit-type') || 'cell';
            this.cellWeek = parseInt(this.$el.getAttribute('data-week') || '0', 10);
            this.cellSet = parseInt(this.$el.getAttribute('data-set') || '0', 10);
        },

        startEditing() {
            this.editing = true;
            this.editValue = '';
            this.$nextTick(() => {
                const input = this.$refs.input;
                if (input) {
                    input.focus();
                }
            });
        },

        cancelEditing() {
            this.editing = false;
            this.editValue = '';
        },

        commitEdit() {
            this.editing = false;

            if (this.editValue === '') {
                return;
            }

            const input = this.$refs.input;
            const inputType = input ? input.type : 'text';
            const step = input ? (input.step || '1') : '1';
            let parsedValue = this.editValue;

            if (inputType === 'number') {
                parsedValue = step.includes('.') ? parseFloat(this.editValue) : parseInt(this.editValue, 10);
                if (isNaN(parsedValue)) {
                    return;
                }
            }

            if (this.cellEditType === 'week') {
                this.$wire.updateWeekOverride(this.cellWeek, this.cellField, parsedValue);
            } else {
                this.$wire.updateCellOverride(this.cellWeek, this.cellSet, this.cellField, parsedValue);
            }
        },

        applyMask(e) {
            if (!this.mask) {
                return;
            }

            const input = e.target;
            const value = input.value;
            const digits = value.replace(/[^0-9]/g, '');

            let result = '';
            let digitIndex = 0;

            for (let i = 0; i < this.mask.length && digitIndex < digits.length; i++) {
                if (this.mask[i] === '9') {
                    result += digits[digitIndex];
                    digitIndex++;
                } else {
                    result += this.mask[i];
                    if (digits[digitIndex] === this.mask[i]) {
                        digitIndex++;
                    }
                }
            }

            if (result !== value) {
                const cursorPos = input.selectionStart;
                const oldLength = value.length;
                input.value = result;
                this.editValue = result;

                const newCursorPos = cursorPos + (result.length - oldLength);
                input.setSelectionRange(newCursorPos, newCursorPos);
            }
        },

        handleKeydown(event) {
            if (event.key === 'Enter') {
                this.$refs.input.blur();
            }
            if (event.key === 'Escape') {
                this.cancelEditing();
            }

            if (this.mask && event.key === 'Backspace') {
                const input = event.target;
                const cursorPos = input.selectionStart;

                if (cursorPos > 0 && this.mask[cursorPos - 1] !== '9') {
                    event.preventDefault();
                    const newValue = input.value.slice(0, cursorPos - 2) + input.value.slice(cursorPos);
                    input.value = newValue;
                    this.editValue = newValue;
                    input.setSelectionRange(cursorPos - 2, cursorPos - 2);
                }
            }
        }
    }));
});
