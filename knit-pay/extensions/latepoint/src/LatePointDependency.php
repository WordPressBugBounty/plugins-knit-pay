<?php

/**
 * Title: LatePoint extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.4.0
 */

namespace KnitPay\Extensions\LatePoint;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class LatePointDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( 'LatePoint' ) && \defined( 'KNIT_PAY_LATEPOINT' );
	}
}
