(function ($) {
    const toggleEndDate = () => {
        const $checkbox = $('#eoc-open-ended');
        const $endDate = $('#eoc-end-date');
        if ($checkbox.is(':checked')) {
            $endDate.prop('disabled', true).val('');
        } else {
            $endDate.prop('disabled', false);
        }
    };

    $(document).ready(function () {
        toggleEndDate();
        $('#eoc-open-ended').on('change', toggleEndDate);
    });
})(jQuery);
