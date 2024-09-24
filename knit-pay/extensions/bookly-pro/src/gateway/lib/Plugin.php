<?php
namespace BooklyKnitPay\Lib;

use Bookly\Lib as BooklyLib;
use BooklyKnitPay\Backend\Modules as Backend;
use BooklyKnitPay\Frontend\Modules as Frontend;

/**
 * Class Plugin
 *
 * @package BooklyKnitPay\Lib
 */
abstract class Plugin extends BooklyLib\Base\Plugin {

	protected static $prefix;
	protected static $title;
	protected static $version;
	protected static $slug;
	protected static $directory;
	protected static $main_file;
	protected static $basename;
	protected static $text_domain;
	protected static $root_namespace;
	protected static $embedded;

	/**
	 * @inheritdoc
	 */
	public static function init() {
		// Init proxy.
		Backend\Appearance\ProxyProviders\Shared::init();
		Backend\Settings\ProxyProviders\Shared::init();

		Frontend\Booking\ProxyProviders\Shared::init();

		ProxyProviders\Shared::init();
		Payment\ProxyProviders\Shared::init();
	}
}
