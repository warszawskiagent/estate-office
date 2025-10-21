(function ($) {
    'use strict';

    function initTabs(container) {
        const tabs = container.find('.estate-office-crm-tab');
        const panels = container.find('.estate-office-crm-panel');

        tabs.on('click', function (event) {
            event.preventDefault();
            const tab = $(this);
            const target = tab.data('tab');

            tabs.removeClass('is-active');
            panels.removeClass('is-active');

            tab.addClass('is-active');
            panels.filter('[data-panel="' + target + '"]').addClass('is-active');
        });
    }

    function initSearch(container) {
        const input = container.find('#estate-office-crm-search-field');
        const tables = container.find('.estate-office-crm-table');

        input.on('input', function () {
            const query = $(this).val().toLowerCase();

            tables.each(function () {
                const table = $(this);
                const rows = table.find('tbody tr');
                let hasVisible = false;

                rows.each(function () {
                    const row = $(this);
                    if (row.hasClass('estate-office-empty')) {
                        return;
                    }

                    const text = row.text().toLowerCase();
                    const match = text.indexOf(query) !== -1;
                    row.toggle(match);
                    if (match) {
                        hasVisible = true;
                    }
                });

                table.find('.estate-office-empty').toggle(!hasVisible);
            });
        });
    }

    $(document).ready(function () {
        const crmContainer = $('.estate-office-crm');
        if (!crmContainer.length) {
            return;
        }

        initTabs(crmContainer);
        initSearch(crmContainer);
    });
})(jQuery);
