(function () {
    'use strict';

    const filterTable = (table, query) => {
        if (!table) {
            return;
        }

        const rows = table.querySelectorAll('tbody tr');
        const needle = query.trim().toLowerCase();

        rows.forEach((row) => {
            if (needle.length === 0) {
                row.style.display = '';
                return;
            }

            const text = row.textContent || '';
            if (text.toLowerCase().indexOf(needle) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        const tableId = target.getAttribute('data-eo-crm-search');
        if (!tableId) {
            return;
        }

        const table = document.getElementById(tableId);
        filterTable(table, target.value);
    });
})();
