<?php
/**
 * Subscription Frequency field
 *
 * @author    Knit Pay
 * @copyright 2020-2022 Knit Pay
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Extensions\NinjaForms
 */

namespace Pronamic\WordPress\Pay\Extensions\NinjaForms;

use NF_Abstracts_List;

/**
 * Payment methods field
 *
 * @version 1.0.1
 * @since   1.0.0
 */
class RecurringIntervalPeriodField extends NF_Abstracts_List {

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected $_name = 'knit_pay_recurring_interval_period';

	/**
	 * Type.
	 *
	 * @var string
	 */
	protected $_type = 'knit_pay_recurring_interval_period';

	/**
	 * Nice name for display.
	 *
	 * @var string
	 */
	protected $_nicename = 'Interval Period';

	/**
	 * Section.
	 *
	 * @var string
	 */
	protected $_section = 'pronamic_pay';

	/**
	 * Icon.
	 *
	 * @var string
	 */
	protected $_icon = 'refresh';

	/**
	 * Template.
	 *
	 * @var string
	 */
	protected $_templates = 'listselect';

	/**
	 * Old classname for earlier versions.
	 *
	 * @var string
	 */
	protected $_old_classname = 'list-select';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	protected $_settings = array();

	/**
	 * Constructs and initializes the field object.
	 */
	public function __construct() {
		parent::__construct();

		// Set field properties.
		$this->_nicename = __( 'Interval Period', 'knit-pay' );

		$this->_settings['options']['value'] = $this->get_list_options();

		add_filter( 'ninja_forms_render_options_' . $this->_type, array( $this, 'render_options' ) );

		// Remove calc field for options.
		unset( $this->_settings['options']['columns']['calc'] );
		unset( $this->_settings['options']['columns']['selected'] );
	}

	/**
	 * Get default Pronamic payment method options.
	 *
	 * @return array
	 */
	private function get_list_options() {
		$options = array();

		$order = 0;

		$list_options = array(
			'D' => __( 'Daily', 'pronamic_ideal' ),
			'W' => __( 'Weekly', 'pronamic_ideal' ),
			'M' => __( 'Monthly', 'pronamic_ideal' ),
			'Y' => __( 'Yearly', 'pronamic_ideal' ),
		);

		foreach ( $list_options as $value => $label ) {
			$options[] = array(
				'label'    => $label,
				'value'    => $value,
				'calc'     => '',
				'selected' => 1,
				'order'    => $order,
			);

			$order++;
		}

		return $options;
	}

	/**
	 * Render options.
	 *
	 * @param array $options Options.
	 *
	 * @return array
	 */
	public function render_options( $options ) {
		foreach ( $options as &$option ) {
			if ( 0 === $option['value'] ) {
				$option['value'] = '';
			}
		}

		return $options;
	}
}
