document.addEventListener('alpine:init', () => {
    Alpine.data('horizontal_scroll_position', () => ({
        position: 0,
        scrollElement: null,
        observer: null,

        init() {
            this.scrollElement = this.findScrollElement()
            this.position = this.scrollElement?.scrollLeft ?? 0

            this.observer = new MutationObserver(() => {
                this.restoreAfterReplacement()
            })

            this.observer.observe(this.$el, { childList: true, subtree: true })
        },

        remember(event) {
            const element = event.target

            if (!(element instanceof Element) || !element.matches('[data-preserve-horizontal-scroll]')) {
                return
            }

            this.position = element.scrollLeft
            this.scrollElement = element
        },

        restoreAfterReplacement() {
            const replacement = this.findScrollElement()

            if (!replacement || replacement === this.scrollElement) {
                return
            }

            const maximum = Math.max(0, replacement.scrollWidth - replacement.clientWidth)

            replacement.scrollLeft = Math.min(this.position, maximum)
            this.scrollElement = replacement
        },

        findScrollElement() {
            return this.$el.querySelector('[data-preserve-horizontal-scroll]')
        },

        destroy() {
            this.observer?.disconnect()
        },
    }))
})
