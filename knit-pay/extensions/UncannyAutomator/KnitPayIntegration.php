<?php

namespace KnitPay\Extensions\UncannyAutomator;

/**
 * Title: Uncanny Automator Knit Pay Integration
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class KnitPayIntegration extends \Uncanny_Automator\Integration {
	  
	protected function setup() {
		$this->set_integration( 'KNIT_PAY' );
		$this->set_name( 'Knit Pay' );
		$this->set_icon_url( 'https://plugins.svn.wordpress.org/knit-pay/assets/icon.svg' );
	}
}
