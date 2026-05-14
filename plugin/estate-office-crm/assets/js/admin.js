(function ($) {
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getPreviewUrlFromMedia(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }

        if (data.sizes && data.sizes.medium && data.sizes.medium.url) {
            return String(data.sizes.medium.url);
        }

        if (data.sizes && data.sizes.large && data.sizes.large.url) {
            return String(data.sizes.large.url);
        }

        return data.url ? String(data.url) : '';
    }

    function ensureAgentCropModal() {
        var existing = document.getElementById('eocrm-agent-crop-modal');
        if (existing) {
            return existing;
        }

        var modal = document.createElement('div');
        modal.id = 'eocrm-agent-crop-modal';
        modal.className = 'eocrm-crop-modal';
        modal.innerHTML =
            '<div class="eocrm-crop-modal-backdrop" data-eocrm-crop-cancel></div>' +
            '<div class="eocrm-crop-modal-dialog" role="dialog" aria-modal="true">' +
            '<h3 class="eocrm-crop-modal-title"></h3>' +
            '<div class="eocrm-crop-modal-body">' +
            '<canvas id="eocrm-agent-crop-canvas" width="340" height="340"></canvas>' +
            '<div class="eocrm-crop-controls">' +
            '<label><span class="eocrm-crop-label-x"></span><input type="range" min="0" max="0" value="0" step="1" data-eocrm-crop-x></label>' +
            '<label><span class="eocrm-crop-label-y"></span><input type="range" min="0" max="0" value="0" step="1" data-eocrm-crop-y></label>' +
            '<label><span class="eocrm-crop-label-zoom"></span><input type="range" min="100" max="350" value="100" step="1" data-eocrm-crop-zoom></label>' +
            '<p class="description">Kadr jest zapisywany jako kwadrat 1:1 (wysoka jakosc).</p>' +
            '</div>' +
            '</div>' +
            '<div class="eocrm-crop-modal-actions">' +
            '<button type="button" class="button" data-eocrm-crop-cancel></button>' +
            '<button type="button" class="button button-primary" data-eocrm-crop-save></button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(modal);
        return modal;
    }

    function openAgentCropper(mediaData, onSaveSuccess) {
        var modal = ensureAgentCropModal();
        var labels = (window.eocrmAdmin && window.eocrmAdmin.labels) ? window.eocrmAdmin.labels : {};
        var ajaxUrl = (window.eocrmAdmin && window.eocrmAdmin.ajaxUrl) ? String(window.eocrmAdmin.ajaxUrl) : '';
        var cropNonce = (window.eocrmAdmin && window.eocrmAdmin.cropNonce) ? String(window.eocrmAdmin.cropNonce) : '';

        if (!ajaxUrl || !cropNonce || !mediaData || !mediaData.id || !mediaData.url) {
            return false;
        }

        var titleEl = modal.querySelector('.eocrm-crop-modal-title');
        var cancelButtons = modal.querySelectorAll('[data-eocrm-crop-cancel]');
        var saveButton = modal.querySelector('[data-eocrm-crop-save]');
        var xInput = modal.querySelector('[data-eocrm-crop-x]');
        var yInput = modal.querySelector('[data-eocrm-crop-y]');
        var zoomInput = modal.querySelector('[data-eocrm-crop-zoom]');
        var xLabel = modal.querySelector('.eocrm-crop-label-x');
        var yLabel = modal.querySelector('.eocrm-crop-label-y');
        var zoomLabel = modal.querySelector('.eocrm-crop-label-zoom');
        var cancelLabelButton = modal.querySelector('.eocrm-crop-modal-actions [data-eocrm-crop-cancel]');
        var canvas = modal.querySelector('#eocrm-agent-crop-canvas');
        if (!titleEl || !saveButton || !xInput || !yInput || !zoomInput || !xLabel || !yLabel || !zoomLabel || !canvas || !cancelLabelButton) {
            return false;
        }

        titleEl.textContent = labels.cropTitle || 'Kadrowanie zdjecia agenta';
        saveButton.textContent = labels.cropSave || 'Zapisz kadr';
        cancelLabelButton.textContent = labels.cropCancel || 'Anuluj';
        xLabel.textContent = labels.cropX || 'Pozycja X';
        yLabel.textContent = labels.cropY || 'Pozycja Y';
        zoomLabel.textContent = labels.cropZoom || 'Przyblizenie';

        var ctx = canvas.getContext('2d');
        if (!ctx) {
            return false;
        }

        var image = new Image();
        image.crossOrigin = 'anonymous';

        var state = {
            minDim: 0,
            imageW: 0,
            imageH: 0,
            size: 0,
            x: 0,
            y: 0,
            zoom: 100
        };

        var closeModal = function () {
            modal.classList.remove('is-open');
            saveButton.disabled = false;
            saveButton.classList.remove('is-busy');
        };

        var setRanges = function () {
            state.minDim = Math.min(state.imageW, state.imageH);
            state.zoom = Math.max(100, Math.min(parseInt(zoomInput.value || '100', 10), 350));
            state.size = Math.max(120, Math.round(state.minDim * (100 / state.zoom)));
            state.size = Math.min(state.size, state.minDim);

            var maxX = Math.max(0, state.imageW - state.size);
            var maxY = Math.max(0, state.imageH - state.size);
            state.x = Math.max(0, Math.min(state.x, maxX));
            state.y = Math.max(0, Math.min(state.y, maxY));

            xInput.max = String(maxX);
            yInput.max = String(maxY);
            xInput.value = String(state.x);
            yInput.value = String(state.y);
        };

        var render = function () {
            setRanges();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#f1f5fb';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(
                image,
                state.x,
                state.y,
                state.size,
                state.size,
                0,
                0,
                canvas.width,
                canvas.height
            );
            xLabel.textContent = (labels.cropX || 'Pozycja X') + ': ' + state.x + ' px';
            yLabel.textContent = (labels.cropY || 'Pozycja Y') + ': ' + state.y + ' px';
            zoomLabel.textContent = (labels.cropZoom || 'Przyblizenie') + ': ' + state.zoom + '%';
        };

        var onInputChange = function () {
            state.x = Math.max(0, parseInt(xInput.value || '0', 10) || 0);
            state.y = Math.max(0, parseInt(yInput.value || '0', 10) || 0);
            state.zoom = Math.max(100, Math.min(parseInt(zoomInput.value || '100', 10), 350));
            render();
        };

        var onCancel = function (event) {
            event.preventDefault();
            closeModal();
        };

        var onSave = function (event) {
            event.preventDefault();
            saveButton.disabled = true;
            saveButton.classList.add('is-busy');

            var formData = new window.FormData();
            formData.append('action', 'eocrm_crop_agent_photo');
            formData.append('nonce', cropNonce);
            formData.append('attachment_id', String(mediaData.id));
            formData.append('crop_x', String(state.x));
            formData.append('crop_y', String(state.y));
            formData.append('crop_size', String(state.size));

            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.attachmentId) {
                        throw new Error((payload && payload.data && payload.data.message) ? String(payload.data.message) : (labels.cropError || 'Nie udalo sie zapisac kadru.'));
                    }

                    closeModal();
                    onSaveSuccess({
                        id: parseInt(String(payload.data.attachmentId), 10),
                        url: String(payload.data.url || '')
                    });
                })
                .catch(function (error) {
                    saveButton.disabled = false;
                    saveButton.classList.remove('is-busy');
                    window.alert(error && error.message ? error.message : (labels.cropError || 'Nie udalo sie zapisac kadru.'));
                });
        };

        cancelButtons.forEach(function (button) {
            button.onclick = onCancel;
        });
        saveButton.onclick = onSave;
        xInput.oninput = onInputChange;
        yInput.oninput = onInputChange;
        zoomInput.oninput = onInputChange;

        image.onload = function () {
            state.imageW = Math.max(1, image.naturalWidth || image.width || 1);
            state.imageH = Math.max(1, image.naturalHeight || image.height || 1);
            state.zoom = 100;
            state.size = Math.min(state.imageW, state.imageH);
            state.x = Math.max(0, Math.floor((state.imageW - state.size) / 2));
            state.y = Math.max(0, Math.floor((state.imageH - state.size) / 2));
            zoomInput.value = '100';
            render();
            modal.classList.add('is-open');
        };

        image.onerror = function () {
            window.alert(labels.cropError || 'Nie udalo sie zaladowac obrazu do kadrowania.');
        };

        image.src = String(mediaData.url);
        return true;
    }

    function bindMediaPickers() {
        var buttons = document.querySelectorAll('[data-eocrm-media]');
        if (!buttons.length || typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            return;
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var inputId = button.getAttribute('data-eocrm-media');
                var previewId = button.getAttribute('data-eocrm-preview');
                var mediaType = button.getAttribute('data-eocrm-media-type') || 'image';
                if (!inputId) {
                    return;
                }

                var input = document.getElementById(inputId);
                var preview = previewId ? document.getElementById(previewId) : null;
                if (!input) {
                    return;
                }

                var frameConfig = {
                    title: mediaType === 'document' ? 'Wybierz dokument' : 'Wybierz obraz',
                    button: { text: mediaType === 'document' ? 'Uzyj dokumentu' : 'Uzyj obrazu' },
                    multiple: false
                };

                if (mediaType !== 'document') {
                    frameConfig.library = { type: 'image' };
                }

                var frame = wp.media(frameConfig);

                frame.on('select', function () {
                    var selection = frame.state().get('selection').first();
                    if (!selection) {
                        return;
                    }

                    var data = selection.toJSON();
                    if (!data || !data.id) {
                        return;
                    }

                    var setSelectedImage = function (id, url) {
                        input.value = String(id);
                        if (preview && url) {
                            if (mediaType === 'document') {
                                var title = data.title || data.filename || ('Dokument #' + String(id));
                                preview.innerHTML = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(title) + '</a>';
                            } else {
                                preview.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Podglad" style="max-width:160px;height:auto;">';
                            }
                        }
                    };

                    var requiresCrop = button.getAttribute('data-eocrm-crop-agent') === '1';
                    if (requiresCrop) {
                        var opened = openAgentCropper(
                            {
                                id: data.id,
                                url: String(data.url || '')
                            },
                            function (cropResult) {
                                if (!cropResult || !cropResult.id) {
                                    return;
                                }
                                setSelectedImage(cropResult.id, String(cropResult.url || data.url || ''));
                            }
                        );

                        if (!opened) {
                            setSelectedImage(data.id, getPreviewUrlFromMedia(data));
                        }
                    } else {
                        setSelectedImage(data.id, getPreviewUrlFromMedia(data));
                    }
                });

                frame.open();
            });
        });
    }

    function bindMediaClearButtons() {
        var buttons = document.querySelectorAll('[data-eocrm-clear-media]');
        if (!buttons.length) {
            return;
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                var inputId = button.getAttribute('data-eocrm-clear-media');
                var previewId = button.getAttribute('data-eocrm-preview');
                var input = inputId ? document.getElementById(inputId) : null;
                var preview = previewId ? document.getElementById(previewId) : null;

                if (input) {
                    input.value = '0';
                }
                if (preview) {
                    preview.innerHTML = '<span>Brak pliku</span>';
                }
            });
        });
    }

    function bindNumberingSettings() {
        var bindAgreementScopeTabs = function () {
            var tabGroups = document.querySelectorAll('[data-eocrm-numbering-agreement-tabs]');
            if (!tabGroups.length) {
                return;
            }

            tabGroups.forEach(function (tabGroup) {
                var root = tabGroup.closest('.eocrm-numbering-box-agreement');
                if (!root) {
                    return;
                }

                var buttons = tabGroup.querySelectorAll('[data-eocrm-numbering-agreement-tab]');
                var panels = root.querySelectorAll('[data-eocrm-numbering-agreement-panel]');
                if (!buttons.length || !panels.length) {
                    return;
                }

                var setActiveScope = function (scopeKey) {
                    panels.forEach(function (panel) {
                        var panelScope = panel.getAttribute('data-eocrm-numbering-agreement-panel') || '';
                        panel.classList.toggle('is-hidden', panelScope !== scopeKey);
                    });

                    buttons.forEach(function (button) {
                        var buttonScope = button.getAttribute('data-eocrm-numbering-agreement-tab') || '';
                        var isActive = buttonScope === scopeKey;
                        button.classList.toggle('button-primary', isActive);
                    });
                };

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var scopeKey = button.getAttribute('data-eocrm-numbering-agreement-tab') || '';
                        if (!scopeKey) {
                            return;
                        }
                        setActiveScope(scopeKey);
                    });
                });

                var firstScope = buttons[0].getAttribute('data-eocrm-numbering-agreement-tab') || '';
                if (firstScope) {
                    setActiveScope(firstScope);
                }
            });
        };

        bindAgreementScopeTabs();

        var boxes = document.querySelectorAll('[data-eocrm-numbering-box]');
        if (!boxes.length) {
            return;
        }

        boxes.forEach(function (box) {
            var partsSelect = box.querySelector('[data-eocrm-numbering-parts]');
            if (!partsSelect) {
                return;
            }

            var refresh = function () {
                var partsCount = parseInt(partsSelect.value || '1', 10);
                if (!(partsCount >= 1)) {
                    partsCount = 1;
                }
                if (partsCount > 3) {
                    partsCount = 3;
                }

                var partRows = box.querySelectorAll('[data-eocrm-numbering-part]');
                partRows.forEach(function (partRow) {
                    var partIndex = parseInt(partRow.getAttribute('data-eocrm-numbering-part') || '0', 10);
                    var isVisiblePart = partIndex > 0 && partIndex <= partsCount;
                    partRow.classList.toggle('is-hidden', !isVisiblePart);
                    if (!isVisiblePart) {
                        return;
                    }

                    var typeSelect = partRow.querySelector('[data-eocrm-numbering-part-type]');
                    var selectedType = typeSelect ? String(typeSelect.value || 'date').toLowerCase() : 'date';
                    if (selectedType !== 'date' && selectedType !== 'digits' && selectedType !== 'custom') {
                        selectedType = 'date';
                    }

                    var typeBlocks = partRow.querySelectorAll('[data-eocrm-numbering-type-block]');
                    typeBlocks.forEach(function (typeBlock) {
                        var typeKey = String(typeBlock.getAttribute('data-eocrm-numbering-type-block') || '');
                        var separatorPosition = typeKey.indexOf(':');
                        var blockType = separatorPosition >= 0 ? typeKey.substring(separatorPosition + 1) : '';
                        var isBlockVisible = blockType === selectedType;
                        typeBlock.classList.toggle('is-hidden', !isBlockVisible);
                    });
                });
            };

            box.addEventListener('change', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                if (target.matches('[data-eocrm-numbering-parts], [data-eocrm-numbering-part-type]')) {
                    refresh();
                }
            });

            refresh();
        });
    }

    function bindPropertyCustomFieldsBuilder() {
        var builders = document.querySelectorAll('[data-eocrm-custom-fields-builder]');
        if (!builders.length) {
            return;
        }

        builders.forEach(function (builder) {
            var rowsContainer = builder.querySelector('[data-eocrm-custom-fields-rows]');
            var addButton = builder.querySelector('[data-eocrm-custom-field-add]');
            var template = builder.querySelector('[data-eocrm-custom-field-template]');
            if (!rowsContainer || !addButton || !template) {
                return;
            }

            var nextIndex = 0;
            rowsContainer.querySelectorAll('[data-eocrm-custom-field-row]').forEach(function (row) {
                var indexValue = parseInt(row.getAttribute('data-eocrm-custom-field-row') || '0', 10);
                if (indexValue >= nextIndex) {
                    nextIndex = indexValue + 1;
                }
            });

            var addRow = function () {
                var html = String(template.innerHTML || '');
                if (!html) {
                    return;
                }

                html = html.replace(/__INDEX__/g, String(nextIndex));
                nextIndex += 1;
                rowsContainer.insertAdjacentHTML('beforeend', html);
            };

            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                addRow();
            });

            rowsContainer.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var removeButton = target.closest('[data-eocrm-custom-field-remove]');
                if (!removeButton) {
                    return;
                }

                event.preventDefault();
                var row = removeButton.closest('[data-eocrm-custom-field-row]');
                if (row) {
                    row.remove();
                }

                if (!rowsContainer.querySelector('[data-eocrm-custom-field-row]')) {
                    addRow();
                }
            });
        });
    }

    function bindPortalExportSettings() {
        var providerSelect = document.querySelector('[data-eocrm-portal-provider-select]');
        if (!providerSelect) {
            return;
        }

        var panels = document.querySelectorAll('[data-eocrm-portal-provider-panel]');
        if (!panels.length) {
            return;
        }

        var refreshPanels = function () {
            var selectedProvider = String(providerSelect.value || '');
            panels.forEach(function (panel) {
                var panelProvider = String(panel.getAttribute('data-eocrm-portal-provider-panel') || '');
                panel.classList.toggle('is-hidden', panelProvider !== selectedProvider);
            });
        };

        providerSelect.addEventListener('change', refreshPanels);
        refreshPanels();
    }

    function bindOnboardingLinks() {
        var builders = document.querySelectorAll('[data-eocrm-onboarding-links]');
        if (!builders.length) {
            return;
        }

        builders.forEach(function (builder) {
            var rowsContainer = builder.querySelector('[data-eocrm-onboarding-link-rows]');
            var addButton = builder.querySelector('[data-eocrm-onboarding-add-link]');
            var template = builder.querySelector('[data-eocrm-onboarding-link-template]');
            if (!rowsContainer || !addButton || !template) {
                return;
            }

            var nextIndex = 0;
            rowsContainer.querySelectorAll('[data-eocrm-onboarding-link-row]').forEach(function (row) {
                var indexValue = parseInt(row.getAttribute('data-eocrm-onboarding-link-row') || '0', 10);
                if (indexValue >= nextIndex) {
                    nextIndex = indexValue + 1;
                }
            });

            var addRow = function () {
                var html = String(template.innerHTML || '');
                if (!html) {
                    return;
                }

                html = html.replace(/__INDEX__/g, String(nextIndex));
                nextIndex += 1;
                rowsContainer.insertAdjacentHTML('beforeend', html);
            };

            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                addRow();
            });

            rowsContainer.addEventListener('click', function (event) {
                var target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                var removeButton = target.closest('[data-eocrm-onboarding-remove-link]');
                if (!removeButton) {
                    return;
                }

                event.preventDefault();
                var row = removeButton.closest('[data-eocrm-onboarding-link-row]');
                if (row) {
                    row.remove();
                }

                if (!rowsContainer.querySelector('[data-eocrm-onboarding-link-row]')) {
                    addRow();
                }
            });
        });
    }

    function bindDeveloperAccessSettings() {
        var root = document.querySelector('[data-eocrm-dev-access]');
        if (!root) {
            return;
        }

        var toggle = root.querySelector('[data-eocrm-dev-access-toggle]');
        var passwordBox = root.querySelector('[data-eocrm-dev-access-password]');
        if (!toggle || !passwordBox) {
            return;
        }

        var refresh = function () {
            var isEnabled = !!toggle.checked;
            passwordBox.classList.toggle('is-hidden', !isEnabled);

            var label = toggle.closest('.eocrm-dev-access-toggle');
            if (label) {
                label.classList.toggle('is-active', isEnabled);
                var text = label.querySelector('span');
                if (text) {
                    text.textContent = isEnabled ? 'Aktywny' : 'Wlacz dostep';
                }
            }
        };

        toggle.addEventListener('change', refresh);
        refresh();
    }

    $(document).ready(function () {
        bindMediaPickers();
        bindMediaClearButtons();
        bindNumberingSettings();
        bindPropertyCustomFieldsBuilder();
        bindPortalExportSettings();
        bindOnboardingLinks();
        bindDeveloperAccessSettings();
    });
})(jQuery);
