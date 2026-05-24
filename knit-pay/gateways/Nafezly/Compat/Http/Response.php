<?php

namespace Illuminate\Http;

/**
 * Response wrapper that mimics Laravel’s HTTP Client response object.
 *
 * Backed by a plain array (headers + body) and provides json(), body(),
 * and ArrayAccess so that Nafezly code can write:
 *
 *     $response = Http::withHeaders([...])->post($url, $data)->json();
 *     $response['data']['session_id'];
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class Response implements \ArrayAccess {

	/** @var array */
	private $headers;

	/** @var string */
	private $body;

	/** @var int */
	private $status;

	/** @var array|null Cached JSON decode. */
	private $decoded;

	/** @var string|null Static storage for the last raw body (for debugging). */
	public static $lastRawBody = null;

	/**
	 * @param string $body    Raw response body.
	 * @param array  $headers Response headers.
	 * @param int    $status  HTTP status code.
	 */
	public function __construct( $body = '', $headers = [], $status = 200 ) {
		$this->body        = $body;
		$this->headers     = $headers;
		$this->status      = $status;
		self::$lastRawBody = $body;
	}

	/**
	 * Decode the response body as JSON.
	 *
	 * @param string|null $key     Optional dot-notation key to pluck.
	 * @param mixed       $default Default when key missing.
	 * @return mixed
	 */
	public function json( $key = null, $default = null ) {
		if ( null === $this->decoded ) {
			$this->decoded = json_decode( $this->body, true );
			if ( ! is_array( $this->decoded ) ) {
				$this->decoded = [];
			}
		}

		if ( null === $key ) {
			return $this->decoded;
		}

		return $this->dataGet( $this->decoded, $key, $default );
	}

	/**
	 * Return the raw response body.
	 * @return string
	 */
	public function body() {
		return $this->body;
	}

	/**
	 * Check whether the response was successful (2xx).
	 * @return bool
	 */
	public function successful() {
		return $this->status >= 200 && $this->status < 300;
	}

	/**
	 * Check whether the response failed (4xx or 5xx).
	 * @return bool
	 */
	public function failed() {
		return $this->status >= 400;
	}

	/**
	 * Get the HTTP status code.
	 * @return int
	 */
	public function status() {
		return $this->status;
	}

	// ----------------------------------------------------------------
	// ArrayAccess — allows $response['data']['session_id']
	// ----------------------------------------------------------------

	public function offsetExists( $offset ): bool {
		$json = $this->json();
		return is_array( $json ) && array_key_exists( $offset, $json );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		$json = $this->json();
		return is_array( $json ) ? ( $json[ $offset ] ?? null ) : null;
	}

	public function offsetSet( $offset, $value ): void {}
	public function offsetUnset( $offset ): void {}

	// ----------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------

	/**
	 * Dot-notation array accessor.
	 *
	 * @param array  $array
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	private function dataGet( $array, $key, $default ) {
		if ( is_null( $key ) ) {
			return $array;
		}
		foreach ( explode( '.', $key ) as $segment ) {
			if ( is_array( $array ) && array_key_exists( $segment, $array ) ) {
				$array = $array[ $segment ];
			} else {
				return $default;
			}
		}
		return $array;
	}
}
