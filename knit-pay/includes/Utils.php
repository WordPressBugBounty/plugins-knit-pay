<?php

namespace KnitPay;

use Pronamic\WordPress\Pay\Country;
use Pronamic\WordPress\Pay\Region;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Exception;

/**
 * Title: Knit Pay Utils
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 */
class Utils {
	public static function get_country_name( $country ) {
		if ( empty( $country ) ) {
			return '';
		} elseif ( is_string( $country ) && strlen( $country ) == 2 ) {
			$country_obj = new Country();
			$country_obj->set_code( $country );
			$country = $country_obj;
		} elseif ( ! empty( $country->get_name() ) ) {
			return $country->get_name();
		}
		
		$iso_codes_to_names = [
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo, Democratic Republic',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Cote D\'Ivoire (Ivory Coast)',
			'HR' => 'Croatia (Hrvatska)',
			'CU' => 'Cuba',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'TL' => 'East Timor',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands (Malvinas)',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'FX' => 'France, Metropolitan',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island and McDonald Islands',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KP' => 'Korea (North)',
			'KR' => 'Korea (South)',
			'XK' => 'Kosovo',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Laos',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'North Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'AN' => 'Netherlands Antilles',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestinian Territories',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'SH' => 'Saint Helena',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia and the South Sandwich Islands',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syria',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Minor Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VA' => 'Vatican City State',
			'VE' => 'Venezuela',
			'VN' => 'Viet Nam',
			'VG' => 'Virgin Islands (British)',
			'VI' => 'Virgin Islands (U.S.)',
			'WF' => 'Wallis and Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		];
				
		// Check if the ISO code exists in the array, if not return an empty string.
		if ( array_key_exists( $country->get_code(), $iso_codes_to_names ) ) {
			return $iso_codes_to_names[ $country->get_code() ];
		} else {
			return '';
		}
	}

	/**
	 * Get the state name from the state code and country code.
	 */
	public static function get_state_name( $region, $country ) {
		if ( empty( $region ) ) {
			return '';
		} elseif ( $region instanceof Region ) {
			$state_code = empty( $region->get_code() ) ? $region->get_value() : $region->get_code();
		} elseif ( is_string( $region ) ) {
			$state_code = $region;
		} else {
			return '';
		}

		if ( empty( $country ) ) {
			return $state_code;
		} elseif ( $country instanceof Country ) {
			$country_code = $country->get_code();
		} elseif ( is_string( $country ) ) {
			$country_code = $country;
		}

		if ( ! function_exists( 'WC' ) ) {
			return $state_code;
		}

		$countries      = WC()->countries; // Get an instance of the WC_Countries Object
		$country_states = $countries->get_states( $country_code ); // Get the states array for the specific country

		if ( isset( $country_states[ $state_code ] ) ) {
			return $country_states[ $state_code ]; // Return the full state name
		} else {
			return $state_code; // Return the code itself if the full name is not found (e.g., if the user typed it manually)
		}
	}
	
	/*
	 * Get the substring after trimming the string
	 *
	 * @param string $string
	 * @param int $start
	 * @param int $length
	 * @return string
	 */
	public static function substr_after_trim( $string = null, $start = 0, $length = null ) {
		if ( null === $string ) {
			return '';
		}
		return substr( trim( $string ), $start, $length );
	}

	public static function get_server_public_ip() {
		$ipinfo_url = 'https://ipinfo.io/ip';
		$ipify_url  = 'https://api.ipify.org';
		$ip         = '';

		// Try ipinfo first.
		$ip_response = wp_remote_get( $ipinfo_url );

		if ( ! is_wp_error( $ip_response ) && wp_remote_retrieve_response_code( $ip_response ) === 200 ) {
			$ip = wp_remote_retrieve_body( $ip_response );
		} else {
			// ipinfo failed, try ipify.
			$ip_response = wp_remote_get( $ipify_url );

			if ( ! is_wp_error( $ip_response ) && wp_remote_retrieve_response_code( $ip_response ) === 200 ) {
				$ip = wp_remote_retrieve_body( $ip_response );
			} else {
				// Both requests failed, return SERVER_ADDR.
				$ip = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( $_SERVER['SERVER_ADDR'] ) : '';
			}
		}

		// Check for the last time if the IP we received is an actual IP or not.
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		return trim( $ip );
	}
	
	public static function get_contact_name_from_string( $name ) {
		$last_name  = ( strpos( $name, ' ' ) === false ) ? '' : preg_replace( '#.*\s([\w-]*)$#', '$1', $name );
		$first_name = trim( preg_replace( '#' . preg_quote( $last_name, '#' ) . '#', '', $name ) );

		if ( empty( $first_name ) ) {
			$first_name = ' ';
		}
		if ( empty( $last_name ) ) {
			$last_name = ' ';
		}

		return ContactNameHelper::from_array(
			[
				'first_name' => $first_name,
				'last_name'  => $last_name,
			]
		);
	}

	public static function get_gateway_config_id() {
		$config_id = get_the_ID();
		
		// try to get Config ID from Referer URL.
		if ( empty( $config_id ) && wp_get_referer() ) {
			$referer_parameter = [];
			$referer_url       = wp_parse_url( wp_get_referer() );
			if ( isset( $referer_url['query'] ) ) {
				parse_str( $referer_url['query'], $referer_parameter );
				$config_id = isset( $referer_parameter['post'] ) ? $referer_parameter['post'] : 0;
			}
		}

		// Try to get from request url, eg "wp-json/knit-pay/v1/gateways/8808/admin".
		if ( empty( $config_id ) ) {
			global $wp;
			$request_url_params = explode( '/', $wp->request );

			if ( count( $request_url_params ) < 2 ) {
				return $config_id;
			}

			// Get the second-to-last element (index -2).
			$config_id = $request_url_params[ count( $request_url_params ) - 2 ];
		}

		return $config_id;
	}

	/**
	 * Convert cookies to WordPress WP_Http_Cookie array
	 * Automatically detects and converts from Netscape file format or semicolon-separated format
	 *
	 * Supported formats:
	 * 1. Netscape cookie file format (tab-separated):
	 *    domain  flag  path  secure  expiration  name  value
	 *    Example: .example.com TRUE    /   FALSE   1800627982.987  name    value
	 *
	 * 2. Semicolon-separated format:
	 *    Example: name1=value1; name2=value2
	 *
	 * @param string $cookie_data Cookie data in either format
	 * @param string $default_domain Default domain for semicolon-separated format
	 * @return array Array of WP_Http_Cookie objects
	 */
	public static function convert_cookie_string_to_wp_cookies( $cookie_data, $default_domain = '' ) {
		$wp_cookies = [];

		if ( empty( $cookie_data ) ) {
			return $wp_cookies;
		}

		// Trim the input
		$cookie_data = trim( $cookie_data );

		// Check if it's in Netscape cookie file format by looking for tab characters
		if ( strpos( $cookie_data, "\t" ) !== false ) {
			// Parse Netscape cookie file format
			$lines = explode( "\n", $cookie_data );

			foreach ( $lines as $line ) {
				$line = trim( $line );

				// Skip empty lines and comments (lines starting with #)
				if ( empty( $line ) || $line[0] === '#' ) {
					continue;
				}

				// Split by tab character
				$parts = preg_split( '/\t+/', $line );

				// Cookie file format should have 7 fields:
				// domain, flag, path, secure, expiration, name, value
				if ( count( $parts ) >= 7 ) {
					$domain     = $parts[0];
					$flag       = $parts[1]; // TRUE/FALSE for domain flag
					$path       = $parts[2];
					$secure     = ( strtoupper( $parts[3] ) === 'TRUE' );
					$expiration = (int) floatval( $parts[4] ); // Convert float timestamp to int
					$name       = $parts[5];
					$value      = $parts[6];

					// Create WP_Http_Cookie object
					$wp_cookies[] = new \WP_Http_Cookie(
						[
							'name'    => $name,
							'value'   => $value,
							'domain'  => $domain,
							'path'    => $path,
							'expires' => $expiration,
						]
					);
				}
			}
		} else {
			// Parse semicolon-separated format
			$cookie_pairs = explode( ';', $cookie_data );

			foreach ( $cookie_pairs as $cookie_pair ) {
				$cookie_pair = trim( $cookie_pair );

				if ( empty( $cookie_pair ) ) {
					continue;
				}

				// Split name=value
				$parts = explode( '=', $cookie_pair, 2 );

				if ( count( $parts ) === 2 ) {
					$name  = trim( $parts[0] );
					$value = trim( $parts[1] );

					// Create WP_Http_Cookie object
					$wp_cookies[] = new \WP_Http_Cookie(
						[
							'name'   => $name,
							'value'  => $value,
							'domain' => $default_domain,
						]
					);
				}
			}
		}

		return $wp_cookies;
	}

	/**
	 * Convert relative path to absolute URL.
	 * Maintains backward compatibility with existing absolute URLs and base64 strings.
	 *
	 * @param string $path path (relative or absolute).
	 * @return string The absolute URL.
	 */
	public static function convert_relative_path_to_url( $path ) {
		if ( empty( $path ) ) {
			return $path;
		}

		// Return as is if it's already an absolute URL (http:// or https://)
		if ( preg_match( '/^https?:\/\//', $path ) ) {
			return $path;
		}

		// Return as is if it's a base64 encoded string
		if ( strpos( $path, 'data:' ) === 0 ) {
			return $path;
		}

		// Convert relative path to absolute URL
		return home_url( $path );
	}
}
