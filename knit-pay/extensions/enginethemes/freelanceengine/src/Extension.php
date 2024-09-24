<?php

namespace KnitPay\Extensions\EngineThemes\FreelanceEngine;

use KnitPay\Extensions\EngineThemes\Extension as EngineThemesExtension;

/**
 * Title: Freelance Engine extension
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
	const SLUG = 'freelanceengine';

	/**
	 * Constructs and initialize Micro Job Engine extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Freelance Engine', 'knit-pay-lang' ),
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
		
		add_filter( 'ae_admin_menu_pages', [ $this, 'ae_admin_menu_pages' ] );
	}
}
