(function () {
    function toggleIndefiniteContract() {
        var checkbox = document.querySelector('[data-contract-indefinite]');
        var endField = document.querySelector('[data-contract-end]');

        if (!checkbox || !endField) {
            return;
        }

        var update = function () {
            var checked = checkbox.checked;
            endField.disabled = checked;
            if (checked) {
                endField.value = '';
            }
        };

        checkbox.addEventListener('change', update);
        update();
    }

    function toggleClientForms() {
        var typeSelect = document.querySelector('[data-client-type]');
        if (!typeSelect) {
            return;
        }

        var individualFields = document.querySelectorAll('[data-client-individual]');
        var companyFields = document.querySelectorAll('[data-client-company]');
        var toggleMailing = document.querySelector('[data-client-mailing-toggle]');
        var mailingFields = document.querySelector('[data-client-mailing]');

        var refresh = function () {
            var isCompany = typeSelect.value === 'firma';
            individualFields.forEach(function (field) {
                field.classList.toggle('hidden', isCompany);
            });
            companyFields.forEach(function (field) {
                field.classList.toggle('hidden', !isCompany);
            });
        };

        typeSelect.addEventListener('change', refresh);
        refresh();

        if (toggleMailing && mailingFields) {
            var updateMailing = function () {
                mailingFields.classList.toggle('hidden', toggleMailing.checked);
            };
            toggleMailing.addEventListener('change', updateMailing);
            updateMailing();
        }
    }

    function setupPropertyForm() {
        var form = document.querySelector('[data-property-form]');
        if (!form) {
            return;
        }

        var priceField = form.querySelector('[data-property-price]');
        var areaField = form.querySelector('[data-property-area]');
        var pricePerField = form.querySelector('[data-property-price-m2]');

        var updatePricePer = function () {
            if (!priceField || !areaField || !pricePerField) {
                return;
            }

            var price = parseFloat(priceField.value.replace(',', '.')) || 0;
            var area = parseFloat(areaField.value.replace(',', '.')) || 0;
            pricePerField.value = area > 0 ? (price / area).toFixed(2) : '';
        };

        if (priceField && areaField && pricePerField) {
            priceField.addEventListener('input', updatePricePer);
            areaField.addEventListener('input', updatePricePer);
            updatePricePer();
        }

        var kindSelect = form.querySelector('[data-property-kind]');
        if (kindSelect) {
            var updateKind = function () {
                var current = kindSelect.value;
                form.querySelectorAll('[data-property-not]').forEach(function (element) {
                    var attr = element.getAttribute('data-property-not');
                    if (!attr) {
                        return;
                    }
                    var list = attr.split('|');
                    element.classList.toggle('hidden', list.indexOf(current) !== -1);
                });
                form.querySelectorAll('[data-property-only]').forEach(function (element) {
                    var attr = element.getAttribute('data-property-only');
                    element.classList.toggle('hidden', attr !== current);
                });
            };
            kindSelect.addEventListener('change', updateKind);
            updateKind();
        }

        var plotShape = form.querySelector('[data-property-plot-shape]');
        var plotRegular = form.querySelector('[data-property-plot-regular]');
        var plotIrregular = form.querySelector('[data-property-plot-irregular]');
        if (plotShape) {
            var updatePlot = function () {
                var regular = plotShape.value !== 'nieregularny';
                if (plotRegular) {
                    plotRegular.classList.toggle('hidden', !regular);
                }
                if (plotIrregular) {
                    plotIrregular.classList.toggle('hidden', regular);
                }
            };
            plotShape.addEventListener('change', updatePlot);
            updatePlot();
        }

        var parkingToggle = form.querySelector('[data-property-parking]');
        var parkingOptions = form.querySelector('[data-property-parking-options]');
        if (parkingToggle && parkingOptions) {
            var updateParking = function () {
                parkingOptions.classList.toggle('hidden', !parkingToggle.checked);
            };
            parkingToggle.addEventListener('change', updateParking);
            updateParking();
        }

        var kwToggle = form.querySelector('[data-property-no-kw]');
        var kwField = form.querySelector('[data-property-kw]');
        if (kwToggle && kwField) {
            var updateKw = function () {
                kwField.disabled = kwToggle.checked;
                if (kwToggle.checked) {
                    kwField.value = '';
                }
            };
            kwToggle.addEventListener('change', updateKw);
            updateKw();
        }

        form.querySelectorAll('[data-extra-space]').forEach(function (container) {
            var toggle = container.querySelector('[data-extra-space-toggle]');
            var fields = container.querySelector('[data-extra-space-fields]');
            if (!toggle || !fields) {
                return;
            }
            var update = function () {
                fields.classList.toggle('hidden', !toggle.checked);
            };
            toggle.addEventListener('change', update);
            update();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleIndefiniteContract();
        toggleClientForms();
        setupPropertyForm();
    });
})();
