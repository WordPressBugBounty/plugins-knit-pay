<?php

namespace KnitPay\Extensions\EngineThemes\FreelanceEngine;

use KnitPay\Extensions\EngineThemes\Gateway as EngineThemesGateway;

/**
 * Title: Freelance Engine Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.61.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends EngineThemesGateway {
	/**
	 * Bootstrap
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'after_payment_list', [ $this, 'ae_knit_pay_render_button' ], 0 );
		add_action( 'after_payment_list_upgrade_account', [ $this, 'ae_knit_pay_render_button' ], 0 );
	}
}
