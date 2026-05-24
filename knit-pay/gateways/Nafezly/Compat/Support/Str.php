<?php

namespace Illuminate\Support;

/**
 * Minimal stub for Illuminate\Support\Str.
 *
 * Only implements the methods observed in Nafezly gateway code.
 *
 * @author  Knit Pay
 * @version 1.0.0
 */
class Str {

	/**
	 * Generate a random string of the given length.
	 *
	 * @param int $length
	 * @return string
	 */
	public static function random( $length = 16 ) {
		// Borrowed from WordPress wp_generate_password but without special chars.
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$max   = strlen( $chars ) - 1;
		$str   = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$str .= $chars[ random_int( 0, $max ) ];
		}
		return $str;
	}

	/**
	 * Generate a UUID.
	 * @return string
	 */
	public static function uuid() {
		return wp_generate_uuid4();
	}
}
