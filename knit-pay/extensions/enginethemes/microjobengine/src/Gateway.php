<?php

namespace KnitPay\Extensions\EngineThemes\MicroJobEngine;

use KnitPay\Extensions\EngineThemes\Gateway as EngineThemesGateway;

/**
 * Title: Micro Job Engine Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.61.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends EngineThemesGateway {
	/**
	 * Bootstrap
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'mje_after_payment_list', [ $this, 'mje_render_button' ], 0 );
		add_filter( 'mje_render_payment_name', [ $this, 'render_payment_name' ], 0 );
	}

	/**
	 * Filter payment name
	 *
	 * @param array $payment_name
	 * @return array $payment_name
	 */
	function render_payment_name( $payment_name ) {
		$key = $this->id;

		$name      = $this->get_title();
		$icon_path = $this->get_icon_path();

		$payment_name[ $key ] = "<p class='payment-name {$key}' title='{$name}'><img src='{$icon_path}'/><span>{$name}</span></p>";

		return $payment_name;
	}

	public function mje_render_button() {
		if ( isset( $this->setting['enable'] ) && $this->setting['enable'] ) :
			?>
			<li>
				<div class="outer-payment-items hvr-underline-from-left">
					<a href="#" class="btn-submit-price-plan select-payment" data-type="<?php echo $this->id; ?>">
						<img src="<?php echo $this->get_icon_path(); ?>" alt="" style="height: 75px;">
						<p><?php echo $this->get_title(); ?></p>
					</a>
				</div>
			</li>
			<?php
		endif;
	}
}
