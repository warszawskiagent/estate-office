jQuery(function ($) {
    $('.estateoffice-media-upload').on('click', function (event) {
        event.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.estateoffice-media-field');
        const $input = $wrapper.find('input[type="text"]');
        const title = $button.data('title') || 'Wybierz plik';

        const mediaFrame = wp.media({
            title: title,
            button: { text: 'Użyj pliku' },
            multiple: false,
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
        });

        mediaFrame.open();
    });
});
