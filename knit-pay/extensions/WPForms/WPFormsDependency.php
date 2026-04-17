<?php

namespace KnitPay\Extensions\WPForms;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

/**
 * Title: WPForms Dependency
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 */
class WPFormsDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return function_exists( 'wpforms' ) && wpforms()->is_pro() && defined( 'KNIT_PAY_WPFORMS' );
	}
}
