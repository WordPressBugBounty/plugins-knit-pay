<?php

/**
 * Title: Bookly Pro Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.4
 */

namespace KnitPay\Extensions\BooklyPro;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class BooklyProDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( 'Bookly\Lib\Base\Plugin' ) && \class_exists( '\BooklyPro\Lib\Plugin' ) && \defined( 'KNIT_PAY_BOOKLY_PRO' );
	}
}
