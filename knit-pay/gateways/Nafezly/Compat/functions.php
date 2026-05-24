<?php
/**
 * Laravel-to-WordPress global helper shims.
 *
 * All helpers are defined inside the Nafezly\Payments namespace.
 * When any Nafezly vendor class calls config(), env(), route(), or
 * view() without a fully-qualified name, PHP resolves them via the
 * namespace hierarchy FIRST, so these ALWAYS win even if another
 * plugin defines \config(), \env(), etc. in the global namespace.
 *
 * NO global-namespace wrappers are provided. This avoids polluting
 * the global function namespace and eliminates collision risk with
 * other plugins or themes.
 *
 * @author  Knit Pay
 * @version 1.2.0
 */

namespace Nafezly\Payments\Classes {
	// ------------------------------------------------------------------
	// __() — Laravel translation namespace shim
	// ------------------------------------------------------------------
	/**
	 * Intercepts Laravel-style translation keys (nafezly::messages.KEY)
	 * and returns the real translated message from vendor resources/lang/.
	 *
	 * Falls back to WordPress global __() for non-Laravel keys.
	 *
	 * @param string       $text    Translation key or text.
	 * @param array|string $replace Array of replacements (Laravel) or text-domain string (WordPress).
	 * @return string
	 */
	function __( $text, $replace = [] ) {
		if ( is_string( $text ) && preg_match( '/^nafezly::messages\.(.+)$/', $text, $m ) ) {
			$key  = $m[1];
			$lang = ( function_exists( 'get_locale' ) && 'ar' === strtolower( substr( get_locale(), 0, 2 ) ) ) ? 'ar' : 'en';

			static $cache = [];
			if ( ! isset( $cache[ $lang ] ) ) {
				$file           = KNITPAY_DIR . 'secondary-packages/vendor/nafezly/payments/resources/lang/' . $lang . '/messages.php';
				$cache[ $lang ] = file_exists( $file ) ? include $file : [];
			}

			$msg = $cache[ $lang ][ $key ] ?? null;

			// Lazy English fallback — only load when a key is actually missing.
			if ( null === $msg && 'en' !== $lang ) {
				if ( ! isset( $cache['en'] ) ) {
					$en_file     = KNITPAY_DIR . 'secondary-packages/vendor/nafezly/payments/resources/lang/en/messages.php';
					$cache['en'] = file_exists( $en_file ) ? include $en_file : [];
				}
				$msg = $cache['en'][ $key ] ?? null;
			}

			if ( null !== $msg && is_array( $replace ) ) {
				foreach ( $replace as $k => $v ) {
					$msg = str_replace( ':' . $k, $v, $msg );
				}
			}
			return $msg ?? $text;
		}
		return \__( $text, is_string( $replace ) ? $replace : 'default' );
	}

	// ------------------------------------------------------------------
	// env()
	// ------------------------------------------------------------------
	/**
	 * Reads an environment variable.
	 *
	 * In WordPress context we fall back to defaults because .env is
	 * not available. If the caller has put a value into the static
	 * config store it will be returned before the default.
	 *
	 * @param string $key     Environment key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function env( $key, $default = null ) {
		$val = nafezly_config( $key );
		if ( null !== $val ) {
			return $val;
		}

		if ( 'APP_NAME' === $key ) {
			return get_bloginfo( 'name' );
		}

		return $default;
	}

	// ------------------------------------------------------------------
	// config()
	// ------------------------------------------------------------------
	/**
	 * Reads or writes Laravel-style configuration values.
	 *
	 * @param string|null $key   Dot-notation key (e.g. 'nafezly-payments.THAWANI_URL')
	 * @param mixed       $value Value to set when writing.
	 * @return mixed|\Nafezly\Payments\Compat\Config
	 */
	function config( $key = null, $value = null ) {
		$cfg = \Nafezly\Payments\Compat\Config::current();
		if ( null === $cfg ) {
			$cfg = new \Nafezly\Payments\Compat\Config();
		}

		if ( null === $key ) {
			return $cfg;
		}

		if ( null !== $value ) {
			$cfg->set( $key, $value );
			return $value;
		}

		return $cfg->get( $key );
	}

	/**
	 * Internal helper that reads only the static config store.
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	function nafezly_config( $key ) {
		$cfg = \Nafezly\Payments\Compat\Config::current();
		if ( null === $cfg ) {
			return null;
		}
		return $cfg->get( $key );
	}

	// ------------------------------------------------------------------
	// route()
	// ------------------------------------------------------------------
	/**
	 * Generates a URL for a named route.
	 *
	 * In WordPress, route names are stored by the Nafezly gateway
	 * integration and mapped to a front-end URL with query arguments.
	 *
	 * @param string $name       Route name.
	 * @param array  $parameters Query arguments to append.
	 * @param bool   $absolute   Whether to return absolute URL.
	 * @return string
	 */
	function route( $name, $parameters = [], $absolute = true ) {
		// Safety guard against null route names from vendor constructors.
		if ( ! is_string( $name ) || '' === $name ) {
			return home_url( '/' );
		}

		// Strip 'payment' parameter to avoid collision with Knit Pay's
		// 'payment' (internal payment ID) in get_return_url().
		unset( $parameters['payment'] );

		// If $name is an URL, use it directly.
		if ( filter_var( $name, FILTER_VALIDATE_URL ) ) {
			return add_query_arg( $parameters, $name );
		}

		return add_query_arg( $parameters, home_url( '/' ) );
	}

	// ------------------------------------------------------------------
	// view()
	// ------------------------------------------------------------------
	/**
	 * Creates a View instance (plain PHP template, no Blade).
	 *
	 * @param string $path Dot-notation view path, e.g. 'nafezly::html.fawry'
	 * @param array  $data Variables to extract into the template.
	 * @return \Illuminate\Contracts\View\View
	 */
	function view( $path, $data = [] ) {
		$parts = explode( '::', $path, 2 );
		if ( 2 === count( $parts ) ) {
			$view_path = str_replace( '.', '/', $parts[1] );
		} else {
			$view_path = str_replace( '.', '/', $path );
		}

		$file = KNITPAY_DIR . 'gateways/Nafezly/views/' . $view_path . '.php';

		return new \Illuminate\Contracts\View\View( $file, $data );
	}
}

namespace Nafezly\Payments {
	// ------------------------------------------------------------------
	// resource_path() / config_path() / lang_path()
	// ------------------------------------------------------------------
	/**
	 * Resource path helper.
	 *
	 * @param string $path
	 * @return string
	 */
	function resource_path( $path = '' ) {
		return KNITPAY_DIR . 'resources' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}

	/**
	 * Config path helper.
	 *
	 * @param string $path
	 * @return string
	 */
	function config_path( $path = '' ) {
		return KNITPAY_DIR . 'gateways/Nafezly/Compat/config' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}

	/**
	 * Language path helper.
	 *
	 * @param string $path
	 * @return string
	 */
	function lang_path( $path = '' ) {
		return KNITPAY_DIR . 'secondary-packages/vendor/nafezly/payments/resources/lang' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}
