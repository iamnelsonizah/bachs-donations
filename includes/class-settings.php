<?php
/**
 * Donation plugin settings and admin screen.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	const OPTION = 'bachs_donations_settings';

	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_bachs_donations_save_settings', array( __CLASS__, 'save' ) );
	}

	public static function register_page() {
		add_options_page(
			__( 'Bachs Donations', 'bachs-donations' ),
			__( 'Bachs Donations', 'bachs-donations' ),
			'manage_options',
			'bachs-donations-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage donation settings.', 'bachs-donations' ) );
		}
		$options = self::all();
		$environment = self::environment();
		echo '<div class="wrap"><h1>' . esc_html__( 'Bachs Donations', 'bachs-donations' ) . '</h1>';
		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Donation settings saved.', 'bachs-donations' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Configure a restricted Bachs API key, webhook secret, and the currencies your donation form can accept.', 'bachs-donations' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bachs_donations_save_settings">';
		wp_nonce_field( 'bachs_donations_save_settings' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="bachs-donations-environment">' . esc_html__( 'Environment', 'bachs-donations' ) . '</label></th><td><select id="bachs-donations-environment" name="settings[environment]">';
		foreach ( array( 'sandbox' => __( 'Sandbox', 'bachs-donations' ), 'live' => __( 'Live', 'bachs-donations' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $environment, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		foreach ( array( 'sandbox' => __( 'Sandbox', 'bachs-donations' ), 'live' => __( 'Live', 'bachs-donations' ) ) as $env => $label ) {
			$key_exists = defined( 'live' === $env ? 'BACHS_DONATIONS_LIVE_SECRET_KEY' : 'BACHS_DONATIONS_SANDBOX_SECRET_KEY' ) || ! empty( $options[ $env . '_secret_key' ] );
			$webhook_exists = defined( 'live' === $env ? 'BACHS_DONATIONS_LIVE_WEBHOOK_SECRET' : 'BACHS_DONATIONS_SANDBOX_WEBHOOK_SECRET' ) || ! empty( $options[ $env . '_webhook_secret' ] );
			echo '<tr><th colspan="2"><h2>' . esc_html( sprintf( __( '%s credentials', 'bachs-donations' ), $label ) ) . '</h2></th></tr>';
			echo '<tr><th><label for="bachs-' . esc_attr( $env ) . '-key">' . esc_html__( 'Secret API key', 'bachs-donations' ) . '</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="bachs-' . esc_attr( $env ) . '-key" name="settings[' . esc_attr( $env ) . '_secret_key]" value=""><p class="description">' . esc_html( $key_exists ? __( 'A key is saved. Leave blank to keep it unchanged.', 'bachs-donations' ) : __( 'No key is saved yet.', 'bachs-donations' ) ) . '</p></td></tr>';
			echo '<tr><th><label for="bachs-' . esc_attr( $env ) . '-webhook">' . esc_html__( 'Webhook signing secret', 'bachs-donations' ) . '</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="bachs-' . esc_attr( $env ) . '-webhook" name="settings[' . esc_attr( $env ) . '_webhook_secret]" value=""><p class="description">' . esc_html( $webhook_exists ? __( 'A secret is saved. Leave blank to keep it unchanged.', 'bachs-donations' ) : __( 'No webhook secret is saved yet.', 'bachs-donations' ) ) . '</p></td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Donation currencies', 'bachs-donations' ) . '</th><td>';
		foreach ( array( 'USD' => __( 'US dollars (USD)', 'bachs-donations' ), 'NGN' => __( 'Nigerian naira (NGN)', 'bachs-donations' ) ) as $code => $label ) {
			$enabled = in_array( $code, self::enabled_currencies(), true );
			echo '<label style="display:inline-block;margin-right:18px"><input type="checkbox" name="settings[currencies][]" value="' . esc_attr( $code ) . '" ' . checked( $enabled, true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'These are donor charge currencies. USD uses the USD card corridor. NGN offers NGN card and bank transfer when enabled on your Bachs account. Bachs controls settlement into your balance and payout eligibility.', 'bachs-donations' ) . '</p></td></tr>';
		echo '<tr><th><label for="bachs-donations-default-currency">' . esc_html__( 'Default currency', 'bachs-donations' ) . '</label></th><td><select id="bachs-donations-default-currency" name="settings[default_currency]">';
		foreach ( array( 'USD', 'NGN' ) as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( self::default_currency(), $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</tbody></table><p>' . esc_html__( 'The Bachs webhook URL for the selected environment is:', 'bachs-donations' ) . ' <code>' . esc_html( rest_url( 'bachs-donations/v1/webhook' ) ) . '</code></p>';
		echo '<p>' . esc_html__( 'Subscribe this endpoint to checkout.completed, checkout.expired, and collection.succeeded. A donation is marked paid only after a valid signed event reports a succeeded payment and the amount and currency match the donation.', 'bachs-donations' ) . '</p>';
		submit_button( __( 'Save donation settings', 'bachs-donations' ) );
		echo '</form></div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to save donation settings.', 'bachs-donations' ) );
		}
		check_admin_referer( 'bachs_donations_save_settings' );
		$posted = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$settings = self::all();
		$environment = isset( $posted['environment'] ) && is_scalar( $posted['environment'] ) ? sanitize_key( (string) $posted['environment'] ) : 'sandbox';
		$settings['environment'] = in_array( $environment, array( 'sandbox', 'live' ), true ) ? $environment : 'sandbox';
		$currencies = array();
		if ( isset( $posted['currencies'] ) && is_array( $posted['currencies'] ) ) {
			foreach ( $posted['currencies'] as $currency ) {
				if ( is_scalar( $currency ) ) {
					$currencies[] = strtoupper( sanitize_text_field( (string) $currency ) );
				}
			}
		}
		$currencies = array_values( array_intersect( array_unique( $currencies ), array( 'USD', 'NGN' ) ) );
		if ( empty( $currencies ) ) {
			$currencies = array( 'USD' );
		}
		$settings['currencies'] = $currencies;
		$default = isset( $posted['default_currency'] ) && is_scalar( $posted['default_currency'] ) ? strtoupper( sanitize_text_field( (string) $posted['default_currency'] ) ) : 'USD';
		$settings['default_currency'] = in_array( $default, $currencies, true ) ? $default : $currencies[0];

		foreach ( array( 'sandbox', 'live' ) as $env ) {
			$key_name = $env . '_secret_key';
			$secret_name = $env . '_webhook_secret';
			$key = isset( $posted[ $key_name ] ) && is_scalar( $posted[ $key_name ] ) ? trim( (string) $posted[ $key_name ] ) : '';
			$secret = isset( $posted[ $secret_name ] ) && is_scalar( $posted[ $secret_name ] ) ? trim( (string) $posted[ $secret_name ] ) : '';
			$key_pattern = '/^sk_' . preg_quote( $env, '/' ) . '_[A-Za-z0-9_-]{8,}$/';
			if ( '' !== $key && ! preg_match( $key_pattern, $key ) ) {
				wp_die( esc_html__( 'The API key format is invalid.', 'bachs-donations' ) );
			}
			if ( '' !== $key ) {
				$settings[ $key_name ] = $key;
			}
			if ( '' !== $secret ) {
				$settings[ $secret_name ] = substr( sanitize_text_field( $secret ), 0, 255 );
			}
		}
		if ( ! empty( $settings['currencies'] ) && ! in_array( $settings['default_currency'], $settings['currencies'], true ) ) {
			$settings['default_currency'] = $settings['currencies'][0];
		}
		update_option( self::OPTION, $settings, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'bachs-donations-settings', 'settings-updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function all() {
		$options = get_option( self::OPTION, array() );
		return is_array( $options ) ? $options : array();
	}

	public static function environment() {
		$environment = defined( 'BACHS_DONATIONS_ENVIRONMENT' ) ? constant( 'BACHS_DONATIONS_ENVIRONMENT' ) : ( isset( self::all()['environment'] ) ? self::all()['environment'] : 'sandbox' );
		return in_array( $environment, array( 'sandbox', 'live' ), true ) ? $environment : 'sandbox';
	}

	public static function api_key() {
		$environment = self::environment();
		$constant = 'live' === $environment ? 'BACHS_DONATIONS_LIVE_SECRET_KEY' : 'BACHS_DONATIONS_SANDBOX_SECRET_KEY';
		$key = defined( $constant ) ? constant( $constant ) : ( isset( self::all()[ $environment . '_secret_key' ] ) ? self::all()[ $environment . '_secret_key' ] : '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	public static function webhook_secrets() {
		$environment = self::environment();
		$current_constant = 'live' === $environment ? 'BACHS_DONATIONS_LIVE_WEBHOOK_SECRET' : 'BACHS_DONATIONS_SANDBOX_WEBHOOK_SECRET';
		$previous_constant = 'live' === $environment ? 'BACHS_DONATIONS_LIVE_WEBHOOK_SECRET_PREVIOUS' : 'BACHS_DONATIONS_SANDBOX_WEBHOOK_SECRET_PREVIOUS';
		$options = self::all();
		$current = defined( $current_constant ) ? constant( $current_constant ) : ( isset( $options[ $environment . '_webhook_secret' ] ) ? $options[ $environment . '_webhook_secret' ] : '' );
		$previous = defined( $previous_constant ) ? constant( $previous_constant ) : '';
		$secrets = array_filter( array_map( 'trim', array( is_string( $current ) ? $current : '', is_string( $previous ) ? $previous : '' ) ) );
		return array_values( array_unique( $secrets ) );
	}

	public static function enabled_currencies() {
		$options = self::all();
		$stored = isset( $options['currencies'] ) && is_array( $options['currencies'] ) ? $options['currencies'] : array( 'USD', 'NGN' );
		$currencies = array();
		foreach ( $stored as $currency ) {
			if ( is_string( $currency ) && in_array( $currency, array( 'USD', 'NGN' ), true ) ) {
				$currencies[] = $currency;
			}
		}
		$currencies = array_values( array_unique( $currencies ) );
		return empty( $currencies ) ? array( 'USD' ) : $currencies;
	}

	public static function default_currency() {
		$options = self::all();
		$default = isset( $options['default_currency'] ) && is_string( $options['default_currency'] ) ? strtoupper( $options['default_currency'] ) : 'USD';
		return in_array( $default, self::enabled_currencies(), true ) ? $default : self::enabled_currencies()[0];
	}

	public static function is_configured() {
		return '' !== self::api_key() && ! empty( self::webhook_secrets() );
	}
}
