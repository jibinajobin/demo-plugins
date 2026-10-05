<?php

/**
 * Plugin Name: WooCommerce Tally Export Demo
 * Description: Portfolio demo showing WooCommerce order-to-Tally Excel export and role-based admin access.
 * Version: 1.0
 * Author: Jibina
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'lib/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

/**
 * Add metabox to order edit page
 */
add_action('add_meta_boxes', 'portfolio_add_tally_metabox');

function portfolio_add_tally_metabox()
{
    add_meta_box(
        'portfolio_tally_export',
        'Tally Export',
        'portfolio_tally_export_box',
        'shop_order',
        'side',
        'high'
    );
}

/**
 * Normal order screen
 */
function portfolio_tally_export_box($post)
{
    portfolio_render_export_button($post->ID);
}



/**
 * Render button
 */
function portfolio_render_export_button($order_id)
{
    $url = wp_nonce_url(
        admin_url('admin-ajax.php?action=download_tally_excel&order_id=' . $order_id),
        'download_tally_excel'
    );

    echo '<a href="' . esc_url($url) . '" class="button button-primary" style="width:100%;text-align:center;background: #1b8533;">Export Tally Excel</a>';
}

/**
 * Download Excel
 */
add_action('wp_ajax_download_tally_excel', 'download_tally_excel');

function download_tally_excel()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You do not have permission to access this.');
    }
    $order_id = intval($_GET['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_die('Invalid Order');
    }

    $rows = [];

    // Header row
    $rows[] = [
        '<style bgcolor="#f2a944" font-size="12"><b>Voucher Date</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Voucher Type Name</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Voucher Number</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Buyer/Supplier - Address</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Buyer/Supplier - Pincode</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Ledger Name</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Ledger Amount</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Ledger Amount Dr/Cr</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Item Name</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Billed Quantity</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Item Rate</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Item Rate per</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Item Amount</b></style>',
        '<style bgcolor="#f2a944" font-size="12"><b>Change Mode</b></style>'
    ];

    // Order details
    $voucher_date = $order->get_date_created()->date('d-m-Y');

    // Billing state
    $state = $order->get_billing_state();

    // Financial year
    $financial_year = date('y') . '-' . (date('y') + 1);

    $invoice_number = $order->get_id();


    // Kerala / Outside Kerala logic
    if ($state === 'KL') {

        $voucher_type = 'GST SALES CREDIT';
        $voucher_number = 'GCR-' . $invoice_number . '/' . $financial_year;
        

    } else {

        $voucher_type = 'GST INTERSTATE SALES';
        $voucher_number = 'GI-' . $invoice_number . '-' . $financial_year;
    }

    $address = trim(
        $order->get_billing_address_1() . ', ' .
        $order->get_billing_city() . ', ' .
        $order->get_billing_state() . ', ' .
        $order->get_billing_country()
    );

    $pincode = $order->get_billing_postcode();
    $ledger_name = $order->get_formatted_billing_full_name();
    $ledger_amount = $order->get_total();

    foreach ($order->get_items() as $item) {

        $product_name = $item->get_name();
        $qty = $item->get_quantity();
        $line_total = $item->get_total();

        // Unit rate
        $rate = ($qty > 0) ? ($line_total / $qty) : 0;

        // Temporary GST %
        // Calculate GST %
$item_tax = $item->get_total_tax();

$gst_rate = 0;

if ($line_total > 0) {
    $gst_rate = round(($item_tax / $line_total) * 100);
}

$gst_rate = $gst_rate . '%';

        $rows[] = [
            '<style font-size="12">' . $voucher_date . '</style>',
            '<style font-size="12">' . $voucher_type . '</style>',
            '<style font-size="12">' . $voucher_number . '</style>',
            '<style font-size="12">' . $address . '</style>',
            '<style font-size="12">' . $pincode . '</style>',
            '<style font-size="12">' . $ledger_name . '</style>',
            '<style font-size="12">' . round($ledger_amount, 2) . '</style>',
            '<style font-size="12">Dr</style>',
            '<style font-size="12">' . $product_name . '</style>',
            '<style font-size="12">' . $qty . '</style>',
            '<style font-size="12">' . $gst_rate . '</style>',
            '<style font-size="12">' . round($rate, 2) . '</style>',
            '<style font-size="12">' . round($line_total, 2) . '</style>',
            '<style font-size="12"></style>'
        ];
    }

    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);

    $xlsx->downloadAs('tally-order-' . $order_id . '.xlsx');

    exit;
}



