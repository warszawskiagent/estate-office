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

    function slugifyKey(value) {
        if (typeof value !== 'string') {
            return '';
        }

        if (typeof value.normalize === 'function') {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        return value
            .replace(/[^A-Za-z0-9_\s-]/g, '')
            .replace(/[^A-Za-z0-9_]+/g, '_')
            .replace(/_{2,}/g, '_')
            .replace(/^_+|_+$/g, '')
            .toLowerCase();
    }

    function hasDuplicateKey(list, key, current) {
        let duplicate = false;

        list.find('.estate-office-role-key').each(function () {
            if (this === current) {
                return;
            }

            if ($(this).val() === key) {
                duplicate = true;
                return false;
            }
        });

        return duplicate;
    }

    function ensureUniqueKey(list, slug, current) {
        if (!slug) {
            return '';
        }

        let candidate = slug;
        let suffix = 2;

        while (hasDuplicateKey(list, candidate, current)) {
            candidate = slug + '_' + suffix;
            suffix += 1;
        }

        return candidate;
    }

    function setupRoleManager() {
        const template = $('#estate-office-role-template');
        if (!template.length) {
            return;
        }

        $(document).on('click', '.estate-office-role-manager__add', function (event) {
            event.preventDefault();
            const container = $(this).closest('.estate-office-role-manager');
            const list = container.find('.estate-office-role-manager__list');
            const nextIndex = parseInt(container.attr('data-next-index') || list.children().length, 10);
            container.attr('data-next-index', String(nextIndex + 1));

            let markup = template.html().replace(/__index__/g, nextIndex);
            const row = $(markup);
            list.append(row);
        });

        $(document).on('click', '.estate-office-role-remove', function (event) {
            event.preventDefault();
            $(this).closest('.estate-office-role-manager__row').remove();
        });

        $(document).on('input', '.estate-office-role-label', function () {
            const row = $(this).closest('.estate-office-role-manager__row');
            const keyField = row.find('.estate-office-role-key');

            if (!keyField.length) {
                return;
            }

            if (keyField.attr('data-auto') === '0') {
                return;
            }

            const slug = slugifyKey($(this).val());
            const list = row.closest('.estate-office-role-manager__list');

            if (!slug) {
                keyField.val('');
                keyField.attr('data-auto', '1');
                return;
            }

            const unique = ensureUniqueKey(list, slug, keyField.get(0));
            keyField.val(unique);
            keyField.attr('data-auto', '1');
        });

        $(document).on('input', '.estate-office-role-key', function () {
            const field = $(this);
            const list = field.closest('.estate-office-role-manager__list');
            const slug = slugifyKey(field.val());

            if (!slug) {
                field.val('');
                field.attr('data-auto', '1');
                return;
            }

            const unique = ensureUniqueKey(list, slug, field.get(0));
            field.val(unique);
            field.attr('data-auto', '0');
        });
    }

    $(function () {
        setupMediaButtons();
        setupTagsInput();
        setupRoleManager();
    });
})(jQuery);
