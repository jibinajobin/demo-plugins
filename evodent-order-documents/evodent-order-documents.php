<?php

/**
 * Plugin Name: Evodent Order Documents and Quote Generation
 * Description: Generate Commercial Invoice, Packing List and Non-DG documents for WooCommerce orders.
 * Version: 1.0.0
 * Author: Jibina
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EVODENT_DOCS_PATH', plugin_dir_path(__FILE__));
define('EVODENT_DOCS_URL', plugin_dir_url(__FILE__));

require_once EVODENT_DOCS_PATH . 'includes/class-admin.php';
require_once EVODENT_DOCS_PATH . 'includes/class-quote-helpers.php';
require_once EVODENT_DOCS_PATH . 'includes/class-quote-domestic.php';
require_once EVODENT_DOCS_PATH . 'includes/class-quote-intl.php';
require_once EVODENT_DOCS_PATH . 'includes/class-quote-admin.php';
require_once EVODENT_DOCS_PATH . 'includes/class-quote-roles.php';
evodent_create_quote_staff_role();
if (file_exists(EVODENT_DOCS_PATH . 'vendor/autoload.php')) {
    require_once EVODENT_DOCS_PATH . 'vendor/autoload.php';
}
require_once EVODENT_DOCS_PATH . 'includes/helpers.php';
require_once EVODENT_DOCS_PATH . 'includes/class-assets.php';
require_once EVODENT_DOCS_PATH . 'includes/class-email.php';

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Helper to get the correct invoice number for an order.
 * Prefers $order->get_order_number() (Order ID), avoiding raw internal post IDs.
 */
function evodent_get_order_invoice_number($order)
{
    $order_number = $order->get_order_number();
    $meta_invoice = $order->get_meta('wpifw_invoice_no');

    if (! empty($meta_invoice) && (string) $meta_invoice !== (string) $order->get_id()) {
        return $meta_invoice;
    }

    return $order_number;
}

add_action('woocommerce_order_status_processing', 'evodent_sync_order_invoice_meta');
add_action('woocommerce_order_status_changed', 'evodent_sync_order_invoice_meta_on_change', 10, 3);

function evodent_sync_order_invoice_meta($order_id)
{
    $order = wc_get_order($order_id);
    if (! $order) {
        return;
    }
    $meta_inv = $order->get_meta('wpifw_invoice_no');
    if (empty($meta_inv) || (string) $meta_inv === (string) $order->get_id()) {
        $order->update_meta_data('wpifw_invoice_no', $order->get_order_number());
        $order->save();
    }
}

function evodent_sync_order_invoice_meta_on_change($order_id, $old_status, $new_status)
{
    if ('processing' === $new_status) {
        evodent_sync_order_invoice_meta($order_id);
    }
}

/**
 * Helper to retrieve order customer details with shipping to billing fallback.
 */
function evodent_get_order_customer_details($order)
{
    $name = trim($order->get_formatted_shipping_full_name());
    if (empty($name)) {
        $name = trim($order->get_formatted_billing_full_name());
    }
    if (empty($name)) {
        $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    }
    if (empty($name)) {
        $name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
    }
    if (empty($name)) {
        $name = trim($order->get_shipping_company());
    }
    if (empty($name)) {
        $name = trim($order->get_billing_company());
    }
    if (empty($name)) {
        $name = trim($order->get_billing_email());
    }
    if (empty($name)) {
        $name = 'Valued Customer';
    }

    $address1 = $order->get_shipping_address_1();
    $address2 = $order->get_shipping_address_2();
    $address  = trim($address1 . ' ' . $address2);
    if (empty($address)) {
        $address = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
    }

    $city = $order->get_shipping_city();
    if (empty($city)) {
        $city = $order->get_billing_city();
    }

    $state = $order->get_shipping_state();
    if (empty($state)) {
        $state = $order->get_billing_state();
    }

    $postcode = $order->get_shipping_postcode();
    if (empty($postcode)) {
        $postcode = $order->get_billing_postcode();
    }

    $country_code = $order->get_shipping_country();
    if (empty($country_code)) {
        $country_code = $order->get_billing_country();
    }

    $countries    = (function_exists('WC') && WC()->countries) ? WC()->countries->get_countries() : array();
    $country_name = isset($countries[$country_code]) ? $countries[$country_code] : $country_code;

    $phone = $order->get_shipping_phone();
    if (empty($phone)) {
        $phone = $order->get_billing_phone();
    }

    return array(
        'name'         => evodent_doc_text($name),
        'address'      => evodent_doc_text($address),
        'city'         => evodent_doc_text($city),
        'state'        => evodent_doc_text($state),
        'postcode'     => evodent_doc_text($postcode),
        'country_name' => evodent_doc_text($country_name),
        'phone'        => evodent_doc_text($phone),
    );
}

