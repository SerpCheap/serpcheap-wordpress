<?php
/**
 * Central management table: all trackers across the site.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Cron\Runner;
use Serpcheap\RankTracking\Plugin;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class TrackersListTable extends WP_List_Table {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		parent::__construct(
			array(
				'singular' => 'tracker',
				'plural'   => 'trackers',
				'ajax'     => false,
			)
		);
	}

	/** @return array<string,string> */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox" />',
			'keyword'      => __( 'Keyword', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'target'       => __( 'Target', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'country'      => __( 'Country', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'match_type'   => __( 'Match', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'rank'         => __( 'Rank', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'delta'        => __( 'Δ 7d', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'trend'        => __( 'Trend', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'last_checked' => __( 'Last checked', 'serpcheap-cheapest-keyword-rank-tracker' ),
		);
	}

	/** @return array<string,string> */
	protected function get_bulk_actions(): array {
		return array(
			'refresh' => __( 'Refresh now', 'serpcheap-cheapest-keyword-rank-tracker' ),
			'delete'  => __( 'Delete', 'serpcheap-cheapest-keyword-rank-tracker' ),
		);
	}

	public function prepare_items(): void {
		$this->process_bulk_action();

		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$trackers = $this->plugin->trackers()->all();

		if ( '' !== $search ) {
			$trackers = array_values(
				array_filter(
					$trackers,
					static function ( $t ) use ( $search ) {
						return false !== stripos( $t['keyword'], $search );
					}
				)
			);
		}

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = $trackers;
	}

	private function process_bulk_action(): void {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$ids = isset( $_REQUEST['tracker'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['tracker'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $ids || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$this->plugin->trackers()->delete( $id );
			} elseif ( 'refresh' === $action ) {
				$tracker = $this->plugin->trackers()->find( $id );
				if ( $tracker ) {
					( new Runner( $this->plugin ) )->run( $tracker );
				}
			}
		}
	}

	/** @param array<string,mixed> $item */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="tracker[]" value="%d" />', (int) $item['id'] );
	}

	/** @param array<string,mixed> $item */
	protected function column_keyword( $item ): string {
		$id      = (int) $item['id'];
		$actions = array(
			'refresh' => sprintf(
				'<a href="#" data-serpcheap-row="refresh" data-id="%d">%s</a>',
				$id,
				esc_html__( 'Refresh', 'serpcheap-cheapest-keyword-rank-tracker' )
			),
			'delete'  => sprintf(
				'<a href="#" class="serpcheap-del" data-serpcheap-row="delete" data-id="%d">%s</a>',
				$id,
				esc_html__( 'Delete', 'serpcheap-cheapest-keyword-rank-tracker' )
			),
		);
		return '<strong>' . esc_html( $item['keyword'] ) . '</strong>' . $this->row_actions( $actions );
	}

	/** @param array<string,mixed> $item */
	protected function column_target( $item ): string {
		$label = $this->plugin->resolver()->label(
			$item['target_type'],
			isset( $item['target_ref'] ) ? (int) $item['target_ref'] : null,
			isset( $item['taxonomy'] ) ? $item['taxonomy'] : null,
			$item['target_url']
		);
		$type = $this->plugin->resolver()->type_label( $item['target_type'] );
		return '<a href="' . esc_url( $item['target_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' .
			'<br><span class="serpcheap-muted">' . esc_html( $type ) . '</span>';
	}

	/** @param array<string,mixed> $item */
	protected function column_country( $item ): string {
		return esc_html( strtoupper( $item['gl'] ) );
	}

	/** @param array<string,mixed> $item */
	protected function column_match_type( $item ): string {
		return esc_html( $item['match_type'] );
	}

	/** @param array<string,mixed> $item */
	protected function column_rank( $item ): string {
		if ( null === $item['current_rank'] ) {
			return '<span class="serpcheap-muted">' . esc_html__( '—', 'serpcheap-cheapest-keyword-rank-tracker' ) . '</span>';
		}
		return '<span class="serpcheap-rank">#' . (int) $item['current_rank'] . '</span>';
	}

	/** @param array<string,mixed> $item */
	protected function column_delta( $item ): string {
		$delta = $this->plugin->history()->delta_7d(
			(int) $item['id'],
			null !== $item['current_rank'] ? (int) $item['current_rank'] : null
		);
		if ( null === $delta || 0 === $delta ) {
			return '<span class="serpcheap-muted">—</span>';
		}
		// Negative delta = rank number went down = improved.
		$improved = $delta < 0;
		$arrow    = $improved ? '▲' : '▼';
		$class    = $improved ? 'serpcheap-up' : 'serpcheap-down';
		return '<span class="' . esc_attr( $class ) . '">' . $arrow . ' ' . absint( $delta ) . '</span>';
	}

	/** @param array<string,mixed> $item */
	protected function column_trend( $item ): string {
		$history = $this->plugin->history()->recent( (int) $item['id'], 20 );
		$ranks   = array();
		foreach ( $history as $h ) {
			$ranks[] = null !== $h['rank'] ? (int) $h['rank'] : null;
		}
		return self::sparkline( $ranks );
	}

	/** @param array<string,mixed> $item */
	protected function column_last_checked( $item ): string {
		if ( empty( $item['last_checked'] ) ) {
			return '<span class="serpcheap-muted">—</span>';
		}
		$ts = strtotime( $item['last_checked'] . ' UTC' );
		return esc_html( sprintf( /* translators: %s: human time diff */ __( '%s ago', 'serpcheap-cheapest-keyword-rank-tracker' ), human_time_diff( $ts ) ) );
	}

	/** @param array<string,mixed> $item @param string $column_name */
	protected function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function no_items(): void {
		esc_html_e( 'No trackers yet. Add keywords from a post/page/product edit screen, or track the home page above.', 'serpcheap-cheapest-keyword-rank-tracker' );
	}

	/**
	 * Inline SVG sparkline. Lower rank (better) is drawn higher.
	 *
	 * @param array<int,?int> $ranks oldest-first.
	 */
	public static function sparkline( array $ranks ): string {
		$points = array_values( array_filter( $ranks, static function ( $r ) {
			return null !== $r;
		} ) );
		if ( count( $points ) < 2 ) {
			return '<span class="serpcheap-muted">—</span>';
		}
		$w   = 84;
		$h   = 24;
		$max = max( $points );
		$min = min( $points );
		$span = max( 1, $max - $min );
		$n    = count( $ranks );
		$step = $n > 1 ? ( $w - 4 ) / ( $n - 1 ) : 0;

		$coords = array();
		$i      = 0;
		foreach ( $ranks as $r ) {
			$x = round( 2 + ( $i * $step ), 1 );
			if ( null === $r ) {
				++$i;
				continue;
			}
			// invert: smaller rank → higher on chart
			$y        = round( 2 + ( ( $r - $min ) / $span ) * ( $h - 4 ), 1 );
			$coords[] = $x . ',' . $y;
			++$i;
		}
		$poly = implode( ' ', $coords );
		$last = $ranks[ $n - 1 ];

		$svg  = '<svg class="serpcheap-spark" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">';
		$svg .= '<polyline fill="none" stroke="#2271b1" stroke-width="1.5" points="' . esc_attr( $poly ) . '" />';
		$svg .= '</svg>';
		return $svg;
	}
}
