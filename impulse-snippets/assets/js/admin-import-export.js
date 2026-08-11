/**
 * Import/Export page: "Select all" master checkbox for the export picker.
 * Master toggles every row; unticking any row unticks the master, and
 * re-ticking the last row re-ticks it, so the master always reflects state.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var master = document.getElementById( 'wpci-export-select-all' );
		if ( ! master ) {
			return;
		}

		var boxes = Array.prototype.slice.call(
			document.querySelectorAll( '.wpci-export-list input[type="checkbox"]' )
		);

		master.addEventListener( 'change', function () {
			boxes.forEach( function ( box ) {
				box.checked = master.checked;
			} );
		} );

		boxes.forEach( function ( box ) {
			box.addEventListener( 'change', function () {
				master.checked = boxes.every( function ( b ) {
					return b.checked;
				} );
			} );
		} );
	} );
} )();
