<?php

/**
 * Title: MotoPress Hotel Booking Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.6.0
 */

namespace KnitPay\Extensions\MotopressHotelBooking;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class MotoPressHotelBookingDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'KNIT_PAY_MOTOPRESS_PRESS_HOTEL_BOOKING' ) && \class_exists( '\HotelBookingPlugin' );
	}
}