//========================= Commercial Invoice ===========================================

add_action('admin_post_evodent_download_invoice', 'evodent_download_invoice');

function evodent_download_invoice()
{
    check_admin_referer('evodent_download_invoice');

    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if (! $order_id) {
        wp_die('Invalid Order ID.');
    }

    // Get WooCommerce order
    $order = wc_get_order($order_id);

    if (! $order) {
        wp_die('Order not found.');
    }

    $tracking = array();
    $awb      = '';

    $tracking_items = $order->get_meta('_wc_shipment_tracking_items');

    if (! empty($tracking_items) && is_array($tracking_items)) {
        $tracking = reset($tracking_items);
        if (is_array($tracking)) {
            $awb = ! empty($tracking['tracking_number']) ? $tracking['tracking_number'] : '';
        }
    }

    // Template path
    $template_path = EVODENT_DOCS_PATH . 'templates/Evodent Commercial Invoice.docx';

    if (! file_exists($template_path)) {
        wp_die('Template file not found.');
    }

    $transaction_id = $order->get_transaction_id();
    if (empty($transaction_id)) {
        $transaction_id = '-';
    }

    global $wpdb;

    $provider_slug = (is_array($tracking) && isset($tracking['tracking_provider'])) ? $tracking['tracking_provider'] : '';
    $carrier_name  = '';

    if (! empty($provider_slug)) {
        $carrier_name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT provider_name
                FROM {$wpdb->prefix}woo_shippment_provider
                WHERE ts_slug = %s",
                $provider_slug
            )
        );

        if (empty($carrier_name)) {
            $carrier_name = $provider_slug;
        }
    }

    // Load template
    $template = new TemplateProcessor($template_path);

    $invoice_number = evodent_get_order_invoice_number($order);

    $date_created = $order->get_date_created();
    $invoice_date = $date_created ? wc_format_datetime($date_created, 'd-F-Y') : date_i18n('d-F-Y');

    $cust = evodent_get_order_customer_details($order);

    $template->setValue('invoice_number', $invoice_number);
    $template->setValue('invoice_date', $invoice_date);

    $template->setValue('customer_name', $cust['name']);
    $template->setValue('customer_address', $cust['address']);
    $template->setValue('customer_city', $cust['city']);
    $template->setValue('customer_state', $cust['state']);
    $template->setValue('customer_postcode', $cust['postcode']);
    $template->setValue('customer_country', $cust['country_name']);

    $template->setValue('customer_email', evodent_doc_text($order->get_billing_email()));
    $template->setValue('customer_phone', $cust['phone']);

    $template->setValue('currency', evodent_doc_text($order->get_currency()));
    $template->setValue('transaction_id', evodent_doc_text($transaction_id));

    $template->setValue('awb', evodent_doc_text($awb));
    $template->setValue('carrier_name', evodent_doc_text($carrier_name));

    $currency_symbol = html_entity_decode(
        get_woocommerce_currency_symbol($order->get_currency()),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $subtotal    = $currency_symbol . number_format((float) $order->get_subtotal(), 2);
    $ship_amount = $currency_symbol . number_format((float) $order->get_shipping_total(), 2);

    $template->setValue('subtotal', evodent_doc_text($subtotal));
    $template->setValue('ship_amount', evodent_doc_text($ship_amount));
    $template->setValue('grand_total', evodent_doc_text($currency_symbol . number_format((float) $order->get_total(), 2)));

    $template->setValue(
        'total_in_words',
        evodent_doc_text(evodent_number_to_words($order->get_total(), $order->get_currency()))
    );

    // Get all order items
    $items      = $order->get_items();
    $item_count = count($items);

    if ($item_count > 0) {
        $template->cloneRow('serial', $item_count);
        $i = 1;

        foreach ($items as $item) {
            $product = $item->get_product();

            $template->setValue("serial#{$i}", $i);
            $template->setValue("product_name#{$i}", evodent_doc_text($item->get_name()));
            $template->setValue("sku#{$i}", evodent_doc_text($product ? $product->get_sku() : ''));
            $template->setValue("quantity#{$i}", $item->get_quantity());

            $qty         = max(1, $item->get_quantity());
            $unit_price  = $item->get_total() / $qty;
            $unit_value  = $currency_symbol . number_format((float) $unit_price, 2);
            $total_value = $currency_symbol . number_format((float) $item->get_total(), 2);

            $template->setValue("unit_value#{$i}", evodent_doc_text($unit_value));
            $template->setValue("total_value#{$i}", evodent_doc_text($total_value));

            $i++;
        }
    }

    // Temporary file
    $temp_file = wp_tempnam('commercial-invoice.docx');
    $template->saveAs($temp_file);

    // Download
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="Commercial-Invoice-' . $order->get_order_number() . '.docx"');
    header('Content-Length: ' . filesize($temp_file));

    readfile($temp_file);
    @unlink($temp_file);
    exit;
}

