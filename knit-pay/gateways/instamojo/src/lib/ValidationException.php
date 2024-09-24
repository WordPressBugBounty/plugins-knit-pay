<?php
/**
 * ValidationException
 * - used to generate the exception releted to validation which raised when response
 *   from instamojo server is not as desired.
 *   used to throw the Validation errors at the time of creating order.
 *   used to throw the authentication failed errors.
 */

namespace KnitPay\Gateways\Instamojo;

use Exception;

class ValidationException extends Exception {

	private $errors;
	private $api_response;
	public function __construct( $message, $errors, $api_response ) {
		parent::__construct( $message, 0 );
		$this->errors       = $errors;
		$this->api_response = $api_response;
	}

	public function getErrors() {
		return $this->errors;
	}
	public function getResponse() {
		return $this->api_response;
	}
}
