<?php
/**
 * Evodent Quote Roles and Capabilities
 *
 * Handles creation of the Quote Staff user role, capability assignment,
 * and WooCommerce admin access / login redirects for Quote Staff users.
 *
 * @package Evodent_Order_Documents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register or update the Quote Staff user role and grant required capabilities.
 */
function evodent_create_quote_staff_role() {
	if ( ! function_exists( 'get_role' ) ) {
		return;
	}

	$role = get_role( 'quote_staff' );
	if ( ! $role ) {
		add_role(
			'quote_staff',
			__( 'Quote Staff', 'evodent' ),
			array(
				'read'                         => true,
				'evodent_generate_quote'       => true,
				'edit_shop_orders'             => true,
				'edit_others_shop_orders'      => true,
				'edit_published_shop_orders'   => true,
				'edit_private_shop_orders'     => true,
				'read_shop_order'              => true,
				'read_private_shop_orders'     => true,
				'publish_shop_orders'          => true,
				'delete_shop_orders'           => true,
				'delete_others_shop_orders'    => true,
				'delete_published_shop_orders' => true,
				'delete_private_shop_orders'   => true,
			)
		);
	} else {
		$role->add_cap( 'read' );
		$role->add_cap( 'evodent_generate_quote' );
		// WooCommerce order management
		$role->add_cap( 'edit_shop_orders' );
		$role->add_cap( 'edit_others_shop_orders' );
		$role->add_cap( 'edit_published_shop_orders' );
		$role->add_cap( 'edit_private_shop_orders' );
		$role->add_cap( 'read_shop_order' );
		$role->add_cap( 'read_private_shop_orders' );
		$role->add_cap( 'publish_shop_orders' );
		$role->add_cap( 'delete_shop_orders' );
		$role->add_cap( 'delete_others_shop_orders' );
		$role->add_cap( 'delete_published_shop_orders' );
		$role->add_cap( 'delete_private_shop_orders' );
	}

	// Grant administrators capability to generate quotes as well
	$admin_role = get_role( 'administrator' );
	if ( $admin_role && ! $admin_role->has_cap( 'evodent_generate_quote' ) ) {
		$admin_role->add_cap( 'evodent_generate_quote' );
	}
}

// Ensure role creation/update runs on init hook
add_action( 'init', 'evodent_create_quote_staff_role' );

/**
 * Prevent WooCommerce from blocking wp-admin access for Quote Staff users.
 *
 * @param bool $prevent_access Whether access is prevented.
 * @return bool
 */
function evodent_allow_quote_staff_admin_access( $prevent_access ) {
	if ( current_user_can( 'evodent_generate_quote' ) || current_user_can( 'quote_staff' ) ) {
		return false;
	}
	return $prevent_access;
}
add_filter( 'woocommerce_prevent_admin_access', 'evodent_allow_quote_staff_admin_access', 10, 1 );

/**
 * Redirect Quote Staff users to the Generate Quote admin page upon login.
 *
 * @param string           $redirect_to Target URL.
 * @param string           $request     Requested URL.
 * @param WP_User|WP_Error $user        Logged in user object.
 * @return string
 */
function evodent_quote_staff_login_redirect( $redirect_to, $request, $user ) {
	if ( is_a( $user, 'WP_User' ) ) {
		$user_roles = (array) $user->roles;
		if ( in_array( 'quote_staff', $user_roles, true ) || ( ! empty( $user->allcaps['evodent_generate_quote'] ) && ! in_array( 'administrator', $user_roles, true ) ) ) {
			return admin_url( 'admin.php?page=evodent-generate-quote' );
		}
	}
	return $redirect_to;
}
add_filter( 'login_redirect', 'evodent_quote_staff_login_redirect', 10, 3 );

/**
 * Redirect Quote Staff users to Generate Quote admin page upon WooCommerce login.
 *
 * @param string  $redirect Target URL.
 * @param WP_User $user     Logged in user object.
 * @return string
 */
function evodent_quote_staff_wc_login_redirect( $redirect, $user ) {
	if ( is_a( $user, 'WP_User' ) ) {
		$user_roles = (array) $user->roles;
		if ( in_array( 'quote_staff', $user_roles, true ) || ( ! empty( $user->allcaps['evodent_generate_quote'] ) && ! in_array( 'administrator', $user_roles, true ) ) ) {
			return admin_url( 'admin.php?page=evodent-generate-quote' );
		}
	}
	return $redirect;
}
add_filter( 'woocommerce_login_redirect', 'evodent_quote_staff_wc_login_redirect', 10, 2 );
