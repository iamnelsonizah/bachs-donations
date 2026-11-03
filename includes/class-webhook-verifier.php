<?php
/**
 * Verifies Bachs timestamped HMAC signatures against the exact request body.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebhookVerifier {
	const TOLERANCE = 300;

	public static function verify( $raw_body, $headers, $secrets ) {
		if ( ! is_string( $raw_body ) || empty( $secrets ) ) {
			return false;
		}
		$headers = array_change_key_case( $headers, CASE_LOWER );
		$v2 = isset( $headers['x-bachs-signature-v2'] ) && is_string( $headers['x-bachs-signature-v2'] ) ? trim( $headers['x-bachs-signature-v2'] ) : '';
		if ( '' !== $v2 ) {
			return self::verify_v2( $raw_body, $v2, $secrets );
		}
		$timestamp = isset( $headers['x-bachs-timestamp'] ) ? trim( (string) $headers['x-bachs-timestamp'] ) : '';
		$signature = isset( $headers['x-bachs-signature'] ) ? trim( (string) $headers['x-bachs-signature'] ) : '';
		return self::verify_legacy( $raw_body, $timestamp, $signature, $secrets );
	}

	private static function verify_v2( $raw_body, $header, $secrets ) {
		$timestamp = '';
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === trim( $pair[0] ) ) {
				$timestamp = trim( $pair[1] );
			} elseif ( 'v1' === trim( $pair[0] ) && preg_match( '/^[a-f0-9]{64}$/i', trim( $pair[1] ) ) ) {
				$signatures[] = strtolower( trim( $pair[1] ) );
			}
		}
		if ( ! self::valid_timestamp( $timestamp ) || empty( $signatures ) ) {
			return false;
		}
		$message = $timestamp . '.' . $raw_body;
		foreach ( $secrets as $secret ) {
			$expected = hash_hmac( 'sha256', $message, $secret );
			foreach ( $signatures as $signature ) {
				if ( hash_equals( $expected, $signature ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function verify_legacy( $raw_body, $timestamp, $signature, $secrets ) {
		if ( ! self::valid_timestamp( $timestamp ) || ! preg_match( '/^[a-f0-9]{64}$/i', $signature ) ) {
			return false;
		}
		$message = $timestamp . '.' . $raw_body;
		foreach ( $secrets as $secret ) {
			if ( hash_equals( hash_hmac( 'sha256', $message, $secret ), strtolower( $signature ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function valid_timestamp( $timestamp ) {
		return is_string( $timestamp ) && ctype_digit( $timestamp ) && abs( time() - (int) $timestamp ) <= self::TOLERANCE;
	}
}
