(function ($) {
    'use strict';

    function initMediaButtons() {
        $('.estate-office-media-button').on('click', function (event) {
            event.preventDefault();
            var button = $(this);
            var targetField = $('#' + button.data('target'));

            var frame = wp.media({
                title: button.text(),
                button: {
                    text: button.text()
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                targetField.val(attachment.url).trigger('change');
            });

            frame.open();
        });
    }

    function toggleFieldsByVisibility(container, contextValue) {
        container.find('[data-visibility]').each(function () {
            var field = $(this);
            var visibleFor = field.data('visibility');
            if (!visibleFor) {
                field.show();
                return;
            }

            var values = String(visibleFor).split(',');
            if ($.inArray(contextValue, values) !== -1) {
                field.show();
            } else {
                field.hide();
            }
        });
    }

    function initPropertyMeta() {
        var container = $('.estate-office-property-meta');
        if (!container.length) {
            return;
        }

        var propertyTypeField = container.find('#estate_office_property_type');
        var transactionField = container.find('#estate_office_transaction_type');
        var lotShapeField = container.find('#estate_office_lot_shape');
        var priceField = container.find('#estate_office_price');
        var areaField = container.find('#estate_office_area');
        var pricePerSqmField = container.find('#estate_office_price_sqm');
        var kwCheckbox = container.find('input[name="estate_office_meta[no_lmr]"]');
        var kwField = container.find('#estate_office_land_and_mortgage_register');

        function updatePropertyVisibility() {
            toggleFieldsByVisibility(container, propertyTypeField.val());
        }

        function updateLotShapeVisibility() {
            var value = lotShapeField.val();
            container.find('[data-lot-shape]').each(function () {
                var field = $(this);
                var allowed = field.data('lot-shape');
                if (!allowed || allowed === value) {
                    field.show();
                } else {
                    field.hide();
                }
            });
        }

        function updateFlagsAvailability() {
            var transaction = transactionField.val();
            container.find('.estate-office-flags label').each(function () {
                var label = $(this);
                var required = label.data('transaction');
                var checkbox = label.find('input[type="checkbox"]');
                if (!required || required === transaction) {
                    checkbox.prop('disabled', false);
                } else {
                    checkbox.prop('disabled', true).prop('checked', false);
                }
            });
        }

        function updatePricePerSqm() {
            var price = parseFloat(priceField.val());
            var area = parseFloat(areaField.val());
            if (!isNaN(price) && !isNaN(area) && area > 0) {
                var value = (price / area).toFixed(2);
                pricePerSqmField.val(value);
            }
        }

        function updateKwState() {
            if (kwField.length) {
                kwField.prop('disabled', kwCheckbox.is(':checked'));
            }
        }

        propertyTypeField.on('change', updatePropertyVisibility);
        transactionField.on('change', updateFlagsAvailability);
        lotShapeField.on('change', updateLotShapeVisibility);
        priceField.on('input', updatePricePerSqm);
        areaField.on('input', updatePricePerSqm);
        kwCheckbox.on('change', updateKwState);

        updatePropertyVisibility();
        updateLotShapeVisibility();
        updateFlagsAvailability();
        updatePricePerSqm();
        updateKwState();
    }

    function initContractMeta() {
        var container = $('.estate-office-contract-meta');
        if (!container.length) {
            return;
        }

        container.find('[data-disabled-toggle]').each(function () {
            var input = $(this);
            var toggleName = input.data('disabled-toggle');
            var selector = 'input[name="estate_office_meta[' + toggleName + ']"]';
            var toggle = container.find(selector);
            var update = function () {
                var disabled = toggle.is(':checked');
                input.prop('disabled', disabled);
                if (disabled) {
                    input.val('');
                }
            };
            toggle.on('change', update);
            update();
        });
    }

    function initClientMeta() {
        var container = $('.estate-office-client-meta');
        if (!container.length) {
            return;
        }

        var typeField = container.find('#estate_office_client_type');
        var correspondenceToggle = container.find('input[name="estate_office_meta[same_correspondence]"]');
        var correspondenceBox = container.find('.estate-office-correspondence');

        function updateVisibility() {
            toggleFieldsByVisibility(container, typeField.val());
        }

        function updateCorrespondence() {
            if (correspondenceToggle.is(':checked')) {
                correspondenceBox.hide();
            } else {
                correspondenceBox.show();
            }
        }

        typeField.on('change', updateVisibility);
        correspondenceToggle.on('change', updateCorrespondence);
        updateVisibility();
        updateCorrespondence();
    }

    function initToggleFields() {
        $('.estate-office-toggle-field input[type="checkbox"]').each(function () {
            var checkbox = $(this);
            var target = checkbox.closest('.estate-office-toggle-field').find('.estate-office-toggle-target');
            var update = function () {
                if (checkbox.is(':checked')) {
                    target.slideDown(150);
                    target.find('input').prop('disabled', false);
                } else {
                    target.slideUp(150);
                    target.find('input').prop('disabled', true);
                }
            };
            checkbox.on('change', update);
            update();
        });
    }

    function initSearchMeta() {
        var container = $('.estate-office-search-meta');
        if (!container.length) {
            return;
        }

        var propertyTypeField = container.find('#estate_office_property_type');
        propertyTypeField.on('change', function () {
            toggleFieldsByVisibility(container, propertyTypeField.val());
        });
        toggleFieldsByVisibility(container, propertyTypeField.val());
    }

    $(document).ready(function () {
        initMediaButtons();
        initPropertyMeta();
        initContractMeta();
        initClientMeta();
        initToggleFields();
        initSearchMeta();
    });
})(jQuery);
