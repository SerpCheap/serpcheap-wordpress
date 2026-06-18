<?php
/**
 * At-rest encryption for the connected API key. The key is derived from the
 * site's wp-config auth salts (filesystem), so a database-only leak can't
 * decrypt the stored token. Uses libsodium (bundled with PHP 7.2+).
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SecretStore {

	public static function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) && defined( 'SECURE_AUTH_KEY' ) && defined( 'SECURE_AUTH_SALT' );
	}

	/** 32-byte key derived from the wp-config salts (never stored in the DB). */
	private static function key(): string {
		return hash_hmac( 'sha256', 'serpcheap-api-key', SECURE_AUTH_KEY . SECURE_AUTH_SALT, true );
	}

	/** @return string base64(nonce . ciphertext), or '' if unavailable. */
	public static function encrypt( string $plaintext ): string {
		if ( ! self::available() ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, self::key() );
		return base64_encode( $nonce . $cipher );
	}

	/** @return string|null plaintext, or null on any failure (e.g. salts rotated). */
	public static function decrypt( string $stored ): ?string {
		if ( '' === $stored || ! self::available() ) {
			return null;
		}
		$raw = base64_decode( $stored, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		return false === $plain ? null : $plain;
	}
}
