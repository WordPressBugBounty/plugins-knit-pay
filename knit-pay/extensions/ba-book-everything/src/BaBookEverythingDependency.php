<?php

/**
 * Title: BA Book Everything Dependency
 * Description:
 * Copyright: 2020-2023 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   9.1.0.0
 */

namespace KnitPay\Extensions\BaBookEverything;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class BaBookEverythingDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return class_exists( 'BABE_Settings' );
	}
}
