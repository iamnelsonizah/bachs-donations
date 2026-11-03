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
		add_submenu_page(
			'bachs-donations',
			__( 'Payment Setup', 'bachs-donations' ),
			__( 'Payment Setup', 'bachs-donations' ),
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
		echo '<div class="wrap bachs-fundraising bachs-payment-setup"><h1>' . esc_html__( 'Connect Bachs', 'bachs-donations' ) . '</h1>';
		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Donation settings saved.', 'bachs-donations' ) . '</p></div>';
		}
		echo '<p class="bachs-payment-intro">' . esc_html__( 'Connect your organization’s Bachs account so donation forms can create secure hosted checkouts and confirm gifts automatically.', 'bachs-donations' ) . '</p>';
		$current_key = self::api_key();
		$key_prefix = 'live' === $environment ? 'sk_live_' : 'sk_sandbox_';
		$key_ready = 0 === strpos( $current_key, $key_prefix );
		$webhook_ready = ! empty( self::webhook_secrets() );
		$connection_ready = $key_ready && $webhook_ready;
		$connection_message = 'sandbox' === $environment
			? __( 'Sandbox is selected. Complete a test donation and verify its webhook before switching to Live.', 'bachs-donations' )
			: __( 'Live is selected. Confirm that the Live webhook is registered and verified before accepting real donations.', 'bachs-donations' );
		$connection_status = 'sandbox' === $environment ? __( 'Ready for Sandbox test', 'bachs-donations' ) : __( 'Live credentials saved', 'bachs-donations' );
		echo '<section class="bachs-connection-card"><span class="bachs-connection-card__icon dashicons dashicons-admin-links" aria-hidden="true"></span><div><h2>' . esc_html( $connection_ready ? __( 'Bachs credentials are saved', 'bachs-donations' ) : __( 'Finish connecting Bachs', 'bachs-donations' ) ) . '</h2><p>' . esc_html( $connection_ready ? $connection_message : sprintf( __( 'Add the %1$s API key and webhook signing secret below. You can create them in the Bachs Developer Portal.', 'bachs-donations' ), ucfirst( $environment ) ) ) . '</p><a href="https://docs.bachs.io/developer-portal/api-keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to create an API key', 'bachs-donations' ) . ' <span aria-hidden="true">↗</span></a></div><span class="bachs-connection-card__status ' . ( $connection_ready ? 'is-ready' : 'is-needed' ) . '">' . esc_html( $connection_ready ? $connection_status : __( 'Setup needed', 'bachs-donations' ) ) . '</span></section>';
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
			echo '<tr><th colspan="2"><h2>' . esc_html( sprintf( __( '%s credentials', 'bachs-donations' ), $label ) ) . '</h2><p class="description">' . esc_html( 'sandbox' === $env ? __( 'Use these while testing. Sandbox donations do not charge real funds.', 'bachs-donations' ) : __( 'Use Live only after a successful Sandbox donation and webhook check.', 'bachs-donations' ) ) . '</p></th></tr>';
			echo '<tr><th><label for="bachs-' . esc_attr( $env ) . '-key">' . esc_html__( 'Secret API key', 'bachs-donations' ) . '</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="bachs-' . esc_attr( $env ) . '-key" name="settings[' . esc_attr( $env ) . '_secret_key]" value="" placeholder="' . esc_attr( 'sandbox' === $env ? 'sk_sandbox_…' : 'sk_live_…' ) . '"><p class="description">' . esc_html( $key_exists ? __( 'A key is saved. Leave blank to keep it unchanged.', 'bachs-donations' ) : __( 'No key is saved yet. Paste the secret key from Bachs here.', 'bachs-donations' ) ) . '</p></td></tr>';
			echo '<tr><th><label for="bachs-' . esc_attr( $env ) . '-webhook">' . esc_html__( 'Webhook signing secret', 'bachs-donations' ) . '</label></th><td><input class="regular-text" type="password" autocomplete="new-password" id="bachs-' . esc_attr( $env ) . '-webhook" name="settings[' . esc_attr( $env ) . '_webhook_secret]" value="" placeholder="whsec_…"><p class="description">' . esc_html( $webhook_exists ? __( 'A secret is saved. Leave blank to keep it unchanged.', 'bachs-donations' ) : __( 'Create a webhook endpoint in Bachs and paste its signing secret here.', 'bachs-donations' ) ) . '</p></td></tr>';
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
		$webhook_url = rest_url( 'bachs-donations/v1/webhook' );
		echo '</tbody></table><section class="bachs-webhook-setup"><h2>' . esc_html__( 'Webhook confirmation', 'bachs-donations' ) . '</h2><p>' . esc_html__( 'In Bachs, add this URL as a webhook endpoint and subscribe it to the events below. Then paste the endpoint’s signing secret above.', 'bachs-donations' ) . '</p><div class="bachs-webhook-copy"><code>' . esc_html( $webhook_url ) . '</code><button type="button" class="button" data-bachs-copy="' . esc_attr( $webhook_url ) . '">' . esc_html__( 'Copy URL', 'bachs-donations' ) . '</button></div><p><strong>' . esc_html__( 'Events:', 'bachs-donations' ) . '</strong> <code>checkout.completed</code>, <code>checkout.expired</code>, <code>collection.succeeded</code></p></section>';
		echo '<p class="description">' . esc_html__( 'Payment details are entered on Bachs hosted checkout. This plugin never collects or stores card numbers.', 'bachs-donations' ) . '</p>';
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
				if ( strlen( $secret ) > 4096 || preg_match( '/[\x00-\x1F\x7F]/', $secret ) ) {
					wp_die( esc_html__( 'The webhook secret contains invalid characters or is too long.', 'bachs-donations' ) );
				}
				$settings[ $secret_name ] = substr( sanitize_text_field( $secret ), 0, 255 );
			}
		}
		if ( ! empty( $settings['currencies'] ) && ! in_array( $settings['default_currency'], $settings['currencies'], true ) ) {
			$settings['default_currency'] = $settings['currencies'][0];
		}
		update_option( self::OPTION, $settings, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'bachs-donations-settings', 'settings-updated' => '1' ), admin_url( 'admin.php' ) ) );
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
		$environment = self::environment();
		$expected_prefix = 'live' === $environment ? 'sk_live_' : 'sk_sandbox_';
		return 0 === strpos( self::api_key(), $expected_prefix ) && ! empty( self::webhook_secrets() );
	}
}
