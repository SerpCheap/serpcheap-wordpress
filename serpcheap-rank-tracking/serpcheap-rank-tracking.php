<?php
/**
 * Plugin Name:       serp.cheap Rank Tracking
 * Plugin URI:        https://serp.cheap
 * Description:       Track where your posts, pages, products, categories and home page rank on Google — powered by the serp.cheap Google Search API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            serp.cheap
 * Author URI:        https://serp.cheap
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       serpcheap-rank-tracking
 * Domain Path:       /languages
 *
 * @package Serpcheap\RankTracking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SERPCHEAP_RT_VERSION', '1.0.0' );
define( 'SERPCHEAP_RT_FILE', __FILE__ );
define( 'SERPCHEAP_RT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SERPCHEAP_RT_URL', plugin_dir_url( __FILE__ ) );
define( 'SERPCHEAP_RT_BASENAME', plugin_basename( __FILE__ ) );

// serp.cheap account/OAuth host (the "Connect" flow). Override in wp-config.php
// for staging.
if ( ! defined( 'SERPCHEAP_APP_URL' ) ) {
	define( 'SERPCHEAP_APP_URL', 'https://app.serp.cheap' );
}

// Offline demo mode (developer-only): when explicitly enabled in wp-config.php,
// and only while there is no real connection, the plugin serves mock ranks so the
// UX can be explored without an account. OFF by default — a real OAuth connection
// always takes precedence regardless (see Plugin::make_client).
if ( ! defined( 'SERPCHEAP_MOCK' ) ) {
	define( 'SERPCHEAP_MOCK', false );
}

/**
 * Minimal PSR-4-ish autoloader. Serpcheap\RankTracking\Api\MockClient
 * → includes/api/class-mock-client.php
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Serpcheap\\RankTracking\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$kebab    = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) );
		$sub      = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$path     = SERPCHEAP_RT_DIR . 'includes/' . $sub . 'class-' . $kebab . '.php';
		if ( is_file( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( \Serpcheap\RankTracking\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Serpcheap\RankTracking\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		\Serpcheap\RankTracking\Plugin::instance()->boot();
	}
);
