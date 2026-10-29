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
 * The shared WB tab popup editor also gets its own toolbar and skill selector.
 */
( function ( $, window, undefined ) {
	'use strict';

	var CTAI = window.DressCodeAI || null;
	if ( ! CTAI ) {
		return;
	}

	var $select;

	/**
	 * Get the requested TinyMCE editor instance (main content by default).
	 */
	function getEditor( editorId ) {
		try {
			if ( window.tinymce ) {
				return window.tinymce.get( editorId || 'content' );
			}
		} catch ( e ) {}
		return null;
	}

	/**
	 * Whether the visual editor is currently active.
	 */
	function isVisualActive( editorId ) {
		var ed = getEditor( editorId );
		return ! ! ( ed && ed.initialized && ! ed.isHidden() );
	}

	/**
	 * Gather the content to optimize: selection if any, else full content.
	 */
	function getContent( editorId ) {
		editorId = editorId || 'content';
		var ed = getEditor( editorId );
		if ( isVisualActive( editorId ) ) {
			var sel = '';
			try { sel = ed.selection.getContent( { format: 'html' } ); } catch ( e ) {}
			if ( sel && sel.trim() ) {
				var bookmark = null;
				try { bookmark = ed.selection.getBookmark( 2, true ); } catch ( e ) {}
				return { mode: 'visual', has: true, selected: sel, full: ed.getContent(), bookmark: bookmark };
			}
			var content = ed.getContent();
			return { mode: 'visual', has: false, selected: content, full: content };
		}

		var el = document.getElementById( editorId );
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
		var editorId = ctx.editorId || 'content';
		var ed = getEditor( editorId );
		if ( ctx.mode === 'visual' && ed ) {
			if ( ctx.has ) {
				if ( ctx.bookmark ) {
					ed.selection.moveToBookmark( ctx.bookmark );
				}
				ed.selection.setContent( html );
			} else {
				ed.setContent( html );
			}
			var ta = document.getElementById( editorId );
			if ( ta ) {
				ta.value = ed.getContent();
			}
			return;
		}

		var el = document.getElementById( editorId );
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
			// In Text mode the textarea is authoritative. Calling setContent on
			// a hidden TinyMCE instance can rewrite or discard the HTML just
			// inserted there; WordPress syncs it when Visual mode is opened.
			if ( ed && ! ed.isHidden() ) {
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
	 * Persistent error banner. Request results can arrive minutes after the
	 * click (reasoning models are slow), so a 3-second toast is easy to miss
	 * entirely — errors stay on screen until dismissed.
	 */
	var $banner;
	function showError( msg ) {
		if ( ! $banner ) {
			$banner = $( '<div id="dresscode-error-banner" class="dresscode-error-banner" />' ).appendTo( 'body' );
			$( '<span class="dresscode-error-text" />' ).appendTo( $banner );
			$( '<button type="button" class="dresscode-error-close" aria-label="close">&times;</button>' )
				.on( 'click', function () {
					$banner.removeClass( 'dresscode-error-banner-show' );
				} )
				.appendTo( $banner );
		}
		$banner.find( '.dresscode-error-text' ).text( CTAI.i18n.errorTitle + '：' + msg );
		$banner.addClass( 'dresscode-error-banner-show' );
	}

	function clearError() {
		if ( $banner ) {
			$banner.removeClass( 'dresscode-error-banner-show' );
		}
	}

	/**
	 * Turn a failed HTTP request into a readable message: prefer the JSON body
	 * from wp_send_json_error(), otherwise explain by HTTP status code.
	 */
	function requestErrorMessage( xhr ) {
		var body = ( xhr && 'string' === typeof xhr.responseText ) ? $.trim( xhr.responseText ) : '';
		var status = xhr ? xhr.status : 0;
		var msg = null;

		// WordPress dies with a plain "-1" body when the nonce check fails.
		if ( '-1' === body ) {
			return CTAI.i18n.nonceExpired;
		}

		if ( '{' === body.charAt( 0 ) ) {
			try {
				var j = JSON.parse( body );
				if ( 'string' === typeof j.data ) {
					msg = j.data;
				} else if ( j.data && j.data.message ) {
					msg = String( j.data.message );
				}
			} catch ( err ) {}
		}

		if ( ! msg ) {
			if ( 0 === status ) {
				msg = CTAI.i18n.networkError;
			} else if ( 502 === status || 503 === status || 504 === status ) {
				msg = CTAI.i18n.gatewayTimeout.replace( '%s', status );
			} else {
				msg = CTAI.i18n.httpError.replace( '%s', status );
				if ( body && body.length <= 200 && '{' !== body.charAt( 0 ) && '<' !== body.charAt( 0 ) ) {
					msg += '：' + body;
				}
			}
		}
		return msg;
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
	 * Use the clicked editor's skill, then the main toolbar or default skill.
	 */
	function currentSkillId( $button ) {
		var $localSelect = $button && $button.closest( '.dresscode-bar' ).find( '.dresscode-skill' );
		if ( $localSelect && $localSelect.length ) {
			return $localSelect.val();
		}
		if ( $select && $select.val() ) {
			return $select.val();
		}
		return CTAI.defaultSkillId || 0;
	}

	/**
	 * Shared request flow: optimize `content` via AJAX, then hand the
	 * optimized HTML to `applyFn`. Each button tracks its own request.
	 */
	function sendAndApply( content, applyFn, $button ) {
		if ( $button.data( 'dresscode-busy' ) ) {
			return;
		}
		$button.data( 'dresscode-busy', true );
		setLoading( true, $button );

		// Reasoning models can legitimately take minutes; ping the user so a
		// slow request is not mistaken for a silent failure.
		var pings = [ 45, 120, 210 ].map( function ( sec ) {
			return setTimeout( function () {
				toast( CTAI.i18n.stillWorking, false );
			}, sec * 1000 );
		} );

		$.post( CTAI.ajaxUrl, {
			action: CTAI.action,
			nonce: CTAI.nonce,
			content: content,
			skill_id: currentSkillId( $button )
		} ).done( function ( res ) {
			if ( res && res.success && res.data && res.data.html ) {
				if ( applyFn( res.data.html ) !== false ) {
					var message = CTAI.i18n.done;
					if ( typeof res.data.elapsed_ms === 'number' ) {
						message += ' (' + ( res.data.method === 'local' ? CTAI.i18n.localMethod : CTAI.i18n.aiMethod ) + ', ' + ( res.data.elapsed_ms / 1000 ).toFixed( 1 ) + 's)';
					}
					toast( message, false );
				}
			} else {
				var msg = CTAI.i18n.failed;
				if ( res && res.data ) {
					msg = 'string' === typeof res.data ? res.data : String( res.data.message || CTAI.i18n.failed );
				}
				showError( msg );
			}
		} ).fail( function ( xhr ) {
			showError( requestErrorMessage( xhr ) );
		} ).always( function () {
			pings.forEach( function ( id ) {
				clearTimeout( id );
			} );
			$button.removeData( 'dresscode-busy' );
			setLoading( false, $button );
		} );
	}

	/**
	 * Click handler for the main editor button: gather content and call AJAX.
	 */
	function onOptimize( e, editorId ) {
		e.preventDefault();

		editorId = editorId || 'content';
		var $button = $( this );
		var ctx = getContent( editorId );
		if ( ! ctx || ! ctx.selected || ! $.trim( ctx.selected ) ) {
			toast( CTAI.i18n.empty, true );
			return;
		}

		ctx.editorId = editorId;
		// WB reuses one popup editor for all tabs. Discard a late response if
		// the user has finished editing or opened another tab in the meantime.
		var $save = $( '.wb_cptb_tab_save_btn' );
		var targetTab = editorId === 'wb_tab_editor' ? $save.data( 'target-tab' ) : null;
		sendAndApply( ctx.selected, function ( html ) {
			if ( editorId === 'wb_tab_editor' &&
				( ! $button.closest( '.wb_cptb_tab_edit_popup' ).is( ':visible' ) ||
					$save.data( 'target-tab' ) !== targetTab ) ) {
				showError( CTAI.i18n.tabChanged );
				return false;
			}
			var current = getContent( editorId );
			if ( ! current || current.full !== ctx.full ) {
				showError( CTAI.i18n.contentChanged );
				return false;
			}
			applyResult( ctx, html );
		}, $button );
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
			if ( ! document.documentElement.contains( ta ) || ta.value !== val ) {
				showError( CTAI.i18n.contentChanged );
				return false;
			}
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
	function buildBar( editorId ) {
		editorId = editorId || 'content';
		var suffix = editorId === 'content' ? '' : '-' + editorId;
		if ( $( '#dresscode-bar' + suffix ).length ) {
			return;
		}

		var $editorBar = $( '<span class="dresscode-bar" />' ).attr( 'id', 'dresscode-bar' + suffix );

		var sel = $( '<select />' ).attr( {
			id: 'dresscode-skill' + suffix,
			'class': 'dresscode-skill',
			'title': CTAI.i18n.skillLabel,
			'aria-label': CTAI.i18n.skillLabel,
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
		if ( editorId === 'content' ) {
			$select = sel;
		}

		var $editorButton = $( '<button type="button" />' ).attr( {
			id: 'dresscode-btn' + suffix,
			'class': 'button button-primary dresscode-btn',
		} ).html( buttonHtml() );

		$editorBar.append( sel ).append( $editorButton );

		var $target = $( '#wp-' + editorId + '-media-buttons' );
		if ( ! $target.length ) {
			$target = $( '#wp-' + editorId + '-editor-tools' );
		}
		if ( $target.length ) {
			$target.append( $editorBar );
		} else {
			// Fallback: float top-right of the editor container.
			var $c = $( '#wp-' + editorId + '-editor-container' );
			if ( ! $c.length ) {
				$c = $( '#wp-' + editorId + '-wrap' );
			}
			if ( $c.length ) {
				$c.css( 'position', 'relative' );
				$c.append( $editorBar );
				$editorBar.addClass( 'dresscode-floating' );
			}
		}

		$editorButton.on( 'click', function ( e ) {
			onOptimize.call( this, e, editorId );
		} );
	}

	function init() {
		// Classic editor present?
		if ( $( '#content' ).length || $( '#wp-content-media-buttons' ).length ) {
			buildBar();
		}
		// WooCommerce product tabs (present even without the classic editor).
		setupTabButtons();
		if ( $( '#wb_tab_editor' ).length ) {
			buildBar( 'wb_tab_editor' );
		}
	}

	// Wait for the editor DOM, with retries for late-rendered editors.
	var tries = 0;
	var inited = false;
	function tryInit() {
		if ( inited ) {
			return;
		}
		if ( $( '#content' ).length || $( '#wp-content-media-buttons' ).length || $( '.wb_tab_main_inner' ).length || $( '#wb_tab_editor' ).length ) {
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
