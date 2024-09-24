<?php 

add_filter(
	'pronamic_pay_plugin_integrations',
	function( $integrations ) {
		// Freelance Engine.
		$integrations[] = new \KnitPay\Extensions\EngineThemes\FreelanceEngine\Extension();
		
		// Micro Job Engine.
		$integrations[] = new \KnitPay\Extensions\EngineThemes\MicroJobEngine\Extension();
		
		// Return integrations.
		return $integrations;
	}
);
