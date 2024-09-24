<?php

/**
 * Title: Paid Memberships Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.0.0
 */

namespace KnitPay\Extensions\PaidMembershipsPro;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class PaidMembershipsProDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\PMProGateway' );
	}
}
