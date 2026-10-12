/**
 * Audit issues table actions.
 *
 * Generation is delegated to the plugin's existing generator action, so
 * credits, quota, trial gating and error handling all behave exactly as they do
 * everywhere else. This file adds no generation logic of its own.
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

( function () {
	'use strict';

	var config = window.bbaiAuditIssues || {};
	var strings = config.strings || {};

	var table = document.querySelector( '[data-bbai-audit-issues]' );

	if ( ! table || ! config.ajaxUrl || ! config.nonce ) {
		return;
	}

	function t( key, fallback ) {
		return Object.prototype.hasOwnProperty.call( strings, key ) ? strings[ key ] : fallback;
	}

	function post( action, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		Object.keys( fields || {} ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
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
						throw new Error( ( payload && payload.data && payload.data.message ) || t( 'genericError', 'Something went wrong.' ) );
					}
					return payload.data;
				} );
			} );
	}

	function setRowStatus( row, message, tone ) {
		var status = row.querySelector( '[data-bbai-audit-row-status]' );

		if ( ! status ) {
			return;
		}

		status.textContent = message || '';
		status.className = 'bbai-audit-issues__row-status' + ( tone ? ' is-' + tone : '' );
	}

	/**
	 * Record a UI event through the plugin's existing telemetry endpoint.
	 * Fire-and-forget: telemetry must never block or break an action.
	 */
	function track( event, properties ) {
		if ( ! config.telemetry ) {
			return;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'beepbeepai_telemetry' );
		body.append( 'nonce', config.nonce );
		body.append( 'events', JSON.stringify( [ { event: event, properties: properties || {} } ] ) );

		window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
			.catch( function () {
				// Telemetry is best-effort by design.
			} );
	}

	function issueTypeOf( row ) {
		var badge = row.querySelector( '[data-bbai-audit-findings] .bbai-audit-badge' );
		return badge ? ( badge.getAttribute( 'data-code' ) || 'unknown' ) : 'none';
	}

	table.addEventListener( 'click', function ( event ) {
		var upgrade = event.target.closest( '[data-bbai-audit-upgrade]' );

		if ( upgrade ) {
			track( 'image_audit_upgrade_cta_clicked', {
				placement: upgrade.getAttribute( 'data-bbai-audit-upgrade' )
			} );
			return;
		}

		var decorative = event.target.closest( '[data-bbai-audit-decorative]' );

		if ( decorative ) {
			event.preventDefault();
			handleDecorative( decorative );
			return;
		}

		var dismiss = event.target.closest( '[data-bbai-audit-dismiss]' );

		if ( dismiss ) {
			event.preventDefault();
			handleDismiss( dismiss );
			return;
		}

		var button = event.target.closest( '[data-bbai-audit-generate]' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var row = button.closest( '[data-bbai-audit-row]' );
		var attachmentId = button.getAttribute( 'data-bbai-audit-generate' );

		if ( ! row || ! attachmentId ) {
			return;
		}

		track( 'image_issue_action_clicked', {
			action_type: 'generate',
			issue_type: issueTypeOf( row )
		} );

		button.disabled = true;
		setRowStatus( row, t( 'generating', 'Generating…' ), 'busy' );

		// The plugin's own generator — not a copy of it.
		post( 'beepbeepai_regenerate_single', { attachment_id: attachmentId } )
			.then( function ( data ) {
				var altText = data.alt_text || data.altText || '';
				var cell = row.querySelector( '[data-bbai-audit-alt]' );

				if ( cell && altText ) {
					cell.textContent = altText;
				}

				setRowStatus( row, t( 'generated', 'Alt text updated.' ), 'success' );

				// Re-audit just this image so its findings and the score stay honest.
				return post( 'bbai_audit_rescan', { attachment_id: attachmentId } );
			} )
			.then( function ( result ) {
				if ( result && result.resolved ) {
					setRowStatus( row, t( 'resolved', 'Resolved.' ), 'success' );
					row.setAttribute( 'data-resolved', '1' );
				}
				button.disabled = false;
			} )
			.catch( function ( error ) {
				button.disabled = false;
				setRowStatus( row, error.message, 'error' );
			} );
	} );

	function handleDecorative( button ) {
		var row = button.closest( '[data-bbai-audit-row]' );
		var attachmentId = button.getAttribute( 'data-bbai-audit-decorative' );
		var wantsDecorative = button.getAttribute( 'data-decorative' ) === '1';

		if ( ! row || ! attachmentId ) {
			return;
		}

		track( 'image_issue_action_clicked', {
			action_type: wantsDecorative ? 'mark_decorative' : 'unmark_decorative',
			issue_type: issueTypeOf( row )
		} );

		button.disabled = true;
		setRowStatus( row, t( 'saving', 'Saving…' ), 'busy' );

		post( 'bbai_audit_decorative', {
			attachment_id: attachmentId,
			decorative: wantsDecorative ? '1' : '0'
		} )
			.then( function () {
				setRowStatus(
					row,
					wantsDecorative
						? t( 'markedDecorative', 'Marked as decorative.' )
						: t( 'unmarkedDecorative', 'No longer decorative.' ),
					'success'
				);
				row.setAttribute( 'data-resolved', wantsDecorative ? '1' : '0' );
				button.disabled = false;
			} )
			.catch( function ( error ) {
				button.disabled = false;
				setRowStatus( row, error.message, 'error' );
			} );
	}

	function handleDismiss( button ) {
		var row = button.closest( '[data-bbai-audit-row]' );
		var attachmentId = button.getAttribute( 'data-bbai-audit-dismiss' );

		if ( ! row || ! attachmentId ) {
			return;
		}

		if ( ! window.confirm( t( 'dismissConfirm', 'Hide this finding? The image itself is not changed.' ) ) ) {
			return;
		}

		track( 'image_issue_action_clicked', {
			action_type: 'dismiss',
			issue_type: issueTypeOf( row )
		} );

		button.disabled = true;
		setRowStatus( row, t( 'saving', 'Saving…' ), 'busy' );

		post( 'bbai_audit_dismiss', { attachment_id: attachmentId } )
			.then( function () {
				setRowStatus( row, t( 'dismissed', 'Finding dismissed.' ), 'success' );
				row.setAttribute( 'data-resolved', '1' );
				button.disabled = false;
			} )
			.catch( function ( error ) {
				button.disabled = false;
				setRowStatus( row, error.message, 'error' );
			} );
	}
} )();
