<?php
/**
 * Public shortcode for a hosted donation form and return status.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DonationShortcode {
	public static function register_hooks() {
		add_shortcode( 'bachs_donation_form', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		ob_start();
		if ( isset( $_GET['bachs_donation'] ) && is_scalar( $_GET['bachs_donation'] ) ) {
			self::render_return_state( sanitize_text_field( (string) wp_unslash( $_GET['bachs_donation'] ) ) );
		}
		if ( isset( $_GET['bachs_donation_cancel'] ) && is_scalar( $_GET['bachs_donation_cancel'] ) ) {
			self::render_cancel_notice();
		}
		if ( isset( $_GET['bachs_donation_result'] ) && is_scalar( $_GET['bachs_donation_result'] ) ) {
			self::render_result_notice( sanitize_key( (string) wp_unslash( $_GET['bachs_donation_result'] ) ) );
		}
		self::render_form();
		return ob_get_clean();
	}

	private static function render_form() {
		if ( ! Settings::is_configured() ) {
			echo '<p class="bachs-donation-notice">' . esc_html__( 'Donations are temporarily unavailable. Please try again later.', 'bachs-donations' ) . '</p>';
			return;
		}
		$currencies = Settings::enabled_currencies();
		$presets = array(
			'USD' => array( '10.00', '25.00', '50.00', '100.00' ),
			'NGN' => array( '5000.00', '10000.00', '25000.00', '50000.00' ),
		);
		echo '<form class="bachs-donation-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bachs_donation_start">';
		wp_nonce_field( 'bachs_donation_start' );
		echo '<p><label for="bachs-donation-currency">' . esc_html__( 'Donation currency', 'bachs-donations' ) . '</label><br>';
		if ( 1 === count( $currencies ) ) {
			echo '<input type="hidden" name="currency" value="' . esc_attr( $currencies[0] ) . '"><strong>' . esc_html( $currencies[0] ) . '</strong></p>';
		} else {
			echo '<select id="bachs-donation-currency" name="currency">';
			foreach ( $currencies as $currency ) {
				echo '<option value="' . esc_attr( $currency ) . '" ' . selected( Settings::default_currency(), $currency, false ) . '>' . esc_html( 'USD' === $currency ? 'US Dollar (USD)' : 'Nigerian Naira (NGN)' ) . '</option>';
			}
			echo '</select></p>';
		}
		echo '<p><label for="bachs-donation-preset">' . esc_html__( 'Choose an amount', 'bachs-donations' ) . '</label><br><select id="bachs-donation-preset" name="preset_amount"><option value="">' . esc_html__( 'Choose a preset or enter a custom amount', 'bachs-donations' ) . '</option>';
		foreach ( $currencies as $currency ) {
			echo '<optgroup label="' . esc_attr( $currency ) . '">';
			foreach ( $presets[ $currency ] as $amount ) {
				echo '<option value="' . esc_attr( $currency . ':' . $amount ) . '">' . esc_html( $currency . ' ' . number_format_i18n( (float) $amount, 2 ) ) . '</option>';
			}
			echo '</optgroup>';
		}
		echo '</select></p>';
		echo '<p><label for="bachs-donation-custom-amount">' . esc_html__( 'Custom amount', 'bachs-donations' ) . '</label><br><input id="bachs-donation-custom-amount" type="number" name="custom_amount" min="0.01" step="0.01" inputmode="decimal"></p>';
		echo '<p><label for="bachs-donation-name">' . esc_html__( 'Your name (optional)', 'bachs-donations' ) . '</label><br><input id="bachs-donation-name" type="text" name="name" maxlength="191" autocomplete="name"></p>';
		echo '<p><label for="bachs-donation-email">' . esc_html__( 'Email for your donation receipt', 'bachs-donations' ) . '</label><br><input required id="bachs-donation-email" type="email" name="email" maxlength="191" autocomplete="email"></p>';
		echo '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden"><label for="bachs-donation-website">' . esc_html__( 'Leave this field empty', 'bachs-donations' ) . '</label><input id="bachs-donation-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>';
		echo '<p class="bachs-donation-privacy">' . esc_html__( 'Your donation is processed on Bachs secure checkout. Your name and email are shared with Bachs to process the donation and receipt.', 'bachs-donations' ) . '</p>';
		echo '<p><button type="submit">' . esc_html__( 'Continue to secure payment', 'bachs-donations' ) . '</button></p></form>';
	}

	private static function render_return_state( $token ) {
		$donation = DonationsStore::find_by_token( $token );
		if ( ! is_array( $donation ) ) {
			return;
		}
		if ( 'pending' === $donation['status'] && '' !== $donation['checkout_id'] ) {
			Reconciler::refresh( $donation );
			$donation = DonationsStore::find_by_id( $donation['id'] );
		}
		if ( 'paid' === $donation['status'] ) {
			echo '<div class="bachs-donation-notice bachs-donation-success" role="status"><p><strong>' . esc_html__( 'Thank you. Your donation is confirmed.', 'bachs-donations' ) . '</strong></p><p>' . esc_html( $donation['currency'] . ' ' . number_format_i18n( (float) $donation['amount'], 2 ) ) . '</p></div>';
		} elseif ( 'failed' === $donation['status'] || 'expired' === $donation['status'] ) {
			echo '<div class="bachs-donation-notice bachs-donation-error" role="status"><p>' . esc_html__( 'We could not confirm this donation. You can try again using the form below.', 'bachs-donations' ) . '</p></div>';
		} else {
			echo '<div class="bachs-donation-notice" role="status"><p>' . esc_html__( 'Your payment is awaiting confirmation from Bachs. Refresh this page in a moment to check the status.', 'bachs-donations' ) . '</p></div>';
		}
	}

	private static function render_result_notice( $result ) {
		$messages = array(
			'invalid' => __( 'Check the amount, currency, and email address, then try again.', 'bachs-donations' ),
			'unavailable' => __( 'We could not start secure checkout. Please try again or contact the organization.', 'bachs-donations' ),
			'rate_limited' => __( 'Too many donation attempts came from this connection. Please wait a few minutes and try again.', 'bachs-donations' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="bachs-donation-notice bachs-donation-error" role="alert"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}

	private static function render_cancel_notice() {
		echo '<div class="bachs-donation-notice" role="status"><p>' . esc_html__( 'The checkout was not completed. You can make another donation below whenever you are ready.', 'bachs-donations' ) . '</p></div>';
	}
}
