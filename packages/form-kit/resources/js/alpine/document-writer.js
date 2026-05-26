class DocumentWriterChartTool {
    static get toolbox() {
        return {
            title: 'Chart',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/></svg>',
        };
    }

    constructor({ data, config, readOnly }) {
        this.data = {
            type: data?.type ?? config?.chartTypes?.[0] ?? 'bar',
            title: data?.title ?? '',
            seriesLabel: data?.seriesLabel ?? '',
            labels: Array.isArray(data?.labels) ? data.labels : [],
            values: Array.isArray(data?.values) ? data.values : [],
            xAxisLabel: data?.xAxisLabel ?? '',
            yAxisLabel: data?.yAxisLabel ?? '',
        };
        this.chartTypes = Array.isArray(config?.chartTypes) && config.chartTypes.length > 0
            ? config.chartTypes
            : ['bar', 'line', 'pie', 'doughnut'];
        this.readOnly = readOnly;
        this.wrapper = null;
        this.fields = {};
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'space-y-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-950/40';

        const header = document.createElement('div');
        header.className = 'space-y-1';

        const title = document.createElement('div');
        title.className = 'text-sm font-semibold text-zinc-900 dark:text-zinc-100';
        title.textContent = 'Chart';

        const description = document.createElement('div');
        description.className = 'text-xs text-zinc-500 dark:text-zinc-400';
        description.textContent = 'Capture a simple chart definition that can be rendered elsewhere in the app.';

        header.append(title, description);
        this.wrapper.append(header);

        const grid = document.createElement('div');
        grid.className = 'grid gap-3 md:grid-cols-2';

        this.fields.type = this.createSelect('Chart type', this.chartTypes, this.data.type);
        this.fields.title = this.createInput('Title', 'Quarterly revenue', this.data.title);
        this.fields.seriesLabel = this.createInput('Series label', 'Revenue', this.data.seriesLabel);
        this.fields.xAxisLabel = this.createInput('X-axis label', 'Quarter', this.data.xAxisLabel);
        this.fields.yAxisLabel = this.createInput('Y-axis label', 'Amount', this.data.yAxisLabel);
        this.fields.labels = this.createTextarea('Labels', 'Q1, Q2, Q3, Q4', this.data.labels.join(', '));
        this.fields.values = this.createTextarea('Values', '12, 18, 22, 27', this.data.values.join(', '));

        grid.append(
            this.fields.type.container,
            this.fields.title.container,
            this.fields.seriesLabel.container,
            this.fields.xAxisLabel.container,
            this.fields.yAxisLabel.container
        );

        this.wrapper.append(grid, this.fields.labels.container, this.fields.values.container, this.createSummary());

        return this.wrapper;
    }

    save() {
        return {
            type: this.fields.type.input.value,
            title: this.fields.title.input.value.trim(),
            seriesLabel: this.fields.seriesLabel.input.value.trim(),
            labels: this.parseCsv(this.fields.labels.input.value),
            values: this.parseNumericCsv(this.fields.values.input.value),
            xAxisLabel: this.fields.xAxisLabel.input.value.trim(),
            yAxisLabel: this.fields.yAxisLabel.input.value.trim(),
        };
    }

    createSelect(label, options, value) {
        const input = document.createElement('select');
        input.className = this.inputClasses();
        input.disabled = this.readOnly;

        options.forEach((optionValue) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionValue.charAt(0).toUpperCase() + optionValue.slice(1);
            option.selected = optionValue === value;
            input.append(option);
        });

        return this.wrapField(label, input);
    }

    createInput(label, placeholder, value) {
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = placeholder;
        input.value = value;
        input.className = this.inputClasses();
        input.disabled = this.readOnly;

        return this.wrapField(label, input);
    }

    createTextarea(label, placeholder, value) {
        const input = document.createElement('textarea');
        input.rows = 3;
        input.placeholder = placeholder;
        input.value = value;
        input.className = `${this.inputClasses()} min-h-[88px]`;
        input.disabled = this.readOnly;

        return this.wrapField(label, input);
    }

    createSummary() {
        const summary = document.createElement('div');
        summary.className = 'rounded-xl border border-dashed border-zinc-300 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-600 dark:text-zinc-400';
        summary.textContent = 'Tip: keep labels and values aligned so downstream renderers can chart the data cleanly.';

        return summary;
    }

    wrapField(label, input) {
        const container = document.createElement('label');
        container.className = 'flex flex-col gap-2';

        const labelElement = document.createElement('span');
        labelElement.className = 'text-xs font-medium uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400';
        labelElement.textContent = label;

        container.append(labelElement, input);

        return { container, input };
    }

    inputClasses() {
        return 'w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100';
    }

    parseCsv(value) {
        return String(value ?? '')
            .split(',')
            .map((entry) => entry.trim())
            .filter((entry) => entry !== '');
    }

    parseNumericCsv(value) {
        return this.parseCsv(value)
            .map((entry) => Number.parseFloat(entry))
            .filter((entry) => Number.isFinite(entry));
    }
}

