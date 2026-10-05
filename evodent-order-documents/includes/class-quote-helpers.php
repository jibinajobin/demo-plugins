<?php
/**
 * Evodent Quote Helpers
 *
 * Provides number to words conversion utilities for domestic and international quotes.
 *
 * @package Evodent_Order_Documents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Evodent_Quote_Helpers {

	/**
	 * Convert amount to Indian Rupees in words.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function amount_to_words( $amount ) {
		$amount = round( (float) $amount, 2 );

		$rupees = floor( $amount );
		$paise  = round( ( $amount - $rupees ) * 100 );

		$ones = array(
			'', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
			'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
			'Seventeen', 'Eighteen', 'Nineteen',
		);

		$tens = array(
			'', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
		);

		$convert_number = function( $number ) use ( &$convert_number, $ones, $tens ) {
			$number = (int) $number;

			if ( $number < 20 ) {
				return $ones[ $number ];
			}

			if ( $number < 100 ) {
				return trim( $tens[ floor( $number / 10 ) ] . ' ' . $ones[ $number % 10 ] );
			}

			if ( $number < 1000 ) {
				return trim( $ones[ floor( $number / 100 ) ] . ' Hundred ' . $convert_number( $number % 100 ) );
			}

			if ( $number < 100000 ) {
				return trim( $convert_number( floor( $number / 1000 ) ) . ' Thousand ' . $convert_number( $number % 1000 ) );
			}

			if ( $number < 10000000 ) {
				return trim( $convert_number( floor( $number / 100000 ) ) . ' Lakh ' . $convert_number( $number % 100000 ) );
			}

			return trim( $convert_number( floor( $number / 10000000 ) ) . ' Crore ' . $convert_number( $number % 10000000 ) );
		};

		if ( 0 === (int) $rupees ) {
			$words = 'Zero';
		} else {
			$words = $convert_number( $rupees );
		}

		if ( $paise > 0 ) {
			$words .= ' and ' . $convert_number( $paise ) . ' Paise';
		}

		return $words . ' Indian Rupees Only';
	}

	/**
	 * Convert amount to international currency in words.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	
	// public static function amount_to_words_intl( $amount, $currency = 'USD' ) {

	// /**
	//  * Full currency names used at the beginning of
	//  * the "Total in Words" amount.
	//  */
	// $currency_prefixes = array(
	// 	'USD' => 'United States Dollar',
	// 	'EUR' => 'Euro',
	// 	'GBP' => 'Great British Pound',
	// 	'AUD' => 'Australian Dollar',
	// 	'SGD' => 'Singapore Dollar',
	// 	'CAD' => 'Canadian Dollar',
	// 	'AED' => 'United Arab Emirates Dirham',
	// 	'JPY' => 'Japanese Yen',
	// 	'SAR' => 'Saudi Riyal',
	// 	'QAR' => 'Qatari Riyal',
	// 	'MYR' => 'Malaysian Ringgit',
	// 	'NZD' => 'New Zealand Dollar',
	// 	'CHF' => 'Swiss Franc',
	// 	'INR' => 'Indian Rupee',
	// 	'BHD' => 'Bahraini Dinar',
	// 	'BDT' => 'Bangladeshi Taka',
	// 	'BRL' => 'Brazilian Real',
	// 	'CLP' => 'Chilean Peso',
	// 	'COP' => 'Colombian Peso',
	// 	'CZK' => 'Czech Koruna',
	// 	'DKK' => 'Danish Krone',
	// 	'EGP' => 'Egyptian Pound',
	// 	'GEL' => 'Georgian Lari',
	// 	'HUF' => 'Hungarian Forint',
	// 	'ISK' => 'Icelandic Krona',
	// 	'ILS' => 'Israeli New Shekel',
	// 	'IDR' => 'Indonesian Rupiah',
	// 	'JOD' => 'Jordanian Dinar',
	// 	'KWD' => 'Kuwaiti Dinar',
	// 	'MXN' => 'Mexican Peso',
	// 	'NOK' => 'Norwegian Krone',
	// 	'OMR' => 'Omani Rial',
	// 	'PEN' => 'Peruvian Sol',
	// 	'PHP' => 'Philippine Peso',
	// 	'PLN' => 'Polish Zloty',
	// 	'RON' => 'Romanian Leu',
	// 	'RUB' => 'Russian Ruble',
	// 	'ZAR' => 'South African Rand',
	// 	'KRW' => 'South Korean Won',
	// 	'LKR' => 'Sri Lankan Rupee',
	// 	'SEK' => 'Swedish Krona',
	// 	'THB' => 'Thai Baht',
	// 	'TRY' => 'Turkish Lira',
	// 	'VND' => 'Vietnamese Dong',
	// );

	// /**
	//  * Currency names used at the end of
	//  * the "Total in Words" amount.
	//  *
	//  * GBP is intentionally "Pound Sterling"
	//  * because this is what the client requested.
	//  */
	// $currency_suffixes = array(
	// 	'USD' => 'US Dollars',
	// 	'EUR' => 'Euros',
	// 	'GBP' => 'Pound Sterling',
	// 	'AUD' => 'Australian Dollars',
	// 	'SGD' => 'Singapore Dollars',
	// 	'CAD' => 'Canadian Dollars',
	// 	'AED' => 'UAE Dirhams',
	// 	'JPY' => 'Japanese Yen',
	// 	'SAR' => 'Saudi Riyals',
	// 	'QAR' => 'Qatari Riyals',
	// 	'MYR' => 'Malaysian Ringgit',
	// 	'NZD' => 'New Zealand Dollars',
	// 	'CHF' => 'Swiss Francs',
	// 	'INR' => 'Indian Rupees',
	// 	'BHD' => 'Bahraini Dinar',
	// 	'BDT' => 'Bangladeshi Taka',
	// 	'BRL' => 'Brazilian Real',
	// 	'CLP' => 'Chilean Peso',
	// 	'COP' => 'Colombian Peso',
	// 	'CZK' => 'Czech Koruna',
	// 	'DKK' => 'Danish Krone',
	// 	'EGP' => 'Egyptian Pound',
	// 	'GEL' => 'Georgian Lari',
	// 	'HUF' => 'Hungarian Forint',
	// 	'ISK' => 'Icelandic Krona',
	// 	'ILS' => 'Israeli New Shekel',
	// 	'IDR' => 'Indonesian Rupiah',
	// 	'JOD' => 'Jordanian Dinar',
	// 	'KWD' => 'Kuwaiti Dinar',
	// 	'MXN' => 'Mexican Peso',
	// 	'NOK' => 'Norwegian Krone',
	// 	'OMR' => 'Omani Rial',
	// 	'PEN' => 'Peruvian Sol',
	// 	'PHP' => 'Philippine Peso',
	// 	'PLN' => 'Polish Zloty',
	// 	'RON' => 'Romanian Leu',
	// 	'RUB' => 'Russian Ruble',
	// 	'ZAR' => 'South African Rand',
	// 	'KRW' => 'South Korean Won',
	// 	'LKR' => 'Sri Lankan Rupee',
	// 	'SEK' => 'Swedish Krona',
	// 	'THB' => 'Thai Baht',
	// 	'TRY' => 'Turkish Lira',
	// 	'VND' => 'Vietnamese Dong',
	// );

	/**
	 * Fallback to WooCommerce currency name
	 * if the currency is not in our custom list.
	 */
