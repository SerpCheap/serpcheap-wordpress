<?php
/**
 * WooCommerce support. Products are a public post type, so the generic metabox,
 * "Rank" column and product_cat term field already apply; this guard makes the
 * support explicit and future-proofs Woo-specific tweaks.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Woocommerce;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooIntegration {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		// Make sure 'product' and 'product_cat' stay trackable even if another
		// plugin narrows the public-type lists via our filters.
		add_filter( 'serpcheap_metabox_post_types', array( $this, 'ensure_product' ) );
		add_filter( 'serpcheap_column_post_types', array( $this, 'ensure_product' ) );
		add_filter(
			'serpcheap_term_taxonomies',
			static function ( array $taxonomies ): array {
				if ( ! in_array( 'product_cat', $taxonomies, true ) ) {
					$taxonomies[] = 'product_cat';
				}
				return $taxonomies;
			}
		);
	}

	/** @param string[] $types @return string[] */
	public function ensure_product( array $types ): array {
		if ( post_type_exists( 'product' ) && ! in_array( 'product', $types, true ) ) {
			$types[] = 'product';
		}
		return $types;
	}
}
