<?php

namespace KnitPay\Gateways\NMI;

use Pronamic\WordPress\Pay\Payments\Payment;

class API {

	// Initial Setting Functions
	function setLogin( $security_key ) {
		$this->login['security_key'] = $security_key;
	}

	function setOrder( Payment $payment ) {
			$this->order['orderid']          = $payment->get_transaction_id();
			$this->order['orderdescription'] = $payment->get_description();
			$this->order['tax']              = 0;
			$this->order['shipping']         = 0;
			$this->order['ponumber']         = $payment->get_order_id();
			$this->order['ipaddress']        = $payment->get_customer()->get_ip_address();
	}

	function setBilling( Payment $payment ) {
			$billing_address = $payment->get_billing_address();

			$this->billing['firstname'] = $payment->get_customer()->get_name()->get_first_name();
			$this->billing['lastname']  = $payment->get_customer()->get_name()->get_last_name();
			$this->billing['company']   = $billing_address->get_company_name();
			$this->billing['address1']  = $billing_address->get_line_1();
			$this->billing['address2']  = $billing_address->get_line_2();
			$this->billing['city']      = $billing_address->get_city();
			$this->billing['state']     = $billing_address->get_region();
			$this->billing['zip']       = $billing_address->get_postal_code();
			$this->billing['country']   = $billing_address->get_country_code();
			$this->billing['phone']     = $billing_address->get_phone();
			// $this->billing['fax']       = $fax;
			$this->billing['email']   = $payment->get_email();
			$this->billing['website'] = home_url( '/' );
	}

	// Transaction Functions
	function doSale( Payment $payment, $payment_token ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Sales Information
		$query .= 'payment_token=' . urlencode( $payment_token ) . '&';
		// $query .= "ccnumber=" . urlencode($ccnumber) . "&";
		// $query .= "ccexp=" . urlencode($ccexp) . "&";
		$query .= 'amount=' . urlencode( $payment->get_total_amount()->number_format( null, '.', '' ) ) . '&';
		$query .= 'currency=' . urlencode( $payment->get_total_amount()->get_currency()->get_alphabetic_code() ) . '&';
		// $query .= "cvv=" . urlencode($cvv) . "&";
		// Order Information
		$query .= 'ipaddress=' . urlencode( $this->order['ipaddress'] ) . '&';
		$query .= 'orderid=' . urlencode( $this->order['orderid'] ) . '&';
		$query .= 'orderdescription=' . urlencode( $this->order['orderdescription'] ) . '&';
		$query .= 'tax=' . urlencode( number_format( $this->order['tax'], 2, '.', '' ) ) . '&';
		$query .= 'shipping=' . urlencode( number_format( $this->order['shipping'], 2, '.', '' ) ) . '&';
		$query .= 'ponumber=' . urlencode( $this->order['ponumber'] ) . '&';
		// Billing Information
		$query .= 'firstname=' . urlencode( $this->billing['firstname'] ) . '&';
		$query .= 'lastname=' . urlencode( $this->billing['lastname'] ) . '&';
		$query .= 'company=' . urlencode( $this->billing['company'] ) . '&';
		$query .= 'address1=' . urlencode( $this->billing['address1'] ) . '&';
		$query .= 'address2=' . urlencode( $this->billing['address2'] ) . '&';
		$query .= 'city=' . urlencode( $this->billing['city'] ) . '&';
		$query .= 'state=' . urlencode( $this->billing['state'] ) . '&';
		$query .= 'zip=' . urlencode( $this->billing['zip'] ) . '&';
		$query .= 'country=' . urlencode( $this->billing['country'] ) . '&';
		$query .= 'phone=' . urlencode( $this->billing['phone'] ) . '&';
		// $query .= "fax=" . urlencode($this->billing['fax']) . "&";
		$query .= 'email=' . urlencode( $this->billing['email'] ) . '&';
		$query .= 'website=' . urlencode( $this->billing['website'] ) . '&';
		$query .= 'type=sale';
		return $this->_doPost( $query );
	}

