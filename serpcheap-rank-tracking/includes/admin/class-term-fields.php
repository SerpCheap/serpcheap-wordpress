<?php
/**
 * Rank-tracking field on category / product_cat term edit screens.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Plugin;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TermFields {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		$taxonomies = apply_filters( 'serpcheap_term_taxonomies', array( 'category', 'product_cat' ) );
		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render' ), 10, 2 );
		}
	}

	/** @param WP_Term $term */
	public function render( $term, string $taxonomy ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'serp.cheap Rank Tracking', 'serpcheap-rank-tracking' ) . '</th><td>';
		printf(
			'<div class="serpcheap-rt-metabox" data-serpcheap-metabox data-target-type="term" data-target-ref="%d" data-taxonomy="%s"><p class="serpcheap-muted">%s</p></div>',
			(int) $term->term_id,
			esc_attr( $taxonomy ),
			esc_html__( 'Loading…', 'serpcheap-rank-tracking' )
		);
		echo '</td></tr>';
	}
}
