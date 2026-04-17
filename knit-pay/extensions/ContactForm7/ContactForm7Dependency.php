<?php

namespace KnitPay\Extensions\ContactForm7;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

/**
 * Title: Contact Form 7 Dependency
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.60.0.0
 */
class ContactForm7Dependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( '\WPCF7_VERSION' );
	}
}
