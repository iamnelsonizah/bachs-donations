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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	/** Enqueue the small, theme-friendly form stylesheet only where needed. */
	public static function enqueue_styles() {
		$should_enqueue = is_singular( DonationForms::POST_TYPE );
		if ( ! $should_enqueue && is_singular() ) {
			$post = get_post();
			$should_enqueue = $post instanceof \WP_Post && has_shortcode( $post->post_content, 'bachs_donation_form' );
		}
		if ( $should_enqueue ) {
			wp_enqueue_style( 'bachs-donations-form', plugins_url( 'assets/css/donation-form.css', BACHS_DONATIONS_FILE ), array(), BACHS_DONATIONS_VERSION );
			wp_enqueue_script( 'bachs-donations-form', plugins_url( 'assets/js/donation-form.js', BACHS_DONATIONS_FILE ), array(), BACHS_DONATIONS_VERSION, true );
		}
	}

	public static function render( $attributes = array() ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'bachs_donation_form' );
		$form_id = absint( $attributes['id'] );
		if ( $form_id && ( DonationForms::POST_TYPE !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) ) {
			return '';
		}
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
		self::render_form( $form_id );
		return ob_get_clean();
	}

	private static function render_form( $form_id = 0 ) {
		$config = DonationForms::settings( $form_id );
		if ( $form_id ) {
			self::render_goal( $form_id, $config );
		}
		if ( ! PaymentGateways::active()->is_configured() ) {
			echo '<p class="bachs-donation-notice" role="status">' . esc_html__( 'Donations are temporarily unavailable. Please try again later.', 'bachs-donations' ) . '</p>';
			return;
		}
		$currencies = $config['currencies'];
		$form_key = wp_unique_id( 'bachs-donation-' . ( $form_id ? $form_id : 'general' ) . '-' );
		echo '<form class="bachs-donation-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bachs_donation_start">';
		echo '<input type="hidden" name="campaign_id" value="' . esc_attr( (string) $form_id ) . '">';
		wp_nonce_field( 'bachs_donation_start' );
		if ( 1 === count( $currencies ) ) {
			echo '<p><span class="bachs-donation-currency-label">' . esc_html__( 'Donation currency', 'bachs-donations' ) . '</span><br><input id="' . esc_attr( $form_key . 'currency' ) . '" type="hidden" name="currency" value="' . esc_attr( $currencies[0] ) . '"><strong>' . esc_html( $currencies[0] ) . '</strong></p>';
		} else {
			echo '<p><label for="' . esc_attr( $form_key . 'currency' ) . '">' . esc_html__( 'Donation currency', 'bachs-donations' ) . '</label><br><select id="' . esc_attr( $form_key . 'currency' ) . '" name="currency" required>';
			foreach ( $currencies as $currency ) {
				echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $config['default_currency'], $currency, false ) . '>' . esc_html( 'USD' === $currency ? 'US Dollar (USD)' : 'Nigerian Naira (NGN)' ) . '</option>';
			}
			echo '</select></p>';
		}
		$has_presets = false;
		foreach ( $currencies as $currency ) {
			$has_presets = $has_presets || ! empty( $config['amounts'][ $currency ] );
		}
		if ( $has_presets ) {
			echo '<fieldset class="bachs-donation-amounts"><legend>' . esc_html__( 'Choose a suggested amount', 'bachs-donations' ) . '</legend><div class="bachs-donation-amount-grid">';
			foreach ( $currencies as $currency ) {
				if ( empty( $config['amounts'][ $currency ] ) ) {
					continue;
				}
				foreach ( $config['amounts'][ $currency ] as $index => $amount ) {
					$choice_id = $form_key . 'amount-' . strtolower( $currency ) . '-' . absint( $index );
					echo '<label class="bachs-donation-amount-choice" data-currency="' . esc_attr( $currency ) . '" for="' . esc_attr( $choice_id ) . '"><input id="' . esc_attr( $choice_id ) . '" type="radio" name="preset_amount" value="' . esc_attr( $currency . ':' . $amount ) . '"><span>' . esc_html( $currency . ' ' . number_format_i18n( (float) $amount, 2 ) ) . '</span></label>';
				}
			}
			echo '</div></fieldset>';
		}
		if ( $config['custom_amount'] ) {
			echo '<p class="bachs-donation-custom"><label for="' . esc_attr( $form_key . 'custom' ) . '">' . esc_html__( 'Or enter a different amount', 'bachs-donations' ) . '</label><br><input id="' . esc_attr( $form_key . 'custom' ) . '" type="number" name="custom_amount" min="0.01" step="0.01" inputmode="decimal"></p>';
		}
		echo '<p class="bachs-donation-validation" role="alert" hidden>' . esc_html__( 'Choose a suggested amount or enter a custom amount to continue.', 'bachs-donations' ) . '</p>';
		echo '<p><label for="' . esc_attr( $form_key . 'name' ) . '">' . esc_html__( 'Your name (optional)', 'bachs-donations' ) . '</label><br><input id="' . esc_attr( $form_key . 'name' ) . '" type="text" name="name" maxlength="191" autocomplete="name"></p>';
		echo '<p><label for="' . esc_attr( $form_key . 'email' ) . '">' . esc_html__( 'Email address', 'bachs-donations' ) . '</label><br><input required id="' . esc_attr( $form_key . 'email' ) . '" type="email" name="email" maxlength="191" autocomplete="email"></p>';
		echo '<div class="bachs-donation-honeypot" aria-hidden="true"><label for="' . esc_attr( $form_key . 'website' ) . '">' . esc_html__( 'Leave this field empty', 'bachs-donations' ) . '</label><input id="' . esc_attr( $form_key . 'website' ) . '" name="website" type="text" tabindex="-1" autocomplete="off"></div>';
		echo '<p class="bachs-donation-privacy">' . esc_html__( 'Payment is completed on the secure checkout hosted by our payment provider. Your name and email are shared with the provider to process your donation.', 'bachs-donations' ) . '</p>';
		echo '<p><button type="submit">' . esc_html__( 'Continue to secure payment', 'bachs-donations' ) . '</button></p></form>';
	}

	/** Show confirmed progress in the fundraiser's goal currency only. */
	private static function render_goal( $form_id, $config ) {
		if ( '' === $config['goal_amount'] ) {
			return;
		}
		$current = DonationsStore::campaign_total( $form_id, $config['goal_currency'] );
		$target = (float) $config['goal_amount'];
		$percent = $target > 0 ? min( 100, ( $current / $target ) * 100 ) : 0;
		echo '<div class="bachs-donation-goal"><p><strong>' . esc_html( $config['goal_currency'] . ' ' . number_format_i18n( $current, 2 ) ) . '</strong> ' . esc_html__( 'raised of', 'bachs-donations' ) . ' ' . esc_html( $config['goal_currency'] . ' ' . number_format_i18n( $target, 2 ) ) . '</p><progress value="' . esc_attr( (string) $percent ) . '" max="100" aria-label="' . esc_attr( sprintf( __( '%1$s percent of the %2$s goal raised', 'bachs-donations' ), number_format_i18n( $percent, 0 ), $config['goal_currency'] ) ) . '">' . esc_html( number_format_i18n( $percent, 0 ) ) . '%</progress></div>';
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