	function doAuth( $amount, $ccnumber, $ccexp, $cvv = '' ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Sales Information
		$query .= 'ccnumber=' . urlencode( $ccnumber ) . '&';
		$query .= 'ccexp=' . urlencode( $ccexp ) . '&';
		$query .= 'amount=' . urlencode( number_format( $amount, 2, '.', '' ) ) . '&';
		$query .= 'cvv=' . urlencode( $cvv ) . '&';
		// Order Information
		$query .= 'ipaddress=' . urlencode( $this->order['ipaddress'] ) . '&';
		$query .= 'orderid=' . urlencode( $this->order['orderid'] ) . '&';
		$query .= 'orderdescription=' . urlencode( $this->order['orderdescription'] ) . '&';
		$query .= 'tax=' . urlencode( number_format( $this->order['tax'], 2, '.', '' ) ) . '&';
		$query .= 'shipping=' . urlencode( number_format( $this->order['shipping'], 2, '.', '' ) ) . '&';
		$query .= 'ponumber=' . urlencode( $this->order['ponumber'] ) . '&';
		// Billing Information
		$query .= 'firstname=' . urlencode( $this->billing['firstname'] ) . '&';
		$query .= 'lastname=' . urlencode( $this->billing['lastname'] ) . '&';
		$query .= 'company=' . urlencode( $this->billing['company'] ) . '&';
		$query .= 'address1=' . urlencode( $this->billing['address1'] ) . '&';
		$query .= 'address2=' . urlencode( $this->billing['address2'] ) . '&';
		$query .= 'city=' . urlencode( $this->billing['city'] ) . '&';
		$query .= 'state=' . urlencode( $this->billing['state'] ) . '&';
		$query .= 'zip=' . urlencode( $this->billing['zip'] ) . '&';
		$query .= 'country=' . urlencode( $this->billing['country'] ) . '&';
		$query .= 'phone=' . urlencode( $this->billing['phone'] ) . '&';
		$query .= 'fax=' . urlencode( $this->billing['fax'] ) . '&';
		$query .= 'email=' . urlencode( $this->billing['email'] ) . '&';
		$query .= 'website=' . urlencode( $this->billing['website'] ) . '&';

		$query .= 'type=auth';
		return $this->_doPost( $query );
	}

	function doCredit( $amount, $ccnumber, $ccexp ) {

		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Sales Information
		$query .= 'ccnumber=' . urlencode( $ccnumber ) . '&';
		$query .= 'ccexp=' . urlencode( $ccexp ) . '&';
		$query .= 'amount=' . urlencode( number_format( $amount, 2, '.', '' ) ) . '&';
		// Order Information
		$query .= 'ipaddress=' . urlencode( $this->order['ipaddress'] ) . '&';
		$query .= 'orderid=' . urlencode( $this->order['orderid'] ) . '&';
		$query .= 'orderdescription=' . urlencode( $this->order['orderdescription'] ) . '&';
		$query .= 'tax=' . urlencode( number_format( $this->order['tax'], 2, '.', '' ) ) . '&';
		$query .= 'shipping=' . urlencode( number_format( $this->order['shipping'], 2, '.', '' ) ) . '&';
		$query .= 'ponumber=' . urlencode( $this->order['ponumber'] ) . '&';
		// Billing Information
		$query .= 'firstname=' . urlencode( $this->billing['firstname'] ) . '&';
		$query .= 'lastname=' . urlencode( $this->billing['lastname'] ) . '&';
		$query .= 'company=' . urlencode( $this->billing['company'] ) . '&';
		$query .= 'address1=' . urlencode( $this->billing['address1'] ) . '&';
		$query .= 'address2=' . urlencode( $this->billing['address2'] ) . '&';
		$query .= 'city=' . urlencode( $this->billing['city'] ) . '&';
		$query .= 'state=' . urlencode( $this->billing['state'] ) . '&';
		$query .= 'zip=' . urlencode( $this->billing['zip'] ) . '&';
		$query .= 'country=' . urlencode( $this->billing['country'] ) . '&';
		$query .= 'phone=' . urlencode( $this->billing['phone'] ) . '&';
		$query .= 'fax=' . urlencode( $this->billing['fax'] ) . '&';
		$query .= 'email=' . urlencode( $this->billing['email'] ) . '&';
		$query .= 'website=' . urlencode( $this->billing['website'] ) . '&';
		$query .= 'type=credit';
		return $this->_doPost( $query );
	}

