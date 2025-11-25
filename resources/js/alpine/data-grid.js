document.addEventListener('alpine:init', () => {
    Alpine.data('data_grid', () => ({
        isDragging: false,
        startCell: null,
        endCell: null,
        clipboard: null,
        cells: {},

        init() {
            document.addEventListener('mouseup', () => {
                this.isDragging = false;
            });

            this.$el.addEventListener('keydown', (e) => {
                console.log(11111, e);
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    e.preventDefault();
                    this.copy();
                    console.log(1111);
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
                    e.preventDefault();
                    this.paste();
                }
            });
        },

        registerCell(rowIndex, colIndex, el) {
            const key = `${rowIndex}-${colIndex}`;
            this.cells[key] = el;
        },

        getCellValue(rowIndex, colIndex) {
            const key = `${rowIndex}-${colIndex}`;
            const el = this.cells[key];
            return el ? el.textContent.trim() : '';
        },

        setCellValue(rowIndex, colIndex, value) {
            const key = `${rowIndex}-${colIndex}`;
            const el = this.cells[key];
            if (el) {
                el.textContent = value;
            }
        },

        getSelectionBounds() {
            if (!this.startCell || !this.endCell) return null;
            return {
                minRow: Math.min(this.startCell.row, this.endCell.row),
                maxRow: Math.max(this.startCell.row, this.endCell.row),
                minCol: Math.min(this.startCell.col, this.endCell.col),
                maxCol: Math.max(this.startCell.col, this.endCell.col)
            };
        },

        copy() {
            const bounds = this.getSelectionBounds();
            if (!bounds) return;

            const data = [];
            for (let row = bounds.minRow; row <= bounds.maxRow; row++) {
                const rowData = [];
                for (let col = bounds.minCol; col <= bounds.maxCol; col++) {
                    rowData.push(this.getCellValue(row, col));
                }
                data.push(rowData);
            }
            this.clipboard = data;
            console.log('Copied data:', data);
        },

        paste() {
            if (!this.clipboard || !this.startCell) return;

            const bounds = this.getSelectionBounds();
            if (!bounds) return;

            const clipboardRows = this.clipboard.length;
            const clipboardCols = this.clipboard[0].length;

            for (let row = bounds.minRow; row <= bounds.maxRow; row++) {
                for (let col = bounds.minCol; col <= bounds.maxCol; col++) {
                    const clipRow = (row - bounds.minRow) % clipboardRows;
                    const clipCol = (col - bounds.minCol) % clipboardCols;
                    this.setCellValue(row, col, this.clipboard[clipRow][clipCol]);
                }
            }
        },

        startSelection(rowIndex, colIndex) {
            this.$el.focus();
            this.isDragging = true;
            this.startCell = { row: rowIndex, col: colIndex };
            this.endCell = { row: rowIndex, col: colIndex };
        },

        extendSelection(rowIndex, colIndex) {
            if (this.isDragging) {
                this.endCell = { row: rowIndex, col: colIndex };
            }
        },

        isSelected(rowIndex, colIndex) {
            if (!this.startCell || !this.endCell) return false;

            const minRow = Math.min(this.startCell.row, this.endCell.row);
            const maxRow = Math.max(this.startCell.row, this.endCell.row);
            const minCol = Math.min(this.startCell.col, this.endCell.col);
            const maxCol = Math.max(this.startCell.col, this.endCell.col);

            return rowIndex >= minRow && rowIndex <= maxRow && colIndex >= minCol && colIndex <= maxCol;
        },

        getBorderClasses(rowIndex, colIndex) {
            if (!this.isSelected(rowIndex, colIndex)) return '';

            const minRow = Math.min(this.startCell.row, this.endCell.row);
            const maxRow = Math.max(this.startCell.row, this.endCell.row);
            const minCol = Math.min(this.startCell.col, this.endCell.col);
            const maxCol = Math.max(this.startCell.col, this.endCell.col);

            let classes = [];

            if (rowIndex === minRow) classes.push('border-t-2 border-t-black');
            if (rowIndex === maxRow) classes.push('border-b-2 border-b-black');
            if (colIndex === minCol) classes.push('border-l-2 border-l-black');
            if (colIndex === maxCol) classes.push('border-r-2 border-r-black');

            return classes.join(' ');
        },

        clearSelection() {
            this.startCell = null;
            this.endCell = null;
        }
    }));
});
