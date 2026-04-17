<?php

namespace KnitPay\Gateways\SabPaisa;

use Exception;

/**
 * Title: Sab Paisa API
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.99.4.0
 * @since 8.75.3.0
 */
class API {
	private $config;
	private $mode;

	private const CIPHER_KEY_LEN = 16;

	// Constants for new encryption method (AES-256-GCM).
	private const IV_SIZE     = 12; // 96 bits for GCM.
	private const TAG_SIZE    = 16; // 128 bits = 16 bytes.
	private const HMAC_LENGTH = 48; // SHA-384 = 48 bytes.

	public function __construct( Config $config, $mode ) {
		$this->config = $config;
		$this->mode   = $mode;
	}

	/**
	 * Detect if the key is in new format (base64 encoded)
	 */
	private function is_new_key_format( $key ) {
		// Check if it's valid base64 and decodes to 32 bytes (256 bits).
		$decoded = base64_decode( $key, true );
		if ( $decoded !== false && 32 === strlen( $decoded ) ) {
			return true;
		}

		return false;
	}

	public function get_endpoint_url() {
		if ( 'test' === $this->mode ) {
			return 'https://stage-securepay.sabpaisa.in/SabPaisa/sabPaisaInit?v=1';
		} else {
			return 'https://securepay.sabpaisa.in/SabPaisa/sabPaisaInit?v=1';
		}
	}

	private static function fix_key( $key ) {
		if ( strlen( $key ) < self::CIPHER_KEY_LEN ) {
			return str_pad( "$key", self::CIPHER_KEY_LEN, '0' );
		} elseif ( strlen( $key ) > self::CIPHER_KEY_LEN ) {
			return substr( $key, 0, self::CIPHER_KEY_LEN );
		}
		return $key;
	}

