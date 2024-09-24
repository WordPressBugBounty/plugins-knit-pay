<?php

/**
 * Title: Team Booking Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.1.0
 */

namespace KnitPay\Extensions\TeamBooking;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class TeamBookingDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\TeamBooking\Loader' ) && \defined( 'KNIT_PAY_TEAM_BOOKING' );
	}
}