// 	if ( ! isset( $currency_suffixes[ $currency ] ) ) {

// 		if ( function_exists( 'get_woocommerce_currencies' ) ) {

// 			$wc_currencies = get_woocommerce_currencies();

// 			if ( isset( $wc_currencies[ $currency ] ) ) {
// 				$currency_suffixes[ $currency ] = $wc_currencies[ $currency ];
// 			}
// 		}
// 	}

// 	$prefix = isset( $currency_prefixes[ $currency ] )
// 		? $currency_prefixes[ $currency ]
// 		: '';

// 	$suffix = isset( $currency_suffixes[ $currency ] )
// 		? $currency_suffixes[ $currency ]
// 		: $currency;

// 	/**
// 	 * Convert the amount to words.
// 	 */
// 	if ( function_exists( 'evodent_number_to_words' ) ) {

// 		$words = evodent_number_to_words( $amount );

// 		if ( ' Only' === substr( $words, -5 ) ) {
// 			$words = substr( $words, 0, -5 );
// 		}

// 		/*
// 		 * Client requested format:
// 		 *
// 		 * Great British Pound Four Thousand Six Hundred
// 		 * Seventy-five Pound Sterling Only
// 		 */
// 		if ( $prefix ) {
// 			return $prefix . ' ' . $words . ' ' . $suffix . ' Only';
// 		}

