document.addEventListener('alpine:init', () => {
    Alpine.data('calendar_slot_popover', (config = {}) => {
        const wireId = config.wireId || null

        return {
            cellData: {},
            cellDataLoaded: false,
            groupId: config.groupId || null,
            userId: config.userId || null,
            startDate: config.startDate || null,
            endDate: config.endDate || null,
            gridCellsUrl: config.gridCellsUrl || '/admin/api/program-grid-cells',
            slotDetailsUrl: config.slotDetailsUrl || '/admin/api/slot-details',
            days: config.days || [],
            athleteSlotOrder: config.athleteSlotOrder || {},
            statusBarColors: config.statusBarColors || {},
            wireId,

            open: false,
            loading: false,
            slotDetails: null,
            trainingProgramId: null,
            date: null,
            color: null,
            x: 0,
            y: 0,
            _above: false,

            init() {
                this._onClickOutside = (e) => {
                    if (!this.open) return
                    let popover = document.getElementById('calendar-slot-popover')
                    if (popover && !popover.contains(e.target)) {
                        this.close()
                    }
                }
                document.addEventListener('click', this._onClickOutside, true)

                if (this.groupId && this.startDate && this.endDate) {
                    this.loadCellData()
                }

                Livewire.on('grid-cells-changed', () => {
                    this.loadCellData()
                })
            },

            destroy() {
                if (this._onClickOutside) {
                    document.removeEventListener('click', this._onClickOutside, true)
                }
            },

            getWire() {
                return wireId ? Livewire.find(wireId) : null
            },

            async loadCellData() {
                let params = new URLSearchParams({
                    group_id: this.groupId,
                    start: this.startDate,
                    end: this.endDate,
                })
                if (this.userId) {
                    params.set('user_id', this.userId)
                }
                let resp = await fetch(this.gridCellsUrl + '?' + params)
                this.cellData = await resp.json()
                this.cellDataLoaded = true
            },

            getCell(programId, date) {
                let val = this.cellData[programId + '-' + date]
                if (val && typeof val === 'object') return val

                return {
                    count: val || 0,
                    status: 'pending',
                    completedCount: 0,
                    partialCount: 0,
                    skippedCount: 0,
                    pendingCount: val || 0,
                }
            },

            getCellCount(programId, date) {
                return this.getCell(programId, date).count || 0
            },

            getCellTime(programId, date) {
                return this.getCell(programId, date).time || null
            },

            getCellSession(programId, date) {
                let key = programId + '-' + date
                if (this.athleteSlotOrder[key] !== undefined) {
                    return this.athleteSlotOrder[key]
                }

                return this.getCell(programId, date).session || null
            },

            getCellStatus(programId, date) {
                return this.getCell(programId, date).status || 'pending'
            },

            getCellStatusStyle(programId, date) {
                let status = this.getCellStatus(programId, date)
                let colors = this.statusBarColors[status] || this.statusBarColors.pending || null
                if (!colors) return {}

                return {
                    '--status-bar-light': colors.light,
                    '--status-bar-dark': colors.dark,
                }
            },

            getCellStatusLabel(programId, date) {
                let status = this.getCellStatus(programId, date)
                return (this.statusBarColors[status] && this.statusBarColors[status].label) || 'Pending'
            },

            hasCategoryData(programIds, date) {
                return programIds.some(pid => this.getCellCount(pid, date) > 0)
            },

            handleCellClick(el, trainingProgramId, date, color) {
                let count = this.getCellCount(trainingProgramId, date)
                let wire = this.getWire()
                if (!wire) return

                if (this.userId) {
                    if (count > 0) {
                        let time = this.getCellTime(trainingProgramId, date)
                        if (time) {
                            wire.editWeekSlot(trainingProgramId, date, time)
                        }
                    } else {
                        wire.openProgramSlot(trainingProgramId, date)
                    }
                } else {
                    if (count > 0) {
                        this.openPopover(el, trainingProgramId, date, color)
                    } else {
                        wire.openProgramSlot(trainingProgramId, date)
                    }
                }
            },

            openPopover(el, trainingProgramId, date, color) {
                let rect = el.getBoundingClientRect()
                this.x = rect.left + rect.width / 2
                this.y = rect.bottom + 4

                if (this.y + 200 > window.innerHeight) {
                    this.y = rect.top - 4
                    this._above = true
                } else {
                    this._above = false
                }

                this.trainingProgramId = trainingProgramId
                this.date = date
                this.color = color
                this.slotDetails = null
                this.loading = true
                this.open = true

                let params = new URLSearchParams({
                    training_program_id: trainingProgramId,
                    date: date,
                })

                fetch(this.slotDetailsUrl + '?' + params)
                    .then(r => r.json())
                    .then(data => {
                        this.slotDetails = data
                        this.loading = false
                    })
            },

            close() {
                this.open = false
                this.slotDetails = null
            },

            editSlot(time) {
                let wire = this.getWire()
                if (wire) {
                    wire.editWeekSlot(this.trainingProgramId, this.date, time)
                }
                this.close()
            },
        }
    })
})
