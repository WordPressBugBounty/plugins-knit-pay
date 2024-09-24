<?php

namespace KnitPay\Gateways\OrderBox;

/**
 * Title: Orderbox Checksum
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.65.0.0
 * @since   6.65.0.0
 */

class Checksum {

	public static function generateChecksum( $transId, $sellingCurrencyAmount, $accountingCurrencyAmount, $status, $rkey, $key ) {
		$str               = "$transId|$sellingCurrencyAmount|$accountingCurrencyAmount|$status|$rkey|$key";
		$generatedCheckSum = md5( $str );
		return $generatedCheckSum;
	}
	
	public static function verifyChecksum( $paymentTypeId, $transId, $userId, $userType, $transactionType, $invoiceIds, $debitNoteIds, $description, $sellingCurrencyAmount, $accountingCurrencyAmount, $key, $checksum ) {
		$str               = "$paymentTypeId|$transId|$userId|$userType|$transactionType|$invoiceIds|$debitNoteIds|$description|$sellingCurrencyAmount|$accountingCurrencyAmount|$key";
		$generatedCheckSum = md5( $str );
	
		return ( $generatedCheckSum === $checksum );
	}

}
