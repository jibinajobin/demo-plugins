<?php
/**
 * Evodent Domestic Quote Handler
 *
 * Renders domestic quote form and processes domestic quote DOCX generation.
 *
 * @package Evodent_Order_Documents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Evodent_Quote_Domestic {

	/**
	 * Render Domestic Quote Form.
	 *
	 * @param int $next_quote_number Next quote number sequence.
	 * @return void
	 */
	public static function render_form( $next_quote_number ) {
		?>
		<div id="evodent-domestic-content">

			<!-- DOMESTIC QUOTE NOTICE -->
			<div class="evodent-quote-notice">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong><?php esc_html_e( 'Domestic Quote', 'evodent' ); ?></strong>
					<p><?php esc_html_e( 'This quotation is for India/domestic customers.', 'evodent' ); ?></p>
				</div>
			</div>

			<form
				id="evodent-quote-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>

				<input type="hidden" name="action" value="evodent_generate_quote">
				<?php wp_nonce_field( 'evodent_generate_quote', 'evodent_quote_nonce' ); ?>

				<!-- QUOTE DETAILS -->
				<div class="evodent-card">
					<div class="evodent-card-header">
						<h2><?php esc_html_e( 'Quote Details', 'evodent' ); ?></h2>
					</div>
					<div class="evodent-card-body">
						<div class="evodent-form-grid">

							<!-- Quote Number -->
							<div class="evodent-field">
								<label for="quote_number_suffix">
									<?php esc_html_e( 'Quote No.', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<div class="evodent-quote-number">
									<span class="evodent-quote-prefix">
										<?php echo esc_html( 'EVO-Q-' . current_time( 'Ymd' ) . '-' ); ?>
									</span>
									<input
										type="text"
										id="quote_number_suffix"
										name="quote_number_suffix"
										value="<?php echo esc_attr( sprintf( '%03d', $next_quote_number ) ); ?>"
										required
									>
								</div>
							</div>

							<!-- Date -->
							<div class="evodent-field">
								<label for="quote_date">
									<?php esc_html_e( 'Date', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="date"
									id="quote_date"
									name="quote_date"
									value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
								>
							</div>

							<!-- Validity -->
							<div class="evodent-field">
								<label for="quote_validity">
									<?php esc_html_e( 'Validity', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<div class="evodent-validity-field">
									<input
										type="number"
										id="quote_validity"
										name="quote_validity"
										value="7"
										min="1"
										required
									>
									<span>Days</span>
								</div>
							</div>

							<!-- Currency -->
							<div class="evodent-field">
								<label for="quote_currency">
									<?php esc_html_e( 'Currency', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="quote_currency"
									name="quote_currency"
									value="INR"
									readonly
								>
							</div>

							<!-- Payment Terms -->
							<div class="evodent-field">
								<label for="payment_terms">
									<?php esc_html_e( 'Payment Terms', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									id="payment_terms"
									name="payment_terms"
									value="100% Advance"
									required
								>
							</div>

							<!-- Delivery Terms -->
							<div class="evodent-field">
								<label for="delivery_terms">
									<?php esc_html_e( 'Delivery Terms', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<select id="delivery_terms" name="delivery_terms">
									<option value="Freight Extra"><?php esc_html_e( 'Freight Extra', 'evodent' ); ?></option>
									<option value="Free Shipping"><?php esc_html_e( 'Free Shipping', 'evodent' ); ?></option>
									<option value="As Agreed"><?php esc_html_e( 'As Agreed', 'evodent' ); ?></option>
								</select>
							</div>

							<!-- Delivery Mode -->
							<div class="evodent-field">
								<label for="delivery_mode">
									<?php esc_html_e( 'Delivery Mode', 'evodent' ); ?>
								</label>
								<select id="delivery_mode" name="delivery_mode">
									<option value="Courier"><?php esc_html_e( 'Courier', 'evodent' ); ?></option>
									<option value="Transport - Freight Paid"><?php esc_html_e( 'Transport - Freight Paid', 'evodent' ); ?></option>
									<option value="Transport - Freight To Pay"><?php esc_html_e( 'Transport - Freight To Pay', 'evodent' ); ?></option>
									<option value="Other"><?php esc_html_e( 'Other', 'evodent' ); ?></option>
								</select>
							</div>

							<!-- Estimated Dispatch -->
							<div class="evodent-field">
								<label for="estimated_dispatch">
									<?php esc_html_e( 'Est. Dispatch', 'evodent' ); ?>
								</label>
								<div class="evodent-dispatch-field">
									<input
										type="date"
										id="estimated_dispatch"
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
								<label for="customer_company">
									<?php esc_html_e( 'Customer / Company', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									id="customer_company"
									name="customer_company"
									placeholder="Customer / Company Name"
									required
								>
							</div>

							<!-- Contact Person -->
							<div class="evodent-field">
								<label for="contact_person">
									<?php esc_html_e( 'Contact Person', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="contact_person"
									name="contact_person"
									placeholder="Contact Name"
								>
							</div>

							<!-- Phone -->
							<div class="evodent-field">
								<label for="customer_phone">
									<?php esc_html_e( 'Phone', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="customer_phone"
									name="customer_phone"
									placeholder="+91 XXXXX XXXXX"
								>
							</div>

							<!-- Email -->
							<div class="evodent-field">
								<label for="customer_email">
									<?php esc_html_e( 'Email', 'evodent' ); ?>
								</label>
								<input
									type="email"
									id="customer_email"
									name="customer_email"
									placeholder="customer@example.com"
								>
							</div>

							<!-- Address -->
							<div class="evodent-field evodent-field-full">
								<label for="customer_address">
									<?php esc_html_e( 'Address', 'evodent' ); ?>
								</label>
								<textarea
									id="customer_address"
									name="customer_address"
									rows="3"
									placeholder="Full billing address"
								></textarea>
							</div>

							<!-- GSTIN -->
							<div class="evodent-field">
								<label for="customer_gstin">
									<?php esc_html_e( 'GSTIN', 'evodent' ); ?>
								</label>
								<input
									type="text"
									id="customer_gstin"
									name="customer_gstin"
									placeholder="GSTIN if applicable"
								>
							</div>

							<!-- Place of Supply -->
							<div class="evodent-field">
								<label for="place_of_supply">
									<?php esc_html_e( 'Place of Supply', 'evodent' ); ?>
									<span class="required">*</span>
								</label>
								<select id="place_of_supply" name="place_of_supply" required>
									<option value=""><?php esc_html_e( 'Select State / UT', 'evodent' ); ?></option>
									<option value="Andhra Pradesh">Andhra Pradesh</option>
									<option value="Arunachal Pradesh">Arunachal Pradesh</option>
									<option value="Assam">Assam</option>
									<option value="Bihar">Bihar</option>
									<option value="Chhattisgarh">Chhattisgarh</option>
									<option value="Goa">Goa</option>
									<option value="Gujarat">Gujarat</option>
									<option value="Haryana">Haryana</option>
									<option value="Himachal Pradesh">Himachal Pradesh</option>
									<option value="Jharkhand">Jharkhand</option>
									<option value="Karnataka">Karnataka</option>
									<option value="Kerala">Kerala</option>
									<option value="Madhya Pradesh">Madhya Pradesh</option>
									<option value="Maharashtra">Maharashtra</option>
									<option value="Manipur">Manipur</option>
									<option value="Meghalaya">Meghalaya</option>
									<option value="Mizoram">Mizoram</option>
									<option value="Nagaland">Nagaland</option>
									<option value="Odisha">Odisha</option>
									<option value="Punjab">Punjab</option>
									<option value="Rajasthan">Rajasthan</option>
									<option value="Sikkim">Sikkim</option>
									<option value="Tamil Nadu">Tamil Nadu</option>
									<option value="Telangana">Telangana</option>
									<option value="Tripura">Tripura</option>
									<option value="Uttar Pradesh">Uttar Pradesh</option>
									<option value="Uttarakhand">Uttarakhand</option>
									<option value="West Bengal">West Bengal</option>
									<option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
									<option value="Chandigarh">Chandigarh</option>
									<option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
									<option value="Delhi">Delhi</option>
									<option value="Jammu and Kashmir">Jammu and Kashmir</option>
									<option value="Ladakh">Ladakh</option>
									<option value="Lakshadweep">Lakshadweep</option>
									<option value="Puducherry">Puducherry</option>
								</select>
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
							id="evodent-add-product"
						>
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Product', 'evodent' ); ?>
						</button>
					</div>

					<div class="evodent-card-body evodent-items-body">
						<div class="evodent-product-table-wrapper">
							<table class="widefat evodent-product-table">
								<thead>
									<tr>
										<th width="40">#</th>
										<th width="220"><?php esc_html_e( 'Product', 'evodent' ); ?></th>
										<th width="100"><?php esc_html_e( 'SKU / Model', 'evodent' ); ?></th>
										<th width="100"><?php esc_html_e( 'HSN', 'evodent' ); ?></th>
										<th width="80"><?php esc_html_e( 'Qty', 'evodent' ); ?></th>
										<th width="120"><?php esc_html_e( 'Rate Ex GST', 'evodent' ); ?></th>
										<th width="80"><?php esc_html_e( 'GST %', 'evodent' ); ?></th>
										<th width="120"><?php esc_html_e( 'Rate Inc GST', 'evodent' ); ?></th>
										<th width="120"><?php esc_html_e( 'Total Inc GST', 'evodent' ); ?></th>
										<th width="40"></th>
									</tr>
								</thead>

								<tbody id="evodent-product-rows">
									<tr class="evodent-product-row">
										<td class="evodent-serial">1</td>
										<td>
											<div class="evodent-product-search-wrap">
												<input
													type="text"
													name="products[0][name]"
													class="evodent-product-search"
													placeholder="Search product..."
													autocomplete="off"
												>
												<input
													type="hidden"
													name="products[0][id]"
													class="evodent-product-id"
												>
												<div class="evodent-product-search-results"></div>
											</div>
										</td>
										<td>
											<input
												type="text"
												name="products[0][sku]"
												class="evodent-sku"
												placeholder="SKU"
												readonly
											>
										</td>
										<td>
											<input
												type="text"
												name="products[0][hsn]"
												class="evodent-hsn"
												placeholder="HSN"
												readonly
											>
										</td>
										<td>
											<input
												type="number"
												name="products[0][quantity]"
												class="evodent-quantity"
												value="1"
												min="1"
											>
										</td>
										<td>
											<input
												type="number"
												name="products[0][rate_ex_gst]"
												class="evodent-rate-ex-gst"
												value="0"
												min="0"
												step="0.01"
												readonly
											>
										</td>
										<td>
											<input
												type="number"
												name="products[0][gst]"
												class="evodent-gst"
												value="18"
												min="0"
												step="0.01"
												readonly
											>
										</td>
										<td>
											<input
												type="number"
												name="products[0][rate_inc_gst]"
												class="evodent-rate-inc-gst"
												value="0.00"
												min="0"
												step="0.01"
											>
										</td>
										<td>
											<input
												type="text"
												class="evodent-item-total"
												value="0.00"
												readonly
											>
										</td>
										<td>
											<button
												type="button"
												class="button-link-delete evodent-remove-product"
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
							<?php esc_html_e( 'Select a WooCommerce product. Product details such as image, SKU and HSN can be automatically loaded later.', 'evodent' ); ?>
						</p>
					</div>
				</div>

				<!-- TOTALS -->
				<div class="evodent-card evodent-totals-card">
					<div class="evodent-card-body">
						<div class="evodent-totals">

							<!-- Subtotal -->
							<div class="evodent-total-row">
								<span><?php esc_html_e( 'Subtotal', 'evodent' ); ?></span>
								<strong>₹ <span id="evodent-subtotal">0.00</span></strong>
							</div>

							<!-- Discount -->
							<div class="evodent-total-row">
								<label for="quote_discount"><?php esc_html_e( 'Discount', 'evodent' ); ?></label>
								<div class="evodent-discount-input">
									<select id="quote_discount_type" name="discount_type">
										<option value="flat"><?php esc_html_e( 'Flat Amount (₹)', 'evodent' ); ?></option>
										<option value="percentage"><?php esc_html_e( 'Percentage (%)', 'evodent' ); ?></option>
									</select>
									<input
										type="number"
										id="quote_discount"
										name="discount"
										value="0"
										min="0"
										step="0.01"
									>
								</div>
							</div>

							<!-- Freight -->
							<div class="evodent-total-row">
								<label for="quote_freight"><?php esc_html_e( 'Freight / Shipping', 'evodent' ); ?></label>
								<div class="evodent-total-input">
									₹
									<input
										type="number"
										id="quote_freight"
										name="freight"
										value="0"
										min="0"
										step="0.01"
									>
								</div>
							</div>

							<!-- Grand Total -->
							<div class="evodent-total-row evodent-grand-total">
								<span><?php esc_html_e( 'Grand Total (Incl. GST)', 'evodent' ); ?></span>
								<strong>₹ <span id="evodent-grand-total">0.00</span></strong>
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
	 * Generate Domestic Quote Word Document.
	 *
	 * @return void
	 */
	public static function generate_docx() {
		if ( ! current_user_can( 'evodent_generate_quote' ) ) {
			wp_die( 'Permission denied.' );
		}

		if (
			! isset( $_POST['evodent_quote_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['evodent_quote_nonce'] ) ),
				'evodent_generate_quote'
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

		$payment_terms = isset( $_POST['payment_terms'] )
			? sanitize_text_field( wp_unslash( $_POST['payment_terms'] ) )
			: '100% Advance';

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

		$customer_address = isset( $_POST['customer_address'] )
			? sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ) )
			: '';

		$customer_gstin = isset( $_POST['customer_gstin'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_gstin'] ) )
			: '';

		$place_of_supply = isset( $_POST['place_of_supply'] )
			? sanitize_text_field( wp_unslash( $_POST['place_of_supply'] ) )
			: '';

		$delivery_terms = isset( $_POST['delivery_terms'] )
			? sanitize_text_field( wp_unslash( $_POST['delivery_terms'] ) )
			: '';

		$delivery_mode = isset( $_POST['delivery_mode'] )
			? sanitize_text_field( wp_unslash( $_POST['delivery_mode'] ) )
			: '';

		$estimated_dispatch = isset( $_POST['estimated_dispatch'] )
			? sanitize_text_field( wp_unslash( $_POST['estimated_dispatch'] ) )
			: '';

		$discount_value = isset( $_POST['discount'] )
			? (float) wp_unslash( $_POST['discount'] )
			: 0;

		$discount_type = isset( $_POST['discount_type'] )
			? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) )
			: 'flat';

		$freight = isset( $_POST['freight'] )
			? (float) wp_unslash( $_POST['freight'] )
			: 0;

		$products = isset( $_POST['products'] ) && is_array( $_POST['products'] )
			? wp_unslash( $_POST['products'] )
			: array();

		$quote_items = array();
		$subtotal    = 0;

		foreach ( $products as $product_data ) {
			$product_id = isset( $product_data['id'] ) ? absint( $product_data['id'] ) : 0;

			if ( ! $product_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$name = isset( $product_data['name'] )
				? sanitize_text_field( $product_data['name'] )
				: $product->get_name();

			$sku = isset( $product_data['sku'] )
				? sanitize_text_field( $product_data['sku'] )
				: $product->get_sku();

			$hsn = isset( $product_data['hsn'] )
				? sanitize_text_field( $product_data['hsn'] )
				: '';

			$quantity = isset( $product_data['quantity'] )
				? max( 1, (int) $product_data['quantity'] )
				: 1;

			$rate_inc_gst = isset( $product_data['rate_inc_gst'] )
				? max( 0, (float) $product_data['rate_inc_gst'] )
				: ( isset( $product_data['rate_ex_gst'] ) ? max( 0, (float) $product_data['rate_ex_gst'] ) : 0 );

			$gst_percent = isset( $product_data['gst'] )
				? max( 0, (float) $product_data['gst'] )
				: 18;

			$rate_ex_gst = $gst_percent > 0
				? ( $rate_inc_gst / ( 1 + ( $gst_percent / 100 ) ) )
				: $rate_inc_gst;

			$item_total = $rate_inc_gst * $quantity;
			$subtotal  += $item_total;

			$quote_items[] = array(
				'product'      => $product,
				'name'         => $name,
				'sku'          => $sku,
				'hsn'          => $hsn,
				'quantity'     => $quantity,
				'rate_ex_gst'  => $rate_ex_gst,
				'gst_percent'  => $gst_percent,
				'rate_inc_gst' => $rate_inc_gst,
				'item_total'   => $item_total,
			);
		}

		if ( empty( $quote_items ) ) {
			wp_die( 'Please select at least one product.' );
		}

		if ( 'percentage' === $discount_type ) {
			$discount = ( $subtotal * $discount_value ) / 100;
		} else {
			$discount = $discount_value;
		}

		$discount    = min( max( 0, $discount ), $subtotal );
		$grand_total = $subtotal - $discount + $freight;

		$total_in_words = Evodent_Quote_Helpers::amount_to_words( $grand_total );

		$template_path = dirname( __DIR__ ) . '/templates/Evodent Quote Format - Domestic India.docx';

		if ( ! file_exists( $template_path ) ) {
			wp_die( 'Domestic quote template not found: ' . esc_html( $template_path ) );
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
		$template->setValue( 'payment_terms', $payment_terms );
		$template->setValue( 'customer_company', $customer_company );
		$template->setValue( 'contact_person', $contact_person );
		$template->setValue( 'customer_address', $customer_address );
		$template->setValue( 'customer_phone', $customer_phone );
		$template->setValue( 'customer_gstin', $customer_gstin );
		$template->setValue( 'customer_email', $customer_email );
		$template->setValue( 'place_of_supply', $place_of_supply );
		$template->setValue( 'delivery_terms', $delivery_terms );
		$template->setValue( 'delivery_mode', $delivery_mode );
		$template->setValue( 'est_dispatch', $estimated_dispatch );
		$template->setValue( 'discount', number_format( $discount, 2 ) );
		$template->setValue( 'freight', number_format( $freight, 2 ) );
		$template->setValue( 'grand_total', number_format( $grand_total, 2 ) );
		$template->setValue( 'total_in_words', $total_in_words );

		$item_count = count( $quote_items );
		$template->cloneRow( 'product_name', $item_count );

		foreach ( $quote_items as $index => $item ) {
			$row_number = $index + 1;
            $template->setValue( 's_no#' . $row_number, $row_number );
			$template->setValue( 'product_name#' . $row_number, $item['name'] );
			$template->setValue( 'sku#' . $row_number, $item['sku'] );
			$template->setValue( 'hsn#' . $row_number, $item['hsn'] );
			$template->setValue( 'qty#' . $row_number, $item['quantity'] );
			$template->setValue( 'rate_ex_gst#' . $row_number, number_format( $item['rate_ex_gst'], 2 ) );
			$template->setValue( 'gst_percent#' . $row_number, number_format( $item['gst_percent'], 0 ) . '%' );
			$template->setValue( 'rate_inc_gst#' . $row_number, number_format( $item['rate_inc_gst'], 2 ) );
			$template->setValue( 'item_total#' . $row_number, number_format( $item['item_total'], 2 ) );

			$image_id = $item['product']->get_image_id();
			if ( $image_id ) {
				$image_path = get_attached_file( $image_id );
				if ( $image_path && file_exists( $image_path ) ) {
					$image_for_word  = $image_path;
					$temporary_image = false;
					$image_type      = wp_get_image_mime( $image_path );

					if ( 'image/webp' === $image_type ) {
						$editor = wp_get_image_editor( $image_path );
						if ( ! is_wp_error( $editor ) ) {
							$temporary_image = wp_tempnam( 'evodent-product-image.png' );
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
			} else {
				$template->setValue( 'product_image#' . $row_number, '' );
			}
		}

		$temp_file = wp_tempnam( 'evodent-quote.docx' );

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
		header( 'Content-Disposition: attachment; filename="Evodent-Quote-' . sanitize_file_name( $quote_number ) . '.docx"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );

		readfile( $temp_file );
		@unlink( $temp_file );
		exit;
	}
}
