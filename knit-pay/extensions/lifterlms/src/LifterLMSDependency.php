<?php

/**
 * Title: Lifter LMS extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.8
 */

namespace KnitPay\Extensions\LifterLMS;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class LifterLMSDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		if ( ! \class_exists( '\LifterLMS' ) ) {
			return false;
		}

		return true;
	}
}
