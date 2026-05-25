<?php

namespace Illuminate\Http;

/**
 * Minimal shim for Laravel's Illuminate\Http\Request.
 *
 * This is NOT a full Symfony HTTP Foundation implementation. It provides only
 * the surface area that Nafezly gateway classes touch:
 * all(), input(), has(), route(), header(), headers(), getContent(), __get().
 *
 * The constructor signature is aligned with Symfony's Request constructor
 * so that external code that does `new Request($query, $request, …)` works.
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class Request implements \ArrayAccess {

	/** @var array Unified request data (merged query + request body). */
	private $data;

	/** @var array Raw HTTP headers extracted from $server. */
	private $headers;

	/** @var string|null Cached php://input content (stream can only be read once). */
	private static ?string $cachedContent = null;

	/**
	 * Create a new Request instance.
	 *
	 * Mirrors Symfony\Component\HttpFoundation\Request constructor params:
	 *   $query, $request, $attributes, $cookies, $files, $server, $content
	 *
	 * @param array       $query      The GET parameters ($_GET equivalent).
	 * @param array       $request    The POST parameters ($_POST equivalent).
	 * @param array       $attributes The request attributes (route params, etc.).
	 * @param array       $cookies    The $_COOKIE parameters.
	 * @param array       $files      The $_FILES parameters.
	 * @param array       $server     The $_SERVER parameters (used to extract headers).
	 * @param string|null $content    Raw request body (populates the static cache).
	 */
	public function __construct(
		array $query = [],
		array $request = [],
		array $attributes = [],
		array $cookies = [],
		array $files = [],
		array $server = [],
		$content = null
	) {
		if ( null !== $content ) {
			self::$cachedContent = $content;
		}

		if ( empty( $query ) && empty( $request ) ) {
			$json = json_decode( $this->getContent(), true );
			if ( ! is_array( $json ) ) {
				$json = [];
			}
			$this->data = array_merge( $_GET, $_POST, $json, $attributes );
		} else {
			$this->data = array_merge( $query, $request, $attributes );
		}

		// Extract headers from $server (keys prefixed with HTTP_).
		if ( empty( $server ) ) {
			$server = $_SERVER;
		}
		$this->headers = [];
		foreach ( $server as $key => $value ) {
			if ( str_starts_with( $key, 'HTTP_' ) ) {
				$header_name                   = str_replace( '_', '-', substr( $key, 5 ) );
				$this->headers[ $header_name ] = $value;
			}
		}
	}

	/**
	 * Get all headers.
	 *
	 * @return array
	 */
	public function headers() {
		return $this->headers;
	}

	/**
	 * Get a specific header.
	 *
	 * @param string     $key
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function header( $key, $default = null ) {
		$normalized = strtoupper( str_replace( '-', '_', $key ) );
		if ( isset( $this->headers[ $key ] ) ) {
			return $this->headers[ $key ];
		}
		if ( isset( $this->headers[ $normalized ] ) ) {
			return $this->headers[ $normalized ];
		}
		return $default;
	}

	/**
	 * Get the raw content body.
	 *
	 * Uses a static cache because php://input can only be read once.
	 *
	 * @return string
	 */
	public function getContent() {
		if ( null !== self::$cachedContent ) {
			return self::$cachedContent;
		}
		self::$cachedContent = file_get_contents( 'php://input' );
		if ( false === self::$cachedContent ) {
			self::$cachedContent = '';
		}
		return self::$cachedContent;
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
	 * Get a route parameter (mapped to unified data in WordPress).
	 *
	 * @param string     $key
	 * @param mixed|null $default
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
