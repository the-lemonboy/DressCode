/**
 * DressCode Tool — Classic Editor integration.
 *
 * Adds an "AI 优化" button + skill dropdown to the classic editor toolbar
 * (the "Add Media" row), visible in both Visual and Text modes. Works on
 * posts, pages and WooCommerce products. Clicking sends the current
 * selection (or full content) to the GLM API via AJAX and writes the
 * optimized HTML back into the editor.
 *
 * On WooCommerce product screens it also injects a per-tab "AI 优化" button
 * into every local tab of the "WB Custom Product Tabs" panel, optimizing
 * that tab's content textarea (tabs added at runtime via "Add new tab" are
 * picked up through a MutationObserver).
 */
( function ( $, window, undefined ) {
	'use strict';

	var CTAI = window.DressCodeAI || null;
	if ( ! CTAI ) {
		return;
	}

	var $bar, $btn, $select;
	var busy = false;

	/**
	 * Get the TinyMCE editor instance for #content (if active).
	 */
	function getEditor() {
		try {
			if ( window.tinymce ) {
				return window.tinymce.get( 'content' );
			}
		} catch ( e ) {}
		return null;
	}

	/**
	 * Whether the visual editor is currently active.
	 */
	function isVisualActive() {
		var ed = getEditor();
		return ! ! ( ed && ed.initialized && ! ed.isHidden() );
	}

	/**
	 * Gather the content to optimize: selection if any, else full content.
	 */
	function getContent() {
		var ed = getEditor();
		if ( isVisualActive() ) {
			var sel = '';
			try { sel = ed.selection.getContent( { format: 'html' } ); } catch ( e ) {}
			if ( sel && sel.trim() ) {
				return { mode: 'visual', has: true, selected: sel };
			}
			return { mode: 'visual', has: false, selected: ed.getContent() };
		}

		var el = document.getElementById( 'content' );
		if ( ! el ) {
			return null;
		}
		var start = el.selectionStart || 0;
		var end = el.selectionEnd || 0;
		var val = el.value || '';
		var has = end > start;
		return {
			mode: 'text',
			has: has,
			start: start,
			end: end,
			full: val,
			selected: has ? val.substring( start, end ) : val,
		};
	}

	/**
	 * Write optimized HTML back, preserving selection scope.
	 */
	function applyResult( ctx, html ) {
		var ed = getEditor();
		if ( ctx.mode === 'visual' && isVisualActive() ) {
			if ( ctx.has ) {
				ed.selection.setContent( html );
			} else {
				ed.setContent( html );
			}
			var ta = document.getElementById( 'content' );
			if ( ta ) {
				ta.value = ed.getContent();
			}
			return;
		}

		var el = document.getElementById( 'content' );
		if ( ! el ) {
			return;
		}
		if ( ctx.has ) {
			var before = ctx.full.substring( 0, ctx.start );
			var after = ctx.full.substring( ctx.end );
			$( el ).val( before + html + after );
			var caret = before.length + html.length;
			try {
				el.focus();
				if ( el.setSelectionRange ) {
					el.setSelectionRange( caret, caret );
				}
			} catch ( e ) {}
		} else {
			$( el ).val( html );
		}

		try {
			if ( ed ) {
				ed.setContent( $( el ).val() );
			}
		} catch ( e ) {}
	}

	/**
	 * Toast notification.
	 */
	var $toast;
	function toast( msg, isError ) {
		if ( ! $toast ) {
			$toast = $( '<div id="dresscode-toast" class="dresscode-toast" />' ).appendTo( 'body' );
		}
		$toast.removeClass( 'dresscode-toast-error' );
		if ( isError ) {
			$toast.addClass( 'dresscode-toast-error' );
		}
		$toast.text( msg ).addClass( 'dresscode-toast-show' );
		clearTimeout( $toast.data( 'timer' ) );
		$toast.data( 'timer', setTimeout( function () {
			$toast.removeClass( 'dresscode-toast-show' );
		}, 3200 ) );
	}

	/**
	 * Sparkle icon shown (animated) while a request is in flight.
	 */
	var ICON =
		'<span class="dresscode-icon" aria-hidden="true">' +
		'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 48 48">' +
		'<path d="M0 0h48v48H0z" fill="none"/>' +
		'<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M25.875 3.944L29.39 17.23a1.94 1.94 0 0 0 1.38 1.379l13.287 3.515c1.924.51 1.924 3.24 0 3.75L30.769 29.39a1.94 1.94 0 0 0-1.379 1.38l-3.515 13.287c-.51 1.924-3.24 1.924-3.75 0L18.61 30.769a1.94 1.94 0 0 0-1.38-1.379L3.944 25.875c-1.924-.51-1.924-3.24 0-3.75l13.288-3.515a1.94 1.94 0 0 0 1.379-1.38l3.515-13.287c.51-1.924 3.24-1.924 3.75 0"/>' +
		'</svg></span>';

	/**
	 * Button inner HTML: animated-while-loading icon + label.
	 */
	function buttonHtml() {
		return ICON + '<span class="dresscode-btn-label">' + CTAI.i18n.buttonLabel + '</span>';
	}

	function setLoading( loading, $button ) {
		$button = $button || $btn;
		if ( ! $button || ! $button.length ) {
			return;
		}
		var $label = $button.find( '.dresscode-btn-label' );
		if ( ! $label.length ) {
			$label = $button;
		}
		$button.prop( 'disabled', loading );
		$button.toggleClass( 'dresscode-loading', !! loading );
		if ( loading ) {
			if ( ! $label.data( 'orig-text' ) ) {
				$label.data( 'orig-text', $label.text() );
			}
			$label.text( CTAI.i18n.loading );
		} else {
			$label.text( $label.data( 'orig-text' ) || CTAI.i18n.buttonLabel );
		}
	}

	/**
	 * Skill id chosen in the main toolbar select (fallback: default skill).
	 */
	function currentSkillId() {
		if ( $select && $select.val() ) {
			return $select.val();
		}
		return CTAI.defaultSkillId || 0;
	}

	/**
	 * Shared request flow: optimize `content` via AJAX, then hand the
	 * optimized HTML to `applyFn`. One request at a time.
	 */
	function sendAndApply( content, applyFn, $button ) {
		if ( ! CTAI.hasApiKey ) {
			toast( CTAI.i18n.noKey, true );
			return;
		}
		if ( busy ) {
			return;
		}
		busy = true;
		setLoading( true, $button );

		$.post( CTAI.ajaxUrl, {
			action: CTAI.action,
			nonce: CTAI.nonce,
			content: content,
			skill_id: currentSkillId()
		} ).done( function ( res ) {
			if ( res && res.success && res.data && res.data.html ) {
				applyFn( res.data.html );
				toast( CTAI.i18n.done, false );
			} else {
				var msg = ( res && res.data ) ? res.data : CTAI.i18n.failed;
				toast( msg, true );
			}
		} ).fail( function ( xhr ) {
			var msg = CTAI.i18n.failed;
			try {
				var j = JSON.parse( xhr.responseText );
				if ( j && j.data ) {
					msg = j.data;
				}
			} catch ( err ) {}
			toast( msg, true );
		} ).always( function () {
			busy = false;
			setLoading( false, $button );
		} );
	}

	/**
	 * Click handler for the main editor button: gather content and call AJAX.
	 */
	function onOptimize( e ) {
		e.preventDefault();

		var ctx = getContent();
		if ( ! ctx || ! ctx.selected || ! $.trim( ctx.selected ) ) {
			toast( CTAI.i18n.empty, true );
			return;
		}

		sendAndApply( ctx.selected, function ( html ) {
			applyResult( ctx, html );
		}, $btn );
	}

	/* ----------------------------------------------------------------------
	 * WB Custom Product Tabs: per-tab AI button
	 * -------------------------------------------------------------------- */

	/**
	 * Click handler for a tab's AI button: optimize that tab's content
	 * textarea (selection if any, else the whole tab content).
	 */
	function onTabOptimize( e ) {
		e.preventDefault();

		var $button = $( this );
		var ta = $button.closest( '.wb_tab_panel' ).find( '.wb_tab_content_input' )[ 0 ];
		if ( ! ta ) {
			return;
		}

		var start = ta.selectionStart || 0;
		var end = ta.selectionEnd || 0;
		var val = ta.value || '';
		var has = end > start;
		var selected = has ? val.substring( start, end ) : val;

		if ( ! selected || ! $.trim( selected ) ) {
			toast( CTAI.i18n.empty, true );
			return;
		}

		sendAndApply( selected, function ( html ) {
			if ( has ) {
				$( ta ).val( val.substring( 0, start ) + html + val.substring( end ) );
			} else {
				$( ta ).val( html );
			}
		}, $button );
	}

	/**
	 * Add an AI button under a local tab's content textarea (idempotent).
	 */
	function injectTabButton( $panel ) {
		if ( ! $panel.is( '.wb_tab_panel_local' ) ) {
			return;
		}
		var $grp = $panel.find( '.wb_tab_content_input' ).closest( '.wb_tab_panel_frmgrp' );
		if ( ! $grp.length || $grp.find( '.dresscode-tab-btn' ).length ) {
			return;
		}
		$( '<div class="dresscode-tab-actions" />' )
			.append(
				$( '<button type="button" />' ).attr( {
					'class': 'button dresscode-btn dresscode-tab-btn',
					'title': CTAI.i18n.buttonLabel,
				} ).html( buttonHtml() )
			)
			.appendTo( $grp );
	}

	/**
	 * Wire up the Custom Tabs panel: buttons for existing tabs + observe the
	 * "Add new tab" flow (panels are cloned and inserted at runtime).
	 */
	function setupTabButtons() {
		var $inner = $( '.wb_tab_main_inner' );
		if ( ! $inner.length || $inner.data( 'dresscode' ) ) {
			return;
		}
		$inner.data( 'dresscode', true );

		$inner.find( '.wb_tab_panel' ).each( function () {
			injectTabButton( $( this ) );
		} );

		if ( window.MutationObserver ) {
			var obs = new MutationObserver( function () {
				$inner.find( '.wb_tab_panel' ).each( function () {
					injectTabButton( $( this ) );
				} );
			} );
			obs.observe( $inner[ 0 ], { childList: true, subtree: true } );
		}
	}

	// Delegated clicks survive panel cloning/re-insertion.
	$( document ).on( 'click', '.dresscode-tab-btn', onTabOptimize );

	/**
	 * Build the AI control (select + button) and inject it into the editor
	 * toolbar's media-buttons row so it shows in both Visual and Text modes.
	 */
	function buildBar() {
		if ( $( '#dresscode-bar' ).length ) {
			return;
		}

		$bar = $( '<span id="dresscode-bar" class="dresscode-bar" />' );

		var sel = $( '<select />' ).attr( {
			id: 'dresscode-skill',
			'class': 'dresscode-skill',
			'title': CTAI.i18n.skillLabel,
		} );

		if ( CTAI.skills && CTAI.skills.length ) {
			$.each( CTAI.skills, function ( i, s ) {
				var $opt = $( '<option />' ).val( s.id ).text( s.name );
				if ( parseInt( s.default, 10 ) || s.id === CTAI.defaultSkillId ) {
					$opt.prop( 'selected', true );
				}
				sel.append( $opt );
			} );
		} else {
			sel.append( $( '<option />' ).val( 0 ).text( CTAI.i18n.skillLabel ) );
		}
		$select = sel;

		$btn = $( '<button type="button" />' ).attr( {
			id: 'dresscode-btn',
			'class': 'button button-primary dresscode-btn',
		} ).html( buttonHtml() );

		$bar.append( sel ).append( $btn );

		var $target = $( '#wp-content-media-buttons' );
		if ( ! $target.length ) {
			$target = $( '#wp-content-editor-tools' );
		}
		if ( $target.length ) {
			$target.append( $bar );
		} else {
			// Fallback: float top-right of the editor container.
			var $c = $( '#wp-content-editor-container' );
			if ( ! $c.length ) {
				$c = $( '#wp-content-wrap' );
			}
			if ( $c.length ) {
				$c.css( 'position', 'relative' );
				$c.append( $bar );
				$bar.addClass( 'dresscode-floating' );
			}
		}

		$btn.on( 'click', onOptimize );
	}

	function init() {
		// Classic editor present?
		if ( $( '#content' ).length || $( '#wp-content-media-buttons' ).length ) {
			buildBar();
		}
		// WooCommerce product tabs (present even without the classic editor).
		setupTabButtons();
	}

	// Wait for the editor DOM, with retries for late-rendered editors.
	var tries = 0;
	var inited = false;
	function tryInit() {
		if ( inited ) {
			return;
		}
		if ( $( '#content' ).length || $( '#wp-content-media-buttons' ).length || $( '.wb_tab_main_inner' ).length ) {
			init();
			inited = true;
			return;
		}
		if ( tries++ < 20 ) {
			setTimeout( tryInit, 250 );
		}
	}

	$( function () {
		setTimeout( tryInit, 200 );
	} );

} )( jQuery, window );
