/**
 * Boat quiz step controller.
 *
 * Navigates quiz steps, tracks answers, posts them to the
 * dealerschoice_quiz_results AJAX action, and renders the returned HTML.
 *
 * @package DealersChoice
 */

(function ($) {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────

    var state = {
        currentStep: 1,
        totalSteps: 0,
        answers: {}      // { activity: 'cruising', crew: 'small', ... }
    };

    // ── Session storage ───────────────────────────────────────────────────────
    // Stores answers and result HTML so returning to the page restores the results.

    var SESSION_KEY = 'dc_boat_quiz';

    function saveSession(answers, resultHtml) {
        try {
            sessionStorage.setItem( SESSION_KEY, JSON.stringify({
                answers:    answers,
                resultHtml: resultHtml
            }) );
        } catch (e) { /* Ignore unavailable or full sessionStorage. */ }
    }

    function loadSession() {
        try {
            var raw = sessionStorage.getItem( SESSION_KEY );
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function clearSession() {
        try { sessionStorage.removeItem( SESSION_KEY ); } catch (e) {}
    }

    // ── Cache ────────────────────────────────────────────────────────────────

    var $wrap, $form, $result, $progressFill;

    // Server-rendered submit button markup, captured before the loading state replaces it.
    var submitBtnHtml = '';

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        $wrap        = $('#dc-boat-quiz');
        $form        = $('#dc-quiz-form', $wrap);
        $result      = $('#dc-quiz-result', $wrap);
        $progressFill = $('#dc-quiz-progress-fill', $wrap);

        if ( ! $wrap.length ) {
            return;
        }

        state.totalSteps = parseInt( $wrap.data('total-steps'), 10 ) || 0;

        submitBtnHtml = $wrap.find('#dc-quiz-submit-btn').html() || '';

        // Delegated events on the wrapper.
        $wrap.on('change', 'input[type="radio"]', onOptionChange);
        $wrap.on('click', '[data-action="next"]',   onNext);
        $wrap.on('click', '[data-action="back"]',   onBack);
        $wrap.on('click', '[data-action="submit"]', onSubmit);

        // "Start Over" button inside the AJAX-rendered result HTML.
        $wrap.on('click', '#dc-quiz-retake-btn', onRetake);

        // Restore saved results from the session.
        var session = loadSession();
        if ( session && session.resultHtml ) {
            state.answers = session.answers || {};
            showResult( session.resultHtml );
            return;
        }

        updateProgress();
    }

    // ── Option selection ──────────────────────────────────────────────────────

    function onOptionChange() {
        var $radio    = $(this);
        var question  = $radio.closest('.dc-quiz-step').data('question');
        var value     = $radio.val();

        $radio.closest('.dc-quiz-grid').find('.dc-quiz-option').removeClass('selected');
        $radio.closest('.dc-quiz-option').addClass('selected');

        state.answers[question] = value;

        // Re-filter the priorities step when the activity changes.
        if ( question === 'activity' ) {
            filterConditionalPriorities( value );
        }

        $radio.closest('.dc-quiz-step').find('[data-action="next"], [data-action="submit"]').prop('disabled', false);
    }

    // ── Conditional priorities filter ────────────────────────────────────────

    /**
     * Shows only the priority option cards for the given activity group.
     *
     * Clears the priorities answer and disables Next when the selected card is hidden.
     *
     * @param {string} activityValue Current answer to the activity question.
     */
    function filterConditionalPriorities( activityValue ) {
        var $prioritiesStep = $wrap.find('.dc-quiz-step[data-question="priorities"]');
        if ( ! $prioritiesStep.length ) {
            return;
        }

        var $allConditional = $prioritiesStep.find('.dc-quiz-option--conditional');
        if ( ! $allConditional.length ) {
            return; // Static (non-conditional) priorities.
        }

        var selectionInvalidated = false;

        $allConditional.each( function () {
            var $card  = $( this );
            var group  = $card.data('group') || '';
            var $radio = $card.find('input[type="radio"]');

            if ( group === activityValue ) {
                $card.show();
            } else {
                // Clear the stored answer when the selected card is hidden.
                if ( $radio.prop('checked') ) {
                    $radio.prop('checked', false);
                    $card.removeClass('selected');
                    selectionInvalidated = true;
                }
                $card.hide();
            }
        } );

        if ( selectionInvalidated ) {
            delete state.answers.priorities;
            $prioritiesStep.find('[data-action="next"], [data-action="submit"]').prop('disabled', true);
        }
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    function onNext() {
        if ( ! stepIsAnswered( state.currentStep ) ) {
            return;
        }
        goToStep( state.currentStep + 1 );
    }

    function onBack() {
        if ( state.currentStep > 1 ) {
            goToStep( state.currentStep - 1 );
        }
    }

    function goToStep(targetStep) {
        if ( targetStep < 1 || targetStep > state.totalSteps ) {
            return;
        }

        getStep( state.currentStep ).removeClass('is-active');

        state.currentStep = targetStep;

        var $next = getStep( targetStep );
        $next.addClass('is-active');

        var question = $next.data('question');
        if ( state.answers[question] ) {
            $next.find('[data-action="next"], [data-action="submit"]').prop('disabled', false);
        }

        // Filter the priorities step to the active activity group.
        if ( question === 'priorities' ) {
            filterConditionalPriorities( state.answers.activity || '' );
        }

        updateProgress();

        scrollToElement( $wrap, 300 );
    }

    function getStep(stepNum) {
        return $wrap.find('.dc-quiz-step[data-step="' + stepNum + '"]');
    }

    // ── Scrolling ─────────────────────────────────────────────────────────────

    // Minimum breathing room above the target when nothing else applies.
    var SCROLL_GAP = 40;

    /**
     * Scrolls an element into view below any sticky or fixed site header.
     *
     * The offset is the element's scroll-margin-top if set, otherwise the
     * height of elements pinned to the top of the viewport, otherwise a small
     * default gap.
     *
     * @param {jQuery} $el      Target element.
     * @param {number} duration Animation duration in ms.
     */
    function scrollToElement($el, duration) {
        if ( ! $el || ! $el.length ) {
            return;
        }

        var offset = scrollOffsetFor( $el[0] );
        var top    = Math.max( 0, $el.offset().top - offset );

        $('html, body').animate({ scrollTop: top }, duration);
    }

    function scrollOffsetFor(el) {
        var cssMargin = 0;

        if ( window.getComputedStyle ) {
            var computed = window.getComputedStyle( el );
            cssMargin = parseFloat( computed.scrollMarginTop || computed.scrollMargin ) || 0;
        }

        if ( cssMargin > 0 ) {
            return cssMargin;
        }

        return measureStickyChrome() + SCROLL_GAP;
    }

    /**
     * Measures the height of fixed or sticky elements pinned to the top of the viewport.
     *
     * Probes points along the top edge and keeps the lowest bottom edge of a
     * fixed or sticky element.
     *
     * @returns {number} Pinned height in pixels.
     */
    function measureStickyChrome() {
        if ( typeof document.elementsFromPoint !== 'function' ) {
            return 0;
        }

        var probeY  = 2;
        var width   = window.innerWidth || document.documentElement.clientWidth;
        var height  = window.innerHeight || document.documentElement.clientHeight;
        var probesX = [ Math.round( width / 2 ), 24, Math.max( 0, width - 24 ) ];
        var bottom  = 0;

        probesX.forEach(function (x) {
            var stack;
            try {
                stack = document.elementsFromPoint( x, probeY ) || [];
            } catch (e) {
                return;
            }

            Array.prototype.forEach.call(stack, function (node) {
                if ( ! node || node === document.body || node === document.documentElement ) {
                    return;
                }

                var position = window.getComputedStyle( node ).position;
                if ( position !== 'fixed' && position !== 'sticky' ) {
                    return;
                }

                var rect = node.getBoundingClientRect();

                // Count only bars at the top, not full-screen fixed overlays.
                if ( rect.top > probeY || rect.height > height / 2 ) {
                    return;
                }

                if ( rect.bottom > bottom ) {
                    bottom = rect.bottom;
                }
            });
        });

        return bottom;
    }

    function stepIsAnswered(stepNum) {
        var $step    = getStep( stepNum );
        var question = $step.data('question');
        return !! state.answers[question];
    }

    // ── Progress bar ──────────────────────────────────────────────────────────

    function updateProgress() {
        if ( ! state.totalSteps ) {
            return;
        }
        var pct = Math.round( ( ( state.currentStep - 1 ) / state.totalSteps ) * 100 );
        $progressFill.css('width', pct + '%');

        $progressFill.closest('[role="progressbar"]').attr('aria-valuenow', pct);
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    function onSubmit() {
        if ( ! stepIsAnswered( state.currentStep ) ) {
            return;
        }

        var $submitBtn = $wrap.find('#dc-quiz-submit-btn');
        $submitBtn.prop('disabled', true).html(
            '<i class="fa-light fa-arrows-rotate-reverse" aria-hidden="true"></i> ' +
            dcBoatQuizL10n.loading
        );

        var payload = {
            action:          'dealerschoice_quiz_results',
            nonce:           $wrap.data('nonce') || ( window.dcBoatQuiz && window.dcBoatQuiz.nonce ) || '',
            activity:        state.answers.activity   || '',
            crew:            state.answers.crew       || '',
            priorities:      state.answers.priorities || '',
            budget:          state.answers.budget     || 'any',
            gravity_form_id: parseInt( $wrap.data('gravity-form-id'), 10 ) || 0,
            boat_count:      parseInt( $wrap.data('boat-count'), 10 ) || 3
        };

        $.ajax({
            url:      ( window.dcBoatQuiz && window.dcBoatQuiz.ajaxUrl ) || window.ajaxurl || '',
            type:     'POST',
            data:     payload,
            success:  function (response) {
                if ( response && response.success && response.data && response.data.html ) {
                    saveSession( state.answers, response.data.html );
                    showResult( response.data.html );
                } else {
                    showError();
                    $submitBtn.prop('disabled', false).html( restoreSubmitLabel() );
                }
            },
            error: function () {
                showError();
                $submitBtn.prop('disabled', false).html( restoreSubmitLabel() );
            }
        });
    }

    function showResult(html) {
        $form.hide();
        $result.html(html).show();

        // Initialize sliders in the result HTML.
        $result.find('.boat-slider').each(function () {
            var $slider = $(this);
            if ( $slider.hasClass('slick-initialized') ) {
                return;
            }
            var sliderId = $slider.attr('id');
            var slidesToShow = parseInt( ($slider.data('slick') || {}).slidesToShow, 10 ) || 3;
            $slider.slick({
                slidesToShow:   slidesToShow,
                slidesToScroll: 1,
                infinite:       false,
                appendArrows:   '#' + sliderId + '-buttons',
                prevArrow: '<button class="slick-prev slick-arrow" type="button"><span class="dc-screen-reader-text">Previous</span><i class="fa-light fa-arrow-left"></i></button>',
                nextArrow: '<button class="slick-next slick-arrow" type="button"><span class="dc-screen-reader-text">Next</span><i class="fa-light fa-arrow-right"></i></button>',
                responsive: [
                    { breakpoint: 1440, settings: { slidesToShow: 2 } },
                    { breakpoint: 960,  settings: { slidesToShow: 1 } }
                ]
            });
        });

        scrollToElement( $result, 400 );

        injectQuizDataIntoGF( $result );
    }

    function showError() {
        var msg = '<p class="dc-quiz-error" style="color:#c00;text-align:center;padding:1rem;">' +
                  dcBoatQuizL10n.error + '</p>';
        $wrap.find('.dc-quiz-step.is-active .dc-quiz-nav').before(msg);
    }

    function restoreSubmitLabel() {
        // Use the captured template markup when available.
        if ( submitBtnHtml ) {
            return submitBtnHtml;
        }

        return dcBoatQuizL10n.submit +
            ' <i class="fa-light fa-arrow-right-long" aria-hidden="true"></i>';
    }

    // ── Retake ────────────────────────────────────────────────────────────────

    function onRetake() {
        clearSession();

        $result.find('.boat-slider.slick-initialized').each(function () {
            $(this).slick('unslick');
        });

        state.currentStep = 1;
        state.answers     = {};

        $wrap.find('.dc-quiz-option.selected').removeClass('selected');
        $wrap.find('input[type="radio"]').prop('checked', false);
        $wrap.find('[data-action="next"], [data-action="submit"]').prop('disabled', true);

        // Replace the loading label on the submit button.
        $wrap.find('#dc-quiz-submit-btn').html( restoreSubmitLabel() );

        $wrap.find('.dc-quiz-error').remove();

        $result.hide().html('');
        $form.show();

        getStep(1).addClass('is-active').siblings('.dc-quiz-step').removeClass('is-active');

        updateProgress();

        scrollToElement( $wrap, 300 );
    }

    // ── Gravity Forms quiz-data injection ────────────────────────────────────

    /**
     * Adds quiz answers as hidden dc_quiz_* inputs to every Gravity Form in the container.
     *
     * DC\GravityForms appends these to notification emails. Dynamically populated
     * GF Hidden fields with matching parameter names also store them in the entry.
     *
     * @param {jQuery} $container Element containing the Gravity Form.
     */
    function injectQuizDataIntoGF( $container ) {
        var $gfForms = $container.find( 'form[id^="gform_"]' );
        if ( ! $gfForms.length ) {
            return;
        }

        // Read the recommended boat type from the rendered result heading.
        var resultLabel = $container.find( '.dc-quiz-result-title' ).first().text().trim();

        var quizFields = {
            dc_quiz_context:    '1',
            dc_quiz_activity:   state.answers.activity   || '',
            dc_quiz_crew:       state.answers.crew        || '',
            dc_quiz_priorities: state.answers.priorities  || '',
            dc_quiz_budget:     state.answers.budget      || '',
            dc_quiz_result:     resultLabel
        };

        $gfForms.each( function () {
            var $form = $( this );
            $.each( quizFields, function ( name, value ) {
                var $existing = $form.find( 'input[name="' + name + '"]' );
                if ( $existing.length ) {
                    $existing.val( value );
                } else {
                    $form.append(
                        $( '<input>' ).attr({ type: 'hidden', name: name, value: value })
                    );
                }
            } );
        } );
    }

    // ── Localization fallback ─────────────────────────────────────────────────

    var dcBoatQuizL10n = window.dcBoatQuizL10n || {
        loading: 'Finding your match',
        submit:  'Find My Perfect Boat',
        error:   'Something went wrong. Please try again.'
    };

    // ── Boot ──────────────────────────────────────────────────────────────────

    $(document).ready(function () {
        init();
    });

    // Re-inject quiz data when Gravity Forms re-renders a form in the result area.
    $( document ).on( 'gform_post_render', function ( event, formId ) {
        if ( ! $result || ! $result.length ) {
            return;
        }
        if ( $result.find( '#gform_' + formId ).length ) {
            injectQuizDataIntoGF( $result );
        }
    } );

}(jQuery));
