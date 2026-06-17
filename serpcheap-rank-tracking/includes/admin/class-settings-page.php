<?php
/**
 * Settings page: (demo) connect/disconnect + default schedule + disclosure.
 * Real OAuth connect replaces the demo button in Phase 2.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Alerts;
use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_serpcheap_connect_demo', array( $this, 'connect_demo' ) );
		add_action( 'admin_post_serpcheap_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_serpcheap_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_serpcheap_save_alerts', array( $this, 'save_alerts' ) );
	}

	public function menu(): void {
		add_submenu_page(
			AdminMenu::MENU_SLUG,
			__( 'Settings', 'serpcheap-rank-tracking' ),
			__( 'Settings', 'serpcheap-rank-tracking' ),
			'manage_options',
			AdminMenu::SETTINGS_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$conn      = Plugin::connection();
		$connected = ! empty( $conn['connected'] );
		$schedule  = get_option( 'serpcheap_default_schedule', 'daily' );

		echo '<div class="wrap serpcheap-rt-wrap"><h1>' . esc_html__( 'serp.cheap Rank Tracking — Settings', 'serpcheap-rank-tracking' ) . '</h1>';

		// Connection card.
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Connection', 'serpcheap-rank-tracking' ) . '</h2>';
		if ( $connected ) {
			printf(
				'<p>%s <code>%s</code>%s</p>',
				esc_html__( 'Connected', 'serpcheap-rank-tracking' ),
				esc_html( isset( $conn['host'] ) ? $conn['host'] : wp_parse_url( home_url(), PHP_URL_HOST ) ),
				! empty( $conn['demo'] ) ? ' <span class="serpcheap-badge">' . esc_html__( 'demo', 'serpcheap-rank-tracking' ) . '</span>' : ''
			);
			if ( isset( $conn['balance'] ) ) {
				printf( '<p>%s <strong>%s</strong></p>', esc_html__( 'Demo credits:', 'serpcheap-rank-tracking' ), esc_html( number_format_i18n( (int) $conn['balance'] ) ) );
			}
			$this->button_form( 'serpcheap_disconnect', __( 'Disconnect', 'serpcheap-rank-tracking' ), 'button' );
		} else {
			echo '<p>' . esc_html__( 'Connect your serp.cheap account to start tracking rankings. (This demo build uses a mocked connection — no real account or key is created.)', 'serpcheap-rank-tracking' ) . '</p>';
			$this->button_form( 'serpcheap_connect_demo', __( 'Connect (Demo)', 'serpcheap-rank-tracking' ), 'button button-primary' );
		}
		echo '</div>';

		// Defaults card.
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Defaults', 'serpcheap-rank-tracking' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="serpcheap_save_settings" />';
		wp_nonce_field( 'serpcheap_save_settings' );
		echo '<table class="form-table"><tr><th scope="row"><label for="serpcheap-schedule">' . esc_html__( 'Default refresh', 'serpcheap-rank-tracking' ) . '</label></th><td>';
		echo '<select id="serpcheap-schedule" name="default_schedule">';
		foreach ( array( 'daily' => __( 'Daily', 'serpcheap-rank-tracking' ), 'weekly' => __( 'Weekly', 'serpcheap-rank-tracking' ), 'manual' => __( 'Manual only', 'serpcheap-rank-tracking' ) ) as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $schedule, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Each refresh consumes serp.cheap credits (one rank check per keyword).', 'serpcheap-rank-tracking' ) . '</p>';
		echo '</td></tr>';

		$pages = (int) get_option( 'serpcheap_default_pages', 1 );
		echo '<tr><th scope="row"><label for="serpcheap-pages">' . esc_html__( 'Default search depth', 'serpcheap-rank-tracking' ) . '</label></th><td>';
		echo '<select id="serpcheap-pages" name="default_pages">';
		for ( $i = 1; $i <= 10; $i++ ) {
			printf(
				'<option value="%d"%s>%s</option>',
				$i,
				selected( $pages, $i, false ),
				/* translators: 1: top N positions, 2: page count, 3: credits per check */
				esc_html( sprintf( __( 'Top %1$d (%2$d page(s)) — ~%3$d credits/check', 'serpcheap-rank-tracking' ), $i * 10, $i, $i * 6 ) )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How deep to scan Google. More pages find lower-ranked positions but cost more per check. Each tracker can override this.', 'serpcheap-rank-tracking' ) . '</p>';
		echo '</td></tr></table>';
		submit_button( __( 'Save', 'serpcheap-rank-tracking' ) );
		echo '</form>';
		echo '</div>';

		// Alerts card.
		$a = Alerts::settings();
		echo '<div class="card">';
		echo '<h2>' . esc_html__( 'Alerts', 'serpcheap-rank-tracking' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="serpcheap_save_alerts" />';
		wp_nonce_field( 'serpcheap_save_alerts' );
		echo '<table class="form-table">';

		$this->checkbox_row( 'enabled', __( 'Enable alerts', 'serpcheap-rank-tracking' ), __( 'Record alerts when rankings change.', 'serpcheap-rank-tracking' ), ! empty( $a['enabled'] ) );

		echo '<tr><th scope="row"><label for="serpcheap-drop">' . esc_html__( 'Rank-drop threshold', 'serpcheap-rank-tracking' ) . '</label></th><td>';
		printf( '<input type="number" min="0" max="100" id="serpcheap-drop" name="drop_threshold" value="%d" class="small-text" /> ', (int) $a['drop_threshold'] );
		echo '<span class="description">' . esc_html__( 'positions (0 disables drop alerts)', 'serpcheap-rank-tracking' ) . '</span></td></tr>';

		$this->checkbox_row( 'lost_top10', __( 'Left top 10', 'serpcheap-rank-tracking' ), __( 'Alert when a keyword falls out of the top 10.', 'serpcheap-rank-tracking' ), ! empty( $a['lost_top10'] ) );
		$this->checkbox_row( 'lost_top3', __( 'Left top 3', 'serpcheap-rank-tracking' ), __( 'Alert when a keyword falls out of the top 3.', 'serpcheap-rank-tracking' ), ! empty( $a['lost_top3'] ) );
		$this->checkbox_row( 'became_not_found', __( 'Dropped out of results', 'serpcheap-rank-tracking' ), __( 'Alert when a keyword is no longer found.', 'serpcheap-rank-tracking' ), ! empty( $a['became_not_found'] ) );
		$this->checkbox_row( 'recovered', __( 'Recovered into top 10', 'serpcheap-rank-tracking' ), __( 'Positive alert when a keyword climbs back into the top 10.', 'serpcheap-rank-tracking' ), ! empty( $a['recovered'] ) );
		$this->checkbox_row( 'email_enabled', __( 'Email me alerts', 'serpcheap-rank-tracking' ), __( 'Send an email when an alert fires.', 'serpcheap-rank-tracking' ), ! empty( $a['email_enabled'] ) );

		echo '<tr><th scope="row"><label for="serpcheap-email">' . esc_html__( 'Send to', 'serpcheap-rank-tracking' ) . '</label></th><td>';
		printf( '<input type="email" id="serpcheap-email" name="email_to" value="%s" class="regular-text" /></td></tr>', esc_attr( $a['email_to'] ) );

		echo '</table>';
		submit_button( __( 'Save alerts', 'serpcheap-rank-tracking' ) );
		echo '</form></div>';

		// Disclosure (required by wp.org for SaaS-backed plugins).
		echo '<div class="card"><h2>' . esc_html__( 'About this plugin', 'serpcheap-rank-tracking' ) . '</h2>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: 1: terms link, 2: privacy link */
				__( 'This plugin connects to the paid <a href="%1$s" target="_blank" rel="noopener">serp.cheap</a> Google Search API to fetch rankings. The page URL, keyword and country you track are sent to api.serp.cheap. See the <a href="%2$s" target="_blank" rel="noopener">Terms</a> and Privacy Policy.', 'serpcheap-rank-tracking' ),
				'https://serp.cheap',
				'https://serp.cheap/terms'
			)
		) . '</p></div>';

		echo '</div>';
	}

	private function checkbox_row( string $name, string $label, string $desc, bool $checked ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label>';
		printf( '<input type="checkbox" name="%s" value="1"%s /> ', esc_attr( $name ), checked( $checked, true, false ) );
		echo '<span class="description">' . esc_html( $desc ) . '</span></label></td></tr>';
	}

	public function save_alerts(): void {
		$this->guard( 'serpcheap_save_alerts' );
		$post     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verifies.
		$email_to = isset( $post['email_to'] ) ? sanitize_email( $post['email_to'] ) : '';
		Alerts::save(
			array(
				'enabled'          => ! empty( $post['enabled'] ),
				'drop_threshold'   => isset( $post['drop_threshold'] ) ? max( 0, min( 100, (int) $post['drop_threshold'] ) ) : 5,
				'lost_top10'       => ! empty( $post['lost_top10'] ),
				'lost_top3'        => ! empty( $post['lost_top3'] ),
				'became_not_found' => ! empty( $post['became_not_found'] ),
				'recovered'        => ! empty( $post['recovered'] ),
				'email_enabled'    => ! empty( $post['email_enabled'] ),
				'email_to'         => $email_to ? $email_to : get_option( 'admin_email' ),
			)
		);
		$this->redirect_back( 'saved' );
	}

	private function button_form( string $action, string $label, string $class ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
		wp_nonce_field( $action );
		printf( '<button type="submit" class="%s">%s</button>', esc_attr( $class ), esc_html( $label ) );
		echo '</form>';
	}

	public function connect_demo(): void {
		$this->guard( 'serpcheap_connect_demo' );
		update_option(
			'serpcheap_connection',
			array(
				'connected'    => true,
				'demo'         => true,
				'host'         => wp_parse_url( home_url(), PHP_URL_HOST ),
				'balance'      => 100000,
				'connected_at' => current_time( 'mysql', true ),
			)
		);
		$this->redirect_back( 'connected' );
	}

	public function disconnect(): void {
		$this->guard( 'serpcheap_disconnect' );
		delete_option( 'serpcheap_connection' );
		$this->redirect_back( 'disconnected' );
	}

	public function save_settings(): void {
		$this->guard( 'serpcheap_save_settings' );
		$schedule = isset( $_POST['default_schedule'] ) ? sanitize_key( wp_unslash( $_POST['default_schedule'] ) ) : 'daily';
		if ( ! in_array( $schedule, array( 'daily', 'weekly', 'manual' ), true ) ) {
			$schedule = 'daily';
		}
		update_option( 'serpcheap_default_schedule', $schedule );

		$pages = isset( $_POST['default_pages'] ) ? (int) $_POST['default_pages'] : 1;
		update_option( 'serpcheap_default_pages', max( 1, min( 10, $pages ) ) );

		$this->redirect_back( 'saved' );
	}

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'serpcheap-rank-tracking' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private function redirect_back( string $status ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => AdminMenu::SETTINGS_SLUG,
					'serpcheap_status' => $status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
