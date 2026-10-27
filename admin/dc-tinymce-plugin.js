/**
 * DealersChoice TinyMCE plugin.
 *
 * Adds a toolbar button to each TinyMCE editor that opens the shortcode
 * builder modal with that editor as the insertion target.
 *
 * @package DealersChoice
 */
/* global tinymce */
( function () {
    'use strict';

    // DealersChoice logo mark used as the button icon.
    var DC_SVG =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -134.28 371.5 371.5" width="20" height="20">' +
        '<path fill="#2271b1" d="M131.04,11.79c83.3,0,106.87,36.93,106.87,36.93h133.59C332.2-.78,165.61,0,165.61,0h-97.28c-.34,0-.67.17-.86.46l-3.61,5.44-3.2,4.83c-.3.45.02,1.07.57,1.07h69.81,0Z"/>' +
        '<path fill="#2271b1" d="M131.04,91.15H7.79c-.34,0-.67.17-.86.46L.11,101.88c-.3.45.02,1.07.57,1.07h164.92s166.59.78,205.88-48.72h-133.59s-23.58,36.93-106.87,36.93h0Z"/>' +
        '</svg>';

    tinymce.PluginManager.add( 'dc_shortcode', function ( editor ) {
        editor.addButton( 'dc_shortcode', {
            title:   'Insert DealersChoice',
            image:   'data:image/svg+xml;base64,' + btoa( DC_SVG ),
            onclick: function () {
                // Notify generate-shortcode.js with the target editor ID.
                document.dispatchEvent(
                    new CustomEvent( 'dc:open-shortcode-modal', {
                        detail: { editorId: editor.id },
                    } )
                );
            },
        } );
    } );
}() );
