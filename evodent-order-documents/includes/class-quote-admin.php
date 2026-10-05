<?php
/**
 * Evodent Quote Admin Controller
 *
 * Handles admin menu registration, asset enqueuing, and AJAX search.
 * Delegates form rendering and DOCX generation to dedicated handler classes.
 *
 * @package Evodent_Order_Documents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Evodent_Quote_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action(
			'admin_menu',
			array( $this, 'add_admin_menu' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);

		add_action(
			'wp_ajax_evodent_search_quote_products',
			array( $this, 'ajax_search_quote_products' )
		);

		add_action(
			'wp_ajax_evodent_get_product_prices_for_country',
			array( $this, 'ajax_get_product_prices_for_country' )
		);

		add_action(
			'admin_post_evodent_generate_quote',
			array( 'Evodent_Quote_Domestic', 'generate_docx' )
		);

		add_action(
			'admin_post_evodent_generate_international_quote',
			array( 'Evodent_Quote_Intl', 'generate_docx' )
		);
	}

	/**
	 * Add standalone Generate Quote admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Generate Quote', 'evodent' ),
			__( 'Generate Quote', 'evodent' ),
			'evodent_generate_quote',
			'evodent-generate-quote',
			array( $this, 'render_page' ),
			'dashicons-media-document',
			56
		);
	}

	/**
	 * Helper to get product price for a specific country and currency using WCPBC Pro if available.
	 *
	 * Uses WCPBC_Pricing_Zones::get_zone_by_country() to find the matching zone, then
	 * WCPBC_Pricing_Zone::get_post_price() to get that zone's price for the product — this
	 * single call correctly handles both manually-set zone prices and exchange-rate-calculated
	 * prices (with WCPBC's own rounding rules), exactly as the storefront would show it.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $country_code Customer country code (ISO 2-letter, e.g. 'US').
	 * @param string     $currency_code Currency code, used only as a fallback if country lookup fails.
	 * @return float
	 */
	// public static function get_product_price_for_country( $product, $country_code = '', $currency_code = '' ) {
	// 	if ( ! $product ) {
	// 		return 0.0;
	// 	}

	// 	$product_id = $product->get_id();
	// 	$price      = null;

	// 	if ( class_exists( 'WCPBC_Pricing_Zones' ) ) {
	// 		$zone = null;

	// 		// 1. Preferred: look up the zone that actually contains this country.
	// 		if ( ! empty( $country_code ) && method_exists( 'WCPBC_Pricing_Zones', 'get_zone_by_country' ) ) {
	// 			$zone = WCPBC_Pricing_Zones::get_zone_by_country( $country_code );
	// 		}

	// 		// 2. Fallback: match by currency if no country match was found (e.g. custom currency selected).
	// 		if ( ! $zone && ! empty( $currency_code ) && method_exists( 'WCPBC_Pricing_Zones', 'get_zones' ) ) {
	// 			$zones = WCPBC_Pricing_Zones::get_zones();
	// 			if ( is_array( $zones ) ) {
	// 				foreach ( $zones as $z ) {
	// 					if ( method_exists( $z, 'get_currency' ) && $z->get_currency() === $currency_code ) {
	// 						$zone = $z;
	// 						break;
	// 					}
	// 				}
	// 			}
	// 		}

	// 		if ( $zone && method_exists( $zone, 'get_post_price' ) ) {
	// 			// '_price' is the base WooCommerce price meta key; WCPBC internally maps this
	// 			// to its own zone-prefixed key (e.g. `_{zone_id}_price`) and returns either the
	// 			// manually-set zone price or the live exchange-rate-converted price.
	// 			$zone_price = $zone->get_post_price( $product_id, '_price', 'product' );

	// 			if ( '' !== $zone_price && false !== $zone_price && null !== $zone_price ) {
	// 				$price = (float) $zone_price;
	// 			}
	// 		}
	// 	}

	// 	if ( null === $price ) {
	// 		$base_price = $product->get_price();
	// 		$price      = '' !== $base_price ? (float) $base_price : 0.0;
	// 	}

	// 	return round( (float) $price, 2 );
	// }
	public static function get_product_price_for_country( $product, $country_code = '', $currency_code = '' ) {

	if ( ! $product ) {
		return 0.0;
	}

	$product_id = $product->get_id();
	$price      = null;

	/*
	 * IMPORTANT:
	 *
	 * Currency is the source of truth for pricing.
	 *
	 * Country is only used to determine the initial/local
	 * currency on the frontend.
	 *
	 * Therefore, if the user selects USD while the country
	 * is Australia, we must use the USD WCPBC zone and NOT
	 * Australia's AUD zone.
	 */

	if (
		class_exists( 'WCPBC_Pricing_Zones' ) &&
		! empty( $currency_code ) &&
		method_exists( 'WCPBC_Pricing_Zones', 'get_zones' )
	) {

		$zones = WCPBC_Pricing_Zones::get_zones();

		if ( is_array( $zones ) ) {

			foreach ( $zones as $zone ) {

				if (
					method_exists( $zone, 'get_enabled' ) &&
					! $zone->get_enabled()
				) {
					continue;
				}

				if (
					method_exists( $zone, 'get_currency' ) &&
					$zone->get_currency() === $currency_code
				) {

					if ( method_exists( $zone, 'get_post_price' ) ) {

						$zone_price = $zone->get_post_price(
							$product_id,
							'_price',
							'product'
						);

						if (
							'' !== $zone_price &&
							false !== $zone_price &&
							null !== $zone_price
						) {
							$price = (float) $zone_price;
						}
					}

					break;
				}
			}
		}
	}


	/*
	 * Fallback:
	 *
	 * If no WCPBC zone exists for the selected currency,
	 * use the normal WooCommerce product price.
	 */
	if ( null === $price ) {

		$base_price = $product->get_price();

		$price = '' !== $base_price
			? (float) $base_price
			: 0.0;
	}

	return round( (float) $price, 2 );
}

	/**
	 * Load admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'evodent-quote-admin',
			EVODENT_DOCS_URL . 'assets/quote-admin.css',
			array(),
			'1.0.0'
		);

		if ( 'toplevel_page_evodent-generate-quote' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'evodent-quote-admin',
			EVODENT_DOCS_URL . 'assets/quote-admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Build WCPBC zone mapping (country -> currency) and exchange rate mapping
		// (currency -> rate FROM base currency), used client-side to convert the grand
		// total into the store's base currency (e.g. INR) for the payment-limit reminder.
		$wcpbc_zones = array();
		$wcpbc_rates = array();
		if ( class_exists( 'WCPBC_Pricing_Zones' ) && method_exists( 'WCPBC_Pricing_Zones', 'get_zones' ) ) {
			$zones = WCPBC_Pricing_Zones::get_zones();
			if ( is_array( $zones ) ) {
				foreach ( $zones as $zone ) {
					$curr      = method_exists( $zone, 'get_currency' ) ? $zone->get_currency() : '';
					$countries = method_exists( $zone, 'get_countries' ) ? $zone->get_countries() : array();
					if ( $curr && is_array( $countries ) ) {
						foreach ( $countries as $c_code ) {
							$wcpbc_zones[ $c_code ] = $curr;
						}
					}
					if ( $curr && method_exists( $zone, 'get_real_exchange_rate' ) ) {
						$wcpbc_rates[ $curr ] = (float) $zone->get_real_exchange_rate();
					}
				}
			}
		}

		$base_currency = function_exists( 'wcpbc_get_base_currency' ) ? wcpbc_get_base_currency() : get_option( 'woocommerce_currency', 'INR' );

		// Base currency always converts 1:1 to itself.
		if ( ! isset( $wcpbc_rates[ $base_currency ] ) ) {
			$wcpbc_rates[ $base_currency ] = 1;
		}

		// Build currency symbols map
		$currency_symbols = array();
		if ( function_exists( 'get_woocommerce_currencies' ) ) {
			$currencies = get_woocommerce_currencies();
			foreach ( array_keys( $currencies ) as $code ) {
				$currency_symbols[ $code ] = html_entity_decode(
					get_woocommerce_currency_symbol( $code ),
					ENT_QUOTES | ENT_HTML5,
					'UTF-8'
				);
			}
		}

		wp_localize_script(
			'evodent-quote-admin',
			'EvodentQuote',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'evodent_quote_product_search' ),
				'wcpbcZones'        => $wcpbc_zones,
				'wcpbcRates'        => $wcpbc_rates,
				'baseCurrency'      => $base_currency,
				'currencySymbols'   => $currency_symbols,
				'paymentLimitBase'  => 100000, // Card-payment acceptance limit, in base currency (INR).
			)
		);
	}

	/**
	 * Search WooCommerce products for quote product autocomplete.
	 *
	 * @return void
	 */
	public function ajax_search_quote_products() {
		check_ajax_referer(
			'evodent_quote_product_search',
			'nonce'
		);

		if ( ! current_user_can( 'evodent_generate_quote' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'evodent' ) ),
				403
			);
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'WooCommerce is not available.', 'evodent' ) ),
				500
			);
		}

		$search        = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$country_code  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$currency_code = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

		$search = trim( $search );

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'products' => array() ) );
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 10,
				'orderby' => 'title',
				'order'   => 'ASC',
				's'       => $search,
				'return'  => 'objects',
			)
		);

		$sku_products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 10,
				'sku'    => $search,
				'return' => 'objects',
			)
		);

		$all_products = array();

		foreach ( $products as $product ) {
			$all_products[ $product->get_id() ] = $product;
		}

		foreach ( $sku_products as $product ) {
			$all_products[ $product->get_id() ] = $product;
		}

		$results = array();

		foreach ( $all_products as $product ) {
			$hsn          = '90230090';
			$price        = $product->get_price();
			$rate_inc_gst = '' !== $price ? (float) $price : 0;
			$rate_ex_gst  = $rate_inc_gst / 1.18;

			$intl_unit_price = self::get_product_price_for_country( $product, $country_code, $currency_code );

			$results[] = array(
				'id'              => $product->get_id(),
				'name'            => $product->get_name(),
				'sku'             => $product->get_sku() ? $product->get_sku() : '',
				'hsn'             => $hsn,
				'rate_inc_gst'    => $rate_inc_gst,
				'rate_ex_gst'     => round( $rate_ex_gst, 2 ),
				'intl_unit_price' => $intl_unit_price,
			);
		}

		wp_send_json_success( array( 'products' => $results ) );
	}

	/**
	 * AJAX endpoint to get bulk product prices for a specific country / currency.
	 *
	 * @return void
	 */
	public function ajax_get_product_prices_for_country() {
		check_ajax_referer(
			'evodent_quote_product_search',
			'nonce'
		);

		if ( ! current_user_can( 'evodent_generate_quote' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'evodent' ) ),
				403
			);
		}

		$country_code  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$currency_code = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';
		$raw_ids       = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? $_POST['product_ids'] : array();

		$prices = array();

		foreach ( $raw_ids as $id ) {
			$product_id = absint( $id );
			if ( ! $product_id ) {
				continue;
			}
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$prices[ $product_id ] = self::get_product_price_for_country( $product, $country_code, $currency_code );
			}
		}

		wp_send_json_success( array( 'prices' => $prices ) );
	}

	/**
	 * Render Generate Quote page.
	 */
	public function render_page() {
		$quote_date_key    = current_time( 'Ymd' );
		$last_quote_number = (int) get_option( 'evodent_last_quote_number_' . $quote_date_key, 0 );
		$next_quote_number = $last_quote_number + 1;
		?>

		<div class="wrap evodent-quote-wrap">

			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Generate Quote', 'evodent' ); ?>
			</h1>

			<hr class="wp-header-end">

			<!-- QUOTE TYPE SWITCHER -->
			<div class="evodent-card evodent-quote-type-card">
				<div class="evodent-card-header">
					<h2><?php esc_html_e( 'Quote Type', 'evodent' ); ?></h2>
				</div>
				<div class="evodent-card-body">
					<div class="evodent-quote-type-options">
						<label class="evodent-quote-type-option">
							<input
								type="radio"
								name="quote_type"
								value="domestic"
								checked
							>
							<span class="evodent-quote-type-content">
								<strong><?php esc_html_e( 'Domestic Quote', 'evodent' ); ?></strong>
								<small><?php esc_html_e( 'For customers within India', 'evodent' ); ?></small>
							</span>
						</label>

						<label class="evodent-quote-type-option">
							<input
								type="radio"
								name="quote_type"
								value="international"
							>
							<span class="evodent-quote-type-content">
								<strong><?php esc_html_e( 'International Quote', 'evodent' ); ?></strong>
								<small><?php esc_html_e( 'For customers outside India', 'evodent' ); ?></small>
							</span>
						</label>
					</div>
				</div>
			</div>

			<!-- DOMESTIC QUOTE FORM -->
			<?php Evodent_Quote_Domestic::render_form( $next_quote_number ); ?>

			<!-- INTERNATIONAL QUOTE FORM -->
			<?php Evodent_Quote_Intl::render_form( $next_quote_number ); ?>

		</div>
		<?php
	}
}

/**
 * Initialize the Quote Admin class.
 */
new Evodent_Quote_Admin();