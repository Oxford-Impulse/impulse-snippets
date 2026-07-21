( function() {
	'use strict';

	document.addEventListener( 'change', function( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'wpci-toggle-input' ) ) {
			return;
		}

		var checkbox     = e.target;
		var postId       = checkbox.getAttribute( 'data-post-id' );
		var wasChecked   = ! checkbox.checked; // state before this click

		checkbox.disabled = true;

		wp.apiFetch( {
			path: 'wpci/v1/snippets/' + postId + '/toggle',
			method: 'POST'
		} ).then( function( response ) {
			checkbox.checked  = ( 'publish' === response.status );
			checkbox.disabled = false;
		} ).catch( function() {
			checkbox.checked  = wasChecked; // revert on failure
			checkbox.disabled = false;
		} );
	} );
}() );
