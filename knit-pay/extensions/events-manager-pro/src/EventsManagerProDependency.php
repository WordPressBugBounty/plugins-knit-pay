<?php

/**
 * Title: Events Manager Pro Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.2.0
 */

namespace KnitPay\Extensions\EventsManagerPro;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class EventsManagerProDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'EMP_VERSION' ) && \defined( 'EM_VERSION' ) && \defined( 'KNIT_PAY_EVENTS_MANAGER_PRO' );
	}
}
