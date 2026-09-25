<?php
/**
 * API-key codec unit tests.
 *
 * @package Blocky\Core\Tests
 */

declare( strict_types=1 );

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Support\ApiKeyCodec;
use PHPUnit\Framework\TestCase;

/**
 * Key strings, hashes and masks must be stable and safe.
 */
final class ApiKeyCodecTest extends TestCase {

	/**
	 * Generated keys parse, expose a public id and verify by hash.
	 *
	 * @return void
	 */
	public function test_generate_parse_verify_roundtrip(): void {
		$data = ApiKeyCodec::generate();
		self::assertStringStartsWith( ApiKeyCodec::PREFIX, $data['secret'] );
		self::assertSame( 64, strlen( $data['secret'] ) ); // Prefix 11 + public 12 + separator 1 + secret 40.
		self::assertSame( 64, strlen( $data['hash'] ) );

		$public_id = ApiKeyCodec::public_id_from_key( $data['secret'] );
		self::assertSame( $data['public_id'], $public_id );

		self::assertNotNull( ApiKeyCodec::parse( $data['secret'] ) );
		self::assertTrue( ApiKeyCodec::verify( $data['secret'], $data['hash'] ) );

		$forged = 'bky_live_' . str_repeat( 'a', 12 ) . '_' . str_repeat( 'b', 40 );
		self::assertFalse( ApiKeyCodec::verify( $forged, $data['hash'] ) );

		$legacy = 'bky_live_' . str_repeat( 'c', 12 ) . '_' . str_repeat( 'd', 40 );
		self::assertNotNull( ApiKeyCodec::parse( $legacy ) );
		self::assertSame( str_repeat( 'c', 12 ), ApiKeyCodec::public_id_from_key( $legacy ) );
	}

	/**
	 * Malformed tokens are rejected.
	 *
	 * @return void
	 */
	public function test_parse_rejects_malformed(): void {
		self::assertNull( ApiKeyCodec::parse( 'hunter2' ) );
		self::assertNull( ApiKeyCodec::parse( 'bky_live_short_x' ) );
		self::assertNull( ApiKeyCodec::parse( 'bky_live_' . str_repeat( 'x', 12 ) . '_' . str_repeat( 'x', 39 ) ) );
		self::assertNull( ApiKeyCodec::parse( 'bky_live_' . str_repeat( 'x', 12 ) . '_' . str_repeat( 'x', 41 ) ) );
		self::assertNull( ApiKeyCodec::parse( 'bky_test_' . str_repeat( 'a', 12 ) . '_' . str_repeat( 'a', 40 ) ) );
	}

	/**
	 * Masks never expose secret material.
	 *
	 * @return void
	 */
	public function test_mask_hides_secret(): void {
		$data = ApiKeyCodec::generate();
		$mask = ApiKeyCodec::mask( $data['secret'] );
		self::assertStringNotContainsString( substr( $data['secret'], -10 ), $mask );
		self::assertStringContainsString( $data['public_id'], $mask );
	}

	/**
	 * Generations do not collide.
	 *
	 * @return void
	 */
	public function test_no_collisions_between_generations(): void {
		$hashes = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$generated = ApiKeyCodec::generate();
			$hashes[]  = $generated['hash'];
		}
		self::assertCount( 20, array_unique( $hashes ) );
	}
}
