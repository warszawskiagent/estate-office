(function ($) {
    'use strict';

    function initMediaButtons() {
        $('.estate-office-media-button').on('click', function (event) {
            event.preventDefault();
            const button = $(this);
            const targetField = $('#' + button.data('target'));

            const frame = wp.media({
                title: button.text(),
                button: {
                    text: button.text()
                },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                targetField.val(attachment.url).trigger('change');
            });

            frame.open();
        });
    }

    function initToggles() {
        $('.estate-office-toggle').on('click', function (event) {
            event.preventDefault();
            const target = $('#' + $(this).data('target'));
            target.slideToggle(150);
        });
    }

    $(document).ready(function () {
        initMediaButtons();
        initToggles();
    });
})(jQuery);