	function doOffline( $authorizationcode, $amount, $ccnumber, $ccexp ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Sales Information
		$query .= 'ccnumber=' . urlencode( $ccnumber ) . '&';
		$query .= 'ccexp=' . urlencode( $ccexp ) . '&';
		$query .= 'amount=' . urlencode( number_format( $amount, 2, '.', '' ) ) . '&';
		$query .= 'authorizationcode=' . urlencode( $authorizationcode ) . '&';
		// Order Information
		$query .= 'ipaddress=' . urlencode( $this->order['ipaddress'] ) . '&';
		$query .= 'orderid=' . urlencode( $this->order['orderid'] ) . '&';
		$query .= 'orderdescription=' . urlencode( $this->order['orderdescription'] ) . '&';
		$query .= 'tax=' . urlencode( number_format( $this->order['tax'], 2, '.', '' ) ) . '&';
		$query .= 'shipping=' . urlencode( number_format( $this->order['shipping'], 2, '.', '' ) ) . '&';
		$query .= 'ponumber=' . urlencode( $this->order['ponumber'] ) . '&';
		// Billing Information
		$query .= 'firstname=' . urlencode( $this->billing['firstname'] ) . '&';
		$query .= 'lastname=' . urlencode( $this->billing['lastname'] ) . '&';
		$query .= 'company=' . urlencode( $this->billing['company'] ) . '&';
		$query .= 'address1=' . urlencode( $this->billing['address1'] ) . '&';
		$query .= 'address2=' . urlencode( $this->billing['address2'] ) . '&';
		$query .= 'city=' . urlencode( $this->billing['city'] ) . '&';
		$query .= 'state=' . urlencode( $this->billing['state'] ) . '&';
		$query .= 'zip=' . urlencode( $this->billing['zip'] ) . '&';
		$query .= 'country=' . urlencode( $this->billing['country'] ) . '&';
		$query .= 'phone=' . urlencode( $this->billing['phone'] ) . '&';
		$query .= 'fax=' . urlencode( $this->billing['fax'] ) . '&';
		$query .= 'email=' . urlencode( $this->billing['email'] ) . '&';
		$query .= 'website=' . urlencode( $this->billing['website'] ) . '&';

		$query .= 'type=offline';
		return $this->_doPost( $query );
	}

	function doCapture( $transactionid, $amount = 0 ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Transaction Information
		$query .= 'transactionid=' . urlencode( $transactionid ) . '&';
		if ( $amount > 0 ) {
			$query .= 'amount=' . urlencode( number_format( $amount, 2, '.', '' ) ) . '&';
		}
		$query .= 'type=capture';
		return $this->_doPost( $query );
	}

	function doVoid( $transactionid ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Transaction Information
		$query .= 'transactionid=' . urlencode( $transactionid ) . '&';
		$query .= 'type=void';
		return $this->_doPost( $query );
	}

	function doRefund( $transactionid, $amount = 0 ) {
		$query = '';
		// Login Information
		$query .= 'security_key=' . urlencode( $this->login['security_key'] ) . '&';
		// Transaction Information
		$query .= 'transactionid=' . urlencode( $transactionid ) . '&';
		if ( $amount > 0 ) {
			$query .= 'amount=' . urlencode( number_format( $amount, 2, '.', '' ) ) . '&';
		}
		$query .= 'type=refund';
		return $this->_doPost( $query );
	}

	function _doPost( $query ) {
		// TODO Use WordPress Functions.
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://secure.networkmerchants.com/api/transact.php' );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

		curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
		curl_setopt( $ch, CURLOPT_POST, 1 );

		if ( ! ( $data = curl_exec( $ch ) ) ) {
			return ERROR;
		}
		curl_close( $ch );
		unset( $ch );

		$data = explode( '&', $data );
		for ( $i = 0;$i < count( $data );$i++ ) {
			$rdata                        = explode( '=', $data[ $i ] );
			$this->responses[ $rdata[0] ] = $rdata[1];
		}
		return $this->responses['response'];
	}
}
