(function ($) {
    const togglePropertyType = () => {
        const type = $('#eoc-property-type').val();
        $('.eoc-property-section').hide().find('input, select, textarea').prop('disabled', true);
        if (type === 'MIESZKANIE' || type === 'LOKAL') {
            $('.eoc-property-section--apartment').show().find('input, select, textarea').prop('disabled', false);
        }
        if (type === 'DOM' || type === 'DZIALKA') {
            $('.eoc-property-section--house').show().find('input, select, textarea').prop('disabled', false);
        }
    };

    $(document).ready(function () {
        togglePropertyType();
        $('#eoc-property-type').on('change', togglePropertyType);
    });
})(jQuery);
