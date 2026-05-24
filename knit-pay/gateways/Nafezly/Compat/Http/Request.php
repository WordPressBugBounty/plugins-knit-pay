<?php

namespace Illuminate\Http;

/**
 * Request wrapper that mimics Laravel's Illuminate\Http\Request.
 *
 * Wraps $_REQUEST, $_GET, $_POST, and php://input so Nafezly gateway
 * verify() methods can read callback data the same way they do in
 * Laravel.
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class Request implements \ArrayAccess {

	/** @var array Unified request data. */
	private $data;

	/**
	 * @param array $data Optional data array. Defaults to $_REQUEST + input.
	 */
	public function __construct( $data = null ) {
		if ( null === $data ) {
			$json = json_decode( file_get_contents( 'php://input' ), true );
			if ( ! is_array( $json ) ) {
				$json = [];
			}
			$this->data = array_merge( $_GET, $_POST, $json );
		} else {
			$this->data = $data;
		}
	}

	/**
	 * Get all input data.
	 *
	 * @return array
	 */
	public function all() {
		return $this->data;
	}

	/**
	 * Retrieve an input item.
	 *
	 * @param string     $key
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function input( $key = null, $default = null ) {
		if ( null === $key ) {
			return $this->data;
		}
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Check if a key exists.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has( $key ) {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * Get a route parameter (mapped to $_GET in WordPress).
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function route( $key = null, $default = null ) {
		if ( null === $key ) {
			return $this->data;
		}
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Magic property access (Laravel Request style).
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->data[ $key ] ?? null;
	}

	// ----------------------------------------------------------------
	// ArrayAccess
	// ----------------------------------------------------------------

	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->data );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->data[ $offset ] ?? null;
	}

	public function offsetSet( $offset, $value ): void {
		$this->data[ $offset ] = $value;
	}

	public function offsetUnset( $offset ): void {
		unset( $this->data[ $offset ] );
	}
}
