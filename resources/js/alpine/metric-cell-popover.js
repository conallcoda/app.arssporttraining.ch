document.addEventListener('alpine:init', () => {
    Alpine.data('metric_cell_popover', () => ({
        open: false,
        loading: false,
        entries: [],
        canAdd: false,
        metricValue: null,
        date: null,
        x: 0,
        y: 0,
        _above: false,
        _wire: null,

        init() {
            this._wire = this.$wire

            this._onClickOutside = (e) => {
                if (!this.open) return
                const popover = document.getElementById('metric-cell-popover')
                if (popover && !popover.contains(e.target)) {
                    this.close()
                }
            }
            document.addEventListener('click', this._onClickOutside, true)
        },

        destroy() {
            if (this._onClickOutside) {
                document.removeEventListener('click', this._onClickOutside, true)
            }
        },

        openPopover(el, metricValue, date, cellData) {
            const rect = el.getBoundingClientRect()
            this.x = rect.left + rect.width / 2
            this.y = rect.bottom + 4

            if (this.y + 200 > window.innerHeight) {
                this.y = rect.top - 4
                this._above = true
            } else {
                this._above = false
            }

            this.metricValue = metricValue
            this.date = date
            this.entries = cellData.entries || []
            this.canAdd = cellData.count < cellData.memberCount
            this.open = true
        },

        close() {
            this.open = false
            this.entries = []
        },

        editEntry(userId, submissionId) {
            if (this._wire) {
                this._wire.openGroupMetricCell(this.metricValue, this.date, userId, submissionId)
            }
            this.close()
        },

        addNew() {
            if (this._wire) {
                this._wire.openGroupMetricCell(this.metricValue, this.date)
            }
            this.close()
        },
    }))
})
