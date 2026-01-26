document.addEventListener('alpine:init', () => {
    Alpine.data('editable_cell', (wire, method, params, initialValue, suffix = '', isNumeric = true, mask = null, validationPattern = null) => ({
        editing: false,
        value: initialValue,
        originalValue: initialValue,
        suffix: suffix,
        wire: wire,
        method: method,
        params: params,
        isNumeric: isNumeric,
        mask: mask,
        validationPattern: validationPattern,
        isInvalid: false,

        startEditing() {
            this.editing = true;
            this.isInvalid = false;
            this.$nextTick(() => {
                const ref = this.$refs.input;
                if (ref) {
                    const input = ref.tagName === 'INPUT' ? ref : ref.querySelector('input');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }
            });
        },

        applyMask(e) {
            if (!this.mask) return;

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
                this.value = result;

                this.$nextTick(() => {
                    const newCursorPos = cursorPos + (result.length - oldLength);
                    input.setSelectionRange(newCursorPos, newCursorPos);
                });
            }

            this.validateValue();
        },

        validateValue() {
            if (!this.validationPattern || !this.value || this.value === '') {
                this.isInvalid = false;
                return true;
            }

            const regex = new RegExp(this.validationPattern);
            this.isInvalid = !regex.test(this.value);
            return !this.isInvalid;
        },

        save() {
            if (this.value && this.value !== '' && !this.validateValue()) {
                this.value = this.originalValue;
                this.isInvalid = false;
            }

            this.editing = false;
            let valueToSave = this.value;

            if (this.isNumeric) {
                let numericValue = parseFloat(this.value);
                if (isNaN(numericValue) || numericValue < 1) {
                    numericValue = 1;
                }
                valueToSave = numericValue;
                this.value = numericValue;
            }

            if (valueToSave !== this.originalValue) {
                this.originalValue = valueToSave;
                this.wire[this.method](...this.params, valueToSave);
            }
        },

        cancel() {
            this.value = this.originalValue;
            this.editing = false;
            this.isInvalid = false;
        },

        handleKeydown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.save();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.cancel();
            }
        }
    }));
});
