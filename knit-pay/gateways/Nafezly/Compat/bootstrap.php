<?php
/**
 * Nafezly Payments Compatibility Bootstrap
 *
 * Loads the Laravel-to-WordPress shim layer before any Nafezly classes
 * are instantiated. Must be included once at the top of Gateway.php.
 *
 * @author  Knit Pay
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading.
if ( defined( 'NAFEZLY_COMPAT_LOADED' ) ) {
	return;
}
define( 'NAFEZLY_COMPAT_LOADED', true );

$compat_dir = __DIR__;

// ------------------------------------------------------------------
// 1. Load our own compat helper classes FIRST (functions.php needs them)
// ------------------------------------------------------------------
$helper_classes = [
	'Nafezly\Payments\Compat\Config' => $compat_dir . '/Config.php',
];

foreach ( $helper_classes as $class_name => $file_path ) {
	if ( ! class_exists( $class_name ) ) {
		require_once $file_path;
	}
}

// ------------------------------------------------------------------
// 2. Global helper functions (guarded to avoid collisions)
// ------------------------------------------------------------------
require_once $compat_dir . '/functions.php';

// ------------------------------------------------------------------
// 3. Shim class autoloader (manual because PSR-4 doesn't cover them)
// ------------------------------------------------------------------
$classes = [
	'Illuminate\Contracts\Foundation\Application' => $compat_dir . '/Contracts/Foundation/Application.php',
	'Illuminate\Http\Response'                    => $compat_dir . '/Http/Response.php',
	'Illuminate\Http\RedirectResponse'            => $compat_dir . '/Http/RedirectResponse.php',
	'Illuminate\Http\Client\PendingRequest'       => $compat_dir . '/Http/PendingRequest.php',
	'Illuminate\Support\Facades\Http'             => $compat_dir . '/Http/Facades/Http.php',
	'Illuminate\Http\Request'                     => $compat_dir . '/Http/Request.php',
	'Illuminate\Support\ServiceProvider'          => $compat_dir . '/Support/ServiceProvider.php',
	'Illuminate\Support\Str'                      => $compat_dir . '/Support/Str.php',
	'Illuminate\Support\Facades\Cache'            => $compat_dir . '/Support/Facades/Cache.php',
	'Illuminate\Contracts\View\View'              => $compat_dir . '/View/View.php',
	'Illuminate\Routing\Redirector'               => $compat_dir . '/Routing/Redirector.php',
];

foreach ( $classes as $class_name => $file_path ) {
	if ( ! class_exists( $class_name ) && ! interface_exists( $class_name ) ) {
		require_once $file_path;
	}
}
