<?php

use Pronamic\WordPress\Pay\Core\PaymentMethodsCollection;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
add_filter(
	'knit_pay_add_payment_methods',
	function ( PaymentMethodsCollection $payment_methods ) {
		$active_payment_methods = PaymentMethods::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method_id ) {
		
			if ( null === $payment_methods->get( $payment_method_id ) ) {
				$payment_method = new PaymentMethod( $payment_method_id, PaymentMethods::get_name( $payment_method_id, $payment_method_id ) );
				$payment_methods->add( $payment_method );
			}
		}
	
		return $payment_methods;
	}
);

add_filter(
	'knit_pay_add_payment_methods_titles',
	function ( $payment_methods ) {
		$payment_methods['upi']         = 'UPI';
		$payment_methods['paytm']       = 'Paytm';
		$payment_methods['debit_card']  = 'Debit Card';
		$payment_methods['net_banking'] = 'NetBanking';
	
		$payment_methods['ebs']           = 'EBS';
		$payment_methods['flutterwave']   = 'Flutter Wave';
		$payment_methods['icici_eazypay'] = 'ICICI EazyPay';
		$payment_methods['open_money']    = 'Open Money';
		$payment_methods['cashfree']      = 'Cashfree';
		$payment_methods['ccavenue']      = 'CCAvenue';
		$payment_methods['easebuzz']      = 'Easebuzz';
		$payment_methods['instamojo']     = 'Instamojo';
		$payment_methods['pay_u']         = 'PayU';
		$payment_methods['razorpay']      = 'Razorpay';
		$payment_methods['sodexo']        = 'Sodexo';
		$payment_methods['stripe']        = 'Stripe';
	
		return $payment_methods;
	}
);
