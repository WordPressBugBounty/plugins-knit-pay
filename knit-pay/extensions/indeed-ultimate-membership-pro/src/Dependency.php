<?php

/**
 * Title: Indeed Ultimate Membership Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.5.0
 */

namespace KnitPay\Extensions\IndeedUltimateMembershipPro;

use Pronamic\WordPress\Pay\Dependencies\Dependency as CoreDependency;

class Dependency extends CoreDependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \defined( 'IHC_PATH' ) && \defined( 'KNIT_PAY_INDEED_ULTIMATE_MEMBERSHIP_PRO' );
	}
}
