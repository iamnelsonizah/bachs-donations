<?php
/**
 * Validates donation submissions and creates hosted Bachs checkout sessions.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckoutController {
	public static function register_hooks() {
		add_action( 'admin_post_nopriv_bachs_donation_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_bachs_donation_start', array( __CLASS__, 'start' ) );
	}

	public static function start() {
		check_admin_referer( 'bachs_donation_start' );
		$return_url = self::return_url();

		// Quietly reject bots that fill this visually hidden form field.
		if ( ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( $return_url );
			exit;
		}
		if ( ! PaymentGateways::active()->is_configured() ) {
			self::redirect_with_result( $return_url, 'unavailable' );
		}
		$campaign_id = isset( $_POST['campaign_id'] ) && is_scalar( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$campaign = null;
		if ( $campaign_id ) {
			$campaign = get_post( $campaign_id );
			if ( ! $campaign instanceof \WP_Post || DonationForms::POST_TYPE !== $campaign->post_type || 'publish' !== $campaign->post_status ) {
				self::redirect_with_result( $return_url, 'invalid' );
			}
		}
		$form_settings = DonationForms::settings( $campaign_id );

		$currency = isset( $_POST['currency'] ) && is_scalar( $_POST['currency'] ) ? strtoupper( sanitize_text_field( (string) wp_unslash( $_POST['currency'] ) ) ) : '';
		$email = isset( $_POST['email'] ) && is_scalar( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		if ( ! is_email( $email ) || strlen( $email ) > 191 || strlen( $name ) > 191 ) {
			self::redirect_with_result( $return_url, 'invalid' );
		}

		$amount_input = isset( $_POST['custom_amount'] ) && is_scalar( $_POST['custom_amount'] ) ? trim( (string) wp_unslash( $_POST['custom_amount'] ) ) : '';
		if ( '' !== $amount_input && ! $form_settings['custom_amount'] ) {
			self::redirect_with_result( $return_url, 'invalid' );
		}
		if ( '' === $amount_input && isset( $_POST['preset_amount'] ) && is_scalar( $_POST['preset_amount'] ) ) {
			$preset = explode( ':', trim( (string) wp_unslash( $_POST['preset_amount'] ) ), 2 );
			$preset_currency = 2 === count( $preset ) ? strtoupper( $preset[0] ) : '';
			if ( 2 === count( $preset ) && in_array( $preset_currency, $form_settings['currencies'], true ) ) {
				$preset_amount = self::normalize_amount( $preset[1] );
				if ( false !== $preset_amount && in_array( $preset_amount, $form_settings['amounts'][ $preset_currency ], true ) ) {
					$amount_input = $preset_amount;
					$currency = $preset_currency;
				}
			}
		}
		if ( ! in_array( $currency, $form_settings['currencies'], true ) ) {
			self::redirect_with_result( $return_url, 'invalid' );
		}
		$amount = self::normalize_amount( $amount_input );
		if ( false === $amount ) {
			self::redirect_with_result( $return_url, 'invalid' );
		}
		if ( self::rate_limited() ) {
			self::redirect_with_result( $return_url, 'rate_limited' );
		}

		try {
			$gateway = PaymentGateways::active();
			$campaign_title = $campaign instanceof \WP_Post ? sanitize_text_field( $campaign->post_title ) : '';
			$donation = DonationsStore::create( $currency, $amount, $name, $email, $campaign_id, $campaign_title );
			$token = $donation['token'];
			$success_url = add_query_arg( 'bachs_donation', $token, remove_query_arg( array( 'bachs_donation', 'bachs_donation_result', 'bachs_donation_cancel' ), $return_url ) );
			$cancel_url = add_query_arg( 'bachs_donation_cancel', $token, remove_query_arg( array( 'bachs_donation', 'bachs_donation_result', 'bachs_donation_cancel' ), $return_url ) );
			$payload = array(
				'pricing' => array( 'currency' => $currency, 'amount' => $amount ),
				'customer' => array_filter( array( 'email' => $email, 'name' => $name ) ),
				'payment_method_types' => $gateway->payment_method_types( $currency, $form_settings['payment_methods'][ $currency ] ),
				'success_url' => $success_url,
				'cancel_url'  => $cancel_url,
				'reference'   => $donation['reference'],
				'metadata'    => array(
					'integration' => 'wordpress-donations',
					'donation_id' => (string) $donation['id'],
					'form_id' => (string) $campaign_id,
				),
			);
			$idempotency_key = 'bachs-donation-' . substr( hash( 'sha256', $donation['reference'] ), 0, 48 );
			$session = $gateway->create_checkout_session( $payload, $idempotency_key );
			$checkout_id = isset( $session['checkout_id'] ) && is_scalar( $session['checkout_id'] ) ? sanitize_text_field( (string) $session['checkout_id'] ) : '';
			$checkout_url = isset( $session['checkout_url'] ) && is_string( $session['checkout_url'] ) ? esc_url_raw( $session['checkout_url'] ) : '';
			if ( '' === $checkout_id || ! $gateway->is_checkout_url_allowed( $checkout_url ) ) {
				DonationsStore::set_status( $donation['id'], 'session_error' );
				self::log_failure( 'invalid_checkout_session' );
				self::redirect_with_result( $return_url, 'unavailable', $token );
			}
			if ( 1 !== (int) DonationsStore::set_checkout( $donation['id'], $checkout_id ) ) {
				DonationsStore::set_status( $donation['id'], 'session_error' );
				self::log_failure( 'checkout_id_not_stored' );
				self::redirect_with_result( $return_url, 'unavailable', $token );
			}
			wp_redirect( $checkout_url, 303, 'Bachs Donations' );
			exit;
		} catch ( ApiException $exception ) {
			if ( isset( $donation['id'] ) ) {
				DonationsStore::set_status( $donation['id'], 'session_error' );
			}
			self::log_failure( $exception->get_error_code() );
			self::redirect_with_result( $return_url, 'unavailable', isset( $token ) ? $token : '' );
		} catch ( \Throwable $exception ) {
			self::log_failure( get_class( $exception ) );
			self::redirect_with_result( $return_url, 'unavailable', isset( $token ) ? $token : '' );
		}
	}

	public static function normalize_amount( $amount ) {
		$amount = trim( (string) $amount );
		if ( ! preg_match( '/^\d{1,12}(?:\.\d{1,2})?$/', $amount ) ) {
			return false;
		}
		$parts = explode( '.', $amount, 2 );
		$whole = ltrim( $parts[0], '0' );
		$whole = '' === $whole ? '0' : $whole;
		$decimal = isset( $parts[1] ) ? $parts[1] : '';
		$normalized = $whole . '.' . str_pad( $decimal, 2, '0' );
		return preg_match( '/[1-9]/', str_replace( '.', '', $normalized ) ) ? $normalized : false;
	}

	public static function is_bachs_checkout_url( $url ) {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& 'https' === strtolower( $parts['scheme'] )
			&& 'checkout.bachs.io' === strtolower( $parts['host'] )
			&& ! isset( $parts['user'], $parts['pass'], $parts['port'] );
	}

	public static function return_url() {
		$referer = wp_get_referer();
		if ( ! is_string( $referer ) || '' === $referer ) {
			return home_url( '/' );
		}
		return wp_validate_redirect( $referer, home_url( '/' ) );
	}

	private static function redirect_with_result( $url, $result, $token = '' ) {
		$args = array( 'bachs_donation_result' => sanitize_key( $result ) );
		if ( is_string( $token ) && preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			$args['bachs_donation'] = $token;
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	private static function log_failure( $code ) {
		error_log( '[Bachs Donations] Checkout request failed (' . sanitize_key( $code ) . ').' );
	}

	/** Keep automated checkout creation to ten submissions per address per 15 minutes. */
	private static function rate_limited() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';
		if ( '' === $ip ) {
			return false;
		}
		$key = 'bachs_donation_rate_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= 10 ) {
			return true;
		}
		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		return false;
	}
}
