jQuery(function ($) {
    $('.estateoffice-media-upload').on('click', function (event) {
        event.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.estateoffice-media-field');
        const $input = $wrapper.find('input[type="text"]');
        const $preview = $wrapper.find('.estateoffice-media-preview');
        const title = $button.data('title') || 'Wybierz plik';

        const mediaFrame = wp.media({
            title: title,
            button: { text: 'Użyj pliku' },
            multiple: false,
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
            if (attachment.url) {
                $preview.html('<img src=\"' + attachment.url + '\" alt=\"\" style=\"max-width: 180px; display: block; margin-top: 8px;\" />');
            }
        });

        mediaFrame.open();
    });

    $('.estateoffice-media-remove').on('click', function (event) {
        event.preventDefault();
        const $wrapper = $(this).closest('.estateoffice-media-field');
        $wrapper.find('input[type=\"text\"]').val('');
        $wrapper.find('.estateoffice-media-preview').empty();
    });
});
