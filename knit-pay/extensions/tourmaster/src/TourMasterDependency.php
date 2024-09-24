<?php
/**
 * Title: Tour Master extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.1.0
 * @version 8.85.13.0
 * @package   KnitPay\Extensions\TourMaster
 */

namespace KnitPay\Extensions\TourMaster;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

/**
 * TourMasterDependency
 *
 * @author Gautam Garg
 */
class TourMasterDependency extends Dependency {


	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		if ( ! \defined( '\TOURMASTER_LOCAL' ) ) {
			return false;
		}

		$tourmaster_base = 'tourmaster/tourmaster.php';

		$plugins = get_plugins();
		if ( isset( $plugins[ $tourmaster_base ] ) ) {
			$tourmaster_version = $plugins[ $tourmaster_base ]['Version'];
			return ( version_compare( $tourmaster_version, '5.0.0' ) >= 0 );
		}

		return false;
	}
}
