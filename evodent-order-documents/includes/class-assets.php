<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Evodent_Order_Documents_Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {

        // Only load on WooCommerce order edit page
        if ( $hook !== 'post.php' && $hook !== 'woocommerce_page_wc-orders' ) {
            return;
        }

        wp_enqueue_script(
            'evodent-admin',
            EVODENT_DOCS_URL . 'assets/admin.js',
            array( 'jquery' ),
            time(),
            true
        );

        wp_localize_script(
            'evodent-admin',
            'evodentDocs',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'evodent_documents' ),
            )
        );
    }
}

new Evodent_Order_Documents_Assets();