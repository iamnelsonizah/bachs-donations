<?php
/**
 * Confirms donation state against Bachs over an authenticated server request.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reconciler {
	/**
	 * Refresh a pending donation. Public return-page requests are throttled so
	 * repeated visits cannot generate an unbounded number of API calls.
	 *
	 * @param array $donation Donation row.
	 * @return string Current or resulting status.
	 */
	public static function refresh( $donation ) {
		if ( ! is_array( $donation ) || empty( $donation['id'] ) || empty( $donation['checkout_id'] ) ) {
			return 'pending';
		}
		if ( 'paid' === $donation['status'] ) {
			return 'paid';
		}

		$lock = 'bachs_donations_reconcile_' . absint( $donation['id'] );
		if ( get_transient( $lock ) ) {
			return (string) $donation['status'];
		}
		set_transient( $lock, 1, 30 );

		try {
			$session = ( new ApiClient() )->retrieve_checkout_session( $donation['checkout_id'] );
			$session = self::session_data( $session );
			if ( self::session_is_paid( $session ) ) {
				$amount   = isset( $session['charge']['amount'] ) && is_scalar( $session['charge']['amount'] ) ? (string) $session['charge']['amount'] : '';
				$currency = isset( $session['charge']['currency'] ) && is_scalar( $session['charge']['currency'] ) ? strtoupper( (string) $session['charge']['currency'] ) : '';
				if ( WebhookController::amounts_match_for_reconciliation( $amount, (string) $donation['amount'] ) && $currency === strtoupper( $donation['currency'] ) ) {
					$charge_id = isset( $session['charge']['id'] ) && is_scalar( $session['charge']['id'] ) ? (string) $session['charge']['id'] : ( isset( $session['charge']['payment_id'] ) && is_scalar( $session['charge']['payment_id'] ) ? (string) $session['charge']['payment_id'] : '' );
					if ( false === DonationsStore::mark_paid( (int) $donation['id'], $charge_id ) ) {
						return 'error';
					}
					$latest = DonationsStore::find_by_id( (int) $donation['id'] );
					return is_array( $latest ) ? (string) $latest['status'] : 'error';
				}
				self::log_mismatch();
				return (string) $donation['status'];
			}

			$remote_status = isset( $session['status'] ) && is_scalar( $session['status'] ) ? strtolower( (string) $session['status'] ) : '';
			if ( in_array( $remote_status, array( 'failed', 'expired', 'canceled', 'cancelled' ), true ) ) {
				$status = in_array( $remote_status, array( 'canceled', 'cancelled' ), true ) ? 'failed' : $remote_status;
				DonationsStore::set_status( (int) $donation['id'], $status );
				return $status;
			}
		} catch ( ApiException $exception ) {
			// Keep the donation pending; the webhook or a later admin retry may succeed.
			return 'error';
		} catch ( \Throwable $exception ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( '[Bachs Donations] Checkout reconciliation failed.' );
			}
		}

		return (string) $donation['status'];
	}

	private static function session_data( $response ) {
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		return is_array( $response ) ? $response : array();
	}

	private static function session_is_paid( $session ) {
		$payment_status = isset( $session['payment_status'] ) && is_scalar( $session['payment_status'] ) ? strtolower( (string) $session['payment_status'] ) : '';
		$charge_status  = isset( $session['charge']['status'] ) && is_scalar( $session['charge']['status'] ) ? strtolower( (string) $session['charge']['status'] ) : '';
		return in_array( $payment_status, array( 'paid', 'succeeded' ), true ) && 'succeeded' === $charge_status;
	}

	private static function log_mismatch() {
		if ( function_exists( 'error_log' ) ) {
			error_log( '[Bachs Donations] A retrieved checkout did not match its stored donation amount or currency.' );
		}
	}
}
