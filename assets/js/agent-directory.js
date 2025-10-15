(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.estate-office-agents-directory__filters').forEach(function (form) {
            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            });
        });
    });
})();
