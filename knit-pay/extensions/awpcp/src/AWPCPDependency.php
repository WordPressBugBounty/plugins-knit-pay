<?php

/**
 * Title: AWP Classifieds extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.5
 */

namespace KnitPay\Extensions\AWPCP;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class AWPCPDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		return \class_exists( '\AWPCP_PaymentGateway' ) && \defined( 'KNIT_PAY_AWPCP' );
	}
}
