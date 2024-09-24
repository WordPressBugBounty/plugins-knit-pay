<?php

namespace KnitPay\Extensions\EngineThemes\MicroJobEngine;

use KnitPay\Extensions\EngineThemes\Extension as EngineThemesExtension;

/**
 * Title: Micro Job Engine extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.61.0
 */
class Extension extends EngineThemesExtension {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'microjobengine';

	/**
	 * Constructs and initialize Micro Job Engine extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Micro Job Engine', 'knit-pay-lang' ),
			]
		);
	}
	
	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		parent::setup();
		
		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}
		
		add_filter( 'mje_payment_gateway_setting_sections', [ $this, 'add_gateway_setting_fields' ] );
	}

}
