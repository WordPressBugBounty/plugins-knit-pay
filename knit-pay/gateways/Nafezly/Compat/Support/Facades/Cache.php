<?php

namespace Illuminate\Support\Facades;

use GautamMKGarg\PsrForWordPress\Cache\WpTransientsCache;

/**
 * Stub for Illuminate\Support\Facades\Cache.
 *
 * Delegates to the PSR-16 WpTransientsCache from gautammkgarg/psr-for-wordpress.
 * Transients are always persistent (database by default, object cache when
 * a persistent-cache plugin is active).
 *
 * All keys are prefixed with "nafezly_cache_" to avoid collisions.
 *
 * @author  Knit Pay
 * @version 2.0.0
 */
class Cache {

	/**
	 * @var WpTransientsCache|null
	 */
	private static ?WpTransientsCache $cache = null;

	/**
	 * Return the PSR-16 cache instance.
	 *
	 * @return WpTransientsCache
	 */
	private static function engine(): WpTransientsCache {
		if ( null === self::$cache ) {
			self::$cache = new WpTransientsCache();
		}
		return self::$cache;
	}

	/**
	 * Build the internal key with the Nafezly prefix.
	 *
	 * @param string $key
	 * @return string
	 */
	private static function prefix( string $key ): string {
		return 'nafezly_cache_' . $key;
	}

	// ------------------------------------------------------------------
	// Laravel-style facade methods used by vendor gateways.
	// ------------------------------------------------------------------

	/**
	 * Store an item permanently.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function forever( $key, $value ) {
		self::engine()->set( self::prefix( $key ), $value );
	}

	/**
	 * Store an item for a given TTL.
	 *
	 * Accepts Laravel-style TTLs: int (seconds), \DateTime, \DateInterval,
	 * or anything that PSR-16's ttlToSeconds() can parse.
	 *
	 * @param string                                 $key
	 * @param mixed                                  $value
	 * @param int|\DateTimeInterface|\DateInterval|null $ttl
	 */
	public static function put( $key, $value, $ttl = null ) {
		self::engine()->set( self::prefix( $key ), $value, $ttl );
	}

	/**
	 * Retrieve an item.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		return self::engine()->get( self::prefix( $key ), $default );
	}

	/**
	 * Remove an item.
	 *
	 * @param string $key
	 */
	public static function forget( $key ) {
		self::engine()->delete( self::prefix( $key ) );
	}
}
