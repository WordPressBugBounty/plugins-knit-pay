<?php

namespace KnitPay\Extensions\UncannyAutomator;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Uncanny Automator extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'uncanny-automator';

	/**
	 * Constructs and initialize Uncanny Automator extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Uncanny Automator', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new UncannyAutomatorDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_action(
			'automator_add_integration',
			function () {
				new KnitPayIntegration();

				require_once 'triggers/PaymentStatusUpdateTrigger.php';
				new Triggers\PaymentStatusUpdateTrigger();

				require_once 'actions/CreatePaymentAction.php';
				new Actions\CreatePaymentAction();
			} 
		);
	}
}
