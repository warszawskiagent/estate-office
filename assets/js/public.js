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

    $(document).on('click', '.estate-office-table tr', function (event) {
        var $link = $(this).find('a').first();
        if ($link.length && !$(event.target).is('a, button, input, textarea')) {
            window.location = $link.attr('href');
        }
    });

    $(function () {
        initCalculators();
    });
})(jQuery);
