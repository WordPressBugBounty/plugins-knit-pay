<?php

/**
 * Title: Restro Press extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.6
 */

namespace KnitPay\Extensions\RestroPress;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class RestroPressDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'KNIT_PAY_RESTRO_PRESS' ) && \class_exists( '\RestroPress' );
	}
}
