(function ($) {
    'use strict';

    const mediaFrames = {};

    function openMediaFrame(target) {
        const strings = window.estateOfficeSettings || {};
        if (mediaFrames[target]) {
            mediaFrames[target].open();
            return;
        }

        mediaFrames[target] = wp.media({
            title: strings.choose || 'Wybierz plik',
            button: {
                text: strings.choose || 'Wybierz',
            },
            multiple: false,
        });

        mediaFrames[target].on('select', function () {
            const attachment = mediaFrames[target].state().get('selection').first().toJSON();
            const field = $('#' + target);
            field.val(attachment.id);

            const container = field.closest('.estate-office-media-field');
            let preview = container.find('.preview');
            if (!preview.length) {
                preview = $('<div>', { class: 'preview' }).prependTo(container);
            }

            const img = $('<img>', {
                src: attachment.url,
                alt: '',
                css: { maxWidth: '150px', height: 'auto' },
            });

            preview.empty().append(img);
            container.find('.estate-office-media-remove').prop('disabled', false);
        });

        mediaFrames[target].open();
    }

    function removeMedia(target) {
        const field = $('#' + target);
        field.val('');
        field.closest('.estate-office-media-field').find('.preview').remove();
        field.closest('.estate-office-media-field').find('.estate-office-media-remove').prop('disabled', true);
    }

    function setupMediaButtons() {
        $(document).on('click', '.estate-office-media-upload', function (event) {
            event.preventDefault();
            openMediaFrame($(this).data('target'));
        });

        $(document).on('click', '.estate-office-media-remove', function (event) {
            event.preventDefault();
            removeMedia($(this).data('target'));
        });
    }

    function setupTagsInput() {
        const strings = window.estateOfficeSettings || {};
        const selectors = '.estate-office-tags-input';

        $(document).on('keydown', selectors + ' .tag-input', function (event) {
            if (event.key !== 'Enter' && event.key !== ',') {
                return;
            }

            event.preventDefault();
            const input = $(this);
            const value = input.val().trim();

            if (!value) {
                return;
            }

            const wrapper = input.closest('.estate-office-tags-input');
            const name = input.data('name');

            const tag = $('<span>', { class: 'tag' }).text(value);
            $('<button>', {
                type: 'button',
                class: 'dashicons dashicons-no-alt',
                'aria-label': strings.remove || 'Usuń',
            }).appendTo(tag);

            $('<input>', {
                type: 'hidden',
                name,
                value,
            }).appendTo(tag);

            input.before(tag);
            input.val('');
        });

        $(document).on('click', selectors + ' .dashicons-no-alt', function (event) {
            event.preventDefault();
            $(this).closest('.tag').remove();
        });
    }

    $(function () {
        setupMediaButtons();
        setupTagsInput();
    });
})(jQuery);
