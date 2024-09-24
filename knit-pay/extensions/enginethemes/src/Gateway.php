<?php

namespace KnitPay\Extensions\EngineThemes;

/**
 * Title: Engine Themes Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.7.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Bootstrap
	 */
	public function __construct() {
		$this->setting = ae_get_option( $this->id );
	}

	protected function get_icon_path() {
		$icon_url = $this->setting['icon_url'];
		if ( empty( $icon_url ) ) {
			$icon_url = 'https://plugins.svn.wordpress.org/knit-pay/assets/icon.svg';
		}
		return $icon_url;
	}

	protected function get_title() {
		$name = $this->setting['title'];
		if ( empty( $name ) ) {
			$name = __( 'Pay Online', 'knit-pay-lang' );
		}
		return $name;
	}
	
	public function ae_knit_pay_render_button() {
		if ( isset( $this->setting['enable'] ) && $this->setting['enable'] ) :
			?>
			<li class="panel">
				<span class="title-plan" data-type="<?php echo $this->id; ?>">
					<?php echo $this->get_title(); ?>
					<span><?php echo $this->setting['description']; ?></span>
				</span>
				<a data-toggle="collapse" data-parent="#fre-payment-accordion" href="#fre-payment-<?php echo $this->id; ?>" class="btn collapsed select-payment" data-type="<?php echo $this->id; ?>"><?php _e( 'Select', ET_DOMAIN ); ?></a>
			</li>
			<?php
		endif;
	}
}
