<?php
/**
 * Configurable alerting: evaluates rank changes after each check and records
 * alerts (+ optional email). Rules live in the serpcheap_alert_settings option.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Alerts {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		$defaults = array(
			'enabled'         => true,
			'drop_threshold'  => 5,
			'lost_top10'      => true,
			'lost_top3'       => false,
			'became_not_found'=> true,
			'recovered'       => true,
			'email_enabled'   => false,
			'email_to'        => get_option( 'admin_email' ),
		);
		$saved = get_option( 'serpcheap_alert_settings', array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function save( array $settings ): void {
		update_option( 'serpcheap_alert_settings', $settings );
	}

	/**
	 * Evaluate one check. $old_rank/$new_rank are positions (null = not found).
	 *
	 * @param array<string,mixed> $tracker
	 */
	public function evaluate( array $tracker, ?int $old_rank, ?int $new_rank ): void {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}

		$events = $this->detect( $s, $old_rank, $new_rank );
		if ( ! $events ) {
			return;
		}

		$keyword = $tracker['keyword'];
		$repo    = $this->plugin->alerts();
		foreach ( $events as $e ) {
			$repo->create(
				array(
					'tracker_id' => (int) $tracker['id'],
					'type'       => $e['type'],
					'severity'   => $e['severity'],
					'old_rank'   => $old_rank,
					'new_rank'   => $new_rank,
					'message'    => $e['message'],
				)
			);
		}

		if ( ! empty( $s['email_enabled'] ) && ! empty( $s['email_to'] ) ) {
			$this->email( $s['email_to'], $keyword, $events );
		}
	}

	/**
	 * @param array<string,mixed> $s
	 * @return array<int,array<string,string>>
	 */
	private function detect( array $s, ?int $old, ?int $new ): array {
		$out = array();

		if ( null !== $old && null === $new && ! empty( $s['became_not_found'] ) ) {
			$out[] = array(
				'type'     => 'lost',
				'severity' => 'critical',
				'message'  => sprintf(
					/* translators: %d: previous rank */
					__( 'Dropped out of results (was #%d).', 'serpcheap-cheapest-keyword-rank-tracker' ),
					$old
				),
			);
			return $out;
		}

		if ( null === $old || null === $new ) {
			if ( null !== $new && null === $old && ! empty( $s['recovered'] ) && $new <= 10 ) {
				$out[] = array(
					'type'     => 'recovered',
					'severity' => 'success',
					'message'  => sprintf(
						/* translators: %d: new rank */
						__( 'Now ranking at #%d.', 'serpcheap-cheapest-keyword-rank-tracker' ),
						$new
					),
				);
			}
			return $out;
		}

		$delta = $new - $old; // positive = worse.

		if ( $delta >= (int) $s['drop_threshold'] && (int) $s['drop_threshold'] > 0 ) {
			$out[] = array(
				'type'     => 'drop',
				'severity' => $new > 10 ? 'critical' : 'warning',
				'message'  => sprintf(
					/* translators: 1: positions dropped, 2: old rank, 3: new rank */
					__( 'Fell %1$d positions (#%2$d → #%3$d).', 'serpcheap-cheapest-keyword-rank-tracker' ),
					$delta,
					$old,
					$new
				),
			);
		}

		if ( ! empty( $s['lost_top3'] ) && $old <= 3 && $new > 3 ) {
			$out[] = array(
				'type'     => 'lost_top3',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: new rank */
					__( 'Left the top 3 (now #%d).', 'serpcheap-cheapest-keyword-rank-tracker' ),
					$new
				),
			);
		} elseif ( ! empty( $s['lost_top10'] ) && $old <= 10 && $new > 10 ) {
			$out[] = array(
				'type'     => 'lost_top10',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: new rank */
					__( 'Left the top 10 (now #%d).', 'serpcheap-cheapest-keyword-rank-tracker' ),
					$new
				),
			);
		}

		if ( ! empty( $s['recovered'] ) && $old > 10 && $new <= 10 ) {
			$out[] = array(
				'type'     => 'recovered',
				'severity' => 'success',
				'message'  => sprintf(
					/* translators: 1: old rank, 2: new rank */
					__( 'Back in the top 10 (#%1$d → #%2$d).', 'serpcheap-cheapest-keyword-rank-tracker' ),
					$old,
					$new
				),
			);
		}

		return $out;
	}

	/**
	 * @param array<int,array<string,string>> $events
	 */
	private function email( string $to, string $keyword, array $events ): void {
		$lines = array();
		foreach ( $events as $e ) {
			$lines[] = '• ' . wp_strip_all_tags( $e['message'] );
		}
		$subject = sprintf(
			/* translators: %s: keyword */
			__( '[serp.cheap] Rank alert for "%s"', 'serpcheap-cheapest-keyword-rank-tracker' ),
			$keyword
		);
		$body = sprintf(
			/* translators: %s: keyword */
			__( 'Keyword: %s', 'serpcheap-cheapest-keyword-rank-tracker' ),
			$keyword
		) . "\n\n" . implode( "\n", $lines ) . "\n";

		wp_mail( $to, $subject, $body );
	}
}
