(function ($) {
    'use strict';

    $(document).on('click', '.estate-office-table tr', function (event) {
        var $link = $(this).find('a').first();
        if ($link.length && !$(event.target).is('a, button, input, textarea')) {
            window.location = $link.attr('href');
        }
    });
})(jQuery);
