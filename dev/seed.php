<?php
/**
 * Seed demo content + trackers. Run via `wp eval-file /seed.php` (WordPress loaded,
 * plugin active). Backfills 14 days of history by calling the mock API over real
 * HTTP, then runs each tracker once for "today".
 *
 * @package Serpcheap\RankTracking
 */

use Serpcheap\RankTracking\Cron\Runner;
use Serpcheap\RankTracking\Plugin;

if ( get_option( 'serpcheap_demo_seeded' ) ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( 'Already seeded — skipping.' );
	}
	return;
}

/** Backfill rows from the mock API for the past N days. */
function serpcheap_backfill( $url, $keyword, $gl ) {
	$rows  = array();
	$today = (int) floor( time() / 86400 );
	for ( $d = 14; $d >= 1; $d-- ) {
		$day  = $today - $d;
		$resp = wp_remote_post(
			'http://mock-api:8090/v1/rank',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => 'demo-key-local',
				),
				'body'    => wp_json_encode(
					array(
						'url'   => $url,
						'q'     => $keyword,
						'gl'    => $gl,
						'pages' => 10,
						'_day'  => $day,
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			continue;
		}
		$b = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $b ) ) {
			continue;
		}
		$rows[] = array(
			'checked_at'    => gmdate( 'Y-m-d H:i:s', ( $day * 86400 ) + 32400 ),
			'rank'          => isset( $b['rank'] ) ? $b['rank'] : null,
			'found'         => ! empty( $b['found'] ) ? 1 : 0,
			'pages_scanned' => 1,
		);
	}
	return $rows;
}

/** Create a tracker, backfill its history, run once for today. */
function serpcheap_track( $type, $ref, $tax, $keyword, $gl = 'us', $explicit_url = null, $schedule = 'daily' ) {
	$plugin = Plugin::instance();
	$runner = new Runner( $plugin );

	if ( 'url' === $type ) {
		$url = $explicit_url;
	} elseif ( 'home' === $type ) {
		$url = home_url( '/' );
	} else {
		$url = $plugin->resolver()->resolve( $type, $ref, $tax, null );
	}
	if ( ! $url ) {
		return;
	}

	$id = $plugin->trackers()->create(
		array(
			'target_type' => $type,
			'target_ref'  => $ref,
			'taxonomy'    => $tax,
			'target_url'  => $url,
			'keyword'     => $keyword,
			'gl'          => $gl,
			'match_type'  => 'domain',
			'schedule'    => $schedule,
			'interval_minutes' => Plugin::interval_minutes( $schedule ),
		)
	);
	if ( ! $id ) {
		return;
	}

	$plugin->history()->insert_many( $id, serpcheap_backfill( $url, $keyword, $gl ) );
	$runner->run( $plugin->trackers()->find( $id ) );
}

/* ---- posts & a page ---- */
$p1 = wp_insert_post( array( 'post_title' => 'Best Running Shoes 2026', 'post_status' => 'publish', 'post_content' => 'Our roundup of the best running shoes this year.' ) );
$p2 = wp_insert_post( array( 'post_title' => 'Trail vs Road Running', 'post_status' => 'publish', 'post_content' => 'A practical guide for runners.' ) );
$pg = wp_insert_post( array( 'post_title' => 'About Our Store', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => 'Who we are.' ) );

serpcheap_track( 'post', $p1, null, 'best running shoes', 'us', null, 'hourly' );
serpcheap_track( 'post', $p1, null, 'running shoes 2026', 'gb', null, '6h' );
serpcheap_track( 'post', $p2, null, 'trail running guide', 'us', null, 'daily' );
serpcheap_track( 'post', $pg, null, 'about our store', 'us', null, 'weekly' );

/* ---- category ---- */
$cat = wp_insert_term( 'Running Gear', 'category' );
if ( ! is_wp_error( $cat ) ) {
	wp_set_post_terms( $p1, array( (int) $cat['term_id'] ), 'category' );
	serpcheap_track( 'term', (int) $cat['term_id'], 'category', 'running gear', 'us', null, '12h' );
}

/* ---- home + custom URL ---- */
serpcheap_track( 'home', null, null, 'my running store' );
serpcheap_track( 'url', null, null, 'cheap running shoes', 'us', home_url( '/shop/' ) );

/* ---- WooCommerce products + product category ---- */
if ( class_exists( 'WooCommerce' ) ) {
	$prod = new WC_Product_Simple();
	$prod->set_name( 'Trail Runner Pro' );
	$prod->set_regular_price( '120' );
	$prod->set_status( 'publish' );
	$prod->set_description( 'Premium trail running shoe.' );
	$prod_id = $prod->save();

	serpcheap_track( 'post', $prod_id, null, 'trail runner pro' );
	serpcheap_track( 'post', $prod_id, null, 'best trail shoes' );

	$pcat = wp_insert_term( 'Shoes', 'product_cat' );
	if ( ! is_wp_error( $pcat ) ) {
		$prod->set_category_ids( array( (int) $pcat['term_id'] ) );
		$prod->save();
		serpcheap_track( 'term', (int) $pcat['term_id'], 'product_cat', 'running shoes store' );
	}
}

update_option( 'serpcheap_demo_seeded', 1 );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::log( 'Seeded demo posts, products, categories and trackers.' );
}
