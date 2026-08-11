/**
 * Import/Export page: export picker behavior.
 *
 * - Filter box hides non-matching rows (same .wpci-hidden mechanism as the
 *   edit screen's category filter, so the two pickers feel identical).
 * - "Select all" applies to the currently visible rows only, so a filtered
 *   view can be bulk-toggled without disturbing hidden rows' choices.
 * - The count and the submit button always reflect the OVERALL selection
 *   (hidden-but-ticked rows still export); zero selected disables the button.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var master = document.getElementById( 'wpci-export-select-all' );
		var list   = document.querySelector( '.wpci-export-list' );
		if ( ! master || ! list ) {
			return;
		}

		var filter   = document.getElementById( 'wpci-export-filter' );
		var countEl  = document.getElementById( 'wpci-export-count' );
		var submit   = document.getElementById( 'wpci-export-submit' );
		var noMatch  = list.querySelector( '.wpci-export-no-match' );
		var rows     = Array.prototype.slice.call( list.querySelectorAll( 'label' ) );
		var template = ( window.wpciImportExportL10n && window.wpciImportExportL10n.countTemplate ) || '%1$s of %2$s selected';

		function boxOf( row ) {
			return row.querySelector( 'input[type="checkbox"]' );
		}

		function visibleRows() {
			return rows.filter( function ( row ) {
				return ! row.classList.contains( 'wpci-hidden' );
			} );
		}

		function refresh() {
			var selected = rows.filter( function ( row ) {
				return boxOf( row ).checked;
			} ).length;
			var visible  = visibleRows();

			if ( countEl ) {
				countEl.textContent = template.replace( '%1$s', selected ).replace( '%2$s', rows.length );
			}
			if ( submit ) {
				submit.disabled = 0 === selected;
			}
			if ( noMatch ) {
				noMatch.hidden = visible.length > 0;
			}
			master.checked = visible.length > 0 && visible.every( function ( row ) {
				return boxOf( row ).checked;
			} );
		}

		master.addEventListener( 'change', function () {
			visibleRows().forEach( function ( row ) {
				boxOf( row ).checked = master.checked;
			} );
			refresh();
		} );

		rows.forEach( function ( row ) {
			boxOf( row ).addEventListener( 'change', refresh );
		} );

		if ( filter ) {
			filter.addEventListener( 'input', function () {
				var query = filter.value.toLowerCase();
				rows.forEach( function ( row ) {
					var matches = '' === query || row.textContent.toLowerCase().indexOf( query ) !== -1;
					row.classList.toggle( 'wpci-hidden', ! matches );
				} );
				refresh();
			} );
		}

		refresh();
	} );
}() );
