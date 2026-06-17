<?php
/**
 * Real serp.cheap client (server-side, via WP HTTP API). Unused in the demo/mock
 * build; wired in Phase 2 once the OAuth connect issues a real per-site API key.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HttpClient implements ClientInterface {

	/** @var string */
	private $base_url;

	/** @var string */
	private $api_key;

	public function __construct( string $base_url, string $api_key ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->api_key  = $api_key;
	}

	public function rank( string $url, string $keyword, array $opts = array() ): array {
		$payload = array_merge(
			array(
				'url'        => $url,
				'q'          => $keyword,
				'gl'         => 'us',
				'pages'      => 1,
				'match_type' => 'domain',
			),
			array_intersect_key( $opts, array_flip( array( 'gl', 'hl', 'tbs', 'pages', 'match_type' ) ) )
		);

		$response = wp_remote_post(
			$this->base_url . '/v1/rank',
			array(
				'timeout'   => 20,
				'headers'   => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $this->api_key,
					'User-Agent'   => 'serpcheap-wp/' . SERPCHEAP_RT_VERSION,
				),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : 'serp.cheap API error';
			throw new \RuntimeException( esc_html( (string) $message ), $code );
		}

		return $body;
	}
}
