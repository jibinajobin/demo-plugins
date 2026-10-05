<?php

if (! defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Evodent_Order_Documents_Admin
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_custom_meta_box'));
    }

    public function add_custom_meta_box()
    {

        $screen = 'shop_order';

        if (
            function_exists('wc_get_container') &&
            class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') &&
            wc_get_container()->has(CustomOrdersTableController::class) &&
            wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ) {
            $screen = wc_get_page_screen_id('shop-order');
        }

        add_meta_box(
            'evodent-documents',
            __('Evodent Documents', 'evodent'),
            array($this, 'documents_markup'),
            $screen,
            'side',
            'high'
        );
    }

    public function documents_markup($post_or_order)
    {
        if ($post_or_order instanceof WC_Order) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order($post_or_order->ID);
        }

        $order_id = $order->get_id();

        // We'll use $order_id in the download URLs

?>

        <table class="widefat striped evodent-documents-table" style="border:none;">
            <tbody>

                <tr>
                    <td>Commercial Invoice</td>
                    <td style="text-align:right;">
                        <a href="<?php echo esc_url(
                                        wp_nonce_url(
                                            admin_url(
                                                'admin-post.php?action=evodent_download_invoice&order_id=' . $order_id
                                            ),
                                            'evodent_download_invoice'
                                        )
                                    ); ?>" class="button button-small" title="Download">

                            <span class="dashicons dashicons-download"></span>

                        </a>
                    </td>
                </tr>

                <tr>
                    <td>Packing List</td>
                    <td style="text-align:right;">
                        <a href="<?php echo esc_url(
                                        wp_nonce_url(
                                            admin_url(
                                                'admin-post.php?action=evodent_packing_list&order_id=' . $order_id
                                            ),
                                            'evodent_packing_list'
                                        )
                                    ); ?>" class="button button-small" title="Download">
                            <span class="dashicons dashicons-download" style="font-size:16px; line-height:20px;"></span>
                        </a>
                    </td>
                </tr>

                <tr>
                    <td>Non-DG Certificate</td>
                    <td style="text-align:right;">
                        <a href="<?php echo esc_url(
                                        wp_nonce_url(
                                            admin_url(
                                                'admin-post.php?action=evodent_download_non_dg&order_id=' . $order_id
                                            ),
                                            'evodent_download_non_dg'
                                        )
                                    ); ?>" class="button button-small" title="Download">
                            <span class="dashicons dashicons-download" style="font-size:16px; line-height:20px;"></span>
                        </a>
                    </td>
                </tr>

            </tbody>
        </table>
        <h4>Upload Filled PDF Documents</h4>
        <input
            type="hidden"
            id="evodent_order_id"
            value="<?php echo esc_attr($order_id); ?>">
        <input
            type="hidden"
            id="evodent_order_number"
            value="<?php echo esc_attr($order->get_order_number()); ?>">
        <input
            type="file"
            id="evodent_documents"
            multiple
            accept=".pdf">

        <ul id="evodent_document_list"></ul>

        <button
            type="button"
            id="evodent_send_documents"
            class="button button-primary">

            Send Documents

        </button>

        <?php

        $sent_documents = get_post_meta($order_id, '_evodent_sent_documents', true);

        if (! empty($sent_documents)) : ?>

            <hr style="margin-top:20px;">

            <h4 style="margin-top:20px;">📤 Sent Documents</h4>

            <?php foreach ($sent_documents as $doc) : ?>

                <p style="margin:0 10px 12px;">
                    <a href="<?php echo esc_url($doc['url']); ?>" target="_blank">
                        📄 <?php echo esc_html($doc['file']); ?>
                    </a><br>

                    <small style="color:#666;">
                        Sent:
                        <?php echo esc_html(date_i18n('d M Y h:i A', strtotime($doc['sent']))); ?>
                    </small>
                </p>

            <?php endforeach; ?>

        <?php endif; ?>

<?php
    }
}

new Evodent_Order_Documents_Admin();
