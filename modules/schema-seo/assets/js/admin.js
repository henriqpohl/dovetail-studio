( function ( $ ) {
	'use strict';

	const $form = $( '.dovetail-wrap form' );
	const $loadJson = $( '#dovetail-load-json' );
	const $copyJson = $( '#dovetail-copy-json' );
	const $jsonOutput = $( '#dovetail-json-output' );
	const $jsonStatus = $( '#dovetail-json-status' );

	function setJsonStatus( message, state ) {
		$jsonStatus
			.removeClass( 'is-loading is-success is-error is-warning' )
			.addClass( state ? 'is-' + state : '' )
			.text( message );
	}

	function parseSchemaFromHtml( html ) {
		const documentHtml = new DOMParser().parseFromString( html, 'text/html' );
		const scripts = Array.from( documentHtml.querySelectorAll( 'script[type="application/ld+json"]' ) );
		const script = scripts.find( ( item ) => item.classList.contains( 'yoast-schema-graph' ) )
			|| scripts.find( ( item ) => item.classList.contains( 'local-business-schema' ) )
			|| scripts.find( ( item ) => item.textContent.includes( '"@graph"' ) );

		if ( ! script ) {
			throw new Error( 'schema-not-found' );
		}

		return JSON.stringify( JSON.parse( script.textContent.trim() ), null, 2 );
	}

	async function loadSchemaDirectly() {
		const previewUrl = new URL( lbsAdmin.previewUrl, window.location.href );
		previewUrl.searchParams.set( '_lbs_schema_preview', Date.now().toString() );

		const response = await fetch( previewUrl.toString(), {
			cache: 'no-store',
			credentials: 'same-origin',
			headers: { Accept: 'text/html' }
		} );

		if ( ! response.ok ) {
			throw new Error( 'homepage-request-failed' );
		}

		return parseSchemaFromHtml( await response.text() );
	}

	async function loadSchemaThroughWordPress() {
		const body = new URLSearchParams( {
			action: 'lbs_schema_preview',
			nonce: lbsAdmin.previewNonce
		} );
		const response = await fetch( lbsAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} );
		const payload = await response.json();

		if ( ! response.ok || ! payload.success || ! payload.data || ! payload.data.json ) {
			throw new Error( 'ajax-preview-failed' );
		}

		return payload.data.json;
	}

	async function copyJsonToClipboard() {
		const json = $jsonOutput.val();
		if ( ! json ) {
			return;
		}

		try {
			if ( navigator.clipboard && window.isSecureContext ) {
				await navigator.clipboard.writeText( json );
			} else {
				$jsonOutput.trigger( 'focus' ).trigger( 'select' );
				if ( ! document.execCommand( 'copy' ) ) {
					throw new Error( 'copy-command-failed' );
				}
			}
			setJsonStatus( lbsAdmin.previewCopied, 'success' );
		} catch ( error ) {
			$jsonOutput.trigger( 'focus' ).trigger( 'select' );
			setJsonStatus( lbsAdmin.previewCopyError, 'error' );
		}
	}

	$( document ).on( 'click', '.dovetail-select-media', function ( event ) {
		event.preventDefault();

		const target = $( this ).data( 'target' );
		const frame = wp.media( {
			title: lbsAdmin.mediaTitle,
			button: { text: lbsAdmin.mediaButton },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#' + target ).val( attachment.url ).trigger( 'change' );
		} );

		frame.open();
	} );

	$form.on( 'input change', ':input', function () {
		$( '.dovetail-save-bar' ).addClass( 'is-dirty' );
		$loadJson.prop( 'disabled', true );
		$copyJson.prop( 'disabled', true );
		$jsonOutput.val( '' );
		setJsonStatus( lbsAdmin.previewUnsaved, 'warning' );
	} );

	$loadJson.on( 'click', async function () {
		$loadJson.prop( 'disabled', true );
		$copyJson.prop( 'disabled', true );
		setJsonStatus( lbsAdmin.previewLoading, 'loading' );

		try {
			let json;
			try {
				json = await loadSchemaDirectly();
			} catch ( directError ) {
				json = await loadSchemaThroughWordPress();
			}

			$jsonOutput.val( json );
			$copyJson.prop( 'disabled', false );
			setJsonStatus( lbsAdmin.previewReady, 'success' );
		} catch ( error ) {
			$jsonOutput.val( '' );
			setJsonStatus( lbsAdmin.previewError, 'error' );
		} finally {
			$loadJson.prop( 'disabled', false );
		}
	} );

	$copyJson.on( 'click', copyJsonToClipboard );
}( jQuery ) );
