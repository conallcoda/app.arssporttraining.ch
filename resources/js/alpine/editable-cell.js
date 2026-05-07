document.addEventListener('alpine:init', () => {
    Alpine.data('editable_cell', () => ({
        editing: false,
        editValue: '',
        mask: '',
        cellField: '',
        cellEditType: '',
        cellWeek: 0,
        cellSet: 0,
        cellSession: 0,
        cellApplyToAll: false,
        valueTarget: 'planned',
        msgInvalidNumber: 'Please enter a valid number',
        msgInvalidValue: 'Please enter a valid value',

        init() {
            this.mask = this.$el.getAttribute('data-mask') || ''
            this.cellField = this.$el.getAttribute('data-field') || ''
            this.cellEditType = this.$el.getAttribute('data-edit-type') || 'cell'
            this.cellWeek = parseInt(this.$el.getAttribute('data-week') || '0', 10)
            this.cellSet = parseInt(this.$el.getAttribute('data-set') || '0', 10)
            this.cellSession = parseInt(this.$el.getAttribute('data-session') || '0', 10)
            this.cellApplyToAll = this.$el.getAttribute('data-apply-to-all') === 'true'
            this.valueTarget = this.$el.getAttribute('data-value-target') || 'planned'
            this.msgInvalidNumber = this.$el.getAttribute('data-msg-invalid-number') || this.msgInvalidNumber
            this.msgInvalidValue = this.$el.getAttribute('data-msg-invalid-value') || this.msgInvalidValue
        },

        startEditing() {
            this.editing = true
            this.editValue = ''

            this.$nextTick(() => {
                const input = this.$refs.input

                if (input) {
                    input.focus()
                }
            })
        },

        cancelEditing() {
            this.editing = false
            this.editValue = ''
        },

        commitEdit() {
            this.editing = false

            if (this.editValue === '') {
                return
            }

            const input = this.$refs.input
            const inputType = input ? input.type : 'text'
            const inputStep = (input && input.step) || '1'
            let value = this.editValue

            if (inputType === 'number') {
                value = inputStep.includes('.')
                    ? parseFloat(this.editValue)
                    : parseInt(this.editValue, 10)

                if (isNaN(value)) {
                    window.Flux.toast({ text: this.msgInvalidNumber, variant: 'danger' })

                    return
                }
            } else if (input && input.pattern) {
                if (! new RegExp(`^${input.pattern}$`).test(this.editValue.trim())) {
                    window.Flux.toast({ text: this.msgInvalidValue, variant: 'danger' })

                    return
                }

                value = this.editValue.trim()
            }

            if (this.valueTarget === 'actual') {
                if (this.cellEditType === 'session') {
                    this.$wire.updateActualSessionValue(this.cellWeek, this.cellSession, this.cellField, value)

                    return
                }

                this.$wire.updateActualCellValue(this.cellWeek, this.cellSet, this.cellField, value, this.cellSession)

                return
            }

            if (this.cellEditType === 'session') {
                this.$wire.updateSessionOverride(this.cellWeek, this.cellSession, this.cellField, value, this.cellApplyToAll)

                return
            }

            this.$wire.updateCellOverride(this.cellWeek, this.cellSet, this.cellField, value, this.cellSession, this.cellApplyToAll)
        },

        applyMask(event) {
            if (! this.mask) {
                return
            }

            const input = event.target
            const currentValue = input.value
            const digits = currentValue.replace(/[^0-9]/g, '')
            let masked = ''
            let digitIndex = 0

            for (let i = 0; i < this.mask.length && digitIndex < digits.length; i++) {
                if (this.mask[i] === '9') {
                    masked += digits[digitIndex]
                    digitIndex++
                } else {
                    masked += this.mask[i]

                    if (digits[digitIndex] === this.mask[i]) {
                        digitIndex++
                    }
                }
            }

            if (masked !== currentValue) {
                const cursorPosition = input.selectionStart
                const originalLength = currentValue.length

                input.value = masked
                this.editValue = masked

                const newCursorPosition = cursorPosition + (masked.length - originalLength)
                input.setSelectionRange(newCursorPosition, newCursorPosition)
            }
        },

        handleKeydown(event) {
            if (event.key === 'Enter') {
                this.$refs.input.blur()
            }

            if (event.key === 'Escape') {
                this.cancelEditing()
            }

            if (this.mask && event.key === 'Backspace') {
                const input = event.target
                const cursorPosition = input.selectionStart

                if (cursorPosition > 0 && this.mask[cursorPosition - 1] !== '9') {
                    event.preventDefault()

                    const newValue = input.value.slice(0, cursorPosition - 2) + input.value.slice(cursorPosition)
                    input.value = newValue
                    this.editValue = newValue
                    input.setSelectionRange(cursorPosition - 2, cursorPosition - 2)
                }
            }
        },
    }))
})
