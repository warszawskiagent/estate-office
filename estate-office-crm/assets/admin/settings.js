(function ($) {
    const initFieldLists = () => {
        $('.eoc-field-list').each(function () {
            const $listWrapper = $(this);
            const $items = $listWrapper.find('.eoc-field-list__items');

            $listWrapper.on('click', '.eoc-add-field', function () {
                const $item = $('<li class="eoc-field-list__item">');
                const fieldName = `eoc_settings[${$listWrapper.data('field-list')}][]`;
                $item.append(`<input type="text" name="${fieldName}" class="regular-text" />`);
                $item.append('<button type="button" class="button eoc-remove-field">Usuń</button>');
                $items.append($item);
            });

            $listWrapper.on('click', '.eoc-remove-field', function () {
                const $item = $(this).closest('.eoc-field-list__item');
                if ($items.find('.eoc-field-list__item').length > 1) {
                    $item.remove();
                } else {
                    $item.find('input').val('');
                }
            });
        });
    };

    const initMediaFields = () => {
        $('.eoc-media-field').each(function () {
            const $field = $(this);
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.eoc-media-preview-wrapper');

            $field.on('click', '.eoc-media-select', function () {
                const frame = wp.media({
                    title: 'Wybierz plik',
                    button: { text: 'Użyj tego pliku' },
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
        initFieldLists();
        initMediaFields();
    });
})(jQuery);
