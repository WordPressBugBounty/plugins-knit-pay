jQuery( document ).ready( function ( $ ) {
	'use strict';

	const data = window.knitPayPmproCheckout || {};
	const box = document.getElementById( 'knit_pay_payment_method' );

	$( 'input[name="gateway"][value="knit_pay"]' ).each( function () {
		if ( box && box.contains( this ) ) {
			return;
		}

		const labels = this.labels;
		if ( labels && labels.length ) {
			labels[ 0 ].textContent = data.title || 'Online Payment';
		}
	} );

	if ( ! box ) {
		return;
	}

	const hidden = document.getElementById( 'knit_pay_config_id' );
	const radios = Array.prototype.slice.call(
		box.querySelectorAll( 'input[name="gateway"][data-config-id]' )
	);
	if ( ! radios.length ) {
		return;
	}

	const selectConfig = function ( configId ) {
		const radio =
			radios.filter(
				( item ) => item.getAttribute( 'data-config-id' ) === configId
			)[ 0 ] || radios[ 0 ];
		radio.checked = true;
		if ( hidden ) {
			hidden.value = radio.getAttribute( 'data-config-id' );
		}
	};

	const otherRadios = $( 'input[name="gateway"]' ).filter( function () {
		return ! box.contains( this );
	} );

	if ( otherRadios.filter( ':visible' ).length ) {
		const heading = box.querySelector(
			'.knit-pay-payment-method-heading'
		);
		if ( heading ) {
			heading.textContent =
				data.secondaryTitle || 'Choose Payment Provider';
		}

		radios.forEach( function ( radio ) {
			radio.name = 'knit_pay_config_choice';
		} );

		selectConfig( hidden ? hidden.value : '' );

		const knitPayRadio = otherRadios.filter( function () {
			return 'knit_pay' === this.value;
		} )[ 0 ];

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				if ( hidden ) {
					hidden.value = this.getAttribute( 'data-config-id' );
				}
				if ( knitPayRadio ) {
					knitPayRadio.checked = true;
				}
			} );
		} );

		const sync = function () {
			const checked = document.querySelector(
				'input[name="gateway"]:checked'
			);
			box.style.display =
				checked && 'knit_pay' === checked.value ? '' : 'none';
		};

		otherRadios.on( 'change', sync );
		sync();
	} else {
		selectConfig( hidden ? hidden.value : '' );
	}
} );
