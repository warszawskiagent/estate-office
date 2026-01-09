(function ($) {
    const toggleClientType = () => {
        const type = $('#eoc-client-type').val();
        if (type === 'COMPANY') {
            $('.eoc-client-fields--company').show();
            $('.eoc-client-fields--person').hide();
        } else {
            $('.eoc-client-fields--company').hide();
            $('.eoc-client-fields--person').show();
        }
    };

    const toggleMailing = () => {
        const isSame = $('#eoc-mailing-same').is(':checked');
        if (isSame) {
            $('.eoc-mailing-fields').hide();
        } else {
            $('.eoc-mailing-fields').show();
        }
    };

    $(document).ready(function () {
        toggleClientType();
        toggleMailing();

        $('#eoc-client-type').on('change', toggleClientType);
        $('#eoc-mailing-same').on('change', toggleMailing);
    });
})(jQuery);
