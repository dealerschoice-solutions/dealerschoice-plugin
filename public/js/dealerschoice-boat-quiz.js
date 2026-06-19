/**
 * Boat Quiz — Step-by-step JavaScript controller
 *
 * Manages navigation between quiz steps, tracks answers, POSTs to the
 * dealerschoice_quiz_results AJAX action, and renders the returned HTML.
 *
 * No external dependencies beyond jQuery (already a WordPress dependency).
 *
 * @package DealersChoice
 * @since 1.0.0
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
    // Persists answers + rendered HTML so the browser back button returns to
    // results rather than forcing the user to retake the quiz.

    var SESSION_KEY = 'dc_boat_quiz';

    function saveSession(answers, resultHtml) {
        try {
            sessionStorage.setItem( SESSION_KEY, JSON.stringify({
                answers:    answers,
                resultHtml: resultHtml
            }) );
        } catch (e) { /* sessionStorage unavailable or full — silently ignore */ }
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

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        $wrap        = $('#dc-boat-quiz');
        $form        = $('#dc-quiz-form', $wrap);
        $result      = $('#dc-quiz-result', $wrap);
        $progressFill = $('#dc-quiz-progress-fill', $wrap);

        if ( ! $wrap.length ) {
            return; // Quiz not on this page
        }

        state.totalSteps = parseInt( $wrap.data('total-steps'), 10 ) || 0;

        // Delegate events on the wrapper so they survive any DOM changes
        $wrap.on('change', 'input[type="radio"]', onOptionChange);
        $wrap.on('click', '[data-action="next"]',   onNext);
        $wrap.on('click', '[data-action="back"]',   onBack);
        $wrap.on('click', '[data-action="submit"]', onSubmit);

        // "Start Over" button lives inside the AJAX-injected result HTML
        $wrap.on('click', '#dc-quiz-retake-btn', onRetake);

        // Restore previous session so the browser back button returns to results
        var session = loadSession();
        if ( session && session.resultHtml ) {
            state.answers = session.answers || {};
            showResult( session.resultHtml );
            return; // Skip updateProgress — the form is hidden
        }

        updateProgress();
    }

    // ── Option selection ──────────────────────────────────────────────────────

    function onOptionChange() {
        var $radio    = $(this);
        var question  = $radio.closest('.dc-quiz-step').data('question');
        var value     = $radio.val();

        // Mark selected state on cards
        $radio.closest('.dc-quiz-grid').find('.dc-quiz-option').removeClass('selected');
        $radio.closest('.dc-quiz-option').addClass('selected');

        // Store answer
        state.answers[question] = value;

        // When activity changes, re-filter the conditional priorities step and
        // clear any priority answer that belonged to the previous activity group.
        if ( question === 'activity' ) {
            filterConditionalPriorities( value );
        }

        // Enable the Next/Submit button for this step
        $radio.closest('.dc-quiz-step').find('[data-action="next"], [data-action="submit"]').prop('disabled', false);
    }

    // ── Conditional priorities filter ────────────────────────────────────────

    /**
     * Show only the priority option cards that belong to the given activity
     * group, hiding all others.  If a now-hidden card was previously selected,
     * the priorities answer is cleared and its Next button re-disabled.
     *
     * @param {string} activityValue  The current answer for the 'activity' question.
     */
    function filterConditionalPriorities( activityValue ) {
        var $prioritiesStep = $wrap.find('.dc-quiz-step[data-question="priorities"]');
        if ( ! $prioritiesStep.length ) {
            return;
        }

        var $allConditional = $prioritiesStep.find('.dc-quiz-option--conditional');
        if ( ! $allConditional.length ) {
            return; // Static (non-conditional) priorities — nothing to filter
        }

        var selectionInvalidated = false;

        $allConditional.each( function () {
            var $card  = $( this );
            var group  = $card.data('group') || '';
            var $radio = $card.find('input[type="radio"]');

            if ( group === activityValue ) {
                $card.show();
            } else {
                // If this hidden card was selected, invalidate the stored answer
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

        // Hide current step
        getStep( state.currentStep ).removeClass('is-active');

        state.currentStep = targetStep;

        var $next = getStep( targetStep );
        $next.addClass('is-active');

        // Restore Next/Submit enabled state if already answered
        var question = $next.data('question');
        if ( state.answers[question] ) {
            $next.find('[data-action="next"], [data-action="submit"]').prop('disabled', false);
        }

        // When landing on the priorities step, filter options to the active
        // activity group (handles arriving via Next *and* Back).
        if ( question === 'priorities' ) {
            filterConditionalPriorities( state.answers.activity || '' );
        }

        updateProgress();

        // Smooth scroll to top of quiz
        $('html, body').animate({ scrollTop: $wrap.offset().top - 40 }, 300);
    }

    function getStep(stepNum) {
        return $wrap.find('.dc-quiz-step[data-step="' + stepNum + '"]');
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

        // ARIA
        $progressFill.closest('[role="progressbar"]').attr('aria-valuenow', pct);
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    function onSubmit() {
        if ( ! stepIsAnswered( state.currentStep ) ) {
            return;
        }

        // Show loading state
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
            gravity_form_id: parseInt( $wrap.data('gravity-form-id'), 10 ) || 0
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

        // Init any slick sliders injected into the result HTML
        $result.find('.boat-slider').each(function () {
            var $slider = $(this);
            if ( $slider.hasClass('slick-initialized') ) {
                return; // Already init'd (shouldn't happen, but be safe)
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

        // Animate result into view
        $('html, body').animate({ scrollTop: $result.offset().top - 40 }, 400);

        // Inject quiz context into the embedded Gravity Form (if present)
        injectQuizDataIntoGF( $result );
    }

    function showError() {
        var msg = '<p class="dc-quiz-error" style="color:#c00;text-align:center;padding:1rem;">' +
                  dcBoatQuizL10n.error + '</p>';
        $wrap.find('.dc-quiz-step.is-active .dc-quiz-nav').before(msg);
    }

    function restoreSubmitLabel() {
        return dcBoatQuizL10n.submit +
            ' <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    }

    // ── Retake ────────────────────────────────────────────────────────────────

    function onRetake() {
        // Clear persisted session so "Start Over" resets to a fresh quiz
        clearSession();

        // Destroy any slick sliders before clearing result HTML
        $result.find('.boat-slider.slick-initialized').each(function () {
            $(this).slick('unslick');
        });

        // Reset state
        state.currentStep = 1;
        state.answers     = {};

        // Clear selected UI
        $wrap.find('.dc-quiz-option.selected').removeClass('selected');
        $wrap.find('input[type="radio"]').prop('checked', false);
        $wrap.find('[data-action="next"], [data-action="submit"]').prop('disabled', true);

        // Clear any error messages
        $wrap.find('.dc-quiz-error').remove();

        // Hide result, show form at step 1
        $result.hide().html('');
        $form.show();

        getStep(1).addClass('is-active').siblings('.dc-quiz-step').removeClass('is-active');

        updateProgress();

        $('html, body').animate({ scrollTop: $wrap.offset().top - 40 }, 300);
    }

    // ── Gravity Forms quiz-data injection ────────────────────────────────────

    /**
     * Inject quiz answers as hidden <input> fields into every Gravity Form
     * found inside $container.  The PHP GravityForms::append_quiz_data_to_notification
     * filter reads these from $_POST and appends a formatted "Boat Quiz Results"
     * block to every outgoing notification email.
     *
     * Also supports GF dynamic population: if the dealer adds Hidden fields to
     * their GF form with "Allow field to be populated dynamically" checked and
     * parameter names dc_quiz_activity / dc_quiz_crew / dc_quiz_priorities /
     * dc_quiz_budget / dc_quiz_result, GF will store those values in the entry.
     *
     * @param {jQuery} $container  The element that contains the GF <form>.
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
                    $existing.val( value );      // refresh on re-render
                } else {
                    $form.append(
                        $( '<input>' ).attr({ type: 'hidden', name: name, value: value })
                    );
                }
            } );
        } );
    }

    // ── Localisation fallback ─────────────────────────────────────────────────

    var dcBoatQuizL10n = window.dcBoatQuizL10n || {
        loading: 'Finding your match',
        submit:  'Find My Perfect Boat',
        error:   'Something went wrong. Please try again.'
    };

    // ── Boot ──────────────────────────────────────────────────────────────────

    $(document).ready(function () {
        init();
    });

    // Re-inject quiz data when GF re-renders a form inside the result area
    // (handles multi-page GF forms and AJAX validation re-draws).
    $( document ).on( 'gform_post_render', function ( event, formId ) {
        if ( ! $result || ! $result.length ) {
            return; // Quiz not on this page yet
        }
        if ( $result.find( '#gform_' + formId ).length ) {
            injectQuizDataIntoGF( $result );
        }
    } );

}(jQuery));
