<?php
/**
 * Lightweight test bootstrap: stubs the few WordPress functions the
 * pure-logic classes touch, so they can be unit-tested without a full WP install.
 *
 * @package Serpcheap\RankTracking
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
	define( 'SECURE_AUTH_KEY', 'unit-test-auth-key-abcdefghijklmnop' );
}
if ( ! defined( 'SECURE_AUTH_SALT' ) ) {
	define( 'SECURE_AUTH_SALT', 'unit-test-auth-salt-qrstuvwxyz012345' );
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) { // phpcs:ignore
		return $default;
	}
}

$inc = dirname( __DIR__ ) . '/includes/';
require_once $inc . 'class-credits.php';
require_once $inc . 'class-plugin.php';
require_once $inc . 'class-secret-store.php';
