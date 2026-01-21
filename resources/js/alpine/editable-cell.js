document.addEventListener('alpine:init', () => {
    Alpine.data('editable_cell', (wire, method, params, initialValue, suffix = '', isNumeric = true) => ({
        editing: false,
        value: initialValue,
        originalValue: initialValue,
        suffix: suffix,
        wire: wire,
        method: method,
        params: params,
        isNumeric: isNumeric,

        startEditing() {
            this.editing = true;
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

        save() {
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
