document.addEventListener('alpine:init', () => {
    Alpine.data('data_grid', () => ({
        isDragging: false,
        startCell: null,
        endCell: null,
        clipboard: null,
        cells: {},
        cellMeta: {},
        editingCell: null,

        init() {
            document.addEventListener('mouseup', () => {
                this.isDragging = false;
            });
        },

        registerCell(rowIndex, colIndex, el, meta = null) {
            const key = `${rowIndex}-${colIndex}`;
            this.cells[key] = el;
            if (meta) {
                this.cellMeta[key] = meta;
            }
        },

        getCellValue(rowIndex, colIndex) {
            const key = `${rowIndex}-${colIndex}`;
            const el = this.cells[key];
            return el ? el.textContent.trim() : '';
        },

        setCellValue(rowIndex, colIndex, value, dispatchEvent = true) {
            const key = `${rowIndex}-${colIndex}`;
            const el = this.cells[key];
            if (el) {
                el.textContent = value;
                if (dispatchEvent) {
                    this.dispatchCellChange(rowIndex, colIndex, value);
                }
            }
        },

        dispatchCellChange(rowIndex, colIndex, value) {
            const key = `${rowIndex}-${colIndex}`;
            const meta = this.cellMeta[key];
            if (meta) {
                const el = this.cells[key];
                if (el) {
                    this.applyOverrideStyle(el, meta.field);
                }

                if (value === null) {
                    const pairedRowIndex = meta.field === 'reps' ? rowIndex + 1 : rowIndex - 1;
                    const pairedKey = `${pairedRowIndex}-${colIndex}`;
                    const pairedEl = this.cells[pairedKey];
                    if (pairedEl) {
                        pairedEl.textContent = '-';
                        this.applyOverrideStyle(pairedEl, meta.field === 'reps' ? 'weight' : 'reps');
                    }
                }

                this.$dispatch('cell-changed', {
                    rowIndex,
                    colIndex,
                    value,
                    ...meta
                });
            }
        },

        applyOverrideStyle(el, field) {
            if (field === 'reps') {
                el.classList.add('bg-blue-100', 'dark:bg-blue-800/50');
            } else if (field === 'weight') {
                el.classList.add('bg-green-100', 'dark:bg-green-800/50');
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

            const startRow = bounds.minRow;
            const startCol = bounds.minCol;

            for (let clipRow = 0; clipRow < clipboardRows; clipRow++) {
                for (let clipCol = 0; clipCol < clipboardCols; clipCol++) {
                    const targetRow = startRow + clipRow;
                    const targetCol = startCol + clipCol;
                    const key = `${targetRow}-${targetCol}`;
                    if (this.cells[key]) {
                        this.setCellValue(targetRow, targetCol, this.clipboard[clipRow][clipCol]);
                    }
                }
            }
        },

        handleCellMousedown(rowIndex, colIndex, event) {
            if (event.button !== 0) return;

            if (event.shiftKey && this.startCell) {
                this.endCell = { row: rowIndex, col: colIndex };
                return;
            }

            if (event.metaKey || event.ctrlKey) {
                this.isDragging = true;
                if (this.startCell) {
                    this.endCell = { row: rowIndex, col: colIndex };
                } else {
                    this.startCell = { row: rowIndex, col: colIndex };
                    this.endCell = { row: rowIndex, col: colIndex };
                }
                return;
            }

            this.isDragging = true;
            this.startCell = { row: rowIndex, col: colIndex };
            this.endCell = { row: rowIndex, col: colIndex };
        },

        handleCellDblClick(rowIndex, colIndex, event) {
            if (event.button !== 0) return;
            this.clearSelection();
            this.startEdit(rowIndex, colIndex);
        },

        extendSelection(rowIndex, colIndex, event) {
            if (!this.isDragging) return;
            if (event.buttons !== 1) return;
            if (this.endCell?.row === rowIndex && this.endCell?.col === colIndex) {
                return;
            }
            this.endCell = { row: rowIndex, col: colIndex };
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
        },

        startEdit(rowIndex, colIndex) {
            const key = `${rowIndex}-${colIndex}`;
            const el = this.cells[key];
            if (!el) return;

            this.editingCell = { row: rowIndex, col: colIndex };
            const currentValue = el.textContent.trim();

            el.style.position = 'relative';
            el.dataset.originalValue = currentValue;

            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentValue === '-' ? '' : currentValue;
            input.style.cssText = 'position: absolute; inset: 0; width: 100%; height: 100%; text-align: center; border: none; outline: none; background: white; padding: 0; margin: 0; font-size: inherit; font-family: inherit;';

            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });

            el.textContent = '';
            el.appendChild(input);
            input.focus();
            input.select();

            input.addEventListener('dblclick', (e) => {
                e.stopPropagation();
            });

            input.addEventListener('mousedown', (e) => {
                e.stopPropagation();
            });

            input.addEventListener('blur', () => {
                this.commitEdit();
            });
        },

        isEditing(rowIndex, colIndex) {
            return this.editingCell?.row === rowIndex && this.editingCell?.col === colIndex;
        },

        commitEdit() {
            if (!this.editingCell) return;

            const key = `${this.editingCell.row}-${this.editingCell.col}`;
            const el = this.cells[key];
            if (!el) return;

            const input = el.querySelector('input');
            const value = input ? input.value.trim() : '';
            const originalValue = el.dataset.originalValue;
            const displayValue = value === '' ? '-' : value;

            el.textContent = displayValue;
            el.style.position = '';
            delete el.dataset.originalValue;

            const normalizedOriginal = originalValue === '-' ? '' : originalValue;
            if (value !== normalizedOriginal) {
                this.dispatchCellChange(this.editingCell.row, this.editingCell.col, value === '' ? null : value);
            }

            this.editingCell = null;
        },

        cancelEdit() {
            if (!this.editingCell) return;

            const key = `${this.editingCell.row}-${this.editingCell.col}`;
            const el = this.cells[key];
            if (!el) return;

            const originalValue = el.dataset.originalValue || '';
            el.textContent = originalValue;
            el.style.position = '';
            delete el.dataset.originalValue;
            this.editingCell = null;
        }
    }));
});
