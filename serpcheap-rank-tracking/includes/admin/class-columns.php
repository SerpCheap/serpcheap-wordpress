<?php
/**
 * "Rank" column on post / page / product list screens.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Columns {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		$types = apply_filters( 'serpcheap_column_post_types', array_values( $types ) );

		foreach ( $types as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/** @param array<string,string> $columns @return array<string,string> */
	public function add_column( array $columns ): array {
		$columns['serpcheap_rank'] = __( 'Rank', 'serpcheap-rank-tracking' );
		return $columns;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( 'serpcheap_rank' !== $column ) {
			return;
		}
		$trackers = $this->plugin->trackers()->for_target( 'post', $post_id );
		if ( ! $trackers ) {
			echo '<span class="serpcheap-muted">—</span>';
			return;
		}

		$best = null;
		foreach ( $trackers as $t ) {
			if ( null !== $t['current_rank'] ) {
				$best = ( null === $best ) ? (int) $t['current_rank'] : min( $best, (int) $t['current_rank'] );
			}
		}

		$count = count( $trackers );
		if ( null === $best ) {
			printf(
				'<span class="serpcheap-muted">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: number of tracked keywords */
						_n( '%d keyword', '%d keywords', $count, 'serpcheap-rank-tracking' ),
						$count
					)
				)
			);
			return;
		}

		printf(
			'<strong class="serpcheap-rank">#%d</strong> <span class="serpcheap-muted">(%s)</span>',
			(int) $best,
			esc_html(
				sprintf(
					/* translators: %d: number of tracked keywords */
					_n( '%d keyword', '%d keywords', $count, 'serpcheap-rank-tracking' ),
					$count
				)
			)
		);
	}
}
