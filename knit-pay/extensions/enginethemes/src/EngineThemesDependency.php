<?php

/**
 * Title: Engine Themes Dependency
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.7.0
 */

namespace KnitPay\Extensions\EngineThemes;

use Pronamic\WordPress\Pay\Dependencies\Dependency;

class EngineThemesDependency extends Dependency {
	/**
	 * Is met.
	 *
	 * @return bool True if dependency is met, false otherwise.
	 */
	public function is_met() {
		if ( ! \defined( 'KNIT_PAY_ENGINE_THEMES' ) ) {
			return false;
		}
		
		$active_theme = get_option( 'stylesheet' );
		switch ( $active_theme ) {
			case 'freelanceengine':
			case 'freelanceengine-child':
			case 'microjobengine':
			case 'microjobengine-child':
				return true;
			default:
				return false;
		}
	}
}
