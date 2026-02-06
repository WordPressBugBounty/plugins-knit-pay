<?php

/**
 * Title: Uncanny Automator extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */

namespace KnitPay\Extensions\UncannyAutomator;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

/**
 * Title: Uncanny Automator Dependency
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class UncannyAutomatorDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\Uncanny_Automator\Integration' );
	}
}
