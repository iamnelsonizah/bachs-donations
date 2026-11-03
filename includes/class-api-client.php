<?php
/**
 * Small Bachs REST client using WordPress's HTTP API.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiClient {
	/** @var string */
	private $environment;

	/** @var string */
	private $api_key;

	public function __construct() {
		$this->environment = Settings::environment();
		$this->api_key     = Settings::api_key();
	}

	/** Create a one-time, product-less donation checkout. */
	public function create_checkout_session( $payload, $idempotency_key ) {
		return $this->request( 'POST', '/v1/checkout-sessions', $payload, $idempotency_key );
	}

	/** Retrieve a checkout to reconcile an unconfirmed donation. */
	public function retrieve_checkout_session( $checkout_id ) {
		return $this->request( 'GET', '/v1/checkout-sessions/' . rawurlencode( $checkout_id ) );
	}

	/** Send a request to one of the fixed Bachs API hosts. */
	private function request( $method, $path, $payload = null, $idempotency_key = '' ) {
		if ( ! in_array( $this->environment, array( 'sandbox', 'live' ), true ) ) {
			throw new ApiException( __( 'Bachs environment is not configured correctly.', 'bachs-donations' ) );
		}
		$prefix = 'live' === $this->environment ? 'sk_live_' : 'sk_sandbox_';
		if ( '' === $this->api_key || 0 !== strpos( $this->api_key, $prefix ) ) {
			throw new ApiException( __( 'The Bachs API key does not match the selected environment.', 'bachs-donations' ) );
		}
		if ( ! preg_match( '#^/v1/[A-Za-z0-9/_%-]+$#', $path ) ) {
			throw new ApiException( __( 'The Bachs API path is invalid.', 'bachs-donations' ) );
		}

		$host = 'live' === $this->environment ? 'https://api.bachs.io' : 'https://sandbox-api.bachs.io';
		$args = array(
			'method'              => strtoupper( $method ),
			'timeout'             => 20,
			'redirection'         => 0,
			'limit_response_size' => 1048576,
			'sslverify'           => true,
			'headers'             => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $payload ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode( $payload );
			if ( false === $args['body'] ) {
				throw new ApiException( __( 'The donation checkout could not be encoded.', 'bachs-donations' ) );
			}
		}
		if ( '' !== $idempotency_key ) {
			if ( strlen( $idempotency_key ) > 255 || preg_match( '/[\r\n]/', $idempotency_key ) ) {
				throw new ApiException( __( 'The Bachs idempotency key is invalid.', 'bachs-donations' ) );
			}
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_safe_remote_request( $host . $path, $args );
		if ( is_wp_error( $response ) ) {
			throw new ApiException( __( 'Bachs could not be reached. Please try again.', 'bachs-donations' ), 0, 'transport_error' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			throw new ApiException( __( 'Bachs returned an unreadable response.', 'bachs-donations' ), $status, 'invalid_response' );
		}
		if ( $status < 200 || $status >= 300 ) {
			$error_code = isset( $body['error_code'] ) ? sanitize_key( $body['error_code'] ) : 'api_error';
			throw new ApiException( __( 'Bachs could not complete the request. Check the API key permissions.', 'bachs-donations' ), $status, $error_code );
		}
		return $body;
	}
}
