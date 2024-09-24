<?php
/**
 * Mode Trait
 *
 * @author  Knit Pay
 * @copyright 2020-2024 Knit Pay
 * @license   GPL-3.0-or-later
 */

namespace Pronamic\WordPress\Pay\Core;

/**
 * Integration Mode Trait
 */
trait IntegrationModeTrait {
	
	public function get_mode_settings_fields( $modes = [] ) {
		if ( empty( $modes ) ) {
			$modes = [
				Gateway::MODE_LIVE => 'Live',
				Gateway::MODE_TEST => 'Test',
			];
		}

		return [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_mode',
			'title'    => __( 'Mode', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => $modes,
			'default'  => Gateway::MODE_LIVE,
		];
	}
}
