<?php

namespace Nafezly\Payments\Compat;

/**
 * Configuration store that backs the global config() helper.
 *
 * Data is populated by Knit Pay before a Nafezly gateway class is
 * instantiated so that every call to config('nafezly-payments.KEY')
 * returns the value set by the user in the WordPress admin.
 *
 * @author  Knit Pay
 * @version 1.1.0
 */
class Config {

	/**
	 * @var array Stack of active configuration scopes.
	 */
	private static $stack = [];

	/**
	 * @var array<string, mixed> Flat key => value store.
	 */
	private $store = [];

	/**
	 * Push a config instance onto the active stack.
	 *
	 * @param self $config
	 */
	public static function push( self $config ) {
		self::$stack[] = $config;
	}

	/**
	 * Pop the most recent config instance from the stack.
	 *
	 * @return self|null
	 */
	public static function pop() {
		return array_pop( self::$stack );
	}

	/**
	 * Get the currently active config instance (top of stack).
	 *
	 * @return self|null
	 */
	public static function current() {
		return empty( self::$stack ) ? null : end( self::$stack );
	}

	/**
	 * Set a configuration value.
	 *
	 * @param string $key   Dot-notation key, e.g. 'nafezly-payments.SOME_KEY'
	 * @param mixed  $value
	 */
	public function set( $key, $value ) {
		$this->store[ $key ] = $value;
	}

	/**
	 * Get a configuration value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( array_key_exists( $key, $this->store ) ) {
			return $this->store[ $key ];
		}

		// Support nested keys like a.b.c
		$segments = explode( '.', $key );
		$value    = $this->store;
		foreach ( $segments as $segment ) {
			if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
			} else {
				return $default;
			}
		}
		return $value;
	}

	/**
	 * Merge a full config array under the 'nafezly-payments' key.
	 *
	 * @param array $config
	 */
	public function merge( array $config ) {
		$this->store['nafezly-payments'] = array_merge(
			$this->store['nafezly-payments'] ?? [],
			$config
		);
	}

	/**
	 * Clear the store (useful between tests or when switching gateways).
	 */
	public function clear() {
		$this->store = [];
	}
}
