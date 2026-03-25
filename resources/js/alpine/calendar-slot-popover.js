document.addEventListener('alpine:init', () => {
    Alpine.data('calendar_slot_popover', (config = {}) => ({
        cellData: {},
        cellDataLoaded: false,
        groupId: config.groupId || null,
        userId: config.userId || null,
        startDate: config.startDate || null,
        endDate: config.endDate || null,
        gridCellsUrl: config.gridCellsUrl || '/admin/api/program-grid-cells',
        slotDetailsUrl: config.slotDetailsUrl || '/admin/api/slot-details',

        open: false,
        loading: false,
        slotDetails: null,
        trainingProgramId: null,
        date: null,
        color: null,
        x: 0,
        y: 0,
        _above: false,
        _wire: null,

        init() {
            this._wire = this.$wire;

            this._onClickOutside = (e) => {
                if (!this.open) return;
                const popover = document.getElementById('calendar-slot-popover');
                if (popover && !popover.contains(e.target)) {
                    this.close();
                }
            };
            document.addEventListener('click', this._onClickOutside, true);

            if (this.groupId && this.startDate && this.endDate) {
                this.loadCellData();
            }
        },

        destroy() {
            if (this._onClickOutside) {
                document.removeEventListener('click', this._onClickOutside, true);
            }
        },

        async loadCellData() {
            const params = new URLSearchParams({
                group_id: this.groupId,
                start: this.startDate,
                end: this.endDate,
            });
            if (this.userId) {
                params.set('user_id', this.userId);
            }
            const resp = await fetch(this.gridCellsUrl + '?' + params);
            this.cellData = await resp.json();
            this.cellDataLoaded = true;
        },

        getCellCount(programId, date) {
            return this.cellData[programId + '-' + date] || 0;
        },

        hasCategoryData(programIds, date) {
            return programIds.some(pid => this.cellData[pid + '-' + date] > 0);
        },

        openPopover(el, trainingProgramId, date, color) {
            const rect = el.getBoundingClientRect();
            this.x = rect.left + rect.width / 2;
            this.y = rect.bottom + 4;

            if (this.y + 200 > window.innerHeight) {
                this.y = rect.top - 4;
                this._above = true;
            } else {
                this._above = false;
            }

            this.trainingProgramId = trainingProgramId;
            this.date = date;
            this.color = color;
            this.slotDetails = null;
            this.loading = true;
            this.open = true;

            const params = new URLSearchParams({
                training_program_id: trainingProgramId,
                date: date,
            });

            fetch(this.slotDetailsUrl + '?' + params)
                .then(r => r.json())
                .then(data => {
                    this.slotDetails = data;
                    this.loading = false;
                });
        },

        close() {
            this.open = false;
            this.slotDetails = null;
        },

        editSlot(time) {
            if (this._wire) {
                this._wire.editWeekSlot(this.trainingProgramId, this.date, time);
            }
            this.close();
        },
    }));
});
