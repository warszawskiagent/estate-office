(function ($) {
    'use strict';

    function formatCurrency(value) {
        var amount = isFinite(value) ? value : 0;
        if (window.Intl && Intl.NumberFormat) {
            return new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' PLN';
        }
        return (Math.round(amount * 100) / 100).toFixed(2) + ' PLN';
    }

    function formatPercent(value) {
        var amount = isFinite(value) ? value : 0;
        return amount.toFixed(1).replace('.', ',') + '%';
    }

    function parseNumber(raw) {
        if (typeof raw === 'string') {
            raw = raw.replace(/\s+/g, '').replace(',', '.');
        }
        var value = parseFloat(raw);
        return isNaN(value) ? 0 : value;
    }

    function calculateNotaryNet(price) {
        var amount = Math.max(price, 0);
        var fee = 0;

        if (amount <= 3000) {
            fee = 100;
        } else if (amount <= 10000) {
            fee = 100 + 0.03 * (amount - 3000);
        } else if (amount <= 30000) {
            fee = 310 + 0.02 * (amount - 10000);
        } else if (amount <= 60000) {
            fee = 710 + 0.01 * (amount - 30000);
        } else if (amount <= 1000000) {
            fee = 1010 + 0.004 * (amount - 60000);
        } else if (amount <= 2000000) {
            fee = 4770 + 0.002 * (amount - 1000000);
        } else {
            fee = 6770 + 0.0025 * (amount - 2000000);
        }

        return Math.min(fee, 10000);
    }

    function updateNotaryCalculator($calculator) {
        var $form = $calculator.find('form[data-calculator="notary"]');
        if (!$form.length) {
            return;
        }

        var price = parseNumber($form.find('input[name="price"]').val());
        var market = $form.find('select[name="market"]').val();
        var mortgage = parseNumber($form.find('input[name="mortgage"]').val());
        var copies = parseInt($form.find('input[name="copies"]').val(), 10);
        if (isNaN(copies) || copies < 1) {
            copies = 1;
        }

        var includeRegistry = $form.find('input[name="registry"]').is(':checked');
        var includeHypothec = $form.find('input[name="hypothec"]').is(':checked');

        var feeNet = calculateNotaryNet(price);
        var vat = feeNet * 0.23;
        var feeTotal = feeNet + vat;
        var pcc = market === 'secondary' ? price * 0.02 : 0;
        var registry = includeRegistry ? 200 : 0;
        var mortgageFee = (includeHypothec && mortgage > 0) ? 200 : 0;
        var copiesCost = copies * 6;
        var total = feeTotal + pcc + registry + mortgageFee + copiesCost;

        $calculator.find('[data-eo-result="notary-fee-net"]').text(formatCurrency(feeNet));
        $calculator.find('[data-eo-result="notary-fee-vat"]').text(formatCurrency(vat));
        $calculator.find('[data-eo-result="notary-fee-total"]').text(formatCurrency(feeTotal));
        $calculator.find('[data-eo-result="notary-pcc"]').text(formatCurrency(pcc));
        $calculator.find('[data-eo-result="notary-registry"]').text(formatCurrency(registry));
        $calculator.find('[data-eo-result="notary-mortgage"]').text(formatCurrency(mortgageFee));
        $calculator.find('[data-eo-result="notary-copies"]').text(formatCurrency(copiesCost));
        $calculator.find('[data-eo-result="notary-total"]').text(formatCurrency(total));
    }

    function updateMortgageCalculator($calculator) {
        var $form = $calculator.find('form[data-calculator="mortgage"]');
        if (!$form.length) {
            return;
        }

        var price = parseNumber($form.find('input[name="price"]').val());
        var downPayment = parseNumber($form.find('input[name="down_payment"]').val());
        var customLoan = parseNumber($form.find('input[name="loan"]').val());
        var interestRate = parseNumber($form.find('input[name="interest"]').val());
        var years = Math.max(parseNumber($form.find('input[name="years"]').val()), 1);
        var commissionPercent = Math.max(parseNumber($form.find('input[name="commission"]').val()), 0);
        var installmentType = $form.find('select[name="installment_type"]').val();

        var months = Math.max(Math.round(years * 12), 1);
        var loanAmount = customLoan > 0 ? customLoan : Math.max(price - downPayment, 0);
        var monthlyRate = Math.max(interestRate, 0) / 100 / 12;
        var monthlyPayment = 0;
        var totalInterest = 0;

        if (loanAmount > 0) {
            if (monthlyRate === 0) {
                monthlyPayment = loanAmount / months;
                totalInterest = 0;
            } else if (installmentType === 'decreasing') {
                var principalPart = loanAmount / months;
                var firstInterest = loanAmount * monthlyRate;
                monthlyPayment = principalPart + firstInterest;
                totalInterest = loanAmount * monthlyRate * (months + 1) / 2;
            } else {
                var pow = Math.pow(1 + monthlyRate, months);
                monthlyPayment = loanAmount * monthlyRate * pow / (pow - 1);
                totalInterest = monthlyPayment * months - loanAmount;
            }
        }

        var commissionValue = loanAmount * commissionPercent / 100;
        var totalPayment = loanAmount + totalInterest + commissionValue;
        var incomeRequired = monthlyPayment > 0 ? monthlyPayment / 0.4 : 0;
        var downPercent = price > 0 ? (downPayment / price) * 100 : 0;

        $calculator.find('[data-eo-result="mortgage-loan"]').text(formatCurrency(loanAmount));
        $calculator.find('[data-eo-result="mortgage-monthly"]').text(formatCurrency(monthlyPayment));
        $calculator.find('[data-eo-result="mortgage-commission"]').text(formatCurrency(commissionValue));
        $calculator.find('[data-eo-result="mortgage-interest"]').text(formatCurrency(totalInterest));
        $calculator.find('[data-eo-result="mortgage-total"]').text(formatCurrency(totalPayment));
        $calculator.find('[data-eo-result="mortgage-income"]').text(formatCurrency(incomeRequired));
        $calculator.find('[data-eo-result="mortgage-down-percent"]').text(formatPercent(downPercent));
    }

    function initCalculators() {
        $('.estate-office-calculator-form[data-calculator="notary"]').each(function () {
            var $calc = $(this).closest('.estate-office-calculator');
            $(this).on('input change', 'input, select', function () {
                updateNotaryCalculator($calc);
            });
            updateNotaryCalculator($calc);
        });

        $('.estate-office-calculator-form[data-calculator="mortgage"]').each(function () {
            var $calc = $(this).closest('.estate-office-calculator');
            $(this).on('input change', 'input, select', function () {
                updateMortgageCalculator($calc);
            });
            updateMortgageCalculator($calc);
        });
    }

    function initReports() {
        var container = document.querySelector('[data-estate-office-reports]');
        if (!container || container.dataset.initialized) {
            return;
        }
        container.dataset.initialized = '1';

        if (typeof Chart === 'undefined') {
            return;
        }

        var payload = container.getAttribute('data-estate-office-reports');
        if (!payload) {
            return;
        }

        var data;
        try {
            data = JSON.parse(payload);
        } catch (error) {
            return;
        }

        if (!data || typeof data !== 'object' || !data.charts) {
            return;
        }

        var renderLegend = function (card, config) {
            if (!card) {
                return;
            }
            var existing = card.querySelector('.estate-office-report-legend');
            if (existing) {
                existing.remove();
            }
            var labels = config.labels || [];
            var datasets = config.datasets || [];
            if (!labels.length || !datasets.length) {
                return;
            }
            var dataset = datasets[0];
            var values = Array.isArray(dataset.data) ? dataset.data : [];
            var colors = dataset.backgroundColor || dataset.borderColor || '#2563eb';
            var list = document.createElement('ul');
            list.className = 'estate-office-report-legend';
            labels.forEach(function (label, index) {
                var item = document.createElement('li');
                var swatch = document.createElement('span');
                swatch.className = 'estate-office-report-legend__swatch';
                swatch.style.backgroundColor = Array.isArray(colors) ? (colors[index % colors.length] || '#2563eb') : colors;
                var text = document.createElement('span');
                var value = typeof values[index] !== 'undefined' ? values[index] : '';
                text.textContent = value !== '' ? label + ' (' + value + ')' : String(label);
                item.appendChild(swatch);
                item.appendChild(text);
                list.appendChild(item);
            });
            card.appendChild(list);
        };

        Object.keys(data.charts).forEach(function (key) {
            var config = data.charts[key];
            if (!config || typeof config !== 'object') {
                return;
            }

            var canvas = container.querySelector('canvas[data-report="' + key + '"]');
            if (!canvas) {
                return;
            }

            var card = canvas.closest('.estate-office-report-card');

            var context = canvas.getContext('2d');
            if (!context) {
                return;
            }

            var datasets = (config.datasets || []).map(function (dataset) {
                var defaults = {
                    borderWidth: 2,
                    tension: 0.3,
                    fill: config.type === 'line',
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: '#2563eb'
                };
                return $.extend({}, defaults, dataset || {});
            });

            var options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: datasets.length > 0
                    },
                    title: {
                        display: !!config.title,
                        text: config.title || ''
                    }
                }
            };

            if (config.type !== 'doughnut' && config.type !== 'pie') {
                options.scales = {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                };
            }

            var chartInstance = new Chart(context, {
                type: config.type || 'bar',
                data: {
                    labels: config.labels || [],
                    datasets: datasets
                },
                options: options
            });

            if (chartInstance && card) {
                renderLegend(card, config);
                card.classList.add('is-ready');
            }
        });
    }

    function formatRangeLabel(min, max) {
        if (!min && !max) {
            return '';
        }
        if (min && max) {
            return formatCurrency(min) + ' – ' + formatCurrency(max);
        }
        if (min) {
            return window.estateOfficeFiltersLabels && window.estateOfficeFiltersLabels.from ? window.estateOfficeFiltersLabels.from + ' ' + formatCurrency(min) : 'Od ' + formatCurrency(min);
        }
        return (window.estateOfficeFiltersLabels && window.estateOfficeFiltersLabels.to ? window.estateOfficeFiltersLabels.to : 'Do') + ' ' + formatCurrency(max);
    }

    function initFilters() {
        $('.estate-office-filters').each(function () {
            var $form = $(this);
            var $min = $form.find('[data-filter-min]');
            var $max = $form.find('[data-filter-max]');
            var $display = $form.find('[data-filter-display]');

            function refreshRange() {
                var minValue = parseNumber($min.val());
                var maxValue = parseNumber($max.val());
                if (!$display.length) {
                    return;
                }

                var label = formatRangeLabel(minValue > 0 ? minValue : 0, maxValue > 0 ? maxValue : 0);
                $display.text(label);
            }

            refreshRange();

            $form.on('input change', '[data-filter-min], [data-filter-max]', function () {
                refreshRange();
            });

            $form.on('click', '[data-filter-reset]', function (event) {
                event.preventDefault();
                $form.find('select').each(function () {
                    this.selectedIndex = 0;
                });
                if ($min.length) {
                    $min.val('');
                }
                if ($max.length) {
                    $max.val('');
                }
                var $page = $form.find('input[name="eo_page"]');
                if ($page.length) {
                    $page.val('1');
                }
                $form.trigger('submit');
            });

            $form.on('change', 'select[data-auto-submit="1"]', function () {
                var $page = $form.find('input[name="eo_page"]');
                if ($page.length) {
                    $page.val('1');
                }
                $form.trigger('submit');
            });
        });
    }

    function populateMapDataset() {
        if (!window.estateOfficeOfferMap || typeof window.estateOfficeOfferMap !== 'object') {
            return;
        }

        var container = document.getElementById('estate-office-offer-map');
        if (!container) {
            return;
        }

        if (!container.getAttribute('data-lat') && typeof window.estateOfficeOfferMap.lat !== 'undefined') {
            container.setAttribute('data-lat', window.estateOfficeOfferMap.lat);
        }
        if (!container.getAttribute('data-lng') && typeof window.estateOfficeOfferMap.lng !== 'undefined') {
            container.setAttribute('data-lng', window.estateOfficeOfferMap.lng);
        }
        if (!container.getAttribute('data-title') && window.estateOfficeOfferMap.title) {
            container.setAttribute('data-title', window.estateOfficeOfferMap.title);
        }
        if (!container.getAttribute('data-zoom') && typeof window.estateOfficeOfferMap.zoom !== 'undefined') {
            container.setAttribute('data-zoom', window.estateOfficeOfferMap.zoom);
        }
    }

    function renderOfferMap() {
        var container = document.getElementById('estate-office-offer-map');
        if (!container || container.dataset.initialized) {
            return;
        }

        var lat = parseFloat(container.getAttribute('data-lat'));
        var lng = parseFloat(container.getAttribute('data-lng'));
        if (!isFinite(lat) || !isFinite(lng)) {
            return;
        }

        if (!(window.google && window.google.maps)) {
            return;
        }

        container.dataset.initialized = '1';

        var center = { lat: lat, lng: lng };
        var zoom = parseInt(container.getAttribute('data-zoom'), 10);
        if (!isFinite(zoom)) {
            zoom = 15;
        }

        var map = new google.maps.Map(container, {
            zoom: zoom,
            center: center,
            disableDefaultUI: true
        });

        var title = container.getAttribute('data-title') || '';
        new google.maps.Marker({
            position: center,
            map: map,
            title: title
        });
    }

    window.EstateOfficeOfferMapInit = function () {
        populateMapDataset();
        renderOfferMap();
    };

    function maybeRenderOfferMap() {
        populateMapDataset();
        if (window.google && window.google.maps) {
            renderOfferMap();
        }
    }

    $(document).on('click', '.estate-office-table tr', function (event) {
        var $link = $(this).find('a').first();
        if ($link.length && !$(event.target).is('a, button, input, textarea')) {
            window.location = $link.attr('href');
        }
    });

    $(function () {
        initCalculators();
        initFilters();
        initReports();
        maybeRenderOfferMap();
    });

    window.addEventListener('load', maybeRenderOfferMap);
    window.addEventListener('load', initReports);
})(jQuery);
