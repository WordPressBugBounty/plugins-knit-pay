<?php

/**
 * Title: ProfilePress extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.79.0.0
 */

namespace KnitPay\Extensions\ProfilePress;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class ProfilePressDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'PPRESS_VERSION_NUMBER' );
	}
}
