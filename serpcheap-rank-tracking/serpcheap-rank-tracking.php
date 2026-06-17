<?php
/**
 * Plugin Name:       serp.cheap Rank Tracking
 * Plugin URI:        https://serp.cheap
 * Description:       Track where your posts, pages, products, categories and home page rank on Google — powered by the serp.cheap Google Search API. (Demo build: uses a mocked API so you can test the full UX offline.)
 * Version:           0.1.0-demo
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

define( 'SERPCHEAP_RT_VERSION', '0.1.0-demo' );
define( 'SERPCHEAP_RT_FILE', __FILE__ );
define( 'SERPCHEAP_RT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SERPCHEAP_RT_URL', plugin_dir_url( __FILE__ ) );
define( 'SERPCHEAP_RT_BASENAME', plugin_basename( __FILE__ ) );

// Demo/mock mode: forced on for this build. Set to false (or define SERPCHEAP_MOCK
// false in wp-config.php) once the real OAuth connect + live API are wired (Phase 2).
if ( ! defined( 'SERPCHEAP_MOCK' ) ) {
	define( 'SERPCHEAP_MOCK', true );
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
