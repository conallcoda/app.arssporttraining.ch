import jsuites from 'jsuites';
import jspreadsheet from 'jspreadsheet-ce';

document.addEventListener('alpine:init', () => {
    Alpine.data('exercise_spreadsheet', () => ({
        config: {},
        worksheets: null,

        init() {
            this.config = JSON.parse(atob(this.$el.getAttribute('data-config')));
            this.$nextTick(() => {
                this.initSpreadsheet();
            });
        },

        initSpreadsheet() {
            const container = this.$refs.spreadsheet;
            if (!container) return;

            const columns = [
                { type: 'text', width: 50, readOnly: true, align: 'center' }
            ];

            for (let i = 0; i < this.config.setCount; i++) {
                columns.push({
                    type: 'numeric',
                    width: 60,
                    align: 'center'
                });
            }

            const mergeCells = {};

            this.config.rows.forEach((row, index) => {
                const repsRowIndex = index * 2;
                mergeCells[`A${repsRowIndex + 1}`] = [1, 2];
            });

            const self = this;

            this.worksheets = jspreadsheet(container, {
                worksheets: [{
                    data: this.buildData(),
                    columns: columns,
                    mergeCells: mergeCells,
                    minDimensions: [this.config.setCount + 1, this.config.rows.length * 2],
                    tableOverflow: true,
                    tableWidth: '100%',
                    defaultColWidth: 60,
                    columnSorting: false,
                    rowResize: false,
                    columnDrag: false,
                    rowDrag: false,
                    allowInsertRow: false,
                    allowInsertColumn: false,
                    allowDeleteRow: false,
                    allowDeleteColumn: false,
                    allowRenameColumn: false,
                    selectionCopy: false,
                }],
                onchange: function(worksheet, cell, x, y, value) {
                    if (x === 0) return;

                    const rowIndex = Math.floor(y / 2);
                    const isWeightRow = y % 2 === 1;
                    const row = self.config.rows[rowIndex];
                    if (!row) return;

                    const setIndex = x - 1;
                    const numValue = parseInt(value) || 0;
                    const type = isWeightRow ? 'weights' : 'reps';

                    self.$wire.updateCell(
                        self.config.exerciseId,
                        self.config.block,
                        row.week,
                        row.session,
                        type,
                        setIndex,
                        numValue
                    );
                },
                onload: function(spreadsheet) {
                    if (spreadsheet[0]) {
                        spreadsheet[0].hideIndex();
                    }
                }
            });

            if (this.worksheets && this.worksheets[0]) {
                this.worksheets[0].hideIndex();
            }
        },

        buildData() {
            const data = [];
            this.config.rows.forEach(row => {
                const repsRow = [`${row.week}.${row.session}`, ...row.reps];
                const weightsRow = ['', ...row.weights];
                data.push(repsRow);
                data.push(weightsRow);
            });
            return data;
        },

        destroy() {
            if (this.worksheets) {
                jspreadsheet.destroy(this.$refs.spreadsheet);
                this.worksheets = null;
            }
        }
    }));
});