	public function get_transaction_status( $transaction_id ) {
		if ( 'test' === $this->mode ) {
			$url = 'https://stage-txnenquiry.sabpaisa.in/SPTxtnEnquiry/getTxnStatusByClientxnId';
		} else {
			$url = 'https://txnenquiry.sabpaisa.in/SPTxtnEnquiry/getTxnStatusByClientxnId';
		}

		$data           = [
			'clientCode'  => $this->config->client_code,
			'clientTxnId' => $transaction_id,
		];
		$encrypted_data = $this->get_encrypted_data_array( $data, 'statusTransEncData' );

		$response = wp_remote_post(
			$url,
			[
				'body'    => wp_json_encode( $encrypted_data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result, true );

		if ( ! isset( $result['statusResponseData'] ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		$dec_text = $this->decrypt( $result['statusResponseData'] );

		parse_str( $dec_text, $result_array );

		return $result_array;
	}

	private function get_request_headers() {
		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		];
		return $headers;
	}

	/**
	 * Encrypts the data using the appropriate encryption method based on the key format.
	 *
	 * @param string $data The data to encrypt.
	 * @return string The encrypted data.
	 */
	private function encrypt( $data ) {
		$key = $this->config->auth_key;
		$iv  = $this->config->auth_iv;

		// Detect which encryption method to use.
		if ( $this->is_new_key_format( $key ) ) {
			return $this->encrypt_new( $key, $iv, $data );
		} else {
			return $this->encrypt_old( $key, $iv, $data );
		}
	}

	// TODO - Remove this method after 1 Jan 2028.
	/**
	 * Old encryption method (AES-128-CBC)
	 *
	 * @param string $key  Encryption key.
	 * @param string $iv   Initialization vector.
	 * @param string $data Data to encrypt.
	 * @return string Encrypted data.
	 */
	private function encrypt_old( $key, $iv, $data ) {
		$encoded_encrypted_data = base64_encode( openssl_encrypt( $data, 'aes-128-cbc', self::fix_key( $key ), OPENSSL_RAW_DATA, $iv ) );
		$encoded_iv             = base64_encode( $iv );
		$encrypted_payload      = $encoded_encrypted_data . ':' . $encoded_iv;

		return $encrypted_payload;
	}

	/**
	 * New encryption method (AES-256-GCM with HMAC-SHA384)
	 *
	 * @param string $aes_key_base64  Base64 encoded AES key.
	 * @param string $hmac_key_base64 Base64 encoded HMAC key.
	 * @param string $plaintext       Plaintext to encrypt.
	 * @return string Encrypted data in hex format.
	 * @throws Exception If encryption fails.
	 */
	private function encrypt_new( $aes_key_base64, $hmac_key_base64, $plaintext ) {
		$aes_key  = base64_decode( $aes_key_base64, true );
		$hmac_key = base64_decode( $hmac_key_base64, true );

		$iv  = random_bytes( self::IV_SIZE );
		$tag = '';

		$cipher_text = openssl_encrypt(
			$plaintext,
			'aes-256-gcm',
			$aes_key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_SIZE
		);

		if ( false === $cipher_text ) {
			throw new Exception( 'Encryption failed.' );
		}

		$encrypted_message = $iv . $cipher_text . $tag;

		// Generate HMAC.
		$hmac = hash_hmac( 'sha384', $encrypted_message, $hmac_key, true );

		// Final message: [HMAC || IV || CipherText || Tag].
		$final_output = $hmac . $encrypted_message;

		return strtoupper( bin2hex( $final_output ) );
	}

	/**
	 * Decrypts the data using the appropriate decryption method based on the key format.
	 *
	 * @param string $data Encrypted data.
	 * @return string Decrypted data.
	 */
	private function decrypt( $data ) {
		$key = $this->config->auth_key;
		$iv  = $this->config->auth_iv;

		// Detect which decryption method to use.
		if ( $this->is_new_key_format( $key ) ) {
			return $this->decrypt_new( $key, $iv, $data );
		} else {
			return $this->decrypt_old( $key, $data );
		}
	}

	// TODO - Remove this method after 1 Jan 2028.
	/**
	 * Old decryption method (AES-128-CBC)
	 *
	 * @param string $key  Encryption key.
	 * @param string $data Encrypted data.
	 */
	private function decrypt_old( $key, $data ) {
		$parts     = explode( ':', $data );
		$encrypted = $parts[0];

		$decrypted_data = openssl_decrypt( base64_decode( $encrypted ), 'aes-128-cbc', self::fix_key( $key ), OPENSSL_RAW_DATA );
		return $decrypted_data;
	}

	/**
	 * New decryption method (AES-256-GCM with HMAC-SHA384)
	 *
	 * @param string $aes_key_base64  Base64 encoded AES key.
	 * @param string $hmac_key_base64 Base64 encoded HMAC key.
	 * @param string $hex_cipher_text Hex encoded cipher text.
	 * @return string Decrypted data.
	 * @throws Exception If decryption fails.
	 */
	private function decrypt_new( $aes_key_base64, $hmac_key_base64, $hex_cipher_text ) {
		$aes_key  = base64_decode( $aes_key_base64, true );
		$hmac_key = base64_decode( $hmac_key_base64, true );

		$full_message = hex2bin( $hex_cipher_text );

		$hmac_received  = substr( $full_message, 0, self::HMAC_LENGTH );
		$encrypted_data = substr( $full_message, self::HMAC_LENGTH );

		$computed_hmac = hash_hmac( 'sha384', $encrypted_data, $hmac_key, true );

		if ( ! hash_equals( $hmac_received, $computed_hmac ) ) {
			throw new Exception( 'HMAC validation failed. Data may be tampered!' );
		}

		$iv                   = substr( $encrypted_data, 0, self::IV_SIZE );
		$cipher_text_with_tag = substr( $encrypted_data, self::IV_SIZE );
		$cipher_text          = substr( $cipher_text_with_tag, 0, -self::TAG_SIZE );
		$tag                  = substr( $cipher_text_with_tag, -self::TAG_SIZE );

		$plain_text = openssl_decrypt(
			$cipher_text,
			'aes-256-gcm',
			$aes_key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $plain_text ) {
			throw new Exception( 'Decryption failed.' );
		}

		return $plain_text;
	}

	/**
	 * Get encrypted data array.
	 *
	 * @param array  $data    Data to encrypt.
	 * @param string $data_key Data key.
	 * @return array Encrypted data array.
	 */
	public function get_encrypted_data_array( $data, $data_key = 'encData' ) {
		$data = $this->encrypt( build_query( $data ) );

		return [
			$data_key    => $data,
			'clientCode' => $this->config->client_code,
		];
	}
}
