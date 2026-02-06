<?php

/**
 * Title: BookingPress extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.90.0.0
 */

namespace KnitPay\Extensions\Bookingpress;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class BookingpressDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'BOOKINGPRESS_DIR' );
	}
}