// 		return $words . ' ' . $suffix . ' Only';
// 	}

// 	/**
// 	 * Fallback number-to-words conversion.
// 	 */
// 	$amount = round( (float) $amount, 2 );
// 	$units  = floor( $amount );
// 	$cents  = round( ( $amount - $units ) * 100 );

// 	$ones = array(
// 		'', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven',
// 		'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
// 		'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen',
// 		'Nineteen',
// 	);

// 	$tens = array(
// 		'', '', 'Twenty', 'Thirty', 'Forty', 'Fifty',
// 		'Sixty', 'Seventy', 'Eighty', 'Ninety',
// 	);

// 	$convert = function( $num ) use ( &$convert, $ones, $tens ) {

// 		$num = (int) $num;

// 		if ( $num < 20 ) {
// 			return $ones[ $num ];
// 		}

// 		if ( $num < 100 ) {
// 			return trim(
// 				$tens[ floor( $num / 10 ) ] . ' ' .
// 				$ones[ $num % 10 ]
// 			);
// 		}

// 		if ( $num < 1000 ) {
// 			return trim(
// 				$ones[ floor( $num / 100 ) ] .
// 				' Hundred ' .
// 				$convert( $num % 100 )
// 			);
// 		}

// 		if ( $num < 1000000 ) {
// 			return trim(
// 				$convert( floor( $num / 1000 ) ) .
// 				' Thousand ' .
// 				$convert( $num % 1000 )
// 			);
// 		}

// 		if ( $num < 1000000000 ) {
// 			return trim(
// 				$convert( floor( $num / 1000000 ) ) .
// 				' Million ' .
// 				$convert( $num % 1000000 )
// 			);
// 		}

// 		return trim(
// 			$convert( floor( $num / 1000000000 ) ) .
// 			' Billion ' .
// 			$convert( $num % 1000000000 )
// 		);
// 	};

// 	$words = ( 0 === (int) $units )
// 		? 'Zero'
// 		: $convert( $units );

// 	if ( $cents > 0 ) {
// 		$words .= ' and Cents ' . $convert( $cents );
// 	}

// 	if ( $prefix ) {
// 		return $prefix . ' ' . $words . ' ' . $suffix . ' Only';
// 	}

