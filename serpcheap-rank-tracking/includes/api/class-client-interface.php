<?php
/**
 * serp.cheap API client contract. Both MockClient and HttpClient implement it.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ClientInterface {

	/**
	 * Find where $url ranks for $keyword. Returns the /v1/rank response shape:
	 * [ 'found' => bool, 'rank' => ?int, 'matches' => array, 'pages_scanned' => int,
	 *   'stats' => [ 'balance' => int, 'cost' => int, ... ] ].
	 *
	 * @param array<string,mixed> $opts gl, hl, match_type, pages.
	 * @return array<string,mixed>
	 */
	public function rank( string $url, string $keyword, array $opts = array() ): array;
}
