(function () {
    if (typeof window === 'undefined') {
        return;
    }

    const config = window.estateOfficeCalculators || {};
    const thresholds = config.thresholds || [];
    const vatRate = Number(config.vatRate || 0);
    const pccRate = Number(config.pccRate || 0);
    const landFee = Number(config.landFee || 0);
    const extractsFee = Number(config.extractsFee || 0);
    const locale = (config.locale || 'pl-PL').replace('_', '-');
    const currency = config.currency || 'PLN';

    const formatCurrency = (value) => {
        const number = Number.isFinite(value) ? value : 0;

        try {
            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency,
                minimumFractionDigits: 2,
            }).format(number);
        } catch (error) {
            return `${number.toFixed(2)} ${currency}`;
        }
    };

    const findThreshold = (price) => {
        for (const threshold of thresholds) {
            const min = Number(threshold.min || 0);
            const max = Object.prototype.hasOwnProperty.call(threshold, 'max') ? Number(threshold.max) : null;

            if (price < min) {
                continue;
            }

            if (max !== null && price > max) {
                continue;
            }

            return threshold;
        }

        return thresholds[thresholds.length - 1] || null;
    };

    const calculateNotary = (price, transaction) => {
        const value = Math.max(0, Number(price) || 0);
        const threshold = findThreshold(value);
        let base = 100;

        if (threshold) {
            const min = Number(threshold.min || 0);
            const percent = Number(threshold.percent || 0);
            base = Number(threshold.base || 0) + Math.max(0, (value - min) * percent);

            if (Object.prototype.hasOwnProperty.call(threshold, 'cap')) {
                base = Math.min(base, Number(threshold.cap));
            }
        }

        const vat = base * vatRate;
        const pccApplicable = ['SPRZEDAŻ', 'KUPNO'].includes(String(transaction || '').toUpperCase());
        const pcc = pccApplicable ? value * pccRate : 0;
        const gross = base + vat;
        const total = gross + pcc + landFee + extractsFee;

        return {
            base,
            vat,
            gross,
            pcc,
            land: landFee,
            extracts: extractsFee,
            total,
        };
    };

    const calculateMortgage = (price, downPayment, interest, years) => {
        const propertyPrice = Math.max(0, Number(price) || 0);
        const down = Math.min(Math.max(0, Number(downPayment) || 0), propertyPrice);
        const principal = Math.max(0, propertyPrice - down);
        const months = Math.max(1, Math.round(Number(years) || 0) * 12);
        const rate = Math.max(0, Number(interest) || 0) / 100 / 12;

        let payment;

        if (rate > 0) {
            const factor = Math.pow(1 + rate, -months);
            payment = principal * (rate / (1 - factor));
        } else {
            payment = principal / months;
        }

        const totalPayment = payment * months;
        const totalInterest = Math.max(0, totalPayment - principal);

        return {
            principal,
            payment,
            totalPayment,
            totalInterest,
        };
    };

    const updateResultRows = (container, values) => {
        if (!container) {
            return;
        }

        const rows = container.querySelectorAll('[data-calculator-row]');

        rows.forEach((row) => {
            const key = row.getAttribute('data-calculator-row');
            const valueElement = row.querySelector('[data-calculator-value]');

            if (!valueElement || !key) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(values, key)) {
                valueElement.textContent = formatCurrency(values[key]);
            }
        });
    };

    const bindNotaryCalculator = (root) => {
        const priceInput = root.querySelector('[data-calculator-input="price"]');
        const transactionInput = root.querySelector('[data-calculator-input="transaction"]');
        const results = root.querySelector('[data-calculator-results]');

        const render = () => {
            const value = calculateNotary(priceInput.value, transactionInput.value);

            updateResultRows(results, {
                base_fee: value.base,
                vat: value.vat,
                gross_notary: value.gross,
                pcc: value.pcc,
                land_register: value.land,
                extracts: value.extracts,
                total: value.total,
            });
        };

        [priceInput, transactionInput].forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('input', render);
            element.addEventListener('change', render);
        });

        render();
    };

    const bindMortgageCalculator = (root) => {
        const priceInput = root.querySelector('[data-calculator-input="price"]');
        const downInput = root.querySelector('[data-calculator-input="down_payment"]');
        const interestInput = root.querySelector('[data-calculator-input="interest"]');
        const yearsInput = root.querySelector('[data-calculator-input="years"]');
        const results = root.querySelector('[data-calculator-results]');

        const render = () => {
            const value = calculateMortgage(
                priceInput.value,
                downInput.value,
                interestInput.value,
                yearsInput.value
            );

            updateResultRows(results, {
                principal: value.principal,
                monthly_payment: value.payment,
                total_payment: value.totalPayment,
                total_interest: value.totalInterest,
            });
        };

        [priceInput, downInput, interestInput, yearsInput].forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('input', render);
            element.addEventListener('change', render);
        });

        render();
    };

    document.addEventListener('DOMContentLoaded', () => {
        document
            .querySelectorAll('.estate-office-calculator--notary')
            .forEach((calculator) => bindNotaryCalculator(calculator));

        document
            .querySelectorAll('.estate-office-calculator--mortgage')
            .forEach((calculator) => bindMortgageCalculator(calculator));
    });
})();
