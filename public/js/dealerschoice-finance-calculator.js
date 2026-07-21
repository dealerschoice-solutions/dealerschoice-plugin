/**
 * DealersChoice Finance Calculator
 *
 * Client-side loan payment calculator shared by two markup variants:
 * - "full"  (.dc-finance-calc[data-calc-variant="full"])  - the generic,
 *   user-insertable [dealerschoice_finance_calculator] shortcode.
 * - "quick" (.dc-finance-calc[data-calc-variant="quick"]) - the compact
 *   calculator auto-rendered on single-boat.php.
 *
 * No AJAX/server round-trip - all math happens in the browser. Calculation
 * only runs on an explicit "Calculate" button click (no live/on-input
 * recalculation), per product requirements.
 */
(function ($) {
    'use strict';

    function l10n(key, fallback) {
        var strings = window.dcFinanceCalcL10n || {};
        return strings[key] || fallback;
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        $('.dc-finance-calc').each(function () {
            initInstance($(this));
        });
    }

    function initInstance($wrap) {
        var variant = $wrap.data('calc-variant');
        var $form = $wrap.find('form.dc-finance-calc-form');

        $form.on('submit', function (e) {
            e.preventDefault();
            handleCalculate($wrap, variant);
        });

        $form.on('change', 'select[name="loan_term"]', function () {
            toggleOtherTermField($wrap, $(this).val() === 'other');
        });

        if (variant === 'full') {
            var downPaymentTouched = false;
            var $amount = $wrap.find('[name="amount_financed"]');
            var $downPayment = $wrap.find('[name="down_payment"]');

            $downPayment.on('input', function () {
                downPaymentTouched = true;
            });

            $amount.on('input', function () {
                if (downPaymentTouched) {
                    return;
                }
                var percent = parseFloat($wrap.data('default-down-payment-percent'));
                var amount = parseFloat($amount.val());
                if (isNaN(percent) || isNaN(amount) || amount < 0) {
                    return;
                }
                $downPayment.val(roundToCents(amount * percent / 100));
            });
        }
    }

    // ── "Other" term reveal ─────────────────────────────────────────────────

    function toggleOtherTermField($wrap, show) {
        var $otherWrap = $wrap.find('.dc-finance-other-term');
        var $otherInput = $otherWrap.find('input');

        $otherWrap.prop('hidden', !show);
        $otherInput.prop('required', show);

        if (show) {
            $otherInput.trigger('focus');
        } else {
            $otherInput.val('');
            clearFieldError($otherInput);
        }
    }

    // ── Field error helpers ──────────────────────────────────────────────────

    function setFieldError($input, message) {
        var $error = $input.closest('.dc-field').find('.dc-field-error');
        $error.text(message).prop('hidden', false);
        $input.attr('aria-invalid', 'true');
    }

    function clearFieldError($input) {
        var $error = $input.closest('.dc-field').find('.dc-field-error');
        $error.text('').prop('hidden', true);
        $input.removeAttr('aria-invalid');
    }

    // ── Validation ───────────────────────────────────────────────────────────

    function validateForm($wrap, variant) {
        var firstInvalid = null;

        function fail($input, message) {
            setFieldError($input, message);
            if (!firstInvalid) {
                firstInvalid = $input;
            }
        }

        var amount;
        if (variant === 'full') {
            var $amount = $wrap.find('[name="amount_financed"]');
            clearFieldError($amount);
            amount = parseFloat($amount.val());
            if ($amount.val() === '' || isNaN(amount)) {
                fail($amount, l10n('requiredField', 'This field is required.'));
            } else if (amount <= 0) {
                fail($amount, l10n('invalidNumber', 'Please enter a valid number.'));
            }
        } else {
            amount = parseFloat($wrap.data('price'));
        }

        var $rate = $wrap.find('[name="interest_rate"]');
        clearFieldError($rate);
        var rate = parseFloat($rate.val());
        if ($rate.val() === '' || isNaN(rate)) {
            fail($rate, l10n('requiredField', 'This field is required.'));
        } else if (rate < 0 || rate > 30) {
            fail($rate, l10n('rateRange', 'Interest rate must be between 0 and 30.'));
        }

        var $term = $wrap.find('[name="loan_term"]');
        var $termOther = $wrap.find('[name="loan_term_other"]');
        var term;
        if ($term.val() === 'other') {
            clearFieldError($termOther);
            term = parseInt($termOther.val(), 10);
            if ($termOther.val() === '' || isNaN(term)) {
                fail($termOther, l10n('requiredField', 'This field is required.'));
            } else if (term < 1 || term > 600) {
                fail($termOther, l10n('termRange', 'Loan term must be between 1 and 600 months.'));
            }
        } else {
            term = parseInt($term.val(), 10);
        }

        var $downPayment = $wrap.find('[name="down_payment"]');
        clearFieldError($downPayment);
        var downPayment = $downPayment.val() === '' ? 0 : parseFloat($downPayment.val());
        if (isNaN(downPayment) || downPayment < 0) {
            fail($downPayment, l10n('invalidNumber', 'Please enter a valid number.'));
        } else if (!isNaN(amount) && downPayment >= amount) {
            fail($downPayment, l10n('downPaymentTooHigh', 'Down payment must be less than the amount financed.'));
        }

        if (firstInvalid) {
            firstInvalid.trigger('focus');
            return null;
        }

        return {
            amount: amount,
            rate: rate,
            term: term,
            downPayment: downPayment
        };
    }

    // ── Calculate flow ───────────────────────────────────────────────────────

    function handleCalculate($wrap, variant) {
        var values = validateForm($wrap, variant);
        if (!values) {
            return;
        }

        var principal = Math.max(values.amount - values.downPayment, 0);
        var result = computeAmortization(principal, values.rate, values.term);
        result.amountFinanced = values.amount;

        if (variant === 'full') {
            renderFullResult($wrap, result);
        } else {
            renderQuickResult($wrap, result);
        }
    }

    // ── Amortization math ───────────────────────────────────────────────────

    function roundToCents(value) {
        return Math.round(value * 100) / 100;
    }

    function computeAmortization(principal, annualRatePct, termMonths) {
        var i = (annualRatePct / 100) / 12;
        var n = termMonths;
        var monthlyPayment;

        if (i === 0) {
            monthlyPayment = principal / n;
        } else {
            var factor = Math.pow(1 + i, n);
            monthlyPayment = principal * (i * factor) / (factor - 1);
        }
        monthlyPayment = roundToCents(monthlyPayment);

        var schedule = [];
        var balance = principal;
        var totalInterest = 0;

        for (var month = 1; month <= n; month++) {
            var interestPortion = roundToCents(i === 0 ? 0 : balance * i);
            var principalPortion = roundToCents(monthlyPayment - interestPortion);
            var paymentThisMonth = monthlyPayment;

            // Final payment absorbs any accumulated rounding drift so the
            // schedule always sums exactly and the balance ends at $0.00.
            if (month === n) {
                principalPortion = balance;
                paymentThisMonth = roundToCents(principalPortion + interestPortion);
            }

            balance = roundToCents(balance - principalPortion);
            totalInterest += interestPortion;

            schedule.push({
                month: month,
                payment: paymentThisMonth,
                principal: principalPortion,
                interest: interestPortion,
                balance: Math.max(balance, 0)
            });
        }

        return {
            monthlyPayment: monthlyPayment,
            totalInterest: roundToCents(totalInterest),
            totalCost: roundToCents(principal + totalInterest),
            schedule: schedule
        };
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    function formatCurrency(value) {
        var num = roundToCents(value || 0);
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderFullResult($wrap, result) {
        var $resultRegion = $wrap.find('.dc-finance-calc-result');

        var html = '<p class="dc-finance-headline">'
            + l10n('resultHeadline', 'Estimated Monthly Payment:') + ' '
            + '<strong>' + formatCurrency(result.monthlyPayment) + '</strong></p>'
            + '<ul class="dc-finance-totals">'
            + '<li>' + l10n('amountFinancedLabel', 'Amount Financed:') + ' ' + formatCurrency(result.amountFinanced) + '</li>'
            + '<li>' + l10n('totalInterestLabel', 'Total Interest:') + ' ' + formatCurrency(result.totalInterest) + '</li>'
            + '<li>' + l10n('totalCostLabel', 'Total Cost of Loan:') + ' ' + formatCurrency(result.totalCost) + '</li>'
            + '</ul>';

        $resultRegion.html(html);

        var $details = $wrap.find('.dc-finance-schedule-details');
        $details.find('.dc-finance-schedule-table-wrap').html(buildScheduleTable(result.schedule));
        $details.prop('hidden', false);
    }

    function renderQuickResult($wrap, result) {
        $wrap.find('.dc-finance-calc-result').html(
            '<p>' + l10n('quickResultLabel', 'Your estimated monthly payment:') + ' '
            + '<strong>' + formatCurrency(result.monthlyPayment) + '</strong></p>'
        );
    }

    function buildScheduleTable(schedule) {
        var rows = schedule.map(function (row) {
            return '<tr>'
                + '<td>' + row.month + '</td>'
                + '<td>' + formatCurrency(row.payment) + '</td>'
                + '<td>' + formatCurrency(row.principal) + '</td>'
                + '<td>' + formatCurrency(row.interest) + '</td>'
                + '<td>' + formatCurrency(row.balance) + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="dc-finance-schedule-scroll">'
            + '<table class="dc-finance-schedule-table">'
            + '<caption class="dc-screen-reader-text">' + l10n('scheduleCaption', 'Full amortization schedule') + '</caption>'
            + '<thead><tr>'
            + '<th scope="col">' + l10n('columnMonth', 'Month') + '</th>'
            + '<th scope="col">' + l10n('columnPayment', 'Payment') + '</th>'
            + '<th scope="col">' + l10n('columnPrincipal', 'Principal') + '</th>'
            + '<th scope="col">' + l10n('columnInterest', 'Interest') + '</th>'
            + '<th scope="col">' + l10n('columnBalance', 'Remaining Balance') + '</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table>'
            + '</div>';
    }

    $(document).ready(init);

}(jQuery));
