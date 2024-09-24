<?php

/**
 * Title: CampTix Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.74.0.0
 */

namespace KnitPay\Extensions\Camptix;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class CamptixDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\CampTix_Plugin' );
	}
}
