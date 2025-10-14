(function ($) {
    'use strict';

    const selectors = {
        avatar: '.estate-office-agent-avatar',
        selectButton: '.estate-office-agent-avatar-select',
        removeButton: '.estate-office-agent-avatar-remove',
        field: '#estate-office-agent-avatar',
    };

    function initUploader($context) {
        const strings = window.EstateOfficeAgentProfile || {};

        const frameData = {
            title: strings.choose || '',
            button: {
                text: strings.update || '',
            },
            multiple: false,
        };

        let frame = null;

        $context.find(selectors.selectButton).on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media(frameData);

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                const id = attachment.get('id');
                const sizes = attachment.get('sizes') || {};
                const url = sizes.thumbnail ? sizes.thumbnail.url : attachment.get('url');

                $context.find(selectors.field).val(id);
                renderPreview($context, url);
            });

            frame.open();
        });

        $context.find(selectors.removeButton).on('click', function (event) {
            event.preventDefault();

            $context.find(selectors.field).val('');
            renderPlaceholder($context, strings);
        });
    }

    function renderPreview($context, url) {
        const $avatar = $context.find(selectors.avatar);

        $avatar.empty();
        if (url) {
            $('<img>', { src: url, alt: '' }).appendTo($avatar);
        }

        toggleRemoveButton($context, true);
    }

    function renderPlaceholder($context, strings) {
        const $avatar = $context.find(selectors.avatar);
        const label = (strings && strings.placeholder) || '';

        $avatar.empty();
        const $icon = $('<span>', { class: 'placeholder dashicons dashicons-format-image' });
        if (label) {
            $('<span>', { class: 'screen-reader-text', text: label }).appendTo($icon);
        }
        $icon.appendTo($avatar);

        toggleRemoveButton($context, false);
    }

    function toggleRemoveButton($context, visible) {
        const $button = $context.find(selectors.removeButton);
        $button[visible ? 'show' : 'hide']();
        if (!visible) {
            $context.find(selectors.field).val('');
        }
    }

    $(function () {
        $('.estate-office-agent-avatar').each(function () {
            const $cell = $(this).closest('td');
            if ($cell.length) {
                initUploader($cell);
            }
        });
    });
})(jQuery);
