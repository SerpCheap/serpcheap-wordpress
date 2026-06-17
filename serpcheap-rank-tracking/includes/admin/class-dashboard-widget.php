<?php
/**
 * WP dashboard widget: rank-tracking summary.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardWidget {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
	}

	public function add(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'serpcheap_rank_widget',
			__( 'serp.cheap — Rank Tracking', 'serpcheap-rank-tracking' ),
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$trackers = $this->plugin->trackers()->all();
		$total    = count( $trackers );

		$ids = array();
		foreach ( $trackers as $t ) {
			$ids[] = (int) $t['id'];
		}
		$agg = $this->plugin->history()->aggregates_for( $ids );

		$ranked    = array();
		$not_found = 0;
		$movers    = array();
		$monthly   = 0;
		$balance   = null;
		foreach ( $trackers as $t ) {
			$id   = (int) $t['id'];
			$rank = null !== $t['current_rank'] ? (int) $t['current_rank'] : null;
			$a    = isset( $agg[ $id ] ) ? $agg[ $id ] : array( 'delta_baseline' => null, 'cost_avg' => null, 'balance' => null );

			if ( null === $rank ) {
				++$not_found;
			} else {
				$ranked[] = $rank;
			}

			$interval = isset( $t['interval_minutes'] ) ? (int) $t['interval_minutes'] : \Serpcheap\RankTracking\Plugin::interval_minutes( $t['schedule'] );
			$pages    = isset( $t['pages'] ) ? \Serpcheap\RankTracking\Credits::clamp_pages( (int) $t['pages'] ) : \Serpcheap\RankTracking\Credits::default_pages();
			$monthly += \Serpcheap\RankTracking\Credits::monthly( $interval, null, $pages );

			if ( null !== $a['balance'] && (int) $a['balance'] > (int) $balance ) {
				$balance = (int) $a['balance'];
			}

			$delta = ( null !== $rank && null !== $a['delta_baseline'] ) ? $rank - (int) $a['delta_baseline'] : null;
			if ( null !== $delta && 0 !== $delta ) {
				$movers[] = array( 'keyword' => $t['keyword'], 'delta' => (int) $delta );
			}
		}

		$avg = $ranked ? round( array_sum( $ranked ) / count( $ranked ), 1 ) : null;

		usort(
			$movers,
			static function ( $a, $b ) {
				return abs( $b['delta'] ) <=> abs( $a['delta'] );
			}
		);
		$movers = array_slice( $movers, 0, 4 );

		echo '<div class="serpcheap-rt-widget">';

		echo '<div class="serpcheap-w-stats">';
		$this->stat( (string) (int) $total, __( 'Tracked', 'serpcheap-rank-tracking' ), false );
		$this->stat( null !== $avg ? (string) $avg : '—', __( 'Avg position', 'serpcheap-rank-tracking' ), true );
		$this->stat( (string) (int) $not_found, __( 'Not found', 'serpcheap-rank-tracking' ), false );
		$this->stat( number_format_i18n( $monthly ), __( 'Credits / month', 'serpcheap-rank-tracking' ), false );
		echo '</div>';

		$alerts = $this->plugin->alerts()->recent( 3 );
		if ( $alerts ) {
			echo '<div class="serpcheap-w-movers-title">' . esc_html__( 'Recent alerts', 'serpcheap-rank-tracking' ) . '</div>';
			echo '<div class="serpcheap-w-alerts">';
			foreach ( $alerts as $al ) {
				printf(
					'<div class="serpcheap-w-alert is-%s"><span class="serpcheap-w-alert-dot"></span><span class="serpcheap-w-alert-kw">%s</span><span class="serpcheap-w-alert-msg">%s</span></div>',
					esc_attr( $al['severity'] ),
					esc_html( isset( $al['keyword'] ) ? $al['keyword'] : '' ),
					esc_html( $al['message'] )
				);
			}
			echo '</div>';
		}

		if ( $movers ) {
			echo '<div class="serpcheap-w-movers-title">' . esc_html__( 'Top movers · 7 days', 'serpcheap-rank-tracking' ) . '</div>';
			echo '<div class="serpcheap-w-movers">';
			foreach ( $movers as $m ) {
				$up    = $m['delta'] < 0;
				$arrow = $up ? '▲' : '▼';
				$class = $up ? 'serpcheap-up' : 'serpcheap-down';
				printf(
					'<div class="serpcheap-w-mover"><span class="serpcheap-w-mover-kw">%s</span><span class="%s">%s %d</span></div>',
					esc_html( $m['keyword'] ),
					esc_attr( $class ),
					esc_html( $arrow ),
					absint( $m['delta'] )
				);
			}
			echo '</div>';
		} elseif ( 0 === $total ) {
			echo '<p class="serpcheap-w-empty">' . esc_html__( 'No trackers yet — add keywords from any post, product or category.', 'serpcheap-rank-tracking' ) . '</p>';
		}

		echo '<div class="serpcheap-w-foot">';
		printf(
			'<a class="button button-primary button-small" href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG ) ),
			esc_html__( 'Open dashboard', 'serpcheap-rank-tracking' )
		);
		echo '</div>';

		echo '</div>';
	}

	private function stat( string $value, string $label, bool $accent ): void {
		printf(
			'<div class="serpcheap-w-stat%s"><div class="serpcheap-w-num">%s</div><div class="serpcheap-w-lbl">%s</div></div>',
			$accent ? ' is-accent' : '',
			esc_html( $value ),
			esc_html( $label )
		);
	}
}