add_action('admin_footer', 'portfolio_move_tally_export');

function portfolio_move_tally_export()
{
    global $post;

    if (!$post || $post->post_type !== 'shop_order') {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {

        // Move Tally Export above Order Notes
        $('#portfolio_tally_export').insertBefore($('#woocommerce-order-notes'));

    });
    </script>
    <?php
}

// Allow Shop Manager access
add_action('admin_init', 'portfolio_tally_export_permissions');

function portfolio_tally_export_permissions()
{
    $role = get_role('shop_manager');

    if ($role && !$role->has_cap('manage_woocommerce')) {
        $role->add_cap('manage_woocommerce');
    }
}

/**
 * Create Orders Only Role
 */
add_action('init', 'portfolio_create_orders_role');

function portfolio_create_orders_role()
{
    remove_role('order_manager');

    add_role(
        'order_manager',
        'Order Manager',
        [
            'read' => true,
            'edit_posts' => true,

            // Order permissions
            'edit_shop_orders' => true,
            'edit_others_shop_orders' => true,
            'publish_shop_orders' => true,
            'read_private_shop_orders' => true,
            'delete_shop_orders' => false,

            // Required to open single order
            'read_shop_order' => true,
            'edit_shop_order' => true,

            // WooCommerce access
            'manage_woocommerce' => true,
        ]
    );
}


/**
 * Redirect Order Manager to Orders page
 */
add_action('admin_init', 'portfolio_orders_redirect');

function portfolio_orders_redirect()
{
    if (current_user_can('order_manager')) {

        global $pagenow;

        if ($pagenow === 'index.php') {
            wp_redirect(admin_url('admin.php?page=wc-orders'));
            exit;
        }
    }
}

/**
 * Hide all menus except Orders
 */
add_action('admin_menu', 'portfolio_hide_admin_menu', 999);

function portfolio_hide_admin_menu()
{
    if (!current_user_can('order_manager')) {
        return;
    }

    // Remove all menus
    remove_menu_page('index.php'); // Dashboard
    remove_menu_page('edit.php'); // Posts
    remove_menu_page('upload.php'); // Media
    remove_menu_page('edit.php?post_type=page'); // Pages
    remove_menu_page('themes.php'); // Appearance
    remove_menu_page('plugins.php'); // Plugins
    remove_menu_page('users.php'); // Users
    remove_menu_page('tools.php'); // Tools
    remove_menu_page('options-general.php'); // Settings
    remove_menu_page('woocommerce'); // WooCommerce
    remove_menu_page('wc-admin'); // Analytics
    remove_menu_page('marketing');
    remove_menu_page('edit.php?post_type=product'); // Products
    remove_menu_page('admin.php?page=wc-settings'); // Payments

    // Orders menu only
    add_menu_page(
        'Orders',
        'Orders',
        'read',
        'admin.php?page=wc-orders',
        '',
        'dashicons-cart',
        2
    );
}

/**
 * Hide admin menu for Order Manager
 */
add_action('admin_menu', 'portfolio_cleanup_order_manager_menu', 9999);

function portfolio_cleanup_order_manager_menu()
{
    if (!current_user_can('order_manager')) {
        return;
    }

    global $menu;

    // Allowed menus
    $allowed = [
        'profile.php',
        'admin.php?page=wc-orders'
    ];

    foreach ($menu as $key => $item) {

        if (!isset($item[2])) {
            continue;
        }

        if (!in_array($item[2], $allowed)) {
            remove_menu_page($item[2]);
        }
    }
}