const normalizeDocument = (value) => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return { blocks: [] };
    }

    const normalizeListItems = (items) => {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map((item) => {
            if (typeof item === 'string') {
                return {
                    content: item,
                    meta: {},
                    items: [],
                };
            }

            if (!item || typeof item !== 'object') {
                return {
                    content: '',
                    meta: {},
                    items: [],
                };
            }

            if (typeof item.content === 'string') {
                return {
                    content: item.content,
                    meta: item.meta && typeof item.meta === 'object' ? item.meta : {},
                    items: normalizeListItems(item.items),
                };
            }

            return {
                content: typeof item.text === 'string' ? item.text : '',
                meta: typeof item.checked === 'boolean' ? { checked: item.checked } : {},
                items: normalizeListItems(item.items),
            };
        });
    };

    const normalizeBlock = (block) => {
        if (!block || typeof block !== 'object') {
            return block;
        }

        if (block.type !== 'list' || !block.data || typeof block.data !== 'object') {
            return block;
        }

        return {
            ...block,
            data: {
                style: block.data.style ?? 'unordered',
                meta: block.data.meta && typeof block.data.meta === 'object' ? block.data.meta : {},
                items: normalizeListItems(block.data.items),
            },
        };
    };

    const blocks = Array.isArray(value.blocks) ? value.blocks.map(normalizeBlock) : [];

    return {
        time: typeof value.time === 'number' ? value.time : Date.now(),
        version: typeof value.version === 'string' ? value.version : value.version,
        blocks,
    };
};

export const registerDocumentWriter = ({
    Alpine = globalThis.Alpine,
    EditorJS,
    Checklist,
    Delimiter,
    Header,
    List,
    Quote,
    Table,
} = {}) => {
    if (!Alpine || !EditorJS || !Checklist || !Delimiter || !Header || !List || !Quote || !Table) {
        console.warn('Document Writer registration skipped because one or more Editor.js dependencies are missing.');
        return;
    }

    document.addEventListener('alpine:init', () => {
        Alpine.data('document_writer', (config = {}) => ({
            editor: null,
            wireModel: config.wireModel ?? '',
            placeholder: config.placeholder ?? 'Type / to insert a block',
            minHeight: Number(config.minHeight ?? 480),
            autofocus: Boolean(config.autofocus ?? false),
            chartTypes: Array.isArray(config.chartTypes) && config.chartTypes.length > 0
                ? config.chartTypes
                : ['bar', 'line', 'pie', 'doughnut'],
            state: normalizeDocument(config.value),
            lastSavedSerialized: '',
            syncTimer: null,
            applyingExternalState: false,

            async init() {
                await this.initializeEditor();

                this.$wire.$watch(this.wireModel, async (value) => {
                    const normalized = normalizeDocument(value);
                    const serialized = JSON.stringify(normalized);

                    if (serialized === this.lastSavedSerialized) {
                        return;
                    }

                    this.applyingExternalState = true;
                    this.state = normalized;
                    this.lastSavedSerialized = serialized;

                    if (this.editor) {
                        await this.editor.blocks.render(normalized);
                    }

                    this.applyingExternalState = false;
                });
            },

            async initializeEditor() {
                this.editor = new EditorJS({
                    holder: this.$refs.holder,
                    autofocus: this.autofocus,
                    minHeight: this.minHeight,
                    placeholder: this.placeholder,
                    defaultBlock: 'paragraph',
                    inlineToolbar: ['link', 'bold', 'italic'],
                    data: this.state,
                    tools: {
                        header: {
                            class: Header,
                            inlineToolbar: true,
                            config: {
                                levels: [1, 2, 3, 4],
                                defaultLevel: 2,
                            },
                        },
                        list: {
                            class: List,
                            inlineToolbar: true,
                        },
                        checklist: {
                            class: Checklist,
                            inlineToolbar: true,
                        },
                        quote: {
                            class: Quote,
                            inlineToolbar: true,
                        },
                        table: {
                            class: Table,
                            inlineToolbar: true,
                        },
                        delimiter: Delimiter,
                        chart: {
                            class: DocumentWriterChartTool,
                            config: {
                                chartTypes: this.chartTypes,
                            },
                        },
                    },
                    onChange: async () => {
                        if (this.applyingExternalState) {
                            return;
                        }

                        window.clearTimeout(this.syncTimer);
                        this.syncTimer = window.setTimeout(() => {
                            this.flushSync();
                        }, 250);
                    },
                });

                await this.editor.isReady;
                this.lastSavedSerialized = JSON.stringify(await this.editor.save());
            },

            async flushSync() {
                if (!this.editor) {
                    return;
                }

                const output = await this.editor.save();
                const serialized = JSON.stringify(output);

                if (serialized === this.lastSavedSerialized) {
                    return;
                }

                this.state = output;
                this.lastSavedSerialized = serialized;
                this.$wire.$set(this.wireModel, output, false);
            },

            destroy() {
                window.clearTimeout(this.syncTimer);

                if (this.editor && typeof this.editor.destroy === 'function') {
                    this.editor.destroy();
                }

                this.editor = null;
            },
        }));
    });
};
