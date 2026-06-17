<?php
/**
 * Resolves a tracker target (post | term | home | url) to a URL and a human label.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TargetResolver {

	/**
	 * Resolve a fresh URL for the target. Returns null if it can't be resolved
	 * (e.g. post/term deleted).
	 */
	public function resolve( string $type, ?int $ref, ?string $taxonomy, ?string $fallback_url = null ): ?string {
		switch ( $type ) {
			case 'post':
				$link = $ref ? get_permalink( $ref ) : false;
				return $link ? (string) $link : $fallback_url;
			case 'term':
				if ( ! $ref || ! $taxonomy ) {
					return $fallback_url;
				}
				$link = get_term_link( $ref, $taxonomy );
				return is_wp_error( $link ) ? $fallback_url : (string) $link;
			case 'home':
				return home_url( '/' );
			case 'url':
			default:
				return $fallback_url;
		}
	}

	public function label( string $type, ?int $ref, ?string $taxonomy, ?string $url = null ): string {
		switch ( $type ) {
			case 'post':
				$title = $ref ? get_the_title( $ref ) : '';
				return $title ? $title : __( '(untitled)', 'serpcheap-rank-tracking' );
			case 'term':
				$term = ( $ref && $taxonomy ) ? get_term( $ref, $taxonomy ) : null;
				return ( $term && ! is_wp_error( $term ) ) ? $term->name : __( '(term)', 'serpcheap-rank-tracking' );
			case 'home':
				return __( 'Home page', 'serpcheap-rank-tracking' );
			case 'url':
			default:
				return $url ? $url : __( '(custom URL)', 'serpcheap-rank-tracking' );
		}
	}

	public function type_label( string $type ): string {
		$map = array(
			'post' => __( 'Post / Page', 'serpcheap-rank-tracking' ),
			'term' => __( 'Category', 'serpcheap-rank-tracking' ),
			'home' => __( 'Home', 'serpcheap-rank-tracking' ),
			'url'  => __( 'Custom URL', 'serpcheap-rank-tracking' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $type;
	}
}
