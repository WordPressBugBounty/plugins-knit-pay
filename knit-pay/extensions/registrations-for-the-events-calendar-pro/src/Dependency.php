<?php

/**
 * Title: Registrations For The Events Calendar Pro Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.2.0
 */

namespace KnitPay\Extensions\RegistrationsForTheEventsCalendarPro;

use Pronamic\WordPress\Pay\Dependencies\Dependency as Core_Dependency;

class Dependency extends Core_Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return defined( 'KNIT_PAY_RTEC_PRO' ) && class_exists( 'RTEC_Payment' );
	}
}
