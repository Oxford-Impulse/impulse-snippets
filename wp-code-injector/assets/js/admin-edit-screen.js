( function( $ ) {
	'use strict';

	$( function() {
		var textarea  = document.getElementById( 'wpci_code' );
		var typeSelect = document.getElementById( 'wpci_code_type' );

		if ( textarea && 'undefined' !== typeof wpciCodeEditor && wpciCodeEditor.settings && 'undefined' !== typeof wp && wp.codeEditor ) {
			var editor = wp.codeEditor.initialize( textarea, wpciCodeEditor.settings );

			// The code type dropdown decides the CodeMirror mode. htmlmixed is
			// the safe default for 'auto' and 'html' since it tolerates bare tag
			// fragments (and embedded <script>/<style> blocks) without erroring.
			var modeForType = function( type ) {
				if ( 'script' === type ) {
					return 'javascript';
				}
				if ( 'style' === type ) {
					return 'css';
				}
				return 'htmlmixed';
			};

			if ( typeSelect ) {
				editor.codemirror.setOption( 'mode', modeForType( typeSelect.value ) );

				typeSelect.addEventListener( 'change', function() {
					editor.codemirror.setOption( 'mode', modeForType( typeSelect.value ) );
				} );
			}
		}

		// Shared behavior for radio-selected panels: Display Conditions
		// (.wpci-condition-radio / .wpci-condition-panel / data-condition)
		// and the code source toggle (.wpci-source-radio / .wpci-source-panel
		// / data-source) both show only the panel matching the checked radio.
		var wireRadioPanels = function( radioClass, panelClass, dataAttr, defaultValue ) {
			var radios = document.querySelectorAll( '.' + radioClass );
			var panels = document.querySelectorAll( '.' + panelClass );

			if ( ! radios.length || ! panels.length ) {
				return;
			}

			var update = function() {
				var checked = document.querySelector( '.' + radioClass + ':checked' );
				var value   = checked ? checked.value : defaultValue;

				for ( var i = 0; i < panels.length; i++ ) {
					panels[ i ].style.display = ( panels[ i ].getAttribute( 'data-' + dataAttr ) === value ) ? '' : 'none';
				}
			};

			for ( var j = 0; j < radios.length; j++ ) {
				radios[ j ].addEventListener( 'change', update );
			}
		};

		wireRadioPanels( 'wpci-condition-radio', 'wpci-condition-panel', 'condition', 'all' );
		wireRadioPanels( 'wpci-source-radio', 'wpci-source-panel', 'source', 'inline' );

		// Live search filter above the specific-pages / categories checkbox
		// lists: typing hides any label whose text doesn't match.
		var filterInputs = document.querySelectorAll( '.wpci-checkbox-filter' );

		for ( var k = 0; k < filterInputs.length; k++ ) {
			filterInputs[ k ].addEventListener( 'input', function( e ) {
				var query  = e.target.value.toLowerCase();
				var list   = e.target.parentElement.querySelector( '.wpci-checkbox-list' );
				if ( ! list ) {
					return;
				}
				var labels = list.querySelectorAll( 'label' );
				for ( var m = 0; m < labels.length; m++ ) {
					var matches = '' === query || labels[ m ].textContent.toLowerCase().indexOf( query ) !== -1;
					labels[ m ].classList.toggle( 'wpci-hidden', ! matches );
				}
			} );
		}
	} );
}( jQuery ) );
