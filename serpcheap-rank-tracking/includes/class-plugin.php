<?php
/**
 * Main plugin bootstrap / lightweight service container.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

use Serpcheap\RankTracking\Api\ClientInterface;
use Serpcheap\RankTracking\Api\HttpClient;
use Serpcheap\RankTracking\Api\MockClient;
use Serpcheap\RankTracking\Data\AlertsRepository;
use Serpcheap\RankTracking\Data\HistoryRepository;
use Serpcheap\RankTracking\Data\TargetResolver;
use Serpcheap\RankTracking\Data\TrackersRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var TrackersRepository */
	private $trackers;

	/** @var HistoryRepository */
	private $history;

	/** @var AlertsRepository */
	private $alerts;

	/** @var TargetResolver */
	private $resolver;

	/** @var ClientInterface */
	private $client;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->trackers = new TrackersRepository();
		$this->history  = new HistoryRepository();
		$this->alerts   = new AlertsRepository();
		$this->resolver = new TargetResolver();
		$this->client   = $this->make_client();
	}

	/** Wire up all hooks. */
	public function boot(): void {
		load_plugin_textdomain( 'serpcheap-rank-tracking', false, dirname( SERPCHEAP_RT_BASENAME ) . '/languages' );
		Activator::maybe_upgrade();

		( new Rest\RestTrackers( $this ) )->register();
		( new Cron\Scheduler() )->register();

		if ( is_admin() ) {
			( new Admin\AdminMenu( $this ) )->register();
			( new Admin\Metabox( $this ) )->register();
			( new Admin\TermFields( $this ) )->register();
			( new Admin\Columns( $this ) )->register();
			( new Admin\DashboardWidget( $this ) )->register();
			( new Admin\SettingsPage( $this ) )->register();
		}

		if ( class_exists( 'WooCommerce' ) ) {
			( new Woocommerce\WooIntegration( $this ) )->register();
		}
	}

	private function make_client(): ClientInterface {
		if ( defined( 'SERPCHEAP_MOCK' ) && SERPCHEAP_MOCK ) {
			return new MockClient();
		}
		$conn = self::connection();
		$base = defined( 'SERPCHEAP_API_URL' ) ? SERPCHEAP_API_URL : ( isset( $conn['base_url'] ) ? $conn['base_url'] : 'https://api.serp.cheap' );
		$key  = defined( 'SERPCHEAP_API_KEY' ) ? SERPCHEAP_API_KEY : ( isset( $conn['api_key'] ) ? $conn['api_key'] : '' );
		return new HttpClient( $base, $key );
	}

	public function trackers(): TrackersRepository {
		return $this->trackers;
	}

	public function history(): HistoryRepository {
		return $this->history;
	}

	public function alerts(): AlertsRepository {
		return $this->alerts;
	}

	public function resolver(): TargetResolver {
		return $this->resolver;
	}

	public function client(): ClientInterface {
		return $this->client;
	}

	/** Fully-qualified table name. */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'serpcheap_' . $name;
	}

	/** Connection metadata (demo: a fake connection). Never stores secrets in mock mode. */
	public static function connection(): array {
		$conn = get_option( 'serpcheap_connection', array() );
		return is_array( $conn ) ? $conn : array();
	}

	public static function is_connected(): bool {
		if ( defined( 'SERPCHEAP_API_KEY' ) && SERPCHEAP_API_KEY ) {
			return true;
		}
		$conn = self::connection();
		return ! empty( $conn['connected'] );
	}

	/** Allowed country codes (mirrors the API's gl enum). */
	public static function countries(): array {
		return array( 'us', 'br', 'gb', 'de', 'fr', 'es', 'it', 'mx', 'ca', 'au', 'jp', 'nl' );
	}

	/** Schedule presets: label => interval in minutes (0 = manual / no auto-run). */
	public static function schedule_presets(): array {
		return array(
			'hourly' => 60,
			'6h'     => 360,
			'12h'    => 720,
			'daily'  => 1440,
			'weekly' => 10080,
			'manual' => 0,
		);
	}

	/**
	 * Resolve an interval (minutes) from a schedule. A preset label maps to its
	 * minutes; 'custom' uses $custom_minutes (clamped 15m..28d).
	 */
	public static function interval_minutes( string $schedule, int $custom_minutes = 0 ): int {
		$presets = self::schedule_presets();
		if ( isset( $presets[ $schedule ] ) ) {
			return $presets[ $schedule ];
		}
		if ( 'custom' === $schedule && $custom_minutes > 0 ) {
			return max( 15, min( 40320, $custom_minutes ) );
		}
		return 1440;
	}
}
