(function ($) {
    const initMediaFields = () => {
        $('.eoc-media-field').each(function () {
            const $field = $(this);
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.eoc-media-preview-wrapper');

            $field.on('click', '.eoc-media-select', function () {
                const frame = wp.media({
                    title: 'Wybierz zdjęcie',
                    button: { text: 'Użyj tego zdjęcia' },
                    multiple: false,
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    if (attachment.sizes && attachment.sizes.thumbnail) {
                        $preview.html(`<img src="${attachment.sizes.thumbnail.url}" alt="" class="eoc-media-preview" />`);
                    } else {
                        $preview.html('');
                    }
                });

                frame.open();
            });

            $field.on('click', '.eoc-media-remove', function () {
                $input.val('');
                $preview.html('');
            });
        });
    };

    $(document).ready(function () {
        initMediaFields();
    });
})(jQuery);
