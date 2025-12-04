document.addEventListener('alpine:init', () => {
    Alpine.data('editable_cell', (wire, method, params, initialValue, suffix = '') => ({
        editing: false,
        value: initialValue,
        originalValue: initialValue,
        suffix: suffix,
        wire: wire,
        method: method,
        params: params,

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
            let numericValue = parseFloat(this.value);
            if (isNaN(numericValue) || numericValue < 1) {
                numericValue = 1;
            }
            this.value = numericValue;
            if (this.value !== this.originalValue) {
                this.originalValue = this.value;
                this.wire[this.method](...this.params, numericValue);
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
