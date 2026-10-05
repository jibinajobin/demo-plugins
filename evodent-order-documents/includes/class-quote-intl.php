<?php
/**
 * Evodent International Quote Handler
 *
 * Renders international quote form and processes international quote DOCX generation.
 *
 * @package Evodent_Order_Documents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Evodent_Quote_Intl {

	/**
	 * Render International Quote Form.
	 *
	 * @param int $next_quote_number Next quote number sequence.
	 * @return void
	 */
	public static function render_form( $next_quote_number ) {
		?>
		<div id="evodent-international-content" style="display:none;">

						<div class="evodent-quote-notice">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong><?php esc_html_e( 'International Quote', 'evodent' ); ?></strong>
					<p><?php esc_html_e( 'This quotation is for International customers.', 'evodent' ); ?></p>
				</div>
			</div>

			<form
				id="evodent-intl-quote-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>

				<input type="hidden" name="action" value="evodent_generate_international_quote">
				<?php wp_nonce_field( 'evodent_generate_international_quote', 'evodent_intl_quote_nonce' ); ?>

				<!-- QUOTE DETAILS -->
				<div class="evodent-card">
					<div class="evodent-card-header">
						<h2><?php esc_html_e( 'Quote Details (International)', 'evodent' ); ?></h2>
					</div>
					<div class="evodent-card-body">
						<div class="evodent-form-grid">

							<!-- Quote Number -->
							<div class="evodent-field">
								<label for="intl_quote_number_suffix">
									<?php esc_html_e( 'Quote No.', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<div class="evodent-quote-number">
									<span class="evodent-quote-prefix">
										<?php echo esc_html( 'EVO-Q-' . current_time( 'Ymd' ) . '-' ); ?>
									</span>
									<input
										type="text"
										id="intl_quote_number_suffix"
										name="quote_number_suffix"
										value="<?php echo esc_attr( sprintf( '%03d', $next_quote_number ) ); ?>"
										required
									>
								</div>
							</div>

							<!-- Date -->
							<div class="evodent-field">
								<label for="intl_quote_date">
									<?php esc_html_e( 'Date', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="date"
									id="intl_quote_date"
									name="quote_date"
									value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
									required
								>
							</div>

							<!-- Validity -->
							<div class="evodent-field">
								<label for="intl_quote_validity">
									<?php esc_html_e( 'Validity', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<div class="evodent-validity-field">
									<input
										type="number"
										id="intl_quote_validity"
										name="quote_validity"
										value="7"
										min="1"
										required
									>
									<span>Days</span>
								</div>
							</div>

							<!-- Incoterm / Shipping Terms -->
							<div class="evodent-field">
								<label for="intl_incoterm">
									<?php esc_html_e( 'Shipping Terms (Incoterms)', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<select id="intl_incoterm" name="incoterm" required>
									<option value="DAP" selected><?php esc_html_e( 'DAP', 'evodent' ); ?></option>
									<option value="DDU"><?php esc_html_e( 'DDU', 'evodent' ); ?></option>
									<option value="FOB"><?php esc_html_e( 'FOB', 'evodent' ); ?></option>
									<option value="EXW"><?php esc_html_e( 'EXW', 'evodent' ); ?></option>
								</select>
							</div>

							<!-- Shipping Mode -->
							<div class="evodent-field">
								<label for="intl_shipping_mode">
									<?php esc_html_e( 'Shipping Mode', 'evodent' ); ?>
								</label>
								<select id="intl_shipping_mode" name="shipping_mode">
									<option value="Courier" selected><?php esc_html_e( 'Courier', 'evodent' ); ?></option>
									<option value="Air Freight"><?php esc_html_e( 'Air Freight', 'evodent' ); ?></option>
									<option value="Sea Freight"><?php esc_html_e( 'Sea Freight', 'evodent' ); ?></option>
									<option value="FOB as Agreed"><?php esc_html_e( 'FOB as Agreed', 'evodent' ); ?></option>
									<option value="Other"><?php esc_html_e( 'Other', 'evodent' ); ?></option>
								</select>
							</div>

							<!-- Estimated Dispatch -->
							<div class="evodent-field">
								<label for="intl_estimated_dispatch">
									<?php esc_html_e( 'Est. Dispatch', 'evodent' ); ?>
								</label>
								<div class="evodent-dispatch-field">
									<input
										type="date"
										id="intl_estimated_dispatch"
										name="estimated_dispatch"
									>
									<span>; Subject to availability</span>
								</div>
							</div>

						</div>
					</div>
				</div>

				<!-- CUSTOMER DETAILS -->
				<div class="evodent-card">
					<div class="evodent-card-header">
						<h2><?php esc_html_e( 'Customer / Bill To Details', 'evodent' ); ?></h2>
					</div>
					<div class="evodent-card-body">
						<div class="evodent-form-grid">

							<!-- Customer / Company -->
							<div class="evodent-field">
								<label for="intl_customer_company">
									<?php esc_html_e( 'Customer / Company', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									id="intl_customer_company"
									name="customer_company"
									placeholder="Customer / Company Name"
									required
								>
							</div>

							<!-- Contact Person -->
							<div class="evodent-field">
								<label for="intl_contact_person">
									<?php esc_html_e( 'Contact Person', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="intl_contact_person"
									name="contact_person"
									placeholder="Contact Name"
								>
							</div>

							<!-- Phone -->
							<div class="evodent-field">
								<label for="intl_customer_phone">
									<?php esc_html_e( 'Phone (with country code)', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="intl_customer_phone"
									name="customer_phone"
									placeholder="+1 555-0199 / +65 9123 4567"
								>
							</div>

							<!-- Email -->
							<div class="evodent-field">
								<label for="intl_customer_email">
									<?php esc_html_e( 'Email', 'evodent' ); ?>
								</label>
								<input
									type="email"
									id="intl_customer_email"
									name="customer_email"
									placeholder="customer@example.com"
								>
							</div>

							<!-- Billing Country -->
							<div class="evodent-field">
								<label for="intl_customer_country">
									<?php esc_html_e( 'Country', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<select id="intl_customer_country" name="customer_country" required>
									<option value=""><?php esc_html_e( '-- Select Country --', 'evodent' ); ?></option>
									<?php
									$all_countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();
									foreach ( $all_countries as $c_code => $c_name ) {
										echo '<option value="' . esc_attr( $c_name ) . '" data-code="' . esc_attr( $c_code ) . '">' . esc_html( $c_name ) . '</option>';
									}
									?>
								</select>
							</div>

							<!-- Currency -->
							<div class="evodent-field">
								<label for="intl_quote_currency">
									<?php esc_html_e( 'Currency', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<select id="intl_quote_currency" name="quote_currency" required>

	<?php

	/**
	 * Build country => local currency mapping from WCPBC pricing zones.
	 */
	$country_currency_map = array();

	if (
		class_exists( 'WCPBC_Pricing_Zones' ) &&
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

				$currency = method_exists( $zone, 'get_currency' )
					? $zone->get_currency()
					: '';

				$countries = method_exists( $zone, 'get_countries' )
					? $zone->get_countries()
					: array();

				if ( empty( $currency ) || empty( $countries ) ) {
					continue;
				}

				foreach ( $countries as $country_code ) {
					$country_currency_map[ $country_code ] = $currency;
				}
			}
		}
	}

	$all_countries = (
		function_exists( 'WC' ) &&
		WC()->countries
	)
		? WC()->countries->get_countries()
		: array();

	$all_currencies = function_exists( 'get_woocommerce_currencies' )
		? get_woocommerce_currencies()
		: array();

	$currency_country_data = array();

	foreach ( $country_currency_map as $country_code => $currency_code ) {

		$country_name = isset( $all_countries[ $country_code ] )
			? $all_countries[ $country_code ]
			: $country_code;

		$currency_name = isset( $all_currencies[ $currency_code ] )
			? $all_currencies[ $currency_code ]
			: $currency_code;

		$currency_country_data[ $country_code ] = array(
			'code'    => $currency_code,
			'name'    => $currency_name,
			'country' => $country_name,
		);
	}

	?>

	<option value="USD" selected>USD (USA)</option>
	<option value="EUR">EUR (Eurozone)</option>

</select>

<script type="application/json" id="evodent-intl-currency-data">
	<?php echo wp_json_encode( $currency_country_data ); ?>
</script>
							</div>

							<!-- Address -->
							<div class="evodent-field evodent-field-full">
								<label for="intl_customer_address">
									<?php esc_html_e( 'Full Address', 'evodent' ); ?>
								</label>
								<textarea
									id="intl_customer_address"
									name="customer_address"
									rows="3"
									placeholder="Street, City, State/Province, Postal Code, Country"
								></textarea>
							</div>

						

						</div>
					</div>
				</div>

				<!-- QUOTATION ITEMS -->
				<div class="evodent-card">
					<div class="evodent-card-header evodent-items-header">
						<h2><?php esc_html_e( 'Quotation Items', 'evodent' ); ?></h2>
						<button
							type="button"
							class="button button-secondary"
							id="evodent-intl-add-product"
						>
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Product', 'evodent' ); ?>
						</button>
					</div>

					<div class="evodent-card-body evodent-items-body">
						<div class="evodent-product-table-wrapper">
							<table class="widefat evodent-product-table" id="evodent-intl-product-table">
								<thead>
									<tr>
										<th width="40">#</th>
										<th width="220"><?php esc_html_e( 'Product', 'evodent' ); ?></th>
										<th width="100"><?php esc_html_e( 'SKU / Model', 'evodent' ); ?></th>
										<th width="100"><?php esc_html_e( 'Brand', 'evodent' ); ?></th>
										<th width="100"><?php esc_html_e( 'HS Code', 'evodent' ); ?></th>
										<th width="80"><?php esc_html_e( 'Qty', 'evodent' ); ?></th>
										<th width="120">
											<?php esc_html_e( 'Unit Price', 'evodent' ); ?>
											(<span class="evodent-intl-curr-code">USD</span>)
										</th>
										<th width="120">
											<?php esc_html_e( 'Total', 'evodent' ); ?>
											(<span class="evodent-intl-curr-code">USD</span>)
										</th>
										<th width="40"></th>
									</tr>
								</thead>

								<tbody id="evodent-intl-product-rows">
									<tr class="evodent-intl-product-row">
										<td class="evodent-serial">1</td>
										<td>
											<div class="evodent-product-search-wrap">
												<input
													type="text"
													name="intl_products[0][name]"
													class="evodent-product-search"
													placeholder="Search product..."
													autocomplete="off"
												>
												<input
													type="hidden"
													name="intl_products[0][id]"
													class="evodent-product-id"
												>
												<div class="evodent-product-search-results"></div>
											</div>
										</td>
										<td>
											<input
												type="text"
												name="intl_products[0][sku]"
												class="evodent-sku"
												placeholder="SKU"
												readonly
											>
										</td>
										<td>
											<input
												type="text"
												name="intl_products[0][brand]"
												class="evodent-brand"
												value="EVODENT"
												readonly
											>
										</td>
										<td>
											<input
												type="text"
												name="intl_products[0][hsn]"
												class="evodent-hsn"
												value="90230090"
												readonly
											>
										</td>
										<td>
											<input
												type="number"
												name="intl_products[0][quantity]"
												class="evodent-quantity evodent-intl-quantity"
												value="1"
												min="1"
											>
										</td>
										<td>
											<input
												type="number"
												name="intl_products[0][unit_price]"
												class="evodent-intl-unit-price"
												value="0.00"
												min="0"
												step="0.01"
											>
										</td>
										<td>
											<input
												type="text"
												class="evodent-intl-item-total"
												value="0.00"
												readonly
											>
										</td>
										<td>
											<button
												type="button"
												class="button-link-delete evodent-intl-remove-product"
												title="<?php esc_attr_e( 'Remove product', 'evodent' ); ?>"
											>
												<span class="dashicons dashicons-trash"></span>
											</button>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<p class="description evodent-product-description">
							<?php esc_html_e( 'Search a WooCommerce product or type product details manually. Unit price can be manually overridden in the selected currency.', 'evodent' ); ?>
						</p>
					</div>
				</div>

				<!-- COMMERCIAL / SHIPPING NOTES -->
				<div class="evodent-card">
					<div class="evodent-card-header">
						<h2><?php esc_html_e( 'Commercial & Shipping Notes', 'evodent' ); ?></h2>
					</div>
					<div class="evodent-card-body">
						<div class="evodent-form-grid">

							<div class="evodent-field">
								<label for="intl_purpose"><?php esc_html_e( 'Purpose', 'evodent' ); ?></label>
								<input
									type="text"
									id="intl_purpose"
									name="purpose"
									value="Commercial Sale"
									readonly
								>
							</div>

							<div class="evodent-field">
								<label for="intl_country_of_export"><?php esc_html_e( 'Country of Export', 'evodent' ); ?></label>
								<input
									type="text"
									id="intl_country_of_export"
									name="country_of_export"
									value="India"
									readonly
								>
							</div>

							<div class="evodent-field evodent-field-full">
								<label for="intl_special_notes"><?php esc_html_e( 'Special Notes (Product / Shipping)', 'evodent' ); ?></label>
								<textarea
									id="intl_special_notes"
									name="special_notes"
									rows="2"
									placeholder="Any product / shipping notes"
								></textarea>
							</div>

						</div>
					</div>
				</div>

				<!-- TOTALS -->
				<div class="evodent-card evodent-totals-card">
					<div class="evodent-card-body">
						<div class="evodent-totals">

							<div class="evodent-total-row">
								<span><?php esc_html_e( 'Subtotal', 'evodent' ); ?></span>
								<strong>
									<span class="evodent-intl-curr-symbol">$</span>
									<span id="evodent-intl-subtotal">0.00</span>
								</strong>
							</div>

							<div class="evodent-total-row">
								<label for="intl_quote_discount"><?php esc_html_e( 'Discount', 'evodent' ); ?></label>
								<div class="evodent-discount-input">
									<select id="intl_quote_discount_type" name="discount_type">
										<option value="flat"><?php esc_html_e( 'Flat Amount', 'evodent' ); ?></option>
										<option value="percentage"><?php esc_html_e( 'Percentage (%)', 'evodent' ); ?></option>
									</select>
									<input
										type="number"
										id="intl_quote_discount"
										name="discount"
										value="0"
										min="0"
										step="0.01"
									>
								</div>
							</div>

							<!-- <div class="evodent-total-row">
								<label for="intl_quote_freight"><?php esc_html_e( 'Freight / Shipping', 'evodent' ); ?></label>
								<div class="evodent-total-input">
									<span class="evodent-intl-curr-symbol">$</span>
									<input
										type="number"
										id="intl_quote_freight"
										name="freight"
										value="0"
										min="0"
										step="0.01"
									>
								</div>
							</div> -->
							<div class="evodent-total-row evodent-intl-freight-row">
    <label for="intl_quote_freight_option">
        <?php esc_html_e( 'Freight / Shipping', 'evodent' ); ?>
    </label>

    <div class="evodent-intl-freight-wrapper">

        <select
            id="intl_quote_freight_option"
            name="freight_option"
        >
            <option value="amount" selected>
                <?php esc_html_e( 'Shipping Amount', 'evodent' ); ?>
            </option>

            <option value="tbc">
                <?php esc_html_e( 'To Be Calculated Before Dispatch', 'evodent' ); ?>
            </option>
        </select>

        <div
            class="evodent-total-input evodent-intl-freight-amount" 
        >
            <span class="evodent-intl-curr-symbol">$</span>

            <input
                type="number"
                id="intl_quote_freight"
                name="freight"
                value="0"
                min="0"
                step="0.01"
            >
        </div>

    </div>
</div>

							<div class="evodent-total-row">
								<label for="intl_quote_tax_other"><?php esc_html_e( 'Tax / Other Charges', 'evodent' ); ?></label>
								<div class="evodent-total-input">
									<span class="evodent-intl-curr-symbol">$</span>
									<input
										type="number"
										id="intl_quote_tax_other"
										name="tax_other"
										value="0"
										min="0"
										step="0.01"
									>
								</div>
							</div>

							<div class="evodent-total-row evodent-grand-total">
								<span><?php esc_html_e( 'Grand Total', 'evodent' ); ?></span>
								<strong>
									<span class="evodent-intl-curr-symbol">$</span>
									<span id="evodent-intl-grand-total">0.00</span>
								</strong>
							</div>
							<div class="evodent-quote-payment-notice">
    <strong>Note:</strong> We can only accept the payments in all currencies via cards till ₹1,00,000 INR equivalent.
</div>

						</div>
					</div>
				</div>

				<!-- ACTIONS -->
				<div class="evodent-quote-actions">
					<button type="button" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'evodent' ); ?>
					</button>
					<button type="submit" class="button button-primary button-large">
						<span class="dashicons dashicons-media-document"></span>
						<?php esc_html_e( 'Generate Word Quote', 'evodent' ); ?>
					</button>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Generate International Quote Word Document.
	 *
	 * @return void
	 */
	public static function generate_docx() {
		if ( ! current_user_can( 'evodent_generate_quote' ) ) {
			wp_die( 'Permission denied.' );
		}

		if (
			! isset( $_POST['evodent_intl_quote_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['evodent_intl_quote_nonce'] ) ),
				'evodent_generate_international_quote'
			)
		) {
			wp_die( 'Security check failed.' );
		}

		$quote_date_key = current_time( 'Ymd' );

		$quote_number_suffix = isset( $_POST['quote_number_suffix'] )
			? sanitize_text_field( wp_unslash( $_POST['quote_number_suffix'] ) )
			: '';

		$quote_number_suffix = preg_replace( '/[^0-9]/', '', $quote_number_suffix );
		$quote_number_suffix = absint( $quote_number_suffix );

		if ( $quote_number_suffix < 1 ) {
			$quote_number_suffix = 1;
		}

		$quote_number_suffix = sprintf( '%03d', $quote_number_suffix );
		$quote_number        = sprintf( 'EVO-Q-%s-%s', $quote_date_key, $quote_number_suffix );

		$quote_date = isset( $_POST['quote_date'] )
			? sanitize_text_field( wp_unslash( $_POST['quote_date'] ) )
			: '';

		$validity = isset( $_POST['quote_validity'] )
			? sanitize_text_field( wp_unslash( $_POST['quote_validity'] ) )
			: '7 Days';

		if ( is_numeric( $validity ) ) {
			$validity = $validity . ' Days';
		}

		$incoterm = isset( $_POST['incoterm'] )
			? sanitize_text_field( wp_unslash( $_POST['incoterm'] ) )
			: 'DAP';

		$shipping_mode = isset( $_POST['shipping_mode'] )
			? sanitize_text_field( wp_unslash( $_POST['shipping_mode'] ) )
			: 'Courier';

		$estimated_dispatch = isset( $_POST['estimated_dispatch'] )
			? sanitize_text_field( wp_unslash( $_POST['estimated_dispatch'] ) )
			: '';

		$customer_company = isset( $_POST['customer_company'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_company'] ) )
			: '';

		$contact_person = isset( $_POST['contact_person'] )
			? sanitize_text_field( wp_unslash( $_POST['contact_person'] ) )
			: '';

		$customer_phone = isset( $_POST['customer_phone'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) )
			: '';

		$customer_email = isset( $_POST['customer_email'] )
			? sanitize_email( wp_unslash( $_POST['customer_email'] ) )
			: '';

		$customer_country = isset( $_POST['customer_country'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_country'] ) )
			: '';

		$quote_currency = isset( $_POST['quote_currency'] )
			? sanitize_text_field( wp_unslash( $_POST['quote_currency'] ) )
			: 'USD';

		$customer_address = isset( $_POST['customer_address'] )
			? sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ) )
			: '';

		$customer_gstin = isset( $_POST['customer_gstin'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_gstin'] ) )
			: '';

		$purpose = isset( $_POST['purpose'] )
			? sanitize_text_field( wp_unslash( $_POST['purpose'] ) )
			: 'Commercial Sale';

		$country_of_export = isset( $_POST['country_of_export'] )
			? sanitize_text_field( wp_unslash( $_POST['country_of_export'] ) )
			: 'India';

		$special_notes = isset( $_POST['special_notes'] )
			? sanitize_textarea_field( wp_unslash( $_POST['special_notes'] ) )
			: '';

		$discount_value = isset( $_POST['discount'] )
			? (float) wp_unslash( $_POST['discount'] )
			: 0;

		$discount_type = isset( $_POST['discount_type'] )
			? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) )
			: 'flat';

		$freight_option = isset( $_POST['freight_option'] )
    ? sanitize_text_field( wp_unslash( $_POST['freight_option'] ) )
    : 'amount';

$freight = isset( $_POST['freight'] )
    ? (float) wp_unslash( $_POST['freight'] )
    : 0;

// If freight is to be calculated later, do not include
// any freight amount in the Grand Total.
if ( 'tbc' === $freight_option ) {
    $freight = 0;
}

		$tax_other = isset( $_POST['tax_other'] )
			? (float) wp_unslash( $_POST['tax_other'] )
			: 0;

		$raw_products = isset( $_POST['intl_products'] ) && is_array( $_POST['intl_products'] )
			? wp_unslash( $_POST['intl_products'] )
			: array();

		$quote_items = array();
		$subtotal    = 0;

		foreach ( $raw_products as $product_data ) {
			$product_id = isset( $product_data['id'] ) ? absint( $product_data['id'] ) : 0;
			$wc_product = $product_id ? wc_get_product( $product_id ) : null;

			$name = isset( $product_data['name'] )
				? sanitize_text_field( $product_data['name'] )
				: ( $wc_product ? $wc_product->get_name() : '' );

			if ( empty( $name ) ) {
				continue;
			}

			$sku = isset( $product_data['sku'] )
				? sanitize_text_field( $product_data['sku'] )
				: ( $wc_product ? $wc_product->get_sku() : '' );

			$brand = isset( $product_data['brand'] )
				? sanitize_text_field( $product_data['brand'] )
				: 'EVODENT';

			$hsn = isset( $product_data['hsn'] )
				? sanitize_text_field( $product_data['hsn'] )
				: '90230090';

			$quantity = isset( $product_data['quantity'] )
				? max( 1, (int) $product_data['quantity'] )
				: 1;

			$unit_price = isset( $product_data['unit_price'] )
				? max( 0, (float) $product_data['unit_price'] )
				: 0;

			$item_total = $unit_price * $quantity;
			$subtotal  += $item_total;

			$quote_items[] = array(
				'product'    => $wc_product,
				'name'       => $name,
				'sku'        => $sku,
				'brand'      => $brand,
				'hsn'        => $hsn,
				'quantity'   => $quantity,
				'unit_price' => $unit_price,
				'item_total' => $item_total,
			);
		}

		if ( empty( $quote_items ) ) {
			wp_die( 'Please enter at least one product.' );
		}

		if ( 'percentage' === $discount_type ) {
			$discount = ( $subtotal * $discount_value ) / 100;
		} else {
			$discount = $discount_value;
		}

		$discount    = min( max( 0, $discount ), $subtotal );
		$grand_total = $subtotal - $discount + $freight + $tax_other;

		$template_path = dirname( __DIR__ ) . '/templates/Evodent Quote Format - International.docx';

		if ( ! file_exists( $template_path ) ) {
			wp_die( 'International quote template not found: ' . esc_html( $template_path ) );
		}

		if ( ! class_exists( '\PhpOffice\PhpWord\TemplateProcessor' ) ) {
			wp_die( 'PhpWord TemplateProcessor is not available.' );
		}

		$template = new \PhpOffice\PhpWord\TemplateProcessor( $template_path );

		$template->setValue( 'quote_no', $quote_number );

		$formatted_date = $quote_date;
		if ( $quote_date ) {
			$timestamp = strtotime( $quote_date );
			if ( $timestamp ) {
				$formatted_date = wp_date( 'd-M-Y', $timestamp );
			}
		}

		$template->setValue( 'quote_date', $formatted_date );
		$template->setValue( 'validity', $validity );
		$template->setValue( 'currency', $quote_currency );
		$template->setValue( 'payment_terms', '100% Advance' );
		$template->setValue( 'incoterm', $incoterm );
		$template->setValue( 'delivery_terms', $incoterm );
		$template->setValue( 'shipping_mode', $shipping_mode );
		$template->setValue( 'delivery_mode', $shipping_mode );
		$template->setValue( 'est_dispatch', $estimated_dispatch ? $estimated_dispatch . '; Subject to availability' : 'Subject to availability' );

		$template->setValue( 'customer_company', $customer_company );
		$template->setValue( 'contact_person', $contact_person );
		$template->setValue( 'customer_phone', $customer_phone );
		$template->setValue( 'customer_email', $customer_email );
		$template->setValue( 'customer_address', $customer_address );

		$country_display = $customer_country;
		if ( ! empty( $customer_gstin ) ) {
			$country_display .= ' (Tax / VAT ID: ' . $customer_gstin . ')';
		}

		$template->setValue( 'customer_country', $country_display );
		$template->setValue( 'country', $country_display );
		$template->setValue( 'customer_gstin', $customer_gstin );
		$template->setValue( 'tax_id', $customer_gstin );
		$template->setValue( 'purpose', $purpose );
		$template->setValue( 'country_of_export', $country_of_export );
		$template->setValue( 'special_notes', $special_notes );

		$template->setValue( 'subtotal', number_format( $subtotal, 2 ) );
		$template->setValue( 'discount', number_format( $discount, 2 ) );
		$freight_display = ( 'tbc' === $freight_option )
    ? 'To be calculated before dispatch'
    : number_format( $freight, 2 );

$template->setValue( 'freight', $freight_display );
		$template->setValue( 'tax_other', number_format( $tax_other, 2 ) );
		$template->setValue( 'tax_charges', number_format( $tax_other, 2 ) );
		$template->setValue( 'grand_total', number_format( $grand_total, 2 ) );

		$total_in_words = Evodent_Quote_Helpers::amount_to_words_intl( $grand_total, $quote_currency );
		$template->setValue( 'total_in_words', $total_in_words );

		$item_count = count( $quote_items );
		$template->cloneRow( 'product_name', $item_count );

		foreach ( $quote_items as $index => $item ) {
			$row_number = $index + 1;

			$template->setValue( 'serial#' . $row_number, $row_number );
			$template->setValue( 's_no#' . $row_number, $row_number );
			$template->setValue( 'product_name#' . $row_number, $item['name'] );
			$template->setValue( 'sku#' . $row_number, $item['sku'] );
			$template->setValue( 'brand#' . $row_number, $item['brand'] );
			$template->setValue( 'hsn#' . $row_number, $item['hsn'] );
			$template->setValue( 'hs_code#' . $row_number, $item['hsn'] );
			$template->setValue( 'qty#' . $row_number, $item['quantity'] );
			$template->setValue( 'quantity#' . $row_number, $item['quantity'] );
			$template->setValue( 'unit_price#' . $row_number, number_format( $item['unit_price'], 2 ) );
			$template->setValue( 'rate#' . $row_number, number_format( $item['unit_price'], 2 ) );
			$template->setValue( 'item_total#' . $row_number, number_format( $item['item_total'], 2 ) );
			$template->setValue( 'total#' . $row_number, number_format( $item['item_total'], 2 ) );

			$image_path = '';
			if ( $item['product'] ) {
				$image_id = $item['product']->get_image_id();
				if ( $image_id ) {
					$image_path = get_attached_file( $image_id );
				}
			}

			if ( $image_path && file_exists( $image_path ) ) {
				$image_for_word  = $image_path;
				$temporary_image = false;
				$image_type      = wp_get_image_mime( $image_path );

				if ( 'image/webp' === $image_type ) {
					$editor = wp_get_image_editor( $image_path );
					if ( ! is_wp_error( $editor ) ) {
						$temporary_image = wp_tempnam( 'evodent-intl-product-image.png' );
						if ( $temporary_image ) {
							$editor->set_quality( 90 );
							$saved = $editor->save( $temporary_image, 'image/png' );
							if ( ! is_wp_error( $saved ) ) {
								$image_for_word = $saved['path'];
							} else {
								@unlink( $temporary_image );
								$temporary_image = false;
							}
						}
					}
				}

				if ( $image_for_word && file_exists( $image_for_word ) ) {
					try {
						$template->setImageValue(
							'product_image#' . $row_number,
							array(
								'path'   => $image_for_word,
								'width'  => 50,
								'height' => 50,
							)
						);
					} catch ( \Throwable $e ) {
						$template->setValue( 'product_image#' . $row_number, '' );
					}
				} else {
					$template->setValue( 'product_image#' . $row_number, '' );
				}

				if ( $temporary_image && file_exists( $temporary_image ) ) {
					@unlink( $temporary_image );
				}
			} else {
				$template->setValue( 'product_image#' . $row_number, '' );
			}
		}

		$temp_file = wp_tempnam( 'evodent-intl-quote.docx' );

		if ( ! $temp_file ) {
			wp_die( 'Unable to create temporary quote file.' );
		}

		$template->saveAs( $temp_file );

		update_option(
			'evodent_last_quote_number_' . current_time( 'Ymd' ),
			(int) substr( $quote_number, -3 ),
			false
		);

		if ( ob_get_length() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		header( 'Content-Disposition: attachment; filename="Evodent-Quote-International-' . sanitize_file_name( $quote_number ) . '.docx"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );

		readfile( $temp_file );
		@unlink( $temp_file );
		exit;
	}
}
