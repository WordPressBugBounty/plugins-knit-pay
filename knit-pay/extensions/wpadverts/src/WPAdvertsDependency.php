<?php

/**
 * Title: WP Adverts Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.0.0
 */

namespace KnitPay\Extensions\WPAdverts;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class WPAdvertsDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'ADVERTS_FILE' ) && \defined( 'KNIT_PAY_WP_ADVERTS' );
	}
}
