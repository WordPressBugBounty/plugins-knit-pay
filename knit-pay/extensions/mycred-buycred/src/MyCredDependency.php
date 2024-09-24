<?php

/**
 * Title: myCRED buyCRED Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.5.0
 */

namespace KnitPay\Extensions\MycredBuycred;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class MyCredDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\myCRED_Core' )
			&& \defined( 'KNIT_PAY_MYCRED_BUYCRED' );
	}
}
