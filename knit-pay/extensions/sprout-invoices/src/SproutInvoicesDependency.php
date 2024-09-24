<?php
/**
 * Title: Sprout Invoices extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.8.0
 */

namespace KnitPay\Extensions\SproutInvoices;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class SproutInvoicesDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return defined( 'SI_PATH' ) && defined( 'KNIT_PAY_SPROUT_INVOICES' );
	}
}
