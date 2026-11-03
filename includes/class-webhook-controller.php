<?php
/**
 * Public REST route for signed Bachs donation events.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebhookController {
	const ROUTE_NAMESPACE = 'bachs-donations/v1';
	const MAX_BODY_BYTES  = 1048576;

	public static function register_hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function register_route() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/webhook',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'receive' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function receive( $request ) {
		$raw_body = $request->get_body();
		if ( strlen( $raw_body ) > self::MAX_BODY_BYTES ) {
			return new \WP_Error( 'bachs_donations_payload_large', __( 'The webhook payload is too large.', 'bachs-donations' ), array( 'status' => 413 ) );
		}
		$secrets = Settings::webhook_secrets();
		if ( empty( $secrets ) ) {
			return new \WP_Error( 'bachs_donations_webhook_unconfigured', __( 'The webhook signing secret is not configured.', 'bachs-donations' ), array( 'status' => 503 ) );
		}
		$headers = array(
			'x-bachs-signature-v2' => $request->get_header( 'x-bachs-signature-v2' ),
			'x-bachs-timestamp'    => $request->get_header( 'x-bachs-timestamp' ),
			'x-bachs-signature'    => $request->get_header( 'x-bachs-signature' ),
		);
		if ( ! WebhookVerifier::verify( $raw_body, $headers, $secrets ) ) {
			return new \WP_Error( 'bachs_donations_bad_signature', __( 'The webhook signature is invalid or expired.', 'bachs-donations' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || ! is_string( $event['id'] ) || empty( $event['type'] ) || ! is_string( $event['type'] ) || ! isset( $event['data'] ) || ! is_array( $event['data'] ) ) {
			return new \WP_Error( 'bachs_donations_bad_event', __( 'The webhook event is invalid.', 'bachs-donations' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $event['type'], array( 'checkout.completed', 'checkout.expired', 'collection.succeeded' ), true ) ) {
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		$data = $event['data'];
		$donation = self::find_donation( $data );
		if ( ! $donation ) {
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		if ( 'checkout.expired' === $event['type'] ) {
			if ( false === DonationsStore::set_status( (int) $donation['id'], 'expired' ) ) {
				return new \WP_Error( 'bachs_donations_storage_error', __( 'The donation could not be updated.', 'bachs-donations' ), array( 'status' => 500 ) );
			}
			DonationsStore::record_event_once( $event['id'] );
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		if ( ! self::event_is_paid( $event['type'], $data ) ) {
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}

		$event_id = $event['id'];
		$amount = isset( $data['amount'] ) && is_scalar( $data['amount'] ) ? (string) $data['amount'] : '';
		$currency = isset( $data['currency'] ) && is_scalar( $data['currency'] ) ? strtoupper( (string) $data['currency'] ) : '';
		if ( ! self::amounts_match( $amount, (string) $donation['amount'] ) || $currency !== strtoupper( $donation['currency'] ) ) {
			self::log_mismatch();
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		$charge_id = '';
		if ( isset( $data['charge'] ) && is_array( $data['charge'] ) ) {
			$charge_id = isset( $data['charge']['id'] ) && is_scalar( $data['charge']['id'] ) ? (string) $data['charge']['id'] : '';
		} elseif ( isset( $data['charge_id'] ) && is_scalar( $data['charge_id'] ) ) {
			$charge_id = (string) $data['charge_id'];
		}
		if ( false === DonationsStore::mark_paid( (int) $donation['id'], $charge_id ) ) {
			return new \WP_Error( 'bachs_donations_storage_error', __( 'The donation could not be updated.', 'bachs-donations' ), array( 'status' => 500 ) );
		}
		DonationsStore::record_event_once( $event_id );
		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	private static function find_donation( $data ) {
		$reference = isset( $data['reference'] ) && is_scalar( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
		if ( '' !== $reference ) {
			return DonationsStore::find_by_reference( $reference );
		}
		$metadata = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : array();
		if ( isset( $metadata['donation_id'] ) && is_scalar( $metadata['donation_id'] ) ) {
			$donation = DonationsStore::find_by_id( absint( $metadata['donation_id'] ) );
			if ( $donation ) {
				return $donation;
			}
		}
		$checkout_id = isset( $data['checkout_id'] ) && is_scalar( $data['checkout_id'] ) ? (string) $data['checkout_id'] : '';
		return DonationsStore::find_by_checkout_id( $checkout_id );
	}

	private static function event_is_paid( $type, $data ) {
		if ( 'checkout.completed' === $type ) {
			if ( ! isset( $data['payment_status'] ) || 'paid' !== $data['payment_status'] || ! isset( $data['charge'] ) || ! is_array( $data['charge'] ) ) {
				return false;
			}
			return isset( $data['charge']['status'] ) && 'succeeded' === strtolower( (string) $data['charge']['status'] );
		}
		return isset( $data['status'] ) && 'succeeded' === strtolower( (string) $data['status'] );
	}

	public static function amounts_match_for_reconciliation( $left, $right ) {
		return self::amounts_match( $left, $right );
	}

	private static function amounts_match( $left, $right ) {
		$normalize = static function ( $value ) {
			$value = trim( (string) $value );
			if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) {
				return false;
			}
			$parts = explode( '.', $value, 2 );
			return ltrim( $parts[0], '0' ) . '.' . str_pad( isset( $parts[1] ) ? $parts[1] : '', 2, '0' );
		};
		return $normalize( $left ) === $normalize( $right ) && false !== $normalize( $left );
	}

	private static function log_mismatch() {
		if ( function_exists( 'error_log' ) ) {
			error_log( '[Bachs Donations] A signed payment event did not match its stored donation amount or currency.' );
		}
	}
}
