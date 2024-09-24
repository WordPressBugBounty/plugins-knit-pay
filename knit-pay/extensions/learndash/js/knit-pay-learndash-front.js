jQuery( document ).ready( function ( $ ) {
	'use strict';

	$( '.learndash-payment-gateway-form-knit_pay' ).on(
		'submit.knit_pay',
		function ( e ) {
			const $form = $( this );
			const $button = $( this ).find( 'input[type="submit"]' );

			$form.addClass( 'ld-loading' );
			$button.attr( 'disabled', true );

			$.ajax( {
				type: 'POST',
				url: learndash_payments.ajaxurl,
				dataType: 'json',
				data: $( this ).data(),
			} ).done( function ( msg ) {
				if ( msg.success ) {
					window.location.replace( msg.data.redirect_url );
				} else {
					alert( msg.data.message );
				}
				$form.removeClass( 'ld-loading' );
				$button.removeAttr( 'disabled' );
			} );

			e.preventDefault();
		}
	);
} );
