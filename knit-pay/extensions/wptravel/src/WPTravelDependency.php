<?php

/**
 * Title: WP Travel extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.78.0.0
 */

namespace KnitPay\Extensions\WPTravel;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class WPTravelDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\WP_Travel' );
	}
}
