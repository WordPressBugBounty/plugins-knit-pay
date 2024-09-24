<?php

/**
 * Title: Learn Dash LMS extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.7.0
 */

namespace KnitPay\Extensions\LearnDash;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class LearnDashDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return defined( 'LEARNDASH_VERSION' );
	}
}
