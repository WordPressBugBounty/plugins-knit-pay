<?php
namespace KnitPay\Extensions\VikWP;

use JLoader;
use JPayment;
use JPaymentStatus;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

JLoader::import( 'adapter.payment.payment' );


abstract class AbstractKnitPayPayment extends JPayment {
	
	
	protected function buildAdminParameters() {
		return [];
	}
	
	public function __construct( $alias, $order, $params = [] ) {
		parent::__construct( $alias, $order, $params );
	}
	
	protected function beginTransaction() {
		/** See the code below to build this method */
	}
	
	protected function validateTransaction( JPaymentStatus &$status ) {
		/** See the code below to build this method */
		return [];
	}

	protected function complete( $esit = 0 ) {
		/** See the code below to build this method */
	}
}
