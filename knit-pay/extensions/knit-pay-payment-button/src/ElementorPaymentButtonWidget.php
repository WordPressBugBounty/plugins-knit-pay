<?php

namespace KnitPay\Extensions\KnitPayPaymentButton;

use Pronamic\WordPress\Money\Currencies;
use Pronamic\WordPress\Pay\Plugin;
use Elementor\Includes\Widgets\Traits\Button_Trait;
use Elementor\Controls_Manager;
use \Elementor\Widget_Base;


/**
 * Title: Knit Pay - Payment Button Widget for Elementor
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.75.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class ElementorPaymentButtonWidget extends Widget_Base {
	use Button_Trait;
	
	public function get_name() {
		return 'knit_pay_payment_button';
	}
	
	public function get_title() {
		return esc_html__( 'Knit Pay - Payment Button', 'knit-pay-lang' );
	}
	
	public function get_icon() {
		return 'eicon-button';
	}
	
	public function get_custom_help_url() {
		return 'https://www.knitpay.org/contact-us/';
	}
	
	public function get_categories() {
		return [ 'basic', 'general' ];
	}
	
	public function get_keywords() {
		return [ 'instamojo', 'pay u', 'razorpay', 'cashfree', 'upi', 'easebuzz', 'ccavenue' ];
	}
	
	public function get_script_depends() {
		return [ 'knit-pay-payment-button-frontend' ];
	}
	
	protected function register_controls() {
		// Payment Settings
		$this->start_controls_section(
			'section_knit_pay_payment_button',
			[
				'label' => esc_html__( 'Payment Settings', 'elementor' ),
			]
		);
		
		$this->add_control(
			'payment_description',
			[
				'label'       => esc_html__( 'Payment Description', 'textdomain' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => [
					'active' => true,
				],
				'description' => esc_html__( 'Enter Payment Description.', 'textdomain' ),
				'label_block' => true,
			]
		);
		
		$this->add_control(
			'amount',
			[
				'label'       => esc_html__( 'Amount', 'textdomain' ),
				'type'        => Controls_Manager::NUMBER,
				'dynamic'     => [
					'active' => true,
				],
				'min'         => 0,
				'description' => esc_html__( 'Enter Fixed Amount to be collected from the customer.', 'textdomain' ),
			]
		);
		
		$this->add_control(
			'currency',
			[
				'label'   => esc_html__( 'Currency', 'textdomain' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->get_currencies(),
			]
		);
		
		$this->add_control(
			'config_id',
			[
				'label'       => esc_html__( 'Configuration', 'textdomain' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => Plugin::get_config_select_options(),
				'description' => '<strong>Configurations</strong> can be created in Knit Pay gateway configurations page at "Knit Pay >> Configurations"',
				'default'     => 0,
			]
		);
		
		$this->end_controls_section();
		
		
		// Button Settings.
		$this->start_controls_section(
			'section_button',
			[
				'label' => esc_html__( 'Button', 'elementor' ),
			]
		);
		
		$this->register_button_content_controls();
		$this->remove_control( 'link' );
		
		$this->end_controls_section();
		
		// Button Style.
		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Button', 'elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);
		
		$this->register_button_style_controls();
		
		$this->end_controls_section();
		
	}
	
	protected function render() {
		$this->add_link_attributes( 'button', [ 'url' => '#' ] );
		
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			$this->render_button( $this );
			return;
		}
		
		$form_id  = 'knit-pay-payment-button-form-' . uniqid();
		$settings = $this->get_settings_for_display();
		$this->add_render_attribute( 'button', 'class', 'knit-pay-payment-button-submit-class' );
		$this->add_render_attribute( 'text', 'id', 'knit-pay-payment-button-submit' );
		$this->add_render_attribute( 'text', 'knit-pay-button-text', $settings['text'] );
		
		?>
		
		<form id="<?php echo $form_id; ?>" class="knit-pay-payment-button-form-class" method="post">
			<?php 
			   $nonce_action = "knit_pay_payment_button|{$settings['amount']}|{$settings['currency']}|{$settings['payment_description']}|{$settings['config_id']}";
			   wp_nonce_field( $nonce_action, 'knit_pay_nonce' );
			?>
			<input type="hidden" id="knit_pay_payment_button_amount" value="<?php echo esc_attr( $settings['amount'] ); ?>">
			<input type="hidden" id="knit_pay_payment_button_currency" value="<?php echo esc_attr( $settings['currency'] ); ?>">
			<input type="hidden" id="knit_pay_payment_button_payment_description" value="<?php echo esc_attr( $settings['payment_description'] ); ?>">
			<input type="hidden" id="knit_pay_payment_button_config_id" value="<?php echo esc_attr( $settings['config_id'] ); ?>">
		
			<?php $this->render_button( $this ); ?>
		</form>

		<?php
	}
	
	protected function content_template() {
	}
	
	private function get_currencies() {
		foreach ( Currencies::get_currencies() as $currency ) {
			$label = $currency->get_alphabetic_code();
			
			$symbol = $currency->get_symbol();
			
			if ( null !== $symbol ) {
				$label = sprintf( '%s (%s)', $label, $symbol );
			}
			
			$currencies_options[ $currency->get_alphabetic_code() ] = $label;
		}
		return $currencies_options;
	}
}
