<?php

/**
 * Title: Fluent Forms Dependency
 * Description:
 * Copyright: 2020-2025 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.92.0.0
 */

namespace KnitPay\Extensions\FluentForms;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class FluentFormsDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'FLUENTFORMPRO' );
	}
}
