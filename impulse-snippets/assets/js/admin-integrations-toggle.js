( function() {
	'use strict';

	document.addEventListener( 'change', function( e ) {
		if ( ! e.target.classList || ! e.target.classList.contains( 'wpci-integration-toggle' ) ) {
			return;
		}

		var checkbox     = e.target;
		var integration  = checkbox.getAttribute( 'data-integration' );
		var wasChecked   = ! checkbox.checked;

		checkbox.disabled = true;

		wp.apiFetch( {
			path: 'wpci/v1/integrations/' + integration + '/toggle',
			method: 'POST'
		} ).then( function( response ) {
			checkbox.checked  = ( 'publish' === response.status );
			checkbox.disabled = false;
		} ).catch( function() {
			checkbox.checked  = wasChecked;
			checkbox.disabled = false;
		} );
	} );
}() );
