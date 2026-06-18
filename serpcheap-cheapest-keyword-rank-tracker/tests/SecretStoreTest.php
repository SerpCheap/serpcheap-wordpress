<?php

use PHPUnit\Framework\TestCase;
use Serpcheap\RankTracking\SecretStore;

final class SecretStoreTest extends TestCase {

	public function test_round_trip(): void {
		$plain = 'k_' . bin2hex( random_bytes( 16 ) );
		$enc   = SecretStore::encrypt( $plain );

		$this->assertNotSame( '', $enc );
		$this->assertNotSame( $plain, $enc );
		$this->assertSame( $plain, SecretStore::decrypt( $enc ) );
	}

	public function test_ciphertext_is_randomised_per_call(): void {
		$plain = 'k_same_value';
		$this->assertNotSame( SecretStore::encrypt( $plain ), SecretStore::encrypt( $plain ) );
	}

	public function test_decrypt_returns_null_on_bad_input(): void {
		$this->assertNull( SecretStore::decrypt( '' ) );
		$this->assertNull( SecretStore::decrypt( 'not-base64-$$$' ) );
		$this->assertNull( SecretStore::decrypt( base64_encode( 'too-short' ) ) );
	}

	public function test_available(): void {
		$this->assertTrue( SecretStore::available() );
	}
}
