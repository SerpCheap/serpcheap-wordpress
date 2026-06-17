<?php
/**
 * Admin menu, asset enqueue, and the central "Rank Tracking" management page.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Credits;
use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminMenu {

	/** @var Plugin */
	private $plugin;

	const MENU_SLUG     = 'serpcheap-rank-tracking';
	const SETTINGS_SLUG = 'serpcheap-rank-tracking-settings';

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_menu_page(
			__( 'serp.cheap Rank Tracking', 'serpcheap-rank-tracking' ),
			__( 'serp.cheap', 'serpcheap-rank-tracking' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_central' ),
			'dashicons-chart-line',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Rank Tracking', 'serpcheap-rank-tracking' ),
			__( 'Rank Tracking', 'serpcheap-rank-tracking' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_central' )
		);
	}

	public function assets( string $hook ): void {
		$is_central = ( 'toplevel_page_' . self::MENU_SLUG === $hook );
		if ( $is_central ) {
			$this->enqueue_app();
			return;
		}

		$screen     = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_editor  = $screen && 'post' === $screen->base;
		$is_term    = $screen && 'term' === $screen->base;
		$is_listing = $screen && 'edit' === $screen->base;

		if ( ! $is_editor && ! $is_term && ! $is_listing && 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'serpcheap-rt-admin',
			SERPCHEAP_RT_URL . 'assets/css/admin.css',
			array(),
			SERPCHEAP_RT_VERSION
		);
		wp_add_inline_style( 'serpcheap-rt-admin', $this->accent_css() );

		wp_enqueue_script(
			'serpcheap-rt-admin',
			SERPCHEAP_RT_URL . 'assets/js/metabox.js',
			array( 'wp-i18n' ),
			SERPCHEAP_RT_VERSION,
			true
		);

		wp_localize_script( 'serpcheap-rt-admin', 'serpcheapRT', $this->boot_data() );
	}

	/** Hex of the current user's admin color scheme accent (so the UI follows their theme). */
	private function admin_accent(): string {
		$map = array(
			'fresh'     => '#2271b1',
			'light'     => '#2271b1',
			'modern'    => '#3858e9',
			'blue'      => '#52accc',
			'midnight'  => '#e14d43',
			'sunrise'   => '#dd823b',
			'ectoplasm' => '#a3b745',
			'ocean'     => '#9ebaa0',
			'coffee'    => '#c7a589',
		);
		$scheme = (string) get_user_option( 'admin_color' );
		if ( isset( $map[ $scheme ] ) ) {
			return $map[ $scheme ];
		}
		global $_wp_admin_css_colors;
		if ( $scheme && isset( $_wp_admin_css_colors[ $scheme ]->colors[2] ) ) {
			return $_wp_admin_css_colors[ $scheme ]->colors[2];
		}
		return '#2271b1';
	}

	/** CSS vars derived from the admin accent, scoped to the plugin's surfaces. */
	private function accent_css(): string {
		$hex = ltrim( $this->admin_accent(), '#' );
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );
		$dark = sprintf( '#%02x%02x%02x', max( 0, $r - 28 ), max( 0, $g - 28 ), max( 0, $b - 28 ) );

		return sprintf(
			'.scrt-app,.serpcheap-rt-metabox,.serpcheap-rt-widget{--scrt-accent:#%s;--scrt-accent-d:%s;--scrt-accent-soft:rgba(%d,%d,%d,.12);--scrt-accent-soft2:rgba(%d,%d,%d,.22);}',
			$hex,
			$dark,
			$r, $g, $b,
			$r, $g, $b
		);
	}

	private function enqueue_app(): void {
		$asset_file = SERPCHEAP_RT_DIR . 'build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array( 'dependencies' => array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ), 'version' => SERPCHEAP_RT_VERSION );

		wp_enqueue_script(
			'serpcheap-rt-app',
			SERPCHEAP_RT_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$style_handle = '';
		foreach ( array( 'style-index.css', 'index.css' ) as $css ) {
			if ( file_exists( SERPCHEAP_RT_DIR . 'build/' . $css ) ) {
				$handle = 'serpcheap-rt-app' . ( 'index.css' === $css ? '-x' : '' );
				wp_enqueue_style( $handle, SERPCHEAP_RT_URL . 'build/' . $css, array(), $asset['version'] );
				if ( '' === $style_handle ) {
					$style_handle = $handle;
				}
			}
		}
		if ( $style_handle ) {
			wp_add_inline_style( $style_handle, $this->accent_css() );
		}

		wp_localize_script( 'serpcheap-rt-app', 'serpcheapRT', $this->boot_data() );
	}

	/** @return array<string,mixed> */
	private function boot_data(): array {
		return array(
			'root'       => esc_url_raw( rest_url( 'serpcheap/v1/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'countries'  => Plugin::countries(),
			'connected'  => Plugin::is_connected(),
			'mock'       => (bool) ( defined( 'SERPCHEAP_MOCK' ) && SERPCHEAP_MOCK ),
			'settingsUrl' => admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
			'pricing'    => Credits::model(),
			'scheduleMinutes' => Plugin::schedule_presets(),
			'schedules'  => array(
				'hourly' => __( 'Every hour', 'serpcheap-rank-tracking' ),
				'6h'     => __( 'Every 6 hours', 'serpcheap-rank-tracking' ),
				'12h'    => __( 'Every 12 hours', 'serpcheap-rank-tracking' ),
				'daily'  => __( 'Daily', 'serpcheap-rank-tracking' ),
				'weekly' => __( 'Weekly', 'serpcheap-rank-tracking' ),
				'manual' => __( 'Manual only', 'serpcheap-rank-tracking' ),
			),
			'i18n'       => array(
				'add'        => __( 'Add keyword', 'serpcheap-rank-tracking' ),
				'keyword'    => __( 'Keyword', 'serpcheap-rank-tracking' ),
				'domain'     => __( 'Domain', 'serpcheap-rank-tracking' ),
				'exact'      => __( 'Exact URL', 'serpcheap-rank-tracking' ),
				'refresh'    => __( 'Refresh', 'serpcheap-rank-tracking' ),
				'remove'     => __( 'Remove', 'serpcheap-rank-tracking' ),
				'notRanked'  => __( 'Not in top 100', 'serpcheap-rank-tracking' ),
				'checking'   => __( 'Checking…', 'serpcheap-rank-tracking' ),
				'noTrackers' => __( 'No keywords tracked yet. Add one above.', 'serpcheap-rank-tracking' ),
				'confirmDel' => __( 'Remove this tracker and its history?', 'serpcheap-rank-tracking' ),
				'schedule'   => __( 'Check', 'serpcheap-rank-tracking' ),
				'perCheck'   => __( 'cr/check', 'serpcheap-rank-tracking' ),
				'perMonth'   => __( 'cr/mo', 'serpcheap-rank-tracking' ),
				'onDemand'   => __( 'on demand', 'serpcheap-rank-tracking' ),
				'estCost'    => __( 'Est. cost', 'serpcheap-rank-tracking' ),
			),
		);
	}

	public function render_central(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap serpcheap-rt-app-wrap"><div id="serpcheap-rt-app"></div></div>';
	}
}
