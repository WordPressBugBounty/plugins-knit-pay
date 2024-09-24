<?php

/**
 * Title: WP Travel Engine extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.9
 */

namespace KnitPay\Extensions\WPTravelEngine;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class WPTravelEngineDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		if ( ! \class_exists( '\Wp_Travel_Engine' ) ) {
			return false;
		}

		return true;
	}
}
