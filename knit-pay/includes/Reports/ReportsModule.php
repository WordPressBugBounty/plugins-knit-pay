<?php
/**
 * Knit Pay Reports – Module Controller
 *
 * Registers admin menu, enqueues assets (Alpine.js + Chart.js + reports.js),
 * routes tabs, and bootstraps the REST controller.
 *
 * @package KnitPay\Reports
 */

namespace KnitPay\Reports;

use Pronamic\WordPress\Money\Currencies;

class ReportsModule {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function setup(): void {
		if ( ! $this->check_mysql_version() ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'Knit Pay Reports requires MySQL 5.7 or later. Please upgrade your database server.', 'knit-pay-lang' );
					echo '</p></div>';
				}
			);
			return;
		}

		add_action( 'admin_menu', [ $this, 'admin_menu' ], 100 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 10, 0 );
		add_action( 'admin_head', [ $this, 'print_badge_style' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		// TODO: Re-enable redirect after users have adopted the new Reports page (~3 months).
		// See: context/knit-pay-reports-implementation-plan.md — DO NOT DELETE the disabled redirect code.
		// add_action( 'admin_init', [ $this, 'redirect_payments_to_reports' ] );
		add_action( 'admin_init', [ $this, 'redirect_old_reports_url' ] );
		add_action( 'admin_notices', [ $this, 'show_new_reports_notice_on_payments_page' ] );
	}

	private function check_mysql_version(): bool {
		global $wpdb;
		$wpdb->query( "SELECT JSON_EXTRACT('{\"a\":1}', '$.a')" );
		return empty( $wpdb->last_error );
	}

	private const NEW_BADGE_VISIT_THRESHOLD = 25;

	private const NEW_BADGE_META_KEY = 'knit_pay_reports_visits';

	public function print_badge_style(): void {
		if ( $this->should_hide_new_badge() ) {
			return;
		}

		echo '<style>.knit-pay-badge-new{display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;margin:0 0 0 4px;padding:0 7px;min-height:17px;border-radius:9px;background:#00a32a;color:#fff !important;font-size:9px;font-weight:600;line-height:1;box-sizing:border-box;text-decoration:none;letter-spacing:0.3px;transition:background .15s ease}.knit-pay-badge-new .processing-count{font-size:9px !important;line-height:1 !important;padding:0 !important;color:#fff !important}#adminmenu a:hover .knit-pay-badge-new{background:#008a20}</style>';
	}

	private function should_hide_new_badge(): bool {
		$visits = (int) get_user_meta( get_current_user_id(), self::NEW_BADGE_META_KEY, true );
		return $visits >= self::NEW_BADGE_VISIT_THRESHOLD;
	}

	public function admin_menu(): void {
		$reports_label = __( 'Reports', 'knit-pay-lang' );

		if ( ! $this->should_hide_new_badge() ) {
			$reports_label .= ' <span class="knit-pay-badge-new" title="' . esc_attr__( 'New', 'knit-pay-lang' ) . '"><span class="processing-count">' . esc_html__( 'New', 'knit-pay-lang' ) . '</span></span>';
		}

		add_submenu_page(
			'pronamic_ideal',
			__( 'Reports', 'knit-pay-lang' ),
			$reports_label,
			'edit_payments',
			'knit-pay-reports',
			[ $this, 'render_page' ]
		);

		// Register legacy page slug so old bookmarks redirect properly.
		add_submenu_page(
			null,
			'',
			'',
			'edit_payments',
			'pronamic_pay_reports',
			[ $this, 'redirect_old_reports_url' ]
		);

		// Move Reports to position after Configurations in the Knit Pay menu.
		global $submenu;
		if ( isset( $submenu['pronamic_ideal'] ) ) {
			$reports_index = null;
			foreach ( $submenu['pronamic_ideal'] as $i => $item ) {
				if ( 'knit-pay-reports' === $item[2] ) {
					$reports_index = $i;
					break;
				}
			}

			if ( null !== $reports_index ) {
				$reports_item = $submenu['pronamic_ideal'][ $reports_index ];
				unset( $submenu['pronamic_ideal'][ $reports_index ] );

				// Find insertion point: immediately after Configurations.
				$insert_after = 0;
				foreach ( $submenu['pronamic_ideal'] as $i => $item ) {
					if ( 'edit.php?post_type=pronamic_gateway' === $item[2] ) {
						$insert_after = $i;
						break;
					}
				}

				array_splice( $submenu['pronamic_ideal'], $insert_after + 1, 0, [ $reports_item ] );
			}
		}
	}

	public function redirect_old_reports_url(): void {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( 'pronamic_pay_reports' !== $page ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=knit-pay-reports' ), 301 );
		exit;
	}

	// TODO: Re-enable after users have adopted the new Reports page (~3 months).
	// See: context/knit-pay-reports-implementation-plan.md — DO NOT DELETE this method.
	// public function redirect_payments_to_reports(): void {
	//  global $pagenow;
	//
	//  if ( 'edit.php' !== $pagenow ) {
	//      return;
	//  }
	//
	//  $post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_SPECIAL_CHARS );
	//  if ( 'pronamic_payment' !== $post_type ) {
	//      return;
	//  }
	//
	//  wp_safe_redirect( admin_url( 'admin.php?page=knit-pay-reports&tab=transactions' ), 301 );
	//  exit;
	// }

	public function show_new_reports_notice_on_payments_page(): void {
		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		$post_type = filter_input( INPUT_GET, 'post_type', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( 'pronamic_payment' !== $post_type ) {
			return;
		}

		$reports_url = admin_url( 'admin.php?page=knit-pay-reports&tab=payments' );
		$contact_url = 'https://www.knitpay.org/contact-us/';

		printf(
			'<div class="notice notice-info knit-pay-reports-notice is-dismissible" style="border-left-color:#2271b1;background:#f0f6fc;padding:12px 16px;font-size:14px;line-height:1.6">' .
			'<p style="font-size:15px;font-weight:600;margin:0 0 6px">🎉 Knit Pay Payments &amp; Reports have been revamped!</p>' .
			'<p style="margin:0 0 8px">Try the <a href="%1$s" style="font-weight:600">new Reports page</a> for a better experience with charts, filters, and exports.</p>' .
			'<p style="margin:0"><a href="%2$s" style="font-weight:600">Contact us</a> with any suggestions, bugs, or feedback on the new reports.</p>' .
			'</div>',
			esc_url( $reports_url ),
			esc_url( $contact_url )
		);
	}

	public function enqueue_assets(): void {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( 'knit-pay-reports' !== $page ) {
			return;
		}

		$assets_url                 = plugin_dir_url( __FILE__ ) . 'Assets';
		$assets_dir                 = plugin_dir_path( __FILE__ ) . 'Assets';
		$components_url             = plugin_dir_url( __FILE__ ) . '../Components/Assets';
		$components_dir             = dirname( plugin_dir_path( __FILE__ ) ) . '/Components/Assets';
		$use_original_report_assets = knit_pay_plugin()->is_debug_mode() && file_exists( $assets_dir . '/js/reports.js' );
		$use_original_ui_assets     = knit_pay_plugin()->is_debug_mode() && file_exists( $components_dir . '/js/knit-pay-ui.js' );

		$reports_css_file = $use_original_report_assets ? 'reports.css' : 'reports.min.css';

		$ui_css_file = $use_original_ui_assets ? 'knit-pay-ui.css' : 'knit-pay-ui.min.css';
		wp_enqueue_style(
			'knit-pay-ui-css',
			$components_url . '/css/' . $ui_css_file,
			[ 'dashicons' ],
			(string) filemtime( $components_dir . '/css/' . $ui_css_file )
		);

		wp_enqueue_style(
			'knit-pay-reports-css',
			$assets_url . '/css/' . $reports_css_file,
			[ 'dashicons', 'knit-pay-ui-css' ],
			(string) filemtime( $assets_dir . '/css/' . $reports_css_file )
		);

		wp_register_script(
			'knit-pay-chart-js',
			$assets_url . '/js/chart.umd.min.js',
			[],
			(string) filemtime( $assets_dir . '/js/chart.umd.min.js' ),
			true
		);

		$reports_js_file = $use_original_report_assets ? 'reports.js' : 'reports.min.js';
		$ui_js_file      = $use_original_ui_assets ? 'knit-pay-ui.js' : 'knit-pay-ui.min.js';

		wp_enqueue_script(
			'knit-pay-ui-js',
			$components_url . '/js/' . $ui_js_file,
			[],
			(string) filemtime( $components_dir . '/js/' . $ui_js_file ),
			true
		);

		wp_enqueue_script(
			'knit-pay-reports-js',
			$assets_url . '/js/' . $reports_js_file,
			[ 'knit-pay-chart-js', 'knit-pay-ui-js' ],
			(string) filemtime( $assets_dir . '/js/' . $reports_js_file ),
			true
		);

		wp_localize_script(
			'knit-pay-reports-js',
			'knitPayReports',
			[
				'api_url'          => rest_url( 'knit-pay/v1/reports/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'status_map'       => ReportsApiHelper::get_status_map(),
				'gateway_names'    => ( new QueryBuilder() )->get_gateway_list(),
				'method_names'     => ( new QueryBuilder() )->get_payment_method_list(),
				'source_options'   => ( new QueryBuilder() )->get_source_list(),
				'currency_options' => self::get_currency_codes(),
				'currency_symbols' => self::get_currency_symbols(),
				'chart_colors'     => apply_filters( 'knit_pay_reports_chart_colors', [ '#2271b1', '#00a32a', '#dba617', '#d63638', '#5a2d8c', '#0a4b78', '#8c8f94', '#a62626' ] ),
				'month_names'      => self::get_month_abbrev_names(),
				'default_columns'  => [ 'status', 'transaction', 'amount', 'date', 'gateway', 'customer', 'method', 'description' ],
				'csv_max_rows'     => (int) apply_filters( 'knit_pay_reports_csv_max_rows', 15000 ),
				'csv_warn_rows'    => (int) apply_filters( 'knit_pay_reports_csv_warn_rows', 2500 ),
				'fy_start'         => [
					'month' => 4,
					'day'   => 1,
				],
				'i18n'             => [
					'total_revenue'       => __( 'Total Revenue', 'knit-pay-lang' ),
					'total_transactions'  => __( 'Total Payments', 'knit-pay-lang' ),
					'success_rate'        => __( 'Success Rate', 'knit-pay-lang' ),
					'avg_transaction'     => __( 'Avg. Payment', 'knit-pay-lang' ),
					'revenue_trend'       => __( 'Revenue Trend', 'knit-pay-lang' ),
					'volume_trend'        => __( 'Transaction Count', 'knit-pay-lang' ),
					'status_distribution' => __( 'Status Distribution', 'knit-pay-lang' ),
					'top_gateways'        => __( 'Top Gateways', 'knit-pay-lang' ),
					'no_data'             => __( 'No data available for the selected filters.', 'knit-pay-lang' ),
					'loading'             => __( 'Loading…', 'knit-pay-lang' ),
					'export_csv'          => __( 'Export CSV', 'knit-pay-lang' ),
					'export_pdf'          => __( 'Export PDF', 'knit-pay-lang' ),
					'confirm_bulk'        => __( 'Apply bulk action to selected payments?', 'knit-pay-lang' ),
					'total_payments'      => __( 'Total Payments', 'knit-pay-lang' ),
					'refunded_payments'   => __( 'Refunded Payments', 'knit-pay-lang' ),
					'refund_rate'         => __( 'Refund Rate', 'knit-pay-lang' ),
					'refunded_amount'     => __( 'Refunded Payment Volume', 'knit-pay-lang' ),
					'refund_trend'        => __( 'Refund Trend', 'knit-pay-lang' ),
					'refunds_by_gateway'  => __( 'Refunds by Gateway', 'knit-pay-lang' ),
					'refunds_by_source'   => __( 'Refunds by Source', 'knit-pay-lang' ),
					'refunds_by_method'   => __( 'Refunds by Payment Method', 'knit-pay-lang' ),
					'source_distribution' => __( 'Source Distribution', 'knit-pay-lang' ),
					'source_success'      => __( 'Success Rate by Gateway (per Source)', 'knit-pay-lang' ),
					'source_revenue'      => __( 'Revenue per Source', 'knit-pay-lang' ),
					'search_all_results'  => __( 'Showing all results for \'{query}\'.', 'knit-pay-lang' ),
					'clear_search'        => __( 'Clear search', 'knit-pay-lang' ),
				],
			]
		);

		// Alpine must load AFTER reports.js + knit-pay-ui.js so their alpine:init listeners are registered before Alpine fires the event.
		wp_enqueue_script(
			'knit-pay-alpine-js',
			$assets_url . '/js/alpine.min.js',
			[ 'knit-pay-reports-js' ],
			(string) filemtime( $assets_dir . '/js/alpine.min.js' ),
			true
		);
	}

	public function register_rest_routes(): void {
		$controller = new ReportsRestController();
		$controller->register_routes();
	}

	public function render_page(): void {
		$visits = (int) get_user_meta( get_current_user_id(), self::NEW_BADGE_META_KEY, true );

		// Only count visits while the badge is still visible to avoid unnecessary writes.
		if ( $visits < self::NEW_BADGE_VISIT_THRESHOLD ) {
			update_user_meta( get_current_user_id(), self::NEW_BADGE_META_KEY, $visits + 1 );
		}

		$active_tab = 'overview';
		$tab        = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( is_string( $tab ) ) {
			$active_tab = sanitize_key( $tab );
		}

		$tabs        = $this->get_tabs();
		$filter_data = ReportsApiHelper::get_filter_options();

		include __DIR__ . '/views/page-reports.php';
	}

	private function get_tabs(): array {
		$tabs = [
			'overview'        => __( 'Overview', 'knit-pay-lang' ),
			'payments'        => __( 'Payments', 'knit-pay-lang' ),
			'gateways'        => __( 'Gateways', 'knit-pay-lang' ),
			'payment-methods' => __( 'Payment Methods', 'knit-pay-lang' ),
			'integrations'    => __( 'Integrations', 'knit-pay-lang' ),
		];

		/**
		 * Refunds tab is temporarily disabled for the v9.5.0 stable release.
		 *
		 * The refund aggregation logic needs corrections for multi-currency
		 * counting, zero-refund filtering, and chargeback handling. Re-enable
		 * once those issues are fixed.
		 *
		 * @todo Re-enable refunds tab after fixing refund/chargeback aggregation.
		 */

		return apply_filters( 'knit_pay_reports_tabs', $tabs );
	}

	private static function get_currency_symbols(): array {
		$symbols = [];
		foreach ( Currencies::get_currencies() as $code => $currency ) {
			$sym = $currency->get_symbol();
			if ( $sym ) {
				$symbols[ $code ] = $sym;
			}
		}
		return $symbols;
	}

	private static function get_currency_codes(): array {
		return ( new QueryBuilder() )->get_currency_list();
	}

	private static function get_month_abbrev_names(): array {
		global $wp_locale;
		$names = [];
		for ( $i = 1; $i <= 12; $i++ ) {
			$names[ $i ] = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
		}
		return $names;
	}
}
