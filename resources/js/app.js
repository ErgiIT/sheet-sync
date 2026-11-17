import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('mappingForm', ({ columns, keyColumns, syncMode, headersUrl, types }) => ({
    columns: columns.length ? columns : [{ source_column: '', target_column: '', type: 'string' }],
    keyColumns,
    syncMode,
    types,
    loading: false,
    headerError: null,

    addColumn(column = { source_column: '', target_column: '', type: 'string' }) {
        this.columns.push(column);
    },

    removeColumn(index) {
        const [removed] = this.columns.splice(index, 1);
        this.keyColumns = this.keyColumns.filter((key) => key !== removed.target_column);
    },

    keyCandidates() {
        return this.columns.filter((column) => column.target_column && column.type !== 'text');
    },

    async loadHeaders() {
        this.loading = true;
        this.headerError = null;

        try {
            const { data } = await window.axios.get(headersUrl);
            const known = new Set(this.columns.map((column) => column.source_column.trim().toLowerCase()));
            this.columns = this.columns.filter((column) => column.source_column || column.target_column);

            data.headers
                .filter((header) => !known.has(header.source_column.toLowerCase()))
                .forEach((header) => this.addColumn({ ...header, type: 'string' }));

            if (!this.columns.length) {
                this.addColumn();
            }
        } catch (error) {
            this.headerError = error.response?.data?.message ?? 'Could not read the sheet.';
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.data('tabPicker', (url) => ({
    names: [],
    loading: false,
    error: null,

    async detect() {
        this.loading = true;
        this.error = null;

        try {
            const { data } = await window.axios.get(url);
            this.names = data.tabs;
        } catch (error) {
            this.error = error.response?.data?.message ?? 'Could not read the spreadsheet.';
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.start();
