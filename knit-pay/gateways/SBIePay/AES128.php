<?php

namespace KnitPay\Gateways\SBIePay;

class AES128 {
	public static function encrypt( $data, $key ) {
		$algo = 'aes-128-cbc';

		$iv          = substr( $key, 0, 16 );
		$cipher_text = openssl_encrypt( $data, $algo, $key, OPENSSL_RAW_DATA, $iv );
		$cipher_text = base64_encode( $cipher_text );

		return $cipher_text;
	}

	public static function decrypt( $cipher_text, $key ) {
		$algo = 'aes-128-cbc';

		$iv          = substr( $key, 0, 16 );
		$cipher_text = base64_decode( $cipher_text );
		$plain_text  = openssl_decrypt( $cipher_text, $algo, $key, OPENSSL_RAW_DATA, $iv );

		return $plain_text;
	}
}
