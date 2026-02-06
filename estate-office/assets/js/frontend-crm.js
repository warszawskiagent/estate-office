jQuery(function ($) {
    const $tabs = $('.estateoffice-tab');
    const $panels = $('.estateoffice-crm__panel');

    function activateTab(target) {
        $tabs.removeClass('is-active');
        $tabs.filter('[data-tab="' + target + '"]').addClass('is-active');
        $panels.attr('hidden', true);
        $('#estateoffice-tab-' + target).removeAttr('hidden');
    }

    if ($tabs.length) {
        activateTab('dashboard');
    }

    $tabs.on('click', function () {
        const target = $(this).data('tab');
        activateTab(target);
    });

    const $openEnded = $('[data-estateoffice-open-ended]');
    const $endDate = $('[data-estateoffice-end-date]');

    function toggleEndDate() {
        const isChecked = $openEnded.is(':checked');
        $endDate.prop('disabled', isChecked);
        if (isChecked) {
            $endDate.val('');
        }
    }

    $openEnded.on('change', toggleEndDate);
    toggleEndDate();
});
