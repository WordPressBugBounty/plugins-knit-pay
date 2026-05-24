<?php

namespace Illuminate\Support\Facades;

use Illuminate\Http\Client\PendingRequest;

/**
 * Static facade for the HTTP client.
 *
 * Usage inside Nafezly code:
 *     Http::withHeaders([...])->post($url, $data)->json();
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class Http {

	/**
	 * Create a new PendingRequest.
	 *
	 * @return PendingRequest
	 */
	private static function newRequest() {
		return new PendingRequest();
	}

	public static function withHeaders( $headers ) {
		return self::newRequest()->withHeaders( $headers );
	}

	public static function withToken( $token ) {
		return self::newRequest()->withToken( $token );
	}

	public static function withoutVerifying() {
		return self::newRequest()->withoutVerifying();
	}

	public static function withOptions( $options ) {
		return self::newRequest()->withOptions( $options );
	}

	public static function withBody( $body, $contentType ) {
		return self::newRequest()->withBody( $body, $contentType );
	}

	public static function asForm() {
		return self::newRequest()->asForm();
	}

	public static function post( $url, $data = [] ) {
		return self::newRequest()->post( $url, $data );
	}

	public static function get( $url, $query = [] ) {
		return self::newRequest()->get( $url, $query );
	}
}
