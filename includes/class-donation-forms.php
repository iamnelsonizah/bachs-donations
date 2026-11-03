<?php
/**
 * Donation form content type and per-form configuration.
 *
 * Each donation form is a public WordPress post managed through the focused
 * fundraising builder. Payment credentials remain global, while giving
 * options, donor fields, and appearance can be set for each fundraiser.
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

	/** Connect the post type and public form page. */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_public_form' ) );
	}

	/** Register public donation pages while keeping creation in the fundraiser builder. */
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
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'donate', 'with_front' => false ),
				'menu_icon'          => 'dashicons-heart',
				'map_meta_cap'       => true,
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);
	}

	/** Sanitize and store all payment, field, goal, and visual options for a form. */
	public static function save_settings( $post_id, $posted, $builder_submission = false ) {
		$posted = is_array( $posted ) ? $posted : array();
		$submitted_amounts = isset( $posted['amounts'] ) && is_array( $posted['amounts'] ) ? $posted['amounts'] : array();
		$submitted_methods = isset( $posted['payment_methods'] ) && is_array( $posted['payment_methods'] ) ? $posted['payment_methods'] : array();
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
			$value = isset( $submitted_amounts[ $currency ] ) && is_scalar( $submitted_amounts[ $currency ] ) ? (string) $submitted_amounts[ $currency ] : '';
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
			$submitted = isset( $submitted_methods[ $currency ] ) && is_array( $submitted_methods[ $currency ] ) ? $submitted_methods[ $currency ] : array();
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
		$accent_input = isset( $posted['accent_color'] ) && is_scalar( $posted['accent_color'] ) ? (string) $posted['accent_color'] : '';
		$accent_color = sanitize_hex_color( $accent_input );
		$custom_fields = isset( $posted['custom_fields'] ) && is_array( $posted['custom_fields'] ) ? self::sanitize_custom_fields( $posted['custom_fields'] ) : array();
		$form_style = isset( $posted['form_style'] ) && is_string( $posted['form_style'] ) && in_array( $posted['form_style'], array( 'soft', 'outlined', 'minimal' ), true ) ? $posted['form_style'] : 'soft';
		$corner_style = isset( $posted['corner_style'] ) && is_string( $posted['corner_style'] ) && in_array( $posted['corner_style'], array( 'rounded', 'square' ), true ) ? $posted['corner_style'] : 'rounded';
		$button_text = isset( $posted['button_text'] ) && is_scalar( $posted['button_text'] ) ? sanitize_text_field( (string) $posted['button_text'] ) : '';
		$button_text = '' !== $button_text ? wp_html_excerpt( $button_text, 48, '' ) : __( 'Continue to secure payment', 'bachs-donations' );

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
				'donor_name'       => isset( $posted['donor_name'] ) ? ! empty( $posted['donor_name'] ) : ! $builder_submission,
				'donor_name_required' => ! empty( $posted['donor_name_required'] ) && ( ! isset( $posted['donor_name'] ) || ! empty( $posted['donor_name'] ) ),
				'donor_phone'      => ! empty( $posted['donor_phone'] ),
				'donor_phone_required' => ! empty( $posted['donor_phone_required'] ) && ! empty( $posted['donor_phone'] ),
				'custom_fields'    => $custom_fields,
				'accent_color'     => $accent_color ? $accent_color : '#176b52',
				'form_style'       => $form_style,
				'corner_style'     => $corner_style,
				'button_text'      => $button_text,
			)
		);
	}

	/** Return safe form settings, applying global gateway currency restrictions. */
	public static function settings( $post_id = 0 ) {
		$stored = $post_id ? get_post_meta( $post_id, self::META_KEY, true ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		$stored_amount_config = isset( $stored['amounts'] ) && is_array( $stored['amounts'] ) ? $stored['amounts'] : array();
		$stored_method_config = isset( $stored['payment_methods'] ) && is_array( $stored['payment_methods'] ) ? $stored['payment_methods'] : array();
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
			if ( isset( $stored_amount_config[ $currency ] ) && is_array( $stored_amount_config[ $currency ] ) ) {
				$currency_amounts = array();
				foreach ( $stored_amount_config[ $currency ] as $amount ) {
					if ( is_scalar( $amount ) ) {
						$currency_amounts[] = (string) $amount;
					}
				}
				$amounts[ $currency ] = self::sanitize_amounts( implode( ',', $currency_amounts ) );
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
			$currency_methods = isset( $stored_method_config[ $currency ] ) && is_array( $stored_method_config[ $currency ] ) ? $stored_method_config[ $currency ] : $available_methods[ $currency ];
			$currency_methods = array_filter( $currency_methods, 'is_string' );
			$payment_methods[ $currency ] = array_values( array_intersect( $currency_methods, $available_methods[ $currency ] ) );
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
		$stored_accent = isset( $stored['accent_color'] ) && is_string( $stored['accent_color'] ) ? sanitize_hex_color( $stored['accent_color'] ) : false;
		$form_style = isset( $stored['form_style'] ) && is_string( $stored['form_style'] ) && in_array( $stored['form_style'], array( 'soft', 'outlined', 'minimal' ), true ) ? $stored['form_style'] : 'soft';
		$corner_style = isset( $stored['corner_style'] ) && is_string( $stored['corner_style'] ) && in_array( $stored['corner_style'], array( 'rounded', 'square' ), true ) ? $stored['corner_style'] : 'rounded';
		return array(
			'currencies'       => $currencies,
			'default_currency' => $default,
			'amounts'          => $amounts,
			'payment_methods'  => $payment_methods,
			'custom_amount'    => isset( $stored['custom_amount'] ) ? (bool) $stored['custom_amount'] : true,
			'goal_amount'      => $goal_amount,
			'goal_currency'    => $goal_currency,
			'donor_name'       => ! isset( $stored['donor_name'] ) || (bool) $stored['donor_name'],
			'donor_name_required' => isset( $stored['donor_name_required'] ) && (bool) $stored['donor_name_required'],
			'donor_phone'      => isset( $stored['donor_phone'] ) && (bool) $stored['donor_phone'],
			'donor_phone_required' => isset( $stored['donor_phone_required'] ) && (bool) $stored['donor_phone_required'],
			'custom_fields'    => isset( $stored['custom_fields'] ) && is_array( $stored['custom_fields'] ) ? self::sanitize_custom_fields( $stored['custom_fields'] ) : array(),
			'accent_color'     => $stored_accent ? $stored_accent : '#176b52',
			'form_style'       => $form_style,
			'corner_style'     => $corner_style,
			'button_text'      => isset( $stored['button_text'] ) && is_string( $stored['button_text'] ) ? wp_html_excerpt( sanitize_text_field( $stored['button_text'] ), 48, '' ) : __( 'Continue to secure payment', 'bachs-donations' ),
		);
	}

	/** Sanitize and bound custom donor questions stored with a form. */
	public static function sanitize_custom_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}
		$clean = array();
		$used_keys = array();
		foreach ( array_slice( $fields, 0, 10, true ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$label = isset( $field['label'] ) && is_scalar( $field['label'] ) ? wp_html_excerpt( sanitize_text_field( (string) $field['label'] ), 80, '' ) : '';
			$type = isset( $field['type'] ) && is_string( $field['type'] ) ? sanitize_key( $field['type'] ) : '';
			if ( '' === $label || ! in_array( $type, array( 'text', 'textarea', 'select', 'checkbox' ), true ) ) {
				continue;
			}
			$key = isset( $field['key'] ) && is_scalar( $field['key'] ) ? sanitize_key( (string) $field['key'] ) : '';
			if ( ! preg_match( '/^[a-f0-9]{12}$/', $key ) || isset( $used_keys[ $key ] ) ) {
				$key = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 );
			}
			$used_keys[ $key ] = true;
			$options = array();
			if ( 'select' === $type ) {
				$raw_options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : ( isset( $field['options'] ) && is_string( $field['options'] ) ? preg_split( '/[\r\n]+/', $field['options'] ) : array() );
				foreach ( array_slice( $raw_options, 0, 20 ) as $option ) {
					if ( is_scalar( $option ) ) {
						$option = wp_html_excerpt( sanitize_text_field( (string) $option ), 80, '' );
						if ( '' !== $option && ! in_array( $option, $options, true ) ) {
							$options[] = $option;
						}
					}
				}
				if ( count( $options ) < 2 ) {
					continue;
				}
			}
			$clean[] = array(
				'key'      => $key,
				'label'    => $label,
				'type'     => $type,
				'required' => ! empty( $field['required'] ),
				'options'  => $options,
			);
		}
		return $clean;
	}

	/** Choose readable button text for a user-selected brand color. */
	public static function contrast_text( $hex_color ) {
		$hex_color = sanitize_hex_color( $hex_color );
		if ( ! $hex_color ) {
			return '#ffffff';
		}
		$hex = ltrim( $hex_color, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$red = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$green = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$blue = hexdec( substr( $hex, 4, 2 ) ) / 255;
		$channels = array( $red, $green, $blue );
		foreach ( $channels as &$channel ) {
			$channel = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
		$dark_contrast = ( $luminance + 0.05 ) / 0.05;
		$light_contrast = 1.05 / ( $luminance + 0.05 );
		return $dark_contrast >= $light_contrast ? '#15231d' : '#ffffff';
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
