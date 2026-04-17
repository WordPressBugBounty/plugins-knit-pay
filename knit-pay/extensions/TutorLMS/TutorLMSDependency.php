<?php

/**
 * Title: Tutor LMS Dependency
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */

namespace KnitPay\Extensions\TutorLMS;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class TutorLMSDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return defined( 'TUTOR_VERSION' );
	}
}
