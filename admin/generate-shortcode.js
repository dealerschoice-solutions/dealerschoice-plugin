/**
 * DealersChoice shortcode builder modal.
 *
 * Renders a shortcode picker and options form from dcShortcodeBuilder.schema
 * and inserts the built shortcode into the target editor. The TinyMCE button
 * passes its editor ID; the media button uses its surrounding editor, else the
 * last focused editor, else 'content'.
 *
 * @package DealersChoice
 */
/* global dcShortcodeBuilder, tinymce */
( function ( $ ) {
    'use strict';

    // ── Constants ────────────────────────────────────────────────────────────

    var DC_SVG_INLINE =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 371.5 102.94" ' +
        'style="height:10px;vertical-align:middle;margin-right:6px;flex-shrink:0;" ' +
        'aria-hidden="true" focusable="false">' +
        '<path fill="#2271b1" d="M131.04,11.79c83.3,0,106.87,36.93,106.87,36.93h133.59C332.2-.78,165.61,0,165.61,0h-97.28c-.34,0-.67.17-.86.46l-3.61,5.44-3.2,4.83c-.3.45.02,1.07.57,1.07h69.81,0Z"/>' +
        '<path fill="#2271b1" d="M131.04,91.15H7.79c-.34,0-.67.17-.86.46L.11,101.88c-.3.45.02,1.07.57,1.07h164.92s166.59.78,205.88-48.72h-133.59s-23.58,36.93-106.87,36.93h0Z"/>' +
        '</svg>';

    // ID of the TinyMCE editor that most recently received focus.
    var lastFocusedEditorId = 'content';

    // ID of the editor that will receive the insertion when Insert is clicked.
    var targetEditorId = 'content';

    // ── Modal skeleton ────────────────────────────────────────────────────────

    var MODAL_HTML =
        '<div id="dc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="dc-modal-title">' +
            '<div id="dc-modal">' +
                '<div id="dc-modal-header">' +
                    '<h2 id="dc-modal-title">' + DC_SVG_INLINE + 'Insert DealersChoice</h2>' +
                    '<button type="button" id="dc-modal-close" aria-label="Close">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                    '</button>' +
                '</div>' +
                '<div id="dc-modal-body"></div>' +
                '<div id="dc-modal-footer">' +
                    '<button type="button" id="dc-modal-insert" disabled>Insert Shortcode</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    // ── Initialize ────────────────────────────────────────────────────────────

    $( function () {
        $( 'body' ).append( MODAL_HTML );
        bindModalEvents();
        trackFocusedEditors();
    } );

    // ── Track last-focused TinyMCE editor ────────────────────────────────────

    function trackFocusedEditors() {
        if ( typeof tinymce === 'undefined' ) {
            return;
        }

        function attachFocusListener( editor ) {
            editor.on( 'focus', function () {
                lastFocusedEditorId = editor.id;
            } );
        }

        // Editors that already exist.
        if ( tinymce.editors && tinymce.editors.length ) {
            for ( var i = 0; i < tinymce.editors.length; i++ ) {
                attachFocusListener( tinymce.editors[ i ] );
            }
        }

        // Editors added later, such as new ACF Flexible Content rows.
        tinymce.on( 'AddEditor', function ( e ) {
            attachFocusListener( e.editor );
        } );
    }

    // ── Event bindings ────────────────────────────────────────────────────────

    function bindModalEvents() {
        // Media button (delegated): targets the editor from its wp-{id}-editor-tools wrapper.
        $( document ).on( 'click', '.dc-generate-shortcode-button', function () {
            var $tools   = $( this ).closest( '[id$="-editor-tools"]' );
            var editorId;
            if ( $tools.length ) {
                // 'wp-content-editor-tools' becomes 'content'.
                editorId = $tools.attr( 'id' )
                    .replace( /^wp-/, '' )
                    .replace( /-editor-tools$/, '' );
            } else {
                // No wrapper: use the active editor, else the last focused one.
                var active = ( typeof tinymce !== 'undefined' ) && tinymce.activeEditor;
                editorId = active ? tinymce.activeEditor.id : lastFocusedEditorId;
            }
            dcOpenShortcodeModal( editorId );
        } );

        // TinyMCE toolbar button (dc-tinymce-plugin.js) passes its editor ID.
        document.addEventListener( 'dc:open-shortcode-modal', function ( e ) {
            dcOpenShortcodeModal( e.detail && e.detail.editorId );
        } );

        // Close on the close button or an overlay click.
        $( '#dc-modal-close' ).on( 'click', closeModal );
        $( '#dc-modal-overlay' ).on( 'click', function ( e ) {
            if ( $( e.target ).is( '#dc-modal-overlay' ) ) {
                closeModal();
            }
        } );

        // Close on Escape.
        $( document ).on( 'keydown.dc-modal', function ( e ) {
            if ( e.key === 'Escape' && $( '#dc-modal-overlay' ).hasClass( 'dc-modal-open' ) ) {
                closeModal();
            }
        } );

        $( '#dc-modal-insert' ).on( 'click', insertShortcode );
    }

    // ── Public API (called by dc-tinymce-plugin.js) ───────────────────────────

    /**
     * Opens the modal on the shortcode picker and focuses the close button.
     *
     * @param {string} [editorId] Editor ID to insert into. Defaults to the last focused editor.
     */
    window.dcOpenShortcodeModal = function ( editorId ) {
        targetEditorId = editorId || lastFocusedEditorId;
        renderPicker();
        $( '#dc-modal-overlay' ).addClass( 'dc-modal-open' );
        $( '#dc-modal-close' ).trigger( 'focus' );
    };

    // ── Close ─────────────────────────────────────────────────────────────────

    function closeModal() {
        $( '#dc-modal-overlay' ).removeClass( 'dc-modal-open' );
        $( '#dc-modal-insert' ).prop( 'disabled', true );
    }

    // ── Step 1: Shortcode Picker ──────────────────────────────────────────────

    function renderPicker() {
        var schema = dcShortcodeBuilder.schema;
        var $body  = $( '#dc-modal-body' ).empty();
        var $grid  = $( '<div class="dc-picker-grid"></div>' );

        $( '#dc-modal-title' ).html( DC_SVG_INLINE + 'Insert DealersChoice' );
        $( '#dc-modal-insert' ).prop( 'disabled', true );

        $.each( schema, function ( tag, config ) {
            var $card = $(
                '<button type="button" class="dc-picker-card" data-tag="' + escAttr( tag ) + '">' +
                    '<span class="dc-picker-card-label">' + escHtml( config.label ) + '</span>' +
                    '<span class="dc-picker-card-tag">[' + escHtml( tag ) + ']</span>' +
                    '<span class="dc-picker-card-desc">' + escHtml( config.description || '' ) + '</span>' +
                '</button>'
            );
            $card.on( 'click', function () {
                renderOptionsForm( tag );
            } );
            $grid.append( $card );
        } );

        $body.append( $grid );
    }

    // ── Step 2: Options Form ──────────────────────────────────────────────────

    function renderOptionsForm( tag ) {
        var config = dcShortcodeBuilder.schema[ tag ];
        var fields = config.fields || {};
        var $body  = $( '#dc-modal-body' ).empty();

        $( '#dc-modal-title' ).html( DC_SVG_INLINE + escHtml( config.label ) );

        var $back = $( '<button type="button" class="dc-form-back">&#8592; Back</button>' );
        $back.on( 'click', renderPicker );
        $body.append( $back );

        if ( $.isEmptyObject( fields ) ) {
            $body.append(
                '<p class="dc-no-fields-note">This shortcode has no configurable options. ' +
                'Click <strong>Insert Shortcode</strong> to add <code>[' + escHtml( tag ) + ']</code> to the editor.</p>'
            );
        } else {
            var $grid = $( '<div class="dc-field-grid"></div>' );
            $.each( fields, function ( attr, field ) {
                $grid.append( buildFieldRow( attr, field ) );
            } );
            $body.append( $grid );
        }

        $( '#dc-modal-insert' ).data( 'shortcode-tag', tag ).prop( 'disabled', false );
    }

    // ── Field row factory ─────────────────────────────────────────────────────

    function buildFieldRow( attr, field ) {
        var inputId = 'dc-field-' + attr;
        var isFullWidth = ( field.type === 'text' ) || ( field.type === 'taxonomy' && field.multiple );
        var $row = $( '<div class="dc-field-row' + ( isFullWidth ? ' dc-field-full' : '' ) + '"></div>' );
        var $label = $( '<label for="' + escAttr( inputId ) + '">' + escHtml( field.label ) + '</label>' );
        $row.append( $label );

        var $input;

        switch ( field.type ) {
            case 'text':
                $input = $( '<input type="text">' )
                    .attr( { id: inputId, name: attr } )
                    .val( field.default || '' );
                break;

            case 'number':
                $input = $( '<input type="number">' )
                    .attr( { id: inputId, name: attr, min: 1 } )
                    .val( field.default !== undefined ? field.default : '' );
                break;

            case 'toggle':
                $input = $( '<select></select>' ).attr( { id: inputId, name: attr } );
                $input.append( $( '<option value="true">Yes</option>' ) );
                $input.append( $( '<option value="false">No</option>' ) );
                $input.val( String( field.default ) );
                break;

            case 'select':
                $input = $( '<select></select>' ).attr( { id: inputId, name: attr } );
                $.each( field.options, function ( value, optLabel ) {
                    $input.append(
                        $( '<option></option>' ).val( value ).text( optLabel )
                    );
                } );
                $input.val( field.default );
                break;

            case 'taxonomy':
                if ( field.terms && field.terms.length > 0 ) {
                    $input = $( '<select></select>' ).attr( { id: inputId, name: attr } );
                    if ( field.multiple ) {
                        $input.attr( 'multiple', 'multiple' );
                        // No selection means no filter.
                    } else {
                        $input.append( '<option value="">— Any —</option>' );
                    }
                    $.each( field.terms, function ( i, term ) {
                        $input.append(
                            $( '<option></option>' ).val( term.value ).text( term.label )
                        );
                    } );

                    if ( field.multiple ) {
                        $row.append(
                            $( '<span class="dc-field-hint">Hold Ctrl / Cmd to select multiple.</span>' )
                        );
                    }
                } else {
                    $input = $( '<select></select>' )
                        .attr( { id: inputId, name: attr, disabled: true } )
                        .append( '<option>(No terms available)</option>' );
                    $row.append(
                        $( '<span class="dc-field-hint">No ' + escHtml( field.label.toLowerCase() ) + ' terms exist yet.</span>' )
                    );
                }
                break;

            default:
                $input = $( '<input type="text">' ).attr( { id: inputId, name: attr } );
        }

        $row.prepend( $label );
        $row.empty().append( $label ).append( $input );

        // Hints follow the input.
        if ( field.type === 'taxonomy' && field.multiple && field.terms && field.terms.length > 0 ) {
            $row.append( $( '<span class="dc-field-hint">Hold Ctrl / Cmd to select multiple.</span>' ) );
        }
        if ( field.type === 'taxonomy' && ( ! field.terms || field.terms.length === 0 ) ) {
            $row.append( $( '<span class="dc-field-hint">No ' + escHtml( field.label.toLowerCase() ) + ' terms exist yet.</span>' ) );
        }

        return $row;
    }

    // ── Build & insert shortcode ──────────────────────────────────────────────

    function insertShortcode() {
        var $btn  = $( '#dc-modal-insert' );
        var tag   = $btn.data( 'shortcode-tag' );

        if ( ! tag ) {
            return;
        }

        var schema = dcShortcodeBuilder.schema[ tag ];
        var fields = schema.fields || {};
        var attrs  = [];

        $.each( fields, function ( attr, field ) {
            var inputId = 'dc-field-' + attr;
            var $input  = $( '#' + inputId );

            if ( $input.prop( 'disabled' ) ) {
                return; // Skip fields without available terms.
            }

            var value;
            if ( field.type === 'taxonomy' && field.multiple ) {
                var selected = $input.val();
                value = ( selected && selected.length ) ? selected.join( ',' ) : '';
            } else {
                value = $input.val() || '';
            }

            // Include only non-empty attributes that differ from the default.
            var def = ( field.default !== undefined ) ? String( field.default ) : '';
            if ( String( value ) !== def && value !== '' ) {
                attrs.push( attr + '="' + value.replace( /"/g, '&quot;' ) + '"' );
            }
        } );

        var shortcode = '[' + tag + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';

        // Insert into the visual editor when active; otherwise into the textarea at the cursor.
        if ( typeof tinymce !== 'undefined' ) {
            var editor = tinymce.get( targetEditorId );
            if ( editor && ! editor.isHidden() ) {
                editor.insertContent( shortcode );
                closeModal();
                return;
            }
        }

        var textarea = document.getElementById( targetEditorId );
        if ( textarea ) {
            var start = textarea.selectionStart;
            var end   = textarea.selectionEnd;
            textarea.value =
                textarea.value.substring( 0, start ) +
                shortcode +
                textarea.value.substring( end );
            textarea.selectionStart = textarea.selectionEnd = start + shortcode.length;
        }

        closeModal();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    function escAttr( str ) {
        return String( str ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
    }

}( jQuery ) );