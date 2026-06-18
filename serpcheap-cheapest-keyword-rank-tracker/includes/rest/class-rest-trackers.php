<?php
/**
 * REST API for trackers (metabox, block editor, central page).
 * Namespace: serpcheap/v1. All writes are capability + nonce gated.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Rest;

use Serpcheap\RankTracking\Api\MockClient;
use Serpcheap\RankTracking\Credits;
use Serpcheap\RankTracking\Cron\Runner;
use Serpcheap\RankTracking\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestTrackers {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'serpcheap/v1',
			'/trackers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'perm_index' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'perm_create' ),
				),
			)
		);

		register_rest_route(
			'serpcheap/v1',
			'/trackers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'perm_modify' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'perm_modify' ),
				),
			)
		);

		register_rest_route(
			'serpcheap/v1',
			'/trackers/(?P<id>\d+)/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh' ),
				'permission_callback' => array( $this, 'perm_modify' ),
			)
		);

		register_rest_route(
			'serpcheap/v1',
			'/alerts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'alerts_index' ),
				'permission_callback' => array( $this, 'perm_manage' ),
			)
		);

		register_rest_route(
			'serpcheap/v1',
			'/alerts/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'alerts_read' ),
				'permission_callback' => array( $this, 'perm_manage' ),
			)
		);
	}

	public function perm_manage() {
		return current_user_can( 'manage_options' );
	}

	public function alerts_index(): WP_REST_Response {
		$items = array();
		foreach ( $this->plugin->alerts()->recent( 30 ) as $a ) {
			$items[] = array(
				'id'         => (int) $a['id'],
				'type'       => $a['type'],
				'severity'   => $a['severity'],
				'message'    => $a['message'],
				'keyword'    => isset( $a['keyword'] ) ? $a['keyword'] : '',
				'old_rank'   => null !== $a['old_rank'] ? (int) $a['old_rank'] : null,
				'new_rank'   => null !== $a['new_rank'] ? (int) $a['new_rank'] : null,
				'is_read'    => (bool) $a['is_read'],
				'created_at' => $a['created_at'],
			);
		}
		return new WP_REST_Response(
			array(
				'items'  => $items,
				'unread' => $this->plugin->alerts()->unread_count(),
			),
			200
		);
	}

	public function alerts_read( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		if ( $id > 0 ) {
			$this->plugin->alerts()->mark_read( $id );
		} else {
			$this->plugin->alerts()->mark_all_read();
		}
		return new WP_REST_Response( array( 'unread' => $this->plugin->alerts()->unread_count() ), 200 );
	}

	/* ---------- permissions ---------- */

	public function perm_index( WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request->get_param( 'target_type' ) );
		if ( '' === $type ) {
			return current_user_can( 'manage_options' );
		}
		return $this->can_target( $type, (int) $request->get_param( 'target_ref' ) );
	}

	public function perm_create( WP_REST_Request $request ) {
		return $this->can_target(
			sanitize_key( (string) $request->get_param( 'target_type' ) ),
			(int) $request->get_param( 'target_ref' )
		);
	}

	public function perm_modify( WP_REST_Request $request ) {
		$tracker = $this->plugin->trackers()->find( (int) $request['id'] );
		if ( ! $tracker ) {
			return new WP_Error( 'not_found', __( 'Tracker not found.', 'serpcheap-cheapest-keyword-rank-tracker' ), array( 'status' => 404 ) );
		}
		return $this->can_target(
			$tracker['target_type'],
			isset( $tracker['target_ref'] ) ? (int) $tracker['target_ref'] : 0
		);
	}

	private function can_target( string $type, int $ref ) {
		if ( 'post' === $type ) {
			return $ref > 0 && current_user_can( 'edit_post', $ref );
		}
		return current_user_can( 'manage_options' );
	}

	/* ---------- handlers ---------- */

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$type = sanitize_key( (string) $request->get_param( 'target_type' ) );
		$ref  = (int) $request->get_param( 'target_ref' );
		$ref  = $ref > 0 ? $ref : null;

		$trackers = '' === $type
			? $this->plugin->trackers()->all()
			: $this->plugin->trackers()->for_target( $type, $ref );

		$ids = array();
		foreach ( $trackers as $t ) {
			$ids[] = (int) $t['id'];
		}
		$agg = $this->plugin->history()->aggregates_for( $ids );

		$out = array();
		foreach ( $trackers as $tracker ) {
			$out[] = $this->view( $tracker, isset( $agg[ (int) $tracker['id'] ] ) ? $agg[ (int) $tracker['id'] ] : null );
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function create( WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request->get_param( 'target_type' ) );
		if ( ! in_array( $type, array( 'post', 'term', 'home', 'url' ), true ) ) {
			return new WP_Error( 'bad_type', __( 'Invalid target type.', 'serpcheap-cheapest-keyword-rank-tracker' ), array( 'status' => 400 ) );
		}

		$keyword = sanitize_text_field( (string) $request->get_param( 'keyword' ) );
		if ( '' === $keyword || mb_strlen( $keyword ) > 255 ) {
			return new WP_Error( 'bad_keyword', __( 'A keyword (1–255 chars) is required.', 'serpcheap-cheapest-keyword-rank-tracker' ), array( 'status' => 400 ) );
		}

		$gl         = $this->one_of( sanitize_key( (string) $request->get_param( 'gl' ) ), Plugin::countries(), 'us' );
		$match_type = $this->one_of( sanitize_key( (string) $request->get_param( 'match_type' ) ), array( 'domain', 'exact' ), 'domain' );
		$schedules  = array_merge( array_keys( Plugin::schedule_presets() ), array( 'custom' ) );
		$schedule   = $this->one_of( sanitize_key( (string) $request->get_param( 'schedule' ) ), $schedules, get_option( 'serpcheap_default_schedule', 'daily' ) );
		$interval   = Plugin::interval_minutes( $schedule, (int) $request->get_param( 'interval_minutes' ) );
		$pages      = null !== $request->get_param( 'pages' ) ? Credits::clamp_pages( (int) $request->get_param( 'pages' ) ) : Credits::default_pages();

		$ref      = (int) $request->get_param( 'target_ref' );
		$ref      = $ref > 0 ? $ref : null;
		$taxonomy = $request->get_param( 'taxonomy' ) ? sanitize_key( (string) $request->get_param( 'taxonomy' ) ) : null;

		$fallback = 'url' === $type ? esc_url_raw( (string) $request->get_param( 'target_url' ) ) : null;
		$url      = $this->plugin->resolver()->resolve( $type, $ref, $taxonomy, $fallback );
		if ( ! $url ) {
			return new WP_Error( 'bad_target', __( 'Could not resolve a URL for this target.', 'serpcheap-cheapest-keyword-rank-tracker' ), array( 'status' => 400 ) );
		}

		$id = $this->plugin->trackers()->create(
			array(
				'target_type' => $type,
				'target_ref'  => $ref,
				'taxonomy'    => $taxonomy,
				'target_url'  => $url,
				'keyword'     => $keyword,
				'gl'          => $gl,
				'match_type'  => $match_type,
				'schedule'    => $schedule,
				'interval_minutes' => $interval,
				'pages'       => $pages,
			)
		);

		if ( ! $id ) {
			return new WP_Error( 'db_error', __( 'Could not save the tracker.', 'serpcheap-cheapest-keyword-rank-tracker' ), array( 'status' => 500 ) );
		}

		// Demo: seed believable history so the sparkline renders immediately.
		$client = $this->plugin->client();
		if ( $client instanceof MockClient ) {
			$this->plugin->history()->insert_many(
				$id,
				$client->history( $url, $keyword, array( 'gl' => $gl, 'match_type' => $match_type ), 14 )
			);
		}

		$tracker = $this->plugin->trackers()->find( $id );
		( new Runner( $this->plugin ) )->run( $tracker );

		return new WP_REST_Response( $this->view( $this->plugin->trackers()->find( $id ) ), 201 );
	}

	public function refresh( WP_REST_Request $request ): WP_REST_Response {
		$tracker = $this->plugin->trackers()->find( (int) $request['id'] );
		( new Runner( $this->plugin ) )->run( $tracker );
		return new WP_REST_Response( $this->view( $this->plugin->trackers()->find( (int) $request['id'] ) ), 200 );
	}

	public function update( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( null !== $request->get_param( 'schedule' ) ) {
			$schedules = array_merge( array_keys( Plugin::schedule_presets() ), array( 'custom' ) );
			$schedule  = $this->one_of( sanitize_key( (string) $request->get_param( 'schedule' ) ), $schedules, 'daily' );
			$interval  = Plugin::interval_minutes( $schedule, (int) $request->get_param( 'interval_minutes' ) );
			$this->plugin->trackers()->update_schedule( $id, $schedule, $interval );
		}

		if ( null !== $request->get_param( 'pages' ) ) {
			$this->plugin->trackers()->update_pages( $id, (int) $request->get_param( 'pages' ) );
		}

		return new WP_REST_Response( $this->view( $this->plugin->trackers()->find( $id ) ), 200 );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$this->plugin->trackers()->delete( (int) $request['id'] );
		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/* ---------- helpers ---------- */

	/**
	 * @param array<string,mixed> $tracker
	 * @param array<string,mixed>|null $agg precomputed aggregates (batched list path)
	 * @return array<string,mixed>
	 */
	private function view( array $tracker, ?array $agg = null ): array {
		$id   = (int) $tracker['id'];
		$rank = isset( $tracker['current_rank'] ) && null !== $tracker['current_rank'] ? (int) $tracker['current_rank'] : null;

		if ( null === $agg ) {
			$history = $this->plugin->history()->recent( $id, 30 );
			$spark   = array();
			$points  = array();
			foreach ( $history as $h ) {
				$r        = null !== $h['rank'] ? (int) $h['rank'] : null;
				$spark[]  = $r;
				$points[] = array(
					'date' => isset( $h['checked_at'] ) ? gmdate( 'Y-m-d', strtotime( $h['checked_at'] ) ) : '',
					'rank' => $r,
				);
			}
			$delta    = $this->plugin->history()->delta_7d( $id, $rank );
			$balance  = $this->plugin->history()->latest_balance( $id );
			$cost_avg = null;
		} else {
			$spark    = $agg['sparkline'];
			$points   = $agg['points'];
			$balance  = $agg['balance'];
			$cost_avg = $agg['cost_avg'];
			$delta    = ( null !== $rank && null !== $agg['delta_baseline'] ) ? $rank - (int) $agg['delta_baseline'] : null;
		}

		$interval = isset( $tracker['interval_minutes'] ) ? (int) $tracker['interval_minutes'] : Plugin::interval_minutes( $tracker['schedule'] );
		$pages    = isset( $tracker['pages'] ) ? Credits::clamp_pages( (int) $tracker['pages'] ) : Credits::default_pages();
		unset( $cost_avg );
		// Forward-looking projection: reflect the current depth/schedule immediately
		// (worst-case fresh), so a depth change updates the number right away.
		$cost_check   = Credits::per_check( $pages );
		$monthly_cost = Credits::monthly( $interval, null, $pages );

		return array(
			'id'               => $id,
			'keyword'          => $tracker['keyword'],
			'gl'               => $tracker['gl'],
			'match_type'       => $tracker['match_type'],
			'schedule'         => $tracker['schedule'],
			'interval_minutes' => $interval,
			'pages'            => $pages,
			'next_run'         => $tracker['next_run'],
			'target_type'      => $tracker['target_type'],
			'target_label'     => $this->plugin->resolver()->label(
				$tracker['target_type'],
				isset( $tracker['target_ref'] ) ? (int) $tracker['target_ref'] : null,
				isset( $tracker['taxonomy'] ) ? $tracker['taxonomy'] : null,
				$tracker['target_url']
			),
			'target_url'       => $tracker['target_url'],
			'rank'             => $rank,
			'found'            => null !== $rank,
			'delta_7d'         => $delta,
			'last_checked'     => $tracker['last_checked'],
			'sparkline'        => $spark,
			'history'          => $points,
			'balance'          => $balance,
			'cost_per_check'   => $cost_check,
			'monthly_cost'     => $monthly_cost,
		);
	}

	/**
	 * @param array<int,string> $allowed
	 */
	private function one_of( string $value, array $allowed, string $default ): string {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
