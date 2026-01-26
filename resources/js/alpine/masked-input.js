document.addEventListener('alpine:init', () => {
    Alpine.data('masked_input', () => ({
        mask: '',
        init() {
            this.mask = this.$el.getAttribute('data-mask') || '';

            const input = this.$el.querySelector('input');
            if (input) {
                input.addEventListener('input', (e) => this.applyMask(e));
                input.addEventListener('keydown', (e) => this.handleKeydown(e));
            }
        },
        applyMask(e) {
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

                const newCursorPos = cursorPos + (result.length - oldLength);
                input.setSelectionRange(newCursorPos, newCursorPos);

                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        handleKeydown(e) {
            if (e.key === 'Backspace') {
                const input = e.target;
                const cursorPos = input.selectionStart;

                if (cursorPos > 0 && this.mask[cursorPos - 1] !== '9') {
                    e.preventDefault();
                    const newValue = input.value.slice(0, cursorPos - 2) + input.value.slice(cursorPos);
                    input.value = newValue;
                    input.setSelectionRange(cursorPos - 2, cursorPos - 2);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        }
    }));
});
