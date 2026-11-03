<?php
/**
 * Bachs implementation of the donation payment gateway contract.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BachsGateway implements PaymentGatewayInterface {
	/** @var ApiClient */
	private $client;

	public function __construct( $client = null ) {
		$this->client = $client instanceof ApiClient ? $client : new ApiClient();
	}

	public function is_configured() {
		return Settings::is_configured();
	}

	public function create_checkout_session( $payload, $idempotency_key ) {
		return $this->client->create_checkout_session( $payload, $idempotency_key );
	}

	public function payment_method_types( $currency, $configured_methods ) {
		$allowed = 'USD' === strtoupper( $currency ) ? array( 'USD_CARD' ) : array( 'NGN_CARD', 'NGN_BANK_TRANSFER' );
		$selected = is_array( $configured_methods ) ? array_values( array_intersect( $configured_methods, $allowed ) ) : array();
		return empty( $selected ) ? $allowed : $selected;
	}

	public function retrieve_checkout_session( $checkout_id ) {
		return $this->client->retrieve_checkout_session( $checkout_id );
	}

	public function is_checkout_url_allowed( $url ) {
		return CheckoutController::is_bachs_checkout_url( $url );
	}

	public function webhook_is_configured() {
		return ! empty( Settings::webhook_secrets() );
	}

	/** Verify the Bachs signature and map provider data to the gateway event shape. */
	public function parse_webhook( $raw_body, $headers ) {
		if ( ! WebhookVerifier::verify( $raw_body, $headers, Settings::webhook_secrets() ) ) {
			return false;
		}
		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || ! is_string( $event['id'] ) || empty( $event['type'] ) || ! is_string( $event['type'] ) || ! isset( $event['data'] ) || ! is_array( $event['data'] ) ) {
			return false;
		}
		$data = $event['data'];
		$metadata = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array();
		$charge = isset( $data['charge'] ) && is_array( $data['charge'] ) ? $data['charge'] : array();
		$charge_id = isset( $charge['id'] ) && is_scalar( $charge['id'] ) ? (string) $charge['id'] : ( isset( $data['charge_id'] ) && is_scalar( $data['charge_id'] ) ? (string) $data['charge_id'] : '' );
		return array(
			'id'          => $event['id'],
			'type'        => $event['type'],
			'reference'   => isset( $data['reference'] ) && is_scalar( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '',
			'donation_id' => isset( $metadata['donation_id'] ) && is_scalar( $metadata['donation_id'] ) ? absint( $metadata['donation_id'] ) : 0,
			'checkout_id' => isset( $data['checkout_id'] ) && is_scalar( $data['checkout_id'] ) ? (string) $data['checkout_id'] : '',
			'amount'      => isset( $data['amount'] ) && is_scalar( $data['amount'] ) ? (string) $data['amount'] : '',
			'currency'    => isset( $data['currency'] ) && is_scalar( $data['currency'] ) ? strtoupper( (string) $data['currency'] ) : '',
			'paid'        => self::event_is_paid( $event['type'], $data ),
			'charge_id'   => $charge_id,
		);
	}

	/** Match Bachs checkout and collection event success fields. */
	private static function event_is_paid( $type, $data ) {
		if ( 'checkout.completed' === $type ) {
			if ( ! isset( $data['payment_status'] ) || 'paid' !== $data['payment_status'] || ! isset( $data['charge'] ) || ! is_array( $data['charge'] ) ) {
				return false;
			}
			return isset( $data['charge']['status'] ) && 'succeeded' === strtolower( (string) $data['charge']['status'] );
		}
		return 'collection.succeeded' === $type && isset( $data['status'] ) && 'succeeded' === strtolower( (string) $data['status'] );
	}
}
