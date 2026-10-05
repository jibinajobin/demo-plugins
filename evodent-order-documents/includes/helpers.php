<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'evodent_doc_text' ) ) {
    function evodent_doc_text( $text ) {
        $text = (string) $text;

        // Remove invalid XML control characters.
        $text = preg_replace( '/[^\P{C}\t\n\r]/u', '', $text );

        // Escape XML special characters.
        return htmlspecialchars( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }
}

if ( ! function_exists( 'evodent_get_currency_name' ) ) {
    function evodent_get_currency_name( $currency_code ) {
        if ( empty( $currency_code ) ) {
            return '';
        }

        $currency_code = strtoupper( trim( $currency_code ) );

        $currency_names = array(
            'RON' => 'Romanian Leu',
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'Pound Sterling',
            'INR' => 'Indian Rupee',
            'AUD' => 'Australian Dollar',
            'CAD' => 'Canadian Dollar',
            'SGD' => 'Singapore Dollar',
            'AED' => 'UAE Dirham',
            'NZD' => 'New Zealand Dollar',
            'CHF' => 'Swiss Franc',
            'JPY' => 'Japanese Yen',
            'SAR' => 'Saudi Riyal',
            'QAR' => 'Qatari Riyal',
            'MYR' => 'Malaysian Ringgit',
            'OMR' => 'Omani Rial',
            'BHD' => 'Bahraini Dinar',
            'KWD' => 'Kuwaiti Dinar',
            'THB' => 'Thai Baht',
            'ZAR' => 'South African Rand',
            'SEK' => 'Swedish Krona',
            'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone',
            'PLN' => 'Polish Zloty',
            'HUF' => 'Hungarian Forint',
            'CZK' => 'Czech Koruna',
            'TRY' => 'Turkish Lira',
            'RUB' => 'Russian Ruble',
            'BRL' => 'Brazilian Real',
            'MXN' => 'Mexican Peso',
            'PHP' => 'Philippine Peso',
            'IDR' => 'Indonesian Rupiah',
            'KRW' => 'South Korean Won',
            'VND' => 'Vietnamese Dong',
        );

        if ( isset( $currency_names[ $currency_code ] ) ) {
            return $currency_names[ $currency_code ];
        }

        if ( function_exists( 'get_woocommerce_currencies' ) ) {
            $wc_currencies = get_woocommerce_currencies();
            if ( isset( $wc_currencies[ $currency_code ] ) ) {
                return ucwords( strtolower( $wc_currencies[ $currency_code ] ) );
            }
        }

        return $currency_code;
    }
}

if ( ! function_exists( 'evodent_convert_number_to_words_fallback' ) ) {
    function evodent_convert_number_to_words_fallback( $number ) {
        $hyphen      = ' ';
        $conjunction = ' ';
        $separator   = ', ';
        $negative    = 'negative ';
        $dictionary  = array(
            0                   => 'Zero',
            1                   => 'One',
            2                   => 'Two',
            3                   => 'Three',
            4                   => 'Four',
            5                   => 'Five',
            6                   => 'Six',
            7                   => 'Seven',
            8                   => 'Eight',
            9                   => 'Nine',
            10                  => 'Ten',
            11                  => 'Eleven',
            12                  => 'Twelve',
            13                  => 'Thirteen',
            14                  => 'Fourteen',
            15                  => 'Fifteen',
            16                  => 'Sixteen',
            17                  => 'Seventeen',
            18                  => 'Eighteen',
            19                  => 'Nineteen',
            20                  => 'Twenty',
            30                  => 'Thirty',
            40                  => 'Forty',
            50                  => 'Fifty',
            60                  => 'Sixty',
            70                  => 'Seventy',
            80                  => 'Eighty',
            90                  => 'Ninety',
            100                 => 'Hundred',
            1000                => 'Thousand',
            100000              => 'Lakh',
            10000000            => 'Crore',
        );

        if ( ! is_numeric( $number ) ) {
            return '';
        }

        if ( $number < 0 ) {
            return $negative . evodent_convert_number_to_words_fallback( abs( $number ) );
        }

        switch ( true ) {
            case $number < 21:
                $string = $dictionary[ $number ];
                break;
            case $number < 100:
                $tens   = ( (int) ( $number / 10 ) ) * 10;
                $units  = $number % 10;
                $string = $dictionary[ $tens ];
                if ( $units ) {
                    $string .= $hyphen . $dictionary[ $units ];
                }
                break;
            case $number < 1000:
                $hundreds  = (int) ( $number / 100 );
                $remainder = $number % 100;
                $string    = $dictionary[ $hundreds ] . ' ' . $dictionary[100];
                if ( $remainder ) {
                    $string .= $conjunction . evodent_convert_number_to_words_fallback( $remainder );
                }
                break;
            case $number < 100000:
                $thousands = (int) ( $number / 1000 );
                $remainder = $number % 1000;
                $string    = evodent_convert_number_to_words_fallback( $thousands ) . ' ' . $dictionary[1000];
                if ( $remainder ) {
                    $string .= $separator . evodent_convert_number_to_words_fallback( $remainder );
                }
                break;
            case $number < 10000000:
                $lakhs     = (int) ( $number / 100000 );
                $remainder = $number % 100000;
                $string    = evodent_convert_number_to_words_fallback( $lakhs ) . ' ' . $dictionary[100000];
                if ( $remainder ) {
                    $string .= $separator . evodent_convert_number_to_words_fallback( $remainder );
                }
                break;
            default:
                $crores    = (int) ( $number / 10000000 );
                $remainder = $number % 10000000;
                $string    = evodent_convert_number_to_words_fallback( $crores ) . ' ' . $dictionary[10000000];
                if ( $remainder ) {
                    $string .= $separator . evodent_convert_number_to_words_fallback( $remainder );
                }
                break;
        }

        return $string;
    }
}

if ( ! function_exists( 'evodent_number_to_words' ) ) {
    function evodent_number_to_words( $amount, $currency = '' ) {
        $amount  = (float) $amount;
        $whole   = floor( $amount );
        $raw_dec = sprintf( '%02d', round( ( $amount - $whole ) * 100 ) );
        $decimal = rtrim( $raw_dec, '0' );

        if ( class_exists( 'NumberFormatter' ) ) {
            $formatter = new NumberFormatter( 'en_IN', NumberFormatter::SPELLOUT );
            $words     = ucwords( str_replace( '-', ' ', $formatter->format( $whole ) ) );

            if ( ! empty( $decimal ) && $raw_dec !== '00' ) {
                $decimal_words = array();
                foreach ( str_split( $decimal ) as $digit ) {
                    $decimal_words[] = ucwords( $formatter->format( (int) $digit ) );
                }
                $words .= ' Point ' . implode( ' ', $decimal_words );
            }
        } else {
            $words = evodent_convert_number_to_words_fallback( $whole );

            if ( ! empty( $decimal ) && $raw_dec !== '00' ) {
                $digit_words   = array( 'Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine' );
                $decimal_words = array();
                foreach ( str_split( $decimal ) as $digit ) {
                    $decimal_words[] = $digit_words[ (int) $digit ];
                }
                $words .= ' Point ' . implode( ' ', $decimal_words );
            }
        }

        $currency_name = evodent_get_currency_name( $currency );
        if ( ! empty( $currency_name ) ) {
            return $words . ' ' . $currency_name . ' Only';
        }

        return $words . ' Only';
    }
}