(function($){
    'use strict';

    $(function(){
        $('.estate-office-crm-tab').on('click', function(){
            const target = $(this).data('target');
            $(this).addClass('is-active').siblings().removeClass('is-active');
            $('.estate-office-crm-panel').removeClass('is-active');
            $(target).addClass('is-active');
        });

        $('.estate-office-crm-tab').first().trigger('click');
    });
})(jQuery);