// 	return $words . ' ' . $suffix . ' Only';
// }
public static function amount_to_words_intl( $amount, $currency = 'USD' ) {

	/**
	 * Special currency prefixes requested by the client.
	 *
	 * Only add a prefix when the client specifically wants
	 * the currency mentioned before the amount.
	 */
	$currency_prefixes = array(
		'GBP' => 'Great British Pound',
	);

	/**
	 * Get the normal WooCommerce currency names.
	 *
	 * Example:
	 * GBP => Pound sterling
	 * USD => United States (US) dollar
	 * EUR => Euro
	 */
	$currency_names = function_exists( 'get_woocommerce_currencies' )
		? get_woocommerce_currencies()
		: array();

	$curr_label = isset( $currency_names[ $currency ] )
		? $currency_names[ $currency ]
		: $currency;

	/**
	 * Convert amount to words using the plugin's
	 * existing number-to-words helper.
	 */
	if ( function_exists( 'evodent_number_to_words' ) ) {

		$words = evodent_number_to_words( $amount );

		/*
		 * The helper may already append "Only".
		 * Remove it so we can add it consistently below.
		 */
		if ( ' Only' === substr( $words, -5 ) ) {
			$words = substr( $words, 0, -5 );
		}

		/**
		 * Special format for currencies that require
		 * a full currency name before the amount.
		 *
		 * GBP example:
		 * Great British Pound Four Thousand Six Hundred
		 * Seventy-five Pound Sterling Only
		 */
		if ( isset( $currency_prefixes[ $currency ] ) ) {

			return $currency_prefixes[ $currency ]
				. ' '
				. $words
				. ' '
				. $curr_label
				. ' Only';
		}

		/**
		 * Normal format for all other currencies:
		 *
		 * Four Thousand Six Hundred Seventy-five
		 * United States (US) dollar Only
		 */
		return $words . ' ' . $curr_label . ' Only';
	}

	/**
	 * Fallback number-to-words conversion.
	 */
	$amount = round( (float) $amount, 2 );

	$units = floor( $amount );
	$cents = round( ( $amount - $units ) * 100 );

	$ones = array(
		'',
		'One',
		'Two',
		'Three',
		'Four',
		'Five',
		'Six',
		'Seven',
		'Eight',
		'Nine',
		'Ten',
		'Eleven',
		'Twelve',
		'Thirteen',
		'Fourteen',
		'Fifteen',
		'Sixteen',
		'Seventeen',
		'Eighteen',
		'Nineteen',
	);

	$tens = array(
		'',
		'',
		'Twenty',
		'Thirty',
		'Forty',
		'Fifty',
		'Sixty',
		'Seventy',
		'Eighty',
		'Ninety',
	);

	$convert = function( $num ) use ( &$convert, $ones, $tens ) {

		$num = (int) $num;

		if ( $num < 20 ) {
			return $ones[ $num ];
		}

		if ( $num < 100 ) {

			$result = $tens[ floor( $num / 10 ) ];

			if ( ( $num % 10 ) > 0 ) {
				$result .= '-' . $ones[ $num % 10 ];
			}

			return $result;
		}

		if ( $num < 1000 ) {

			$result = $ones[ floor( $num / 100 ) ] . ' Hundred';

			if ( ( $num % 100 ) > 0 ) {
				$result .= ' ' . $convert( $num % 100 );
			}

			return $result;
		}

		if ( $num < 1000000 ) {

			$result = $convert( floor( $num / 1000 ) ) . ' Thousand';

			if ( ( $num % 1000 ) > 0 ) {
				$result .= ' ' . $convert( $num % 1000 );
			}

			return $result;
		}

		if ( $num < 1000000000 ) {

			$result = $convert( floor( $num / 1000000 ) ) . ' Million';

			if ( ( $num % 1000000 ) > 0 ) {
				$result .= ' ' . $convert( $num % 1000000 );
			}

			return $result;
		}

		$result = $convert( floor( $num / 1000000000 ) ) . ' Billion';

		if ( ( $num % 1000000000 ) > 0 ) {
			$result .= ' ' . $convert( $num % 1000000000 );
		}

		return $result;
	};

	/**
	 * Convert the whole-number portion.
	 */
	$words = ( 0 === (int) $units )
		? 'Zero'
		: $convert( $units );

	/**
	 * Add cents if present.
	 */
	if ( $cents > 0 ) {
		$words .= ' and Cents ' . $convert( $cents );
	}

	/**
	 * GBP special format.
	 */
	if ( isset( $currency_prefixes[ $currency ] ) ) {

		return $currency_prefixes[ $currency ]
			. ' '
			. $words
			. ' '
			. $curr_label
			. ' Only';
	}

	/**
	 * Normal format for all other currencies.
	 */
	return $words . ' ' . $curr_label . ' Only';
}
}
