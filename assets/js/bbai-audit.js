/**
 * Image audit run controller.
 *
 * Pumps the scan by polling: start, then tick until done. Each tick processes
 * one batch server-side, so nothing here holds a long request open.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

( function () {
	'use strict';

	var config = window.bbaiAudit || {};
	var strings = config.strings || {};

	var STATE_RUNNING = 'running';
	var STATE_QUEUED = 'queued';

	/** Backs off after repeated failures rather than hammering a struggling site. */
	var MAX_CONSECUTIVE_ERRORS = 3;

	var root = document.querySelector( '[data-bbai-audit-run]' );

	if ( ! root || ! config.ajaxUrl || ! config.nonce ) {
		return;
	}

	var els = {
		start: root.querySelector( '[data-bbai-audit-action="start"]' ),
		resume: root.querySelector( '[data-bbai-audit-action="resume"]' ),
		cancel: root.querySelector( '[data-bbai-audit-action="cancel"]' ),
		progress: root.querySelector( '[data-bbai-audit-progress]' ),
		bar: root.querySelector( '[data-bbai-audit-bar]' ),
		fill: root.querySelector( '.bbai-audit-run__fill' ),
		status: root.querySelector( '[data-bbai-audit-status]' ),
		error: root.querySelector( '[data-bbai-audit-error]' )
	};

	var runId = root.getAttribute( 'data-run-id' ) || '';
	var polling = false;
	var errorCount = 0;

	function t( key, fallback ) {
		return Object.prototype.hasOwnProperty.call( strings, key ) ? strings[ key ] : fallback;
	}

	function formatNumber( value ) {
		try {
			return new Intl.NumberFormat().format( value );
		} catch ( e ) {
			return String( value );
		}
	}

	function show( el, visible ) {
		if ( ! el ) {
			return;
		}
		if ( visible ) {
			el.removeAttribute( 'hidden' );
		} else {
			el.setAttribute( 'hidden', 'hidden' );
		}
	}

	function showError( message ) {
		if ( ! els.error ) {
			return;
		}
		els.error.textContent = message || '';
		show( els.error, Boolean( message ) );
	}

	function request( action, extra ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( extra || {} ).forEach( function ( key ) {
			body.append( key, extra[ key ] );
		} );

		return window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
			.then( function ( response ) {
				return response.json().then( function ( payload ) {
					if ( ! payload || ! payload.success ) {
						var error = new Error( ( payload && payload.data && payload.data.message ) || t( 'genericError', 'Something went wrong.' ) );
						error.payload = payload && payload.data ? payload.data : {};
						throw error;
					}
					return payload.data;
				} );
			} );
	}

	function render( state ) {
		if ( ! state ) {
			return;
		}

		runId = state.run_id || runId;
		root.setAttribute( 'data-status', state.status || '' );
		root.setAttribute( 'data-run-id', runId );

		var isRunning = state.status === STATE_RUNNING || state.status === STATE_QUEUED;
		var percent = Math.max( 0, Math.min( 100, parseInt( state.percent, 10 ) || 0 ) );

		show( els.progress, isRunning );
		show( els.cancel, isRunning );
		show( els.resume, Boolean( state.can_resume ) && ! isRunning );

		if ( els.start ) {
			els.start.disabled = isRunning;
		}

		if ( els.fill ) {
			els.fill.style.width = percent + '%';
		}

		if ( els.bar ) {
			els.bar.setAttribute( 'aria-valuenow', String( percent ) );
		}

		if ( els.status ) {
			els.status.textContent = ( t( 'progress', 'Scanned %1$s of %2$s images (%3$d%%).' ) )
				.replace( '%1$s', formatNumber( state.processed_images || 0 ) )
				.replace( '%2$s', formatNumber( state.total_images || 0 ) )
				.replace( '%3$d', String( percent ) );
		}

		if ( state.done && ! isRunning ) {
			polling = false;
			// The score, counts and issue list are all server-rendered, so a reload
			// is the honest way to show the finished result rather than half-updating.
			window.location.reload();
		}
	}

	function tick() {
		if ( ! polling ) {
			return;
		}

		request( 'bbai_audit_tick', { run_id: runId } )
			.then( function ( state ) {
				errorCount = 0;
				showError( '' );
				render( state );

				if ( polling ) {
					window.setTimeout( tick, 250 );
				}
			} )
			.catch( function ( error ) {
				errorCount++;

				if ( errorCount >= MAX_CONSECUTIVE_ERRORS ) {
					polling = false;
					showError( error.message + ' ' + t( 'resumeHint', 'You can resume the audit to carry on from where it stopped.' ) );
					show( els.resume, true );
					show( els.cancel, false );
					if ( els.start ) {
						els.start.disabled = false;
					}
					return;
				}

				// Transient failures get a couple of retries before giving up.
				window.setTimeout( tick, 1500 );
			} );
	}

	function begin( action, extra ) {
		showError( '' );

		if ( els.start ) {
			els.start.disabled = true;
		}

		request( action, extra )
			.then( function ( state ) {
				errorCount = 0;
				render( state );

				if ( ! state.done ) {
					polling = true;
					tick();
				}
			} )
			.catch( function ( error ) {
				if ( els.start ) {
					els.start.disabled = false;
				}

				// A refused start means a run already exists; offer to resume it.
				if ( error.payload && error.payload.state && error.payload.state.can_resume ) {
					render( error.payload.state );
					show( els.resume, true );
				}

				showError( error.message );
			} );
	}

	if ( els.start ) {
		els.start.addEventListener( 'click', function () {
			var needsConfirm = Boolean( els.resume ) && ! els.resume.hasAttribute( 'hidden' );

			if ( needsConfirm && ! window.confirm( t( 'restartConfirm', 'Start a new audit and discard the partial results from the interrupted one?' ) ) ) {
				return;
			}

			begin( 'bbai_audit_start', needsConfirm ? { restart: '1' } : {} );
		} );
	}

	if ( els.resume ) {
		els.resume.addEventListener( 'click', function () {
			begin( 'bbai_audit_resume', {} );
		} );
	}

	if ( els.cancel ) {
		els.cancel.addEventListener( 'click', function () {
			polling = false;

			request( 'bbai_audit_cancel', {} )
				.then( render )
				.catch( function ( error ) {
					showError( error.message );
				} );
		} );
	}

	// A run left in flight by a closed tab keeps its state; pick it back up.
	if ( root.getAttribute( 'data-status' ) === STATE_RUNNING && ! config.isStale ) {
		polling = true;
		tick();
	}
} )();