//========================== Non-DG Certificate =====================================

add_action('admin_post_evodent_download_non_dg', 'evodent_download_non_dg');

function evodent_download_non_dg()
{
    check_admin_referer('evodent_download_non_dg');

    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if (! $order_id) {
        wp_die('Invalid Order ID.');
    }

    // Get WooCommerce order
    $order = wc_get_order($order_id);

    if (! $order) {
        wp_die('Order not found.');
    }

    // Template path
    $template_path = EVODENT_DOCS_PATH . 'templates/Evodent Non-DG Certificate.docx';

    if (! file_exists($template_path)) {
        wp_die('Template file not found.');
    }

    // Load template
    $template = new TemplateProcessor($template_path);

    $invoice_no = evodent_get_order_invoice_number($order);

    $date_created = $order->get_date_created();
    $order_date   = $date_created ? wc_format_datetime($date_created, 'd-F-Y') : date_i18n('d-F-Y');

    $cust = evodent_get_order_customer_details($order);

    $template->setValue('invoice_mo', $invoice_no);
    $template->setValue('invoice_no', $invoice_no);
    $template->setValue('invoice_date', $order_date);
    $template->setValue('certificate_date', $order_date);
    $template->setValue('consignee_name', $cust['name']);
    $template->setValue('destination_country', $cust['country_name']);
    $template->setValue('sign_date', $order_date);

    // Get all order items
    $items      = $order->get_items();
    $item_count = count($items);

    if ($item_count > 0) {
        $template->cloneRow('serial', $item_count);
        $i = 1;

        foreach ($items as $item) {
            $product = $item->get_product();

            $template->setValue("serial#{$i}", $i);
            $template->setValue("product_name#{$i}", evodent_doc_text($item->get_name()));
            $template->setValue("sku#{$i}", evodent_doc_text($product ? $product->get_sku() : ''));
            $template->setValue("quantity#{$i}", $item->get_quantity());

            $i++;
        }
    }

    // Temporary file
    $temp_file = wp_tempnam('Non-DG-Certificate.docx');
    $template->saveAs($temp_file);

    // Download
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="Non-DG-Certificate-' . $order->get_order_number() . '.docx"');
    header('Content-Length: ' . filesize($temp_file));

    readfile($temp_file);
    @unlink($temp_file);
    exit;
}

//=============================== Packing List ==========================================

