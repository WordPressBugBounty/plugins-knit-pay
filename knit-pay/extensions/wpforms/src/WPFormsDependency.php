<?php

/**
 * Title: WPForms extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 */

namespace KnitPay\Extensions\WPForms;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class WPFormsDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return function_exists( 'wpforms' ) && wpforms()->pro && defined( 'KNIT_PAY_WPFORMS' );
	}
}
