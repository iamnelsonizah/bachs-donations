<?php
/**
 * Donation form content type and per-form configuration.
 *
 * Each donation form is a normal WordPress page-like post: its title, story,
 * excerpt, and featured image are edited with the familiar WordPress editor.
 * Payment credentials remain global, while amounts and accepted currencies
 * can be adjusted for each fundraiser.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DonationForms {
	const POST_TYPE = 'bachs_donation_form';
	const META_KEY  = '_bachs_donations_form_settings';

	/** Connect the post type, editor settings, and public form page. */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'the_content', array( __CLASS__, 'append_public_form' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
	}

	/** Register a public donation-form post type with the block editor enabled. */
	public static function register_post_type() {
		$labels = array(
			'name'                  => __( 'Donation Forms', 'bachs-donations' ),
			'singular_name'         => __( 'Donation Form', 'bachs-donations' ),
			'menu_name'             => __( 'Donation Forms', 'bachs-donations' ),
			'name_admin_bar'        => __( 'Donation Form', 'bachs-donations' ),
			'add_new'               => __( 'Add New', 'bachs-donations' ),
			'add_new_item'          => __( 'Create Donation Form', 'bachs-donations' ),
			'edit_item'             => __( 'Edit Donation Form', 'bachs-donations' ),
			'new_item'              => __( 'New Donation Form', 'bachs-donations' ),
			'view_item'             => __( 'View Donation Form', 'bachs-donations' ),
			'all_items'             => __( 'All Donation Forms', 'bachs-donations' ),
			'search_items'          => __( 'Search Donation Forms', 'bachs-donations' ),
			'not_found'             => __( 'No donation forms found.', 'bachs-donations' ),
			'not_found_in_trash'    => __( 'No donation forms found in Trash.', 'bachs-donations' ),
			'featured_image'        => __( 'Form image', 'bachs-donations' ),
			'set_featured_image'    => __( 'Set form image', 'bachs-donations' ),
			'remove_featured_image' => __( 'Remove form image', 'bachs-donations' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => 'bachs-donations',
				'show_in_rest'       => true,
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'donate', 'with_front' => false ),
				'menu_icon'          => 'dashicons-heart',
				'map_meta_cap'       => true,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);
	}

	/** Add the campaign-specific checkout options to the editor. */
	public static function add_meta_box() {
		add_meta_box(
			'bachs-donation-form-options',
			__( 'Donation Form Settings', 'bachs-donations' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Render the settings box shown below the form story editor. */
	public static function render_meta_box( $post ) {
		$settings  = self::settings( (int) $post->ID );
		$available = Settings::enabled_currencies();
		wp_nonce_field( 'bachs_donation_form_save_' . (int) $post->ID, 'bachs_donation_form_nonce' );
		echo '<p>' . esc_html__( 'The form page uses your title, story, excerpt, and featured image. Configure its accepted currencies and suggested gifts here.', 'bachs-donations' ) . '</p>';
		echo '<fieldset><legend><strong>' . esc_html__( 'Accepted currencies', 'bachs-donations' ) . '</strong></legend>';
		foreach ( array( 'USD' => __( 'US Dollar (USD)', 'bachs-donations' ), 'NGN' => __( 'Nigerian Naira (NGN)', 'bachs-donations' ) ) as $currency => $label ) {
			$disabled = ! in_array( $currency, $available, true );
			echo '<label style="display:inline-block;margin:8px 18px 8px 0"><input type="checkbox" name="bachs_form[currencies][]" value="' . esc_attr( $currency ) . '" ' . checked( in_array( $currency, $settings['currencies'], true ), true, false ) . disabled( $disabled, true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'A currency must also be enabled in Fundraising → Bachs Donations before it can be accepted.', 'bachs-donations' ) . '</p></fieldset>';
		echo '<h4>' . esc_html__( 'Payment methods', 'bachs-donations' ) . '</h4>';
		if ( in_array( 'USD', $settings['currencies'], true ) ) {
			echo '<p><strong>' . esc_html__( 'USD', 'bachs-donations' ) . '</strong><br><label><input type="checkbox" name="bachs_form[payment_methods][USD][]" value="USD_CARD" ' . checked( in_array( 'USD_CARD', $settings['payment_methods']['USD'], true ), true, false ) . '> ' . esc_html__( 'Card', 'bachs-donations' ) . '</label></p>';
		}
		if ( in_array( 'NGN', $settings['currencies'], true ) ) {
			echo '<p><strong>' . esc_html__( 'NGN', 'bachs-donations' ) . '</strong><br><label style="margin-right:16px"><input type="checkbox" name="bachs_form[payment_methods][NGN][]" value="NGN_CARD" ' . checked( in_array( 'NGN_CARD', $settings['payment_methods']['NGN'], true ), true, false ) . '> ' . esc_html__( 'Card', 'bachs-donations' ) . '</label><label><input type="checkbox" name="bachs_form[payment_methods][NGN][]" value="NGN_BANK_TRANSFER" ' . checked( in_array( 'NGN_BANK_TRANSFER', $settings['payment_methods']['NGN'], true ), true, false ) . '> ' . esc_html__( 'Bank transfer', 'bachs-donations' ) . '</label></p>';
		}
		echo '<p class="description">' . esc_html__( 'A selected method is offered only when it is active for your Bachs account and eligible for the donation.', 'bachs-donations' ) . '</p>';

		echo '<p><label for="bachs-form-default-currency"><strong>' . esc_html__( 'Default currency', 'bachs-donations' ) . '</strong></label><br><select id="bachs-form-default-currency" name="bachs_form[default_currency]">';
		foreach ( $settings['currencies'] as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['default_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}
		echo '</select></p>';

		foreach ( array( 'USD' => __( 'Suggested USD amounts', 'bachs-donations' ), 'NGN' => __( 'Suggested NGN amounts', 'bachs-donations' ) ) as $currency => $label ) {
			echo '<p><label for="bachs-form-amounts-' . esc_attr( strtolower( $currency ) ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><input class="regular-text" id="bachs-form-amounts-' . esc_attr( strtolower( $currency ) ) . '" name="bachs_form[amounts][' . esc_attr( $currency ) . ']" value="' . esc_attr( implode( ', ', $settings['amounts'][ $currency ] ) ) . '"><br><span class="description">' . esc_html__( 'Separate amounts with commas. For example: 10, 25, 50, 100. Maximum eight amounts.', 'bachs-donations' ) . '</span></p>';
		}

		echo '<p><label><input type="checkbox" name="bachs_form[custom_amount]" value="1" ' . checked( $settings['custom_amount'], true, false ) . '> ' . esc_html__( 'Allow donors to enter a custom amount', 'bachs-donations' ) . '</label></p>';
		echo '<hr><h4>' . esc_html__( 'Optional fundraising goal', 'bachs-donations' ) . '</h4>';
		echo '<p><label for="bachs-form-goal-amount">' . esc_html__( 'Goal amount', 'bachs-donations' ) . '</label><br><input id="bachs-form-goal-amount" type="number" name="bachs_form[goal_amount]" min="0" step="0.01" value="' . esc_attr( $settings['goal_amount'] ) . '"> <select name="bachs_form[goal_currency]" aria-label="' . esc_attr__( 'Goal currency', 'bachs-donations' ) . '">';
		foreach ( $settings['currencies'] as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['goal_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}
		echo '</select></p><p class="description">' . esc_html__( 'The progress bar counts confirmed donations in this currency only. No currency conversion is assumed.', 'bachs-donations' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'Embed this form elsewhere:', 'bachs-donations' ) . '</strong> <code>[bachs_donation_form id="' . (int) $post->ID . '"]</code></p>';
	}

	/** Save sanitized form settings after checking the editor nonce and capability. */
	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['bachs_donation_form_nonce'] ) || ! is_scalar( $_POST['bachs_donation_form_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['bachs_donation_form_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'bachs_donation_form_save_' . (int) $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$posted = isset( $_POST['bachs_form'] ) && is_array( $_POST['bachs_form'] ) ? wp_unslash( $_POST['bachs_form'] ) : array();
		$global_currencies = Settings::enabled_currencies();
		$currencies = array();
		if ( isset( $posted['currencies'] ) && is_array( $posted['currencies'] ) ) {
			foreach ( $posted['currencies'] as $currency ) {
				if ( is_scalar( $currency ) ) {
					$currency = strtoupper( sanitize_text_field( (string) $currency ) );
					if ( in_array( $currency, $global_currencies, true ) ) {
						$currencies[] = $currency;
					}
				}
			}
		}
		$currencies = array_values( array_unique( $currencies ) );
		if ( empty( $currencies ) ) {
			$currencies = $global_currencies;
		}
		$default = isset( $posted['default_currency'] ) && is_scalar( $posted['default_currency'] ) ? strtoupper( sanitize_text_field( (string) $posted['default_currency'] ) ) : '';
		if ( ! in_array( $default, $currencies, true ) ) {
			$default = $currencies[0];
		}

		$amounts = array();
		foreach ( array( 'USD', 'NGN' ) as $currency ) {
			$value = isset( $posted['amounts'][ $currency ] ) && is_scalar( $posted['amounts'][ $currency ] ) ? (string) $posted['amounts'][ $currency ] : '';
			$amounts[ $currency ] = self::sanitize_amounts( $value );
		}
		$allow_custom = ! empty( $posted['custom_amount'] );
		$payment_methods = array(
			'USD' => array(),
			'NGN' => array(),
		);
		$available_methods = array(
			'USD' => array( 'USD_CARD' ),
			'NGN' => array( 'NGN_CARD', 'NGN_BANK_TRANSFER' ),
		);
		foreach ( array( 'USD', 'NGN' ) as $currency ) {
			$submitted = isset( $posted['payment_methods'][ $currency ] ) && is_array( $posted['payment_methods'][ $currency ] ) ? $posted['payment_methods'][ $currency ] : array();
			foreach ( $submitted as $method ) {
				if ( is_scalar( $method ) ) {
					$method = strtoupper( sanitize_text_field( (string) $method ) );
					if ( in_array( $method, $available_methods[ $currency ], true ) ) {
						$payment_methods[ $currency ][] = $method;
					}
				}
			}
			$payment_methods[ $currency ] = array_values( array_unique( $payment_methods[ $currency ] ) );
			if ( in_array( $currency, $currencies, true ) && empty( $payment_methods[ $currency ] ) ) {
				$payment_methods[ $currency ] = $available_methods[ $currency ];
			}
		}
		$default_amounts = array(
			'USD' => array( '10.00', '25.00', '50.00', '100.00' ),
			'NGN' => array( '5000.00', '10000.00', '25000.00', '50000.00' ),
		);
		if ( ! $allow_custom ) {
			foreach ( $currencies as $currency ) {
				if ( empty( $amounts[ $currency ] ) ) {
					$amounts[ $currency ] = $default_amounts[ $currency ];
				}
			}
		}
		$goal_amount = isset( $posted['goal_amount'] ) && is_scalar( $posted['goal_amount'] ) ? CheckoutController::normalize_amount( (string) $posted['goal_amount'] ) : false;
		$goal_currency = isset( $posted['goal_currency'] ) && is_scalar( $posted['goal_currency'] ) ? strtoupper( sanitize_text_field( (string) $posted['goal_currency'] ) ) : $default;
		if ( ! in_array( $goal_currency, $currencies, true ) ) {
			$goal_currency = $default;
		}

		update_post_meta(
			$post_id,
			self::META_KEY,
			array(
				'currencies'      => $currencies,
				'default_currency' => $default,
				'amounts'          => $amounts,
				'payment_methods'  => $payment_methods,
				'custom_amount'    => $allow_custom,
				'goal_amount'      => false === $goal_amount ? '' : $goal_amount,
				'goal_currency'    => $goal_currency,
			)
		);
	}

	/** Return safe form settings, applying global gateway currency restrictions. */
	public static function settings( $post_id = 0 ) {
		$stored = $post_id ? get_post_meta( $post_id, self::META_KEY, true ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		$global_currencies = Settings::enabled_currencies();
		$currencies = array();
		if ( isset( $stored['currencies'] ) && is_array( $stored['currencies'] ) ) {
			foreach ( $stored['currencies'] as $currency ) {
				if ( is_string( $currency ) && in_array( $currency, $global_currencies, true ) ) {
					$currencies[] = $currency;
				}
			}
		}
		$currencies = array_values( array_unique( empty( $currencies ) && ! isset( $stored['currencies'] ) ? $global_currencies : $currencies ) );
		if ( empty( $currencies ) ) {
			$currencies = $global_currencies;
		}
		$default = isset( $stored['default_currency'] ) && is_string( $stored['default_currency'] ) ? strtoupper( $stored['default_currency'] ) : Settings::default_currency();
		if ( ! in_array( $default, $currencies, true ) ) {
			$default = $currencies[0];
		}
		$amount_defaults = array(
			'USD' => array( '10.00', '25.00', '50.00', '100.00' ),
			'NGN' => array( '5000.00', '10000.00', '25000.00', '50000.00' ),
		);
		$amounts = array();
		foreach ( array( 'USD', 'NGN' ) as $currency ) {
			if ( isset( $stored['amounts'][ $currency ] ) && is_array( $stored['amounts'][ $currency ] ) ) {
				$stored_amounts = array();
				foreach ( $stored['amounts'][ $currency ] as $amount ) {
					if ( is_scalar( $amount ) ) {
						$stored_amounts[] = (string) $amount;
					}
				}
				$amounts[ $currency ] = self::sanitize_amounts( implode( ',', $stored_amounts ) );
			} else {
				$amounts[ $currency ] = $amount_defaults[ $currency ];
			}
		}
		$available_methods = array(
			'USD' => array( 'USD_CARD' ),
			'NGN' => array( 'NGN_CARD', 'NGN_BANK_TRANSFER' ),
		);
		$payment_methods = array();
		foreach ( array( 'USD', 'NGN' ) as $currency ) {
			$stored_methods = isset( $stored['payment_methods'][ $currency ] ) && is_array( $stored['payment_methods'][ $currency ] ) ? $stored['payment_methods'][ $currency ] : $available_methods[ $currency ];
			$stored_methods = array_filter( $stored_methods, 'is_string' );
			$payment_methods[ $currency ] = array_values( array_intersect( $stored_methods, $available_methods[ $currency ] ) );
			if ( in_array( $currency, $currencies, true ) && empty( $payment_methods[ $currency ] ) ) {
				$payment_methods[ $currency ] = $available_methods[ $currency ];
			}
		}
		$goal_amount = isset( $stored['goal_amount'] ) && is_string( $stored['goal_amount'] ) ? $stored['goal_amount'] : '';
		if ( false === CheckoutController::normalize_amount( $goal_amount ) ) {
			$goal_amount = '';
		}
		$goal_currency = isset( $stored['goal_currency'] ) && is_string( $stored['goal_currency'] ) ? strtoupper( $stored['goal_currency'] ) : $default;
		if ( ! in_array( $goal_currency, $currencies, true ) ) {
			$goal_currency = $default;
		}
		return array(
			'currencies'       => $currencies,
			'default_currency' => $default,
			'amounts'          => $amounts,
			'payment_methods'  => $payment_methods,
			'custom_amount'    => isset( $stored['custom_amount'] ) ? (bool) $stored['custom_amount'] : true,
			'goal_amount'      => $goal_amount,
			'goal_currency'    => $goal_currency,
		);
	}

	/** Append the donation form to a published form's normal WordPress content. */
	public static function append_public_form( $content ) {
		if ( is_singular( self::POST_TYPE ) && is_main_query() && in_the_loop() && 'publish' === get_post_status() && ! has_shortcode( $content, 'bachs_donation_form' ) ) {
			$image_id = get_post_thumbnail_id( get_the_ID() );
			if ( $image_id && false === strpos( $content, 'wp-image-' . $image_id ) ) {
				$content = get_the_post_thumbnail( get_the_ID(), 'large', array( 'class' => 'bachs-donation-featured-image', 'loading' => 'lazy' ) ) . $content;
			}
			$content .= do_shortcode( '[bachs_donation_form id="' . get_the_ID() . '"]' );
		}
		return $content;
	}

	/** Add useful publishing and fundraising details to the form list screen. */
	public static function admin_columns( $columns ) {
		$updated = array();
		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'title' === $key ) {
				$updated['bachs_currencies'] = __( 'Currencies', 'bachs-donations' );
				$updated['bachs_gifts'] = __( 'Confirmed gifts', 'bachs-donations' );
				$updated['bachs_raised'] = __( 'Raised', 'bachs-donations' );
				$updated['bachs_shortcode'] = __( 'Embed shortcode', 'bachs-donations' );
			}
		}
		return $updated;
	}

	/** Render per-form accepted currencies, confirmed totals, and embed code. */
	public static function render_admin_column( $column, $post_id ) {
		if ( 'bachs_currencies' === $column ) {
			$config = self::settings( $post_id );
			echo esc_html( implode( ', ', $config['currencies'] ) );
		} elseif ( 'bachs_gifts' === $column || 'bachs_raised' === $column ) {
			$summary = DonationsStore::campaign_summary( $post_id );
			if ( 'bachs_gifts' === $column ) {
				echo esc_html( number_format_i18n( $summary['count'] ) );
			} else {
				$totals = array();
				foreach ( $summary['totals'] as $currency => $amount ) {
					$totals[] = $currency . ' ' . number_format_i18n( $amount, 2 );
				}
				echo esc_html( empty( $totals ) ? '—' : implode( ' / ', $totals ) );
			}
		} elseif ( 'bachs_shortcode' === $column ) {
			echo '<code>[bachs_donation_form id="' . absint( $post_id ) . '"]</code>';
		}
	}

	/** Normalize comma/newline-separated amounts into safe preset values. */
	private static function sanitize_amounts( $value ) {
		$amounts = array();
		foreach ( preg_split( '/[\r\n,]+/', (string) $value ) as $candidate ) {
			$amount = CheckoutController::normalize_amount( trim( $candidate ) );
			if ( false !== $amount && ! in_array( $amount, $amounts, true ) ) {
				$amounts[] = $amount;
			}
			if ( count( $amounts ) >= 8 ) {
				break;
			}
		}
		return $amounts;
	}
}
