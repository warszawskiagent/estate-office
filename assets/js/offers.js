(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var filterForm = document.querySelector('.estate-office-offers__filters');

        if (!filterForm) {
            return;
        }

        filterForm.addEventListener('change', function (event) {
            var target = event.target;

            if (!target || !(target instanceof HTMLSelectElement)) {
                return;
            }

            filterForm.requestSubmit();
        });
    });
})();
