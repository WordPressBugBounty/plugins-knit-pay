<?php 
namespace Pronamic\WordPress\Pay\Extensions\NinjaForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Construct Calculations metabox for the React Submissions page
 *
 * Class must have a public function handle that can receive the $extra_value
 * and a NF submission.
 * 
 * Output of handle method is a \NinjaForms\Includes\Entities\MetaboxOutputEntity with two properties:
 * 
 * 'title' (string output of metabox title/header)
 * 'labelValueCollection' – indexed array of label values
 * 
 * Each label value array has three keys:
 *   'label' – label of the output
 *   'value' –  value of that being output
 *   'styling' – currently accepts 'alert' to add an 'alert' class for CSS styling
 */
class SubmissionMetaboxReact {


	/**
	 * Given submission '$extra' data and the complete submission, return array
	 * construct for metabox If nothing to output, then return null
	 *
	 * If the '$extra' data contains all required information, then simply
	 * construct that as label/value/styling arrays.
	 *
	 * If your output requires other information from the submission, use the
	 * $nf_sub to extract the required information.
	 *
	 * Note that in this example, we want additional information from the
	 * submission for output so we disregard the $extra_value and work directly
	 * with the $nf_sub to extract the information.
	 * 
	 * @param mixed $extra_value
	 * @param NF_Database_Models_Submission $nf_sub
	 * @return \NinjaForms\Includes\Entities\MetaboxOutputEntity|null
	 */
	public function handle( $extra_value, $nf_sub ): ?\NinjaForms\Includes\Entities\MetaboxOutputEntity {
		$return = null;
		
		$this->sub = Ninja_Forms()->form()->get_sub( $nf_sub->get_id() );

		$knit_pay_status         = $this->sub->get_extra_value( 'knit_pay_status' );
		$knit_pay_transaction_id = $this->sub->get_extra_value( 'knit_pay_transaction_id' );
		$knit_pay_payment_id     = $this->sub->get_extra_value( 'knit_pay_payment_id' );
		$knit_pay_amount         = $this->sub->get_extra_value( 'knit_pay_amount' );

		// extract/construct the label/value/styling arrays
		$label_value_collection   = array();
		$label_value_collection[] = array(
			'label' => __( 'Payment Status', 'knit-pay' ),
			'value' => $knit_pay_status,
		);

		if ( $knit_pay_transaction_id ) {
			$label_value_collection[] = array(
				'label' => __( 'Transaction ID', 'knit-pay' ),
				'value' => $knit_pay_transaction_id,
			);
		}
		if ( $knit_pay_payment_id ) {
			$label_value_collection[] = array(
				'label' => __( 'Payment ID', 'knit-pay' ),
				'value' => $knit_pay_payment_id,
			);
		}
		if ( $knit_pay_amount ) {
			$label_value_collection[] = array(
				'label' => __( 'Amount', 'knit-pay' ),
				'value' => $knit_pay_amount,
			);
		}
		
		if ( ! empty( $label_value_collection ) ) {

			$array = array(
				// Set a translatable title for your metabox
				'title'                => __( 'Knit Pay Payment Details', 'ninja-forms' ),

				// set the label/value/styling
				'labelValueCollection' => $label_value_collection,

			);

			$return = \NinjaForms\Includes\Entities\MetaboxOutputEntity::fromArray( $array );
		}
		return $return;
	}
}