add_action('admin_post_evodent_packing_list', 'evodent_packing_list');

function evodent_packing_list()
{
    check_admin_referer('evodent_packing_list');

    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

    if (! $order_id) {
        wp_die('Invalid Order ID.');
    }

    // Get WooCommerce order
    $order = wc_get_order($order_id);

    if (! $order) {
        wp_die('Order not found.');
    }

    // Template path
    $template_path = EVODENT_DOCS_PATH . 'templates/Evodent Packing List.docx';

    if (! file_exists($template_path)) {
        wp_die('Template file not found.');
    }

    // Load template
    $template = new TemplateProcessor($template_path);

    $invoice_no = evodent_get_order_invoice_number($order);

    $date_created = $order->get_date_created();
    $order_date   = $date_created ? wc_format_datetime($date_created, 'd-F-Y') : date_i18n('d-F-Y');

    $cust = evodent_get_order_customer_details($order);

    $template->setValue('invoice_no', $invoice_no);
    $template->setValue('packing_date', $order_date);
    $template->setValue('invoice_date', $order_date);

    $template->setValue('customer_name', $cust['name']);
    $template->setValue('destination_country', $cust['country_name']);
    $template->setValue('full_address', $cust['address']);
    $template->setValue('city', $cust['city']);
    $template->setValue('state', $cust['state']);
    $template->setValue('post', $cust['postcode']);
    $template->setValue('customer_email', evodent_doc_text($order->get_billing_email()));
    $template->setValue('customer_phone', $cust['phone']);

    // Get all order items
    $items      = $order->get_items();
    $item_count = count($items);

    $temp_images = array();

    if ($item_count > 0) {
        $template->cloneRow('serial', $item_count);
        $i = 1;

        foreach ($items as $item) {
            $product    = $item->get_product();
            $image_path = '';

            if ($product) {
                $image_id = $product->get_image_id();
                if (empty($image_id) && method_exists($product, 'is_type') && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id) {
                        $parent_product = wc_get_product($parent_id);
                        if ($parent_product) {
                            $image_id = $parent_product->get_image_id();
                        }
                    }
                }
                if ($image_id) {
                    $image_path = get_attached_file($image_id);
                }
            }

            if (! empty($image_path) && file_exists($image_path)) {
                $path_to_use = $image_path;

                if (
                    strtolower(pathinfo($image_path, PATHINFO_EXTENSION)) === 'webp' &&
                    function_exists('imagecreatefromwebp')
                ) {
                    $im = @imagecreatefromwebp($image_path);

                    if ($im) {
                        $tmp_png = wp_tempnam('packing-image.png');
                        imagepng($im, $tmp_png);
                        imagedestroy($im);
                        $temp_images[] = $tmp_png;
                        $path_to_use   = $tmp_png;
                    }
                }

                if (@getimagesize($path_to_use) !== false) {
                    try {
                        $template->setImageValue(
                            "image#{$i}",
                            array(
                                'path'   => $path_to_use,
                                'width'  => 50,
                                'height' => 50,
                            )
                        );
                    } catch (\Throwable $e) {
                        $template->setValue("image#{$i}", '');
                    }
                } else {
                    $template->setValue("image#{$i}", '');
                }
            } else {
                $template->setValue("image#{$i}", '');
            }

            $template->setValue("serial#{$i}", $i);
            $template->setValue("product_name#{$i}", evodent_doc_text($item->get_name()));
            $template->setValue("sku#{$i}", evodent_doc_text($product ? $product->get_sku() : ''));
            $template->setValue("nos#{$i}", $item->get_quantity());

            $i++;
        }
    }

    // Temporary file
    $temp_file = wp_tempnam('Packing-List.docx');

    $template->saveAs($temp_file);

    foreach ($temp_images as $temp_image) {
        if (file_exists($temp_image)) {
            @unlink($temp_image);
        }
    }

    // Download
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="Packing-List-' . $order->get_order_number() . '.docx"');
    header('Content-Length: ' . filesize($temp_file));

    readfile($temp_file);
    @unlink($temp_file);
    exit;
}
