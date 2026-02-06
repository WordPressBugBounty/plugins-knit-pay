<?php
namespace Pronamic\WordPress\Pay\Extensions\NinjaForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NF_Abstracts_SubmissionMetabox;

final class SubmissionMetabox extends NF_Abstracts_SubmissionMetabox {

	public function __construct() {
		$this->registerReactMetabox();
		
		parent::__construct();

		$this->_title = __( 'Knit Pay Payment Details', 'ninja-forms' );

	}

	public function render_metabox( $post, $metabox ) {
		echo '<dl>';

		echo '<dt>';
		echo __( 'Payment Status', 'knit-pay' );
		echo '</dt>';

		echo '<dd>';
		echo $this->sub->get_extra_value( 'knit_pay_status' );
		echo '</dd>';

		if ( $this->sub->get_extra_value( 'knit_pay_transaction_id' ) ) {
			echo '<dt>';
			echo __( 'Knit Pay Transaction ID', 'knit-pay' );
			echo '</dt>';

			echo '<dd>';
			echo $this->sub->get_extra_value( 'knit_pay_transaction_id' );
			echo '</dd>';
		}

		if ( $this->sub->get_extra_value( 'knit_pay_payment_id' ) ) {
			echo '<dt>';
			echo __( 'Knit Pay Payment ID', 'knit-pay' );
			echo '</dt>';

			echo '<dd>';
			echo $this->sub->get_extra_value( 'knit_pay_payment_id' );
			echo '</dd>';
		}

		if ( $this->sub->get_extra_value( 'knit_pay_amount' ) ) {
			echo '<dt>';
			echo __( 'Knit Pay Amount', 'knit-pay' );
			echo '</dt>';

			echo '<dd>';
			echo $this->sub->get_extra_value( 'knit_pay_amount' );
			echo '</dd>';
		}

	}
	
	/**
	 * Register the React metabox for calculations
	 *
	 * @return void
	 */
	protected function registerReactMetabox(): void {
		add_filter( 'nf_react_table_extra_value_keys', array( $this, 'nfAddMetabox' ) );
	}
	
	
	/**
	 * Add a metabox constructor to the react.js submissions page
	 *
	 * @param array $metaboxHandlers
	 * @return array
	 */
	public function nfAddMetabox( array $metaboxHandlers ): array {
		require_once 'SubmissionMetaboxReact.php';
		
		$metaboxHandlers['knit_pay_payment_details'] = 'Pronamic\WordPress\Pay\Extensions\NinjaForms\SubmissionMetaboxReact';
		
		return $metaboxHandlers;
	}
}
