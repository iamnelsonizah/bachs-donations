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
		$gateway = PaymentGateways::active();
		if ( ! $gateway->webhook_is_configured() ) {
			return new \WP_Error( 'bachs_donations_webhook_unconfigured', __( 'The webhook signing secret is not configured.', 'bachs-donations' ), array( 'status' => 503 ) );
		}
		$headers = array_map(
			static function ( $value ) {
				return is_array( $value ) ? implode( ',', $value ) : (string) $value;
			},
			$request->get_headers()
		);
		$event = $gateway->parse_webhook( $raw_body, $headers );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new \WP_Error( 'bachs_donations_bad_webhook', __( 'The webhook signature or event is invalid.', 'bachs-donations' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $event['type'], array( 'checkout.completed', 'checkout.expired', 'collection.succeeded' ), true ) ) {
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		$donation = self::find_donation( $event );
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
		if ( empty( $event['paid'] ) ) {
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}

		$event_id = $event['id'];
		$amount = isset( $event['amount'] ) && is_scalar( $event['amount'] ) ? (string) $event['amount'] : '';
		$currency = isset( $event['currency'] ) && is_scalar( $event['currency'] ) ? strtoupper( (string) $event['currency'] ) : '';
		if ( ! self::amounts_match( $amount, (string) $donation['amount'] ) || $currency !== strtoupper( $donation['currency'] ) ) {
			self::log_mismatch();
			return new \WP_REST_Response( array( 'received' => true ), 200 );
		}
		$charge_id = isset( $event['charge_id'] ) && is_scalar( $event['charge_id'] ) ? (string) $event['charge_id'] : '';
		if ( false === DonationsStore::mark_paid( (int) $donation['id'], $charge_id ) ) {
			return new \WP_Error( 'bachs_donations_storage_error', __( 'The donation could not be updated.', 'bachs-donations' ), array( 'status' => 500 ) );
		}
		DonationsStore::record_event_once( $event_id );
		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	private static function find_donation( $event ) {
		$reference = isset( $event['reference'] ) && is_scalar( $event['reference'] ) ? sanitize_text_field( (string) $event['reference'] ) : '';
		if ( '' !== $reference ) {
			return DonationsStore::find_by_reference( $reference );
		}
		if ( ! empty( $event['donation_id'] ) ) {
			$donation = DonationsStore::find_by_id( absint( $event['donation_id'] ) );
			if ( $donation ) {
				return $donation;
			}
		}
		$checkout_id = isset( $event['checkout_id'] ) && is_scalar( $event['checkout_id'] ) ? (string) $event['checkout_id'] : '';
		return DonationsStore::find_by_checkout_id( $checkout_id );
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
