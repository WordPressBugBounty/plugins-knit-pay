<?php

/**
 * Title: Tickera extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.84.0.0
 */

namespace KnitPay\Extensions\Tickera;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class TickeraDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\TC' );
	}
}
