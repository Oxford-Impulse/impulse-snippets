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

		// Type-to-search picker for the "Specific pages or posts" condition.
		// Queries the wpci/v1/posts/search REST route (debounced); each pick
		// appends a row carrying a hidden input, which is what actually
		// submits. Rows render server-side for already-targeted posts.
		var searchInput  = document.getElementById( 'wpci-post-search' );
		var resultsBox   = document.getElementById( 'wpci-post-search-results' );
		var selectedList = document.getElementById( 'wpci-selected-posts' );

		if ( searchInput && resultsBox && selectedList && 'undefined' !== typeof wp && wp.apiFetch ) {
			var debounceTimer = null;

			var isSelected = function( id ) {
				return !! selectedList.querySelector( 'li[data-id="' + id + '"]' );
			};

			var addSelected = function( id, label ) {
				if ( isSelected( id ) ) {
					return;
				}
				var li = document.createElement( 'li' );
				li.setAttribute( 'data-id', id );

				var span = document.createElement( 'span' );
				span.textContent = label;

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'wpci-remove-post button-link';
				remove.setAttribute( 'aria-label', 'Remove' );
				remove.innerHTML = '&times;';

				var hidden = document.createElement( 'input' );
				hidden.type  = 'hidden';
				hidden.name  = 'wpci_condition_post_ids[]';
				hidden.value = id;

				li.appendChild( span );
				li.appendChild( remove );
				li.appendChild( hidden );
				selectedList.appendChild( li );
			};

			var hideResults = function() {
				resultsBox.style.display = 'none';
				resultsBox.innerHTML = '';
			};

			var showResults = function( items ) {
				resultsBox.innerHTML = '';
				if ( ! items.length ) {
					hideResults();
					return;
				}
				for ( var i = 0; i < items.length; i++ ) {
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'wpci-picker-result';
					btn.textContent = items[ i ].title + ' (' + items[ i ].type + ')';
					btn.setAttribute( 'data-id', items[ i ].id );
					resultsBox.appendChild( btn );
				}
				resultsBox.style.display = '';
			};

			searchInput.addEventListener( 'input', function() {
				var term = searchInput.value.trim();
				clearTimeout( debounceTimer );
				if ( term.length < 2 ) {
					hideResults();
					return;
				}
				debounceTimer = setTimeout( function() {
					wp.apiFetch( { path: 'wpci/v1/posts/search?term=' + encodeURIComponent( term ) } )
						.then( showResults )
						.catch( hideResults );
				}, 300 );
			} );

			resultsBox.addEventListener( 'click', function( e ) {
				var btn = e.target.closest ? e.target.closest( '.wpci-picker-result' ) : null;
				if ( ! btn ) {
					return;
				}
				addSelected( btn.getAttribute( 'data-id' ), btn.textContent );
				hideResults();
				searchInput.value = '';
				searchInput.focus();
			} );

			selectedList.addEventListener( 'click', function( e ) {
				if ( e.target.classList && e.target.classList.contains( 'wpci-remove-post' ) ) {
					var li = e.target.parentElement;
					li.parentElement.removeChild( li );
				}
			} );

			// Typing elsewhere shouldn't leave a stale dropdown open.
			document.addEventListener( 'click', function( e ) {
				if ( ! resultsBox.contains( e.target ) && e.target !== searchInput ) {
					hideResults();
				}
			} );
		}

		// Live search filter above the categories checkbox
		// list: typing hides any label whose text doesn't match.
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
