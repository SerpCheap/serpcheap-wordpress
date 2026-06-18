<?php
/**
 * Universal metabox on post/page/product edit screens (Classic + Block).
 * Renders a container the JS fills via REST: add keyword + inline rank + sparkline.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Admin;

use Serpcheap\RankTracking\Plugin;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Metabox {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
	}

	public function add(): void {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		/**
		 * Filter the post types that get the rank-tracking metabox.
		 *
		 * @param string[] $types
		 */
		$types = apply_filters( 'serpcheap_metabox_post_types', array_values( $types ) );

		foreach ( $types as $type ) {
			add_meta_box(
				'serpcheap-cheapest-keyword-rank-tracker',
				__( 'serp.cheap — Rank Tracking', 'serpcheap-cheapest-keyword-rank-tracker' ),
				array( $this, 'render' ),
				$type,
				'side',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		printf(
			'<div class="serpcheap-rt-metabox" data-serpcheap-metabox data-target-type="post" data-target-ref="%d"><p class="serpcheap-muted">%s</p></div>',
			(int) $post->ID,
			esc_html__( 'Loading…', 'serpcheap-cheapest-keyword-rank-tracker' )
		);
	}
}
