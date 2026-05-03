<?php

namespace KnitPay\Gateways\OrderBox;

/**
 * Title: Orderbox Checksum
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.65.0.0
 * @since   6.65.0.0
 */

class Checksum {

	public static function generateChecksum( $trans_id, $selling_currency_amount, $accounting_currency_amount, $status, $rkey, $key ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$str                = "{$trans_id}|{$selling_currency_amount}|{$accounting_currency_amount}|{$status}|{$rkey}|{$key}";
		$generated_checksum = md5( $str );
		return $generated_checksum;
	}

	public static function verifyChecksum( $payment_type_id, $trans_id, $user_id, $user_type, $transaction_type, $invoice_ids, $debit_note_ids, $description, $selling_currency_amount, $accounting_currency_amount, $key, $checksum ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$str                = "{$payment_type_id}|{$trans_id}|{$user_id}|{$user_type}|{$transaction_type}|{$invoice_ids}|{$debit_note_ids}|{$description}|{$selling_currency_amount}|{$accounting_currency_amount}|{$key}";
		$generated_checksum = md5( $str );

		return ( $generated_checksum === $checksum );
	}
}
