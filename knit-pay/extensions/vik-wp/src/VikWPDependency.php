<?php

/**
 * Title: Vik WP extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   6.69.0.0
 */

namespace KnitPay\Extensions\VikWP;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class VikWPDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\VikBookingLicense' ) && \VikBookingLicense::isPro();
	}
}
