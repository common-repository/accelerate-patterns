/**
 * Insert a button into the block editor toolbar that saves selected blocks as a Pattern.
 *
 * @package AMP Publisher
 * @subpackage Accelerate Patterns
 * @since 1.0
 * @version 1.0.0
 */

var accpatternsEditorToolbarButton_added = false;
jQuery( document ).ready(
	function($){
		wp.data.subscribe( () => { accpatternsEditorToolbarButton(); } );
	}
);
function accpatternsEditorToolbarButton() {
	if ( accpatternsEditorToolbarButton_added ) {
		return;
	}
	if (ampwptoolsPostType === 'accheading') {
		accpatternsEditorToolbarButton_added = true;
		return;
	}
	const toolbar = document.querySelector( '.edit-post-header__toolbar' );
	if ( ! toolbar ) {
		return;
	}
	const buttonDiv     = document.createElement( 'div' );
	let html            = '<div class="tacwp-editor-toolbar-section">';
		html           += '<button id="accpatternsPatternButton" class="components-button components-icon-button" title="Create Pattern">';
			html       += '<i class="dashicons dashicons-superhero"></i>Create Pattern';
		html           += '</button>';
	html               += '</div>';
	buttonDiv.innerHTML = html;
	toolbar.appendChild( buttonDiv );
	const innerToolbar = document.querySelector( '.components-accessible-toolbar.edit-post-header-toolbar' );
	if ( innerToolbar ) {
		innerToolbar.style.flexGrow = 0; }
	document.getElementById( 'accpatternsPatternButton' ).addEventListener( 'click', accpatternsCreatePattern );
	accpatternsEditorToolbarButton_added = true;
}
function accpatternsCreatePattern() {
	var selected = wp.data.select( 'core/block-editor' ).getSelectedBlockClientIds();
	if (selected.length === 0) {
		accpatterns_notice( 'No blocks were selected' );
		return;
	}
	var blockids = wp.data.select( 'core/block-editor' ).getBlockOrder();
	var byid     = {};
	for (i in blockids) {
		var blockid   = blockids[i];
		byid[blockid] = i;
	}
	var startid = 'N';
	var endid   = 'N';
	var start   = wp.data.select( 'core/block-editor' ).getSelectionStart();
	if (start) {
		var startid = start.clientId;
		if (byid[startid]) {
			startid = parseInt( byid[startid] );}
	}
	var end = wp.data.select( 'core/block-editor' ).getSelectionEnd();
	if (end) {
		var endid = end.clientId;
		if (byid[endid]) {
			endid = parseInt( byid[endid] );}
	}
	if (startid > endid) {
		var selection = endid + '_' + startid;
	} else {
		var selection = startid + '_' + endid;
	}
	wp.data.dispatch( 'core/edit-post' ).switchEditorMode( 'text' );
	var checkExist = setInterval(
		function() {
			if (jQuery( '.editor-post-text-editor' ).length) {
				clearInterval( checkExist );
				var html = jQuery( '.editor-post-text-editor' ).html();
				wp.data.dispatch( 'core/edit-post' ).switchEditorMode( 'visual' );
				var pass     = JSON.stringify( html );
				var security = accpatterns_ajax_handler.ajax_nonce;
				jQuery.post(
					ajaxurl,
					{ 'action': 'accpatterns_ajax_handler' , 'how': 'createpattern' , 'selection':selection , 'pass': pass , 'security':security },
					function(response) {
						if (response.substring( 0,5 ) === 'post=') {
							window.open( adminPostURL + '?' + response + '&action=edit', '_blank' );
						} else {
							tacwpac.loader( 'test',response );
						}
					}
				);
			}
		},
		100
	);
}
var accpatternsTimeout;
accpatterns_notice = function(mess,seconds){
	clearTimeout( accpatternsTimeout );
	if (jQuery( '#ACPNOTICE' ).length === 0) {
		var txt = '<div id="ACPNOTICE" class="tacwp-notice">' + mess + '</div>';
		jQuery( 'body' ).append( txt );
	} else {
		jQuery( '#ACPNOTICE' ).html( mess );
	}
	jQuery( '#ACPNOTICE' ).show();
	var fade = 3000;
	if (seconds) {
		fade = seconds * 1000;}
	if (mess.substr( 0,5 ) === 'ERROR') {
		jQuery( '#ACPNOTICE' ).addClass( 'iserror' );} else {
		jQuery( '#ACPNOTICE' ).removeClass( 'iserror' );}
		accpatternsTimeout = setTimeout( function() {clearTimeout( accpatternsTimeout );jQuery( '#ACPNOTICE' ).fadeOut( 'fast' );}, fade );
}
