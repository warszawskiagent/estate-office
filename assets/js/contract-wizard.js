(function () {
    const { wp } = window;

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

        setupPropertyGallery(form);
    }

    function setupPropertyGallery(form) {
        if (!wp || !wp.media) {
            return;
        }

        var container = form.querySelector('[data-property-gallery]');
        if (!container) {
            return;
        }

        var list = container.querySelector('[data-gallery-list]');
        var emptyState = container.querySelector('[data-gallery-empty]');
        var addButton = container.querySelector('[data-gallery-add]');
        if (!list || !addButton) {
            return;
        }

        var frame;
        var removeLabel = container.getAttribute('data-remove-label') || 'Usuń zdjęcie';

        var refreshEmpty = function () {
            if (!emptyState) {
                return;
            }
            emptyState.classList.toggle('hidden', list.children.length > 0);
        };

        var addAttachment = function (attachment) {
            if (!attachment || !attachment.id) {
                return;
            }

            if (list.querySelector('[data-gallery-id="' + attachment.id + '"]')) {
                return;
            }

            var item = document.createElement('li');
            item.className = 'estate-office-gallery__item';
            item.setAttribute('data-gallery-id', attachment.id);

            var figure = document.createElement('figure');
            figure.className = 'estate-office-gallery__thumb';

            var img = document.createElement('img');
            var sizes = attachment.sizes || {};
            var preferred = sizes.medium || sizes.large || sizes.thumbnail;
            img.src = preferred ? preferred.url : attachment.url;
            img.alt = attachment.alt || attachment.title || '';
            figure.appendChild(img);

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'property[gallery][]';
            input.value = attachment.id;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'estate-office-gallery__remove';
            remove.setAttribute('data-gallery-remove', '1');
            remove.setAttribute('aria-label', removeLabel);
            remove.innerHTML = '×';

            item.appendChild(figure);
            item.appendChild(input);
            item.appendChild(remove);
            list.appendChild(item);
            refreshEmpty();
        };

        addButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!frame) {
                frame = wp.media({
                    title: addButton.dataset.label || addButton.textContent,
                    button: { text: addButton.dataset.label || addButton.textContent },
                    library: { type: 'image' },
                    multiple: true,
                });

                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    selection.each(function (item) {
                        addAttachment(item.toJSON());
                    });
                });
            }

            frame.open();
        });

        list.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-gallery-remove]');
            if (!removeButton) {
                return;
            }

            event.preventDefault();
            var item = removeButton.closest('.estate-office-gallery__item');
            if (item) {
                item.remove();
                refreshEmpty();
            }
        });

        refreshEmpty();
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleIndefiniteContract();
        toggleClientForms();
        setupPropertyForm();
    });
})();
