<?php
/**
 * Fundraiser management screen and accessible donation-form modal.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DonationFormBuilder {
	const PAGE = 'bachs-donations-forms';

	/** Register the modal builder, page, assets, and protected save action. */
	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_bachs_donations_save_form', array( __CLASS__, 'save' ) );
	}

	/** Add the forms workspace beside dashboard, donations, reports, and settings. */
	public static function register_page() {
		add_submenu_page( 'bachs-donations', __( 'Donation Forms', 'bachs-donations' ), __( 'Donation Forms', 'bachs-donations' ), 'edit_posts', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	/** Load the builder, media picker, and color picker only in fundraising screens. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::PAGE ) && 'toplevel_page_bachs-donations' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'bachs-donations-builder', plugins_url( 'assets/css/form-builder.css', BACHS_DONATIONS_FILE ), array( 'wp-color-picker' ), BACHS_DONATIONS_VERSION );
		wp_enqueue_script( 'bachs-donations-builder', plugins_url( 'assets/js/form-builder.js', BACHS_DONATIONS_FILE ), array( 'jquery', 'wp-color-picker' ), BACHS_DONATIONS_VERSION, true );
		wp_localize_script(
			'bachs-donations-builder',
			'BachsDonationBuilder',
			array(
				'chooseImage' => __( 'Choose fundraiser image', 'bachs-donations' ),
				'useImage'    => __( 'Use this image', 'bachs-donations' ),
				'imageAlt'    => __( 'Selected fundraiser image', 'bachs-donations' ),
				'canPublish'  => current_user_can( 'publish_posts' ),
				'reviewLabel' => __( 'Submit for review', 'bachs-donations' ),
			)
		);
	}

	/** Render published and draft forms with direct edit and embed actions. */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage donation forms.', 'bachs-donations' ) );
		}
		$forms = get_posts(
			array(
				'post_type'      => DonationForms::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '<div class="wrap bachs-fundraising bachs-forms-page"><div class="bachs-forms-heading"><div><h1>' . esc_html__( 'Donation Forms', 'bachs-donations' ) . '</h1><p>' . esc_html__( 'Create a page for your cause, choose how people can give, and style the payment form to fit your site.', 'bachs-donations' ) . '</p></div><button type="button" class="button button-primary button-hero" data-bachs-open-modal>' . esc_html__( 'Create a donation form', 'bachs-donations' ) . '</button></div>';
		self::render_notices();
		if ( empty( $forms ) ) {
			echo '<div class="bachs-forms-empty"><span class="dashicons dashicons-heart" aria-hidden="true"></span><h2>' . esc_html__( 'Start with your first fundraiser', 'bachs-donations' ) . '</h2><p>' . esc_html__( 'Give your campaign a name, explain the cause, set suggested gifts, and publish a ready-to-share donation page.', 'bachs-donations' ) . '</p><button type="button" class="button button-primary" data-bachs-open-modal>' . esc_html__( 'Create your first form', 'bachs-donations' ) . '</button></div>';
		} else {
			echo '<table class="widefat striped bachs-forms-table"><thead><tr><th>' . esc_html__( 'Form', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Status', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Currencies', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Confirmed gifts', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Raised', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Actions', 'bachs-donations' ) . '</th></tr></thead><tbody>';
			foreach ( $forms as $form ) {
				if ( current_user_can( 'edit_post', $form->ID ) ) {
					self::render_form_row( $form );
				}
			}
			echo '</tbody></table><p class="description">' . esc_html__( 'Showing up to 100 most recently updated forms. The public page URL is available after publishing.', 'bachs-donations' ) . '</p>';
		}
		self::render_modal();
		echo '</div>';
	}

	/** Render one fundraiser row and provide safe JSON for in-place editing. */
	private static function render_form_row( $form ) {
		$config = DonationForms::settings( (int) $form->ID );
		$summary = DonationsStore::campaign_summary( (int) $form->ID );
		$totals = array();
		foreach ( $summary['totals'] as $currency => $amount ) {
			$totals[] = $currency . ' ' . number_format_i18n( $amount, 2 );
		}
		$payload = array(
			'id'                 => (int) $form->ID,
			'title'              => $form->post_title,
			'content'            => $form->post_content,
			'excerpt'            => $form->post_excerpt,
			'status'             => $form->post_status,
			'featured_image_id'  => get_post_thumbnail_id( $form->ID ),
			'featured_image_url' => get_the_post_thumbnail_url( $form->ID, 'medium' ),
			'settings'           => $config,
		);
		$payload_json = wp_json_encode( $payload );
		$shortcode = '[bachs_donation_form id="' . (int) $form->ID . '"]';
		$status_labels = array(
			'publish' => __( 'Published', 'bachs-donations' ),
			'pending' => __( 'Pending review', 'bachs-donations' ),
			'private' => __( 'Private', 'bachs-donations' ),
		);
		$status = isset( $status_labels[ $form->post_status ] ) ? $status_labels[ $form->post_status ] : __( 'Draft', 'bachs-donations' );
		echo '<tr><td><strong>' . esc_html( $form->post_title ) . '</strong><br><code>' . esc_html( $shortcode ) . '</code></td><td><span class="bachs-form-status bachs-form-status--' . esc_attr( $form->post_status ) . '">' . esc_html( $status ) . '</span></td><td>' . esc_html( implode( ', ', $config['currencies'] ) ) . '</td><td>' . esc_html( number_format_i18n( $summary['count'] ) ) . '</td><td>' . esc_html( empty( $totals ) ? '—' : implode( ' / ', $totals ) ) . '</td><td class="bachs-form-actions">';
		if ( 'publish' === $form->post_status ) {
			echo '<a class="button" href="' . esc_url( get_permalink( $form ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View page', 'bachs-donations' ) . '</a> ';
		}
		echo '<button type="button" class="button" data-bachs-edit-form="' . esc_attr( $payload_json ) . '">' . esc_html__( 'Edit', 'bachs-donations' ) . '</button> <button type="button" class="button-link" data-bachs-copy="' . esc_attr( $shortcode ) . '">' . esc_html__( 'Copy shortcode', 'bachs-donations' ) . '</button></td></tr>';
	}

	/** Explain a successful save or a common missing-payment-setup issue. */
	private static function render_notices() {
		if ( isset( $_GET['saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ) ) ) {
			$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
			$form = $form_id ? get_post( $form_id ) : null;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Donation form saved.', 'bachs-donations' );
			if ( $form instanceof \WP_Post && 'publish' === $form->post_status ) {
				echo ' <a href="' . esc_url( get_permalink( $form ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the public page', 'bachs-donations' ) . '</a>';
			}
			echo '</p></div>';
		}
		if ( isset( $_GET['error'] ) && 'title' === sanitize_key( wp_unslash( $_GET['error'] ) ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Add a name for this donation form before saving.', 'bachs-donations' ) . '</p></div>';
		}
		if ( ! PaymentGateways::active()->is_configured() ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Connect Bachs before accepting donations.', 'bachs-donations' ) . '</strong> ' . esc_html__( 'You can build your form now, then add your Sandbox API key and webhook signing secret in Payment Setup.', 'bachs-donations' );
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=bachs-donations-settings' ) ) . '">' . esc_html__( 'Open Payment Setup', 'bachs-donations' ) . '</a>';
			} else {
				echo ' ' . esc_html__( 'Ask a site administrator to connect the payment account.', 'bachs-donations' );
			}
			echo '</p></div>';
		}
	}

	/** Output one modal instance for both the dashboard and forms workspace. */
	public static function render_modal() {
		$settings = DonationForms::settings();
		$currencies = Settings::enabled_currencies();
		$preview_currency = ! empty( $currencies ) ? $currencies[0] : 'USD';
		$preview_amounts = $settings['amounts'][ $preview_currency ];
		if ( count( $preview_amounts ) < 3 ) {
			$preview_amounts = 'USD' === $preview_currency ? array( '10.00', '25.00', '50.00' ) : array( '5000.00', '10000.00', '25000.00' );
		}
		echo '<div id="bachs-donation-builder" class="bachs-builder" hidden><div class="bachs-builder__backdrop" data-bachs-close-modal></div><section class="bachs-builder__dialog" role="dialog" aria-modal="true" aria-labelledby="bachs-builder-title" tabindex="-1"><header class="bachs-builder__header"><div><p class="bachs-builder__eyebrow">' . esc_html__( 'FUNDRAISING FORM', 'bachs-donations' ) . '</p><h2 id="bachs-builder-title">' . esc_html__( 'Create a donation form', 'bachs-donations' ) . '</h2><p>' . esc_html__( 'Set up your cause, giving options, and brand in one place.', 'bachs-donations' ) . '</p></div><button class="bachs-builder__close" type="button" aria-label="' . esc_attr__( 'Close form builder', 'bachs-donations' ) . '" data-bachs-close-modal>&times;</button></header>';
		echo '<form id="bachs-builder-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bachs_donations_save_form"><input type="hidden" name="form_id" value="0"><input type="hidden" name="featured_image_id" value="0">';
		wp_nonce_field( 'bachs_donations_save_form' );
		echo '<div class="bachs-builder__body"><div class="bachs-builder__fields">';
		self::render_basics_fields();
		self::render_donation_fields( $settings, $currencies );
		self::render_donor_fields( $settings );
		self::render_custom_fields();
		self::render_appearance_fields( $settings );
		echo '</div><aside class="bachs-builder__preview"><div class="bachs-preview-card"><div class="bachs-preview-card__image" data-bachs-preview-image hidden></div><p class="bachs-builder__eyebrow">' . esc_html__( 'LIVE PREVIEW', 'bachs-donations' ) . '</p><h3 data-bachs-preview-title>' . esc_html__( 'Your cause name', 'bachs-donations' ) . '</h3><p data-bachs-preview-description>' . esc_html__( 'A short introduction to the cause will appear here.', 'bachs-donations' ) . '</p><div class="bachs-preview-card__fields" data-bachs-preview-fields></div><div class="bachs-preview-card__amounts"><button type="button">' . esc_html( $preview_currency . ' ' . $preview_amounts[0] ) . '</button><button type="button">' . esc_html( $preview_currency . ' ' . $preview_amounts[1] ) . '</button><button type="button">' . esc_html( $preview_currency . ' ' . $preview_amounts[2] ) . '</button></div><button type="button" class="bachs-preview-card__submit" data-bachs-preview-button>' . esc_html( $settings['button_text'] ) . '</button><p class="bachs-preview-card__secure">' . esc_html__( 'Secure payment powered by Bachs', 'bachs-donations' ) . '</p></div><p class="description">' . esc_html__( 'Preview updates as you edit the form.', 'bachs-donations' ) . '</p></aside></div>';
		echo '<template id="bachs-custom-field-template"><article class="bachs-custom-field" data-bachs-custom-field><header><strong>' . esc_html__( 'Additional question', 'bachs-donations' ) . '</strong><button type="button" class="button-link-delete" data-bachs-remove-custom-field>' . esc_html__( 'Remove', 'bachs-donations' ) . '</button></header><input type="hidden" name="bachs_form[custom_fields][__index__][key]" value=""><label class="bachs-builder__field"><span>' . esc_html__( 'Question or field label', 'bachs-donations' ) . '</span><input required maxlength="80" name="bachs_form[custom_fields][__index__][label]" data-bachs-custom-label></label><div class="bachs-builder__field-row"><label class="bachs-builder__field"><span>' . esc_html__( 'Field type', 'bachs-donations' ) . '</span><select name="bachs_form[custom_fields][__index__][type]" data-bachs-custom-type><option value="text">' . esc_html__( 'Short answer', 'bachs-donations' ) . '</option><option value="textarea">' . esc_html__( 'Long answer', 'bachs-donations' ) . '</option><option value="select">' . esc_html__( 'Dropdown', 'bachs-donations' ) . '</option><option value="checkbox">' . esc_html__( 'Consent checkbox', 'bachs-donations' ) . '</option></select></label><label class="bachs-builder__check bachs-custom-field__required"><input type="checkbox" name="bachs_form[custom_fields][__index__][required]" value="1"> <span><strong>' . esc_html__( 'Required', 'bachs-donations' ) . '</strong></span></label></div><label class="bachs-builder__field" data-bachs-custom-options-row hidden><span>' . esc_html__( 'Dropdown choices', 'bachs-donations' ) . '</span><textarea rows="3" name="bachs_form[custom_fields][__index__][options]" data-bachs-custom-options placeholder="Local projects&#10;Education&#10;Health"></textarea><small>' . esc_html__( 'Enter one choice per line. Add at least two choices.', 'bachs-donations' ) . '</small></label></article></template>';
		$submit_value = current_user_can( 'publish_posts' ) ? 'publish' : 'pending';
		$submit_label = current_user_can( 'publish_posts' ) ? __( 'Publish donation form', 'bachs-donations' ) : __( 'Submit for review', 'bachs-donations' );
		echo '<footer class="bachs-builder__footer"><p class="bachs-builder__error" data-bachs-builder-error role="alert" tabindex="-1" hidden></p><button type="button" class="button" data-bachs-close-modal>' . esc_html__( 'Cancel', 'bachs-donations' ) . '</button><span></span><button type="submit" class="button" name="form_status" value="draft">' . esc_html__( 'Save as draft', 'bachs-donations' ) . '</button><button type="submit" class="button button-primary" name="form_status" value="' . esc_attr( $submit_value ) . '" data-bachs-publish-button>' . esc_html( $submit_label ) . '</button></footer></form></section></div>';
	}

	/** Render campaign story and featured image controls. */
	private static function render_basics_fields() {
		echo '<section class="bachs-builder__section"><div class="bachs-builder__section-heading"><span>01</span><div><h3>' . esc_html__( 'Tell your story', 'bachs-donations' ) . '</h3><p>' . esc_html__( 'Give supporters a clear reason to help.', 'bachs-donations' ) . '</p></div></div>';
		echo '<label class="bachs-builder__field"><span>' . esc_html__( 'Form name', 'bachs-donations' ) . '</span><input required maxlength="191" name="form_title" type="text" placeholder="' . esc_attr__( 'e.g. Clean water for Kogi families', 'bachs-donations' ) . '"></label>';
		echo '<label class="bachs-builder__field"><span>' . esc_html__( 'Short introduction', 'bachs-donations' ) . '</span><textarea name="form_excerpt" rows="2" maxlength="300" placeholder="' . esc_attr__( 'A brief summary supporters see before the full story.', 'bachs-donations' ) . '"></textarea></label>';
		echo '<label class="bachs-builder__field"><span>' . esc_html__( 'Cause story', 'bachs-donations' ) . '</span><textarea name="form_content" rows="5" placeholder="' . esc_attr__( 'Explain the need, who it helps, and what a donation makes possible.', 'bachs-donations' ) . '"></textarea></label>';
		echo '<div class="bachs-builder__field"><span>' . esc_html__( 'Campaign image', 'bachs-donations' ) . '</span><div class="bachs-builder__image-controls"><button type="button" class="button" data-bachs-choose-image>' . esc_html__( 'Choose an image', 'bachs-donations' ) . '</button><button type="button" class="button-link-delete" data-bachs-remove-image hidden>' . esc_html__( 'Remove image', 'bachs-donations' ) . '</button></div><p class="description" data-bachs-image-name>' . esc_html__( 'Optional. A clear, relevant image helps supporters connect with your cause.', 'bachs-donations' ) . '</p><img class="bachs-builder__image-preview" data-bachs-image-preview alt="" hidden></div></section>';
	}

	/** Render currencies, giving amounts, payment methods, and an optional goal. */
	private static function render_donation_fields( $settings, $currencies ) {
		echo '<section class="bachs-builder__section"><div class="bachs-builder__section-heading"><span>02</span><div><h3>' . esc_html__( 'Set up giving', 'bachs-donations' ) . '</h3><p>' . esc_html__( 'Choose how much supporters can give and which payment methods to show.', 'bachs-donations' ) . '</p></div></div>';
		echo '<fieldset class="bachs-builder__field"><legend>' . esc_html__( 'Accepted currencies', 'bachs-donations' ) . '</legend><div class="bachs-builder__choice-grid">';
		foreach ( array( 'USD' => __( 'US dollars', 'bachs-donations' ), 'NGN' => __( 'Nigerian naira', 'bachs-donations' ) ) as $code => $label ) {
			$available = in_array( $code, $currencies, true );
			echo '<label class="bachs-builder__check' . ( $available ? '' : ' is-unavailable' ) . '"><input type="checkbox" name="bachs_form[currencies][]" value="' . esc_attr( $code ) . '" ' . checked( in_array( $code, $settings['currencies'], true ), true, false ) . disabled( ! $available, true, false ) . '> <span><strong>' . esc_html( $code ) . '</strong><small>' . esc_html( $label ) . ( $available ? '' : ' · ' . esc_html__( 'enable in Payment Setup', 'bachs-donations' ) ) . '</small></span></label>';
		}
		echo '</div></fieldset>';
		echo '<div class="bachs-builder__field-row"><label class="bachs-builder__field"><span>' . esc_html__( 'Default currency', 'bachs-donations' ) . '</span><select name="bachs_form[default_currency]">';
		foreach ( $currencies as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['default_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}
		echo '</select></label><label class="bachs-builder__field bachs-builder__toggle"><span>' . esc_html__( 'Let supporters choose an amount', 'bachs-donations' ) . '</span><input type="checkbox" name="bachs_form[custom_amount]" value="1" ' . checked( $settings['custom_amount'], true, false ) . '> <small>' . esc_html__( 'Allow a custom gift amount', 'bachs-donations' ) . '</small></label></div>';
		foreach ( array( 'USD' => __( 'Suggested USD amounts', 'bachs-donations' ), 'NGN' => __( 'Suggested NGN amounts', 'bachs-donations' ) ) as $currency => $label ) {
			echo '<label class="bachs-builder__field bachs-builder__currency-field" data-bachs-currency-field="' . esc_attr( $currency ) . '"><span>' . esc_html( $label ) . '</span><input name="bachs_form[amounts][' . esc_attr( $currency ) . ']" value="' . esc_attr( implode( ', ', $settings['amounts'][ $currency ] ) ) . '" placeholder="10, 25, 50, 100"><small>' . esc_html__( 'Separate amounts with commas. Up to eight options.', 'bachs-donations' ) . '</small></label>';
		}
		echo '<fieldset class="bachs-builder__field"><legend>' . esc_html__( 'Payment methods', 'bachs-donations' ) . '</legend><div class="bachs-builder__methods" data-bachs-methods="USD"><strong>USD</strong><label><input type="checkbox" name="bachs_form[payment_methods][USD][]" value="USD_CARD" ' . checked( in_array( 'USD_CARD', $settings['payment_methods']['USD'], true ), true, false ) . '> ' . esc_html__( 'Card', 'bachs-donations' ) . '</label></div><div class="bachs-builder__methods" data-bachs-methods="NGN"><strong>NGN</strong><label><input type="checkbox" name="bachs_form[payment_methods][NGN][]" value="NGN_CARD" ' . checked( in_array( 'NGN_CARD', $settings['payment_methods']['NGN'], true ), true, false ) . '> ' . esc_html__( 'Card', 'bachs-donations' ) . '</label><label><input type="checkbox" name="bachs_form[payment_methods][NGN][]" value="NGN_BANK_TRANSFER" ' . checked( in_array( 'NGN_BANK_TRANSFER', $settings['payment_methods']['NGN'], true ), true, false ) . '> ' . esc_html__( 'Bank transfer', 'bachs-donations' ) . '</label></div><p class="description">' . esc_html__( 'A payment method appears only when it is enabled for your Bachs account and eligible for the gift.', 'bachs-donations' ) . '</p></fieldset>';
		echo '<details class="bachs-builder__advanced"><summary>' . esc_html__( 'Add a fundraising goal (optional)', 'bachs-donations' ) . '</summary><div class="bachs-builder__field-row"><label class="bachs-builder__field"><span>' . esc_html__( 'Goal amount', 'bachs-donations' ) . '</span><input type="number" min="0" step="0.01" name="bachs_form[goal_amount]" value="' . esc_attr( $settings['goal_amount'] ) . '"></label><label class="bachs-builder__field"><span>' . esc_html__( 'Goal currency', 'bachs-donations' ) . '</span><select name="bachs_form[goal_currency]">';
		foreach ( $currencies as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['goal_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}
		echo '</select></label></div><p class="description">' . esc_html__( 'Confirmed gifts contribute only to the selected currency. No exchange rate is assumed.', 'bachs-donations' ) . '</p></details></section>';
	}

	/** Render the donor information fields that this form asks supporters to fill in. */
	private static function render_donor_fields( $settings ) {
		echo '<section class="bachs-builder__section"><div class="bachs-builder__section-heading"><span>03</span><div><h3>' . esc_html__( 'Donor details', 'bachs-donations' ) . '</h3><p>' . esc_html__( 'Keep checkout focused. Email is always required; name and phone are optional.', 'bachs-donations' ) . '</p></div></div>';
		echo '<label class="bachs-builder__check"><input type="checkbox" name="bachs_form[donor_name]" value="1" ' . checked( $settings['donor_name'], true, false ) . '> <span><strong>' . esc_html__( 'Ask for the donor’s name', 'bachs-donations' ) . '</strong><small>' . esc_html__( 'Useful for acknowledging gifts.', 'bachs-donations' ) . '</small></span></label><label class="bachs-builder__subcheck"><input type="checkbox" name="bachs_form[donor_name_required]" value="1" ' . checked( $settings['donor_name_required'], true, false ) . '> ' . esc_html__( 'Make name required', 'bachs-donations' ) . '</label>';
		echo '<label class="bachs-builder__check"><input type="checkbox" name="bachs_form[donor_phone]" value="1" ' . checked( $settings['donor_phone'], true, false ) . '> <span><strong>' . esc_html__( 'Ask for a phone number', 'bachs-donations' ) . '</strong><small>' . esc_html__( 'Optional. Sent to Bachs with the checkout details.', 'bachs-donations' ) . '</small></span></label><label class="bachs-builder__subcheck"><input type="checkbox" name="bachs_form[donor_phone_required]" value="1" ' . checked( $settings['donor_phone_required'], true, false ) . '> ' . esc_html__( 'Make phone number required', 'bachs-donations' ) . '</label><p class="bachs-builder__required-note"><strong>' . esc_html__( 'Email address', 'bachs-donations' ) . '</strong><span>' . esc_html__( 'Always required for payment checkout.', 'bachs-donations' ) . '</span></p></section>';
	}

	/** Render the add/remove controls for organization-specific donor questions. */
	private static function render_custom_fields() {
		echo '<section class="bachs-builder__section"><div class="bachs-builder__section-heading"><span>04</span><div><h3>' . esc_html__( 'Ask extra questions', 'bachs-donations' ) . '</h3><p>' . esc_html__( 'Add up to 10 short-answer, long-answer, dropdown, or consent fields for your cause.', 'bachs-donations' ) . '</p></div></div><div class="bachs-custom-fields" data-bachs-custom-fields></div><button type="button" class="button" data-bachs-add-custom-field>' . esc_html__( 'Add a donor field', 'bachs-donations' ) . '</button><p class="description">' . esc_html__( 'These answers are stored with the donation on this WordPress site and are not sent to Bachs.', 'bachs-donations' ) . '</p></section>';
	}

	/** Render brand color, layout, button label, and corner shape controls. */
	private static function render_appearance_fields( $settings ) {
		echo '<section class="bachs-builder__section"><div class="bachs-builder__section-heading"><span>05</span><div><h3>' . esc_html__( 'Make it yours', 'bachs-donations' ) . '</h3><p>' . esc_html__( 'Match your website’s colors and give the form a style that fits your cause.', 'bachs-donations' ) . '</p></div></div>';
		echo '<label class="bachs-builder__field"><span>' . esc_html__( 'Brand color', 'bachs-donations' ) . '</span><input class="bachs-builder__color" type="text" name="bachs_form[accent_color]" value="' . esc_attr( $settings['accent_color'] ) . '" data-default-color="#176b52"><small>' . esc_html__( 'Used for the payment button, selected amounts, and progress indicator.', 'bachs-donations' ) . '</small></label>';
		echo '<div class="bachs-builder__field-row"><label class="bachs-builder__field"><span>' . esc_html__( 'Form style', 'bachs-donations' ) . '</span><select name="bachs_form[form_style]"><option value="soft" ' . selected( $settings['form_style'], 'soft', false ) . '>' . esc_html__( 'Soft card', 'bachs-donations' ) . '</option><option value="outlined" ' . selected( $settings['form_style'], 'outlined', false ) . '>' . esc_html__( 'Outlined', 'bachs-donations' ) . '</option><option value="minimal" ' . selected( $settings['form_style'], 'minimal', false ) . '>' . esc_html__( 'Minimal', 'bachs-donations' ) . '</option></select></label><label class="bachs-builder__field"><span>' . esc_html__( 'Corners', 'bachs-donations' ) . '</span><select name="bachs_form[corner_style]"><option value="rounded" ' . selected( $settings['corner_style'], 'rounded', false ) . '>' . esc_html__( 'Rounded', 'bachs-donations' ) . '</option><option value="square" ' . selected( $settings['corner_style'], 'square', false ) . '>' . esc_html__( 'Square', 'bachs-donations' ) . '</option></select></label></div>';
		echo '<label class="bachs-builder__field"><span>' . esc_html__( 'Payment button text', 'bachs-donations' ) . '</span><input name="bachs_form[button_text]" maxlength="48" value="' . esc_attr( $settings['button_text'] ) . '"></label></section>';
	}

	/** Save a draft or publish a fundraiser after capability and nonce checks. */
	public static function save() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to save donation forms.', 'bachs-donations' ) );
		}
		check_admin_referer( 'bachs_donations_save_form' );
		$form_id = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		if ( $form_id && ( DonationForms::POST_TYPE !== get_post_type( $form_id ) || ! current_user_can( 'edit_post', $form_id ) ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this donation form.', 'bachs-donations' ) );
		}
		$title = isset( $_POST['form_title'] ) && is_scalar( $_POST['form_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['form_title'] ) ) : '';
		if ( '' === $title ) {
			self::redirect_with_notice( 'error', 'title', $form_id );
		}
		$posted = isset( $_POST['bachs_form'] ) && is_array( $_POST['bachs_form'] ) ? wp_unslash( $_POST['bachs_form'] ) : array();
		$status = isset( $_POST['form_status'] ) && is_scalar( $_POST['form_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['form_status'] ) ) : 'draft';
		$status = in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ? $status : 'draft';
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to publish donation forms. Save this form as a draft or ask an administrator to publish it.', 'bachs-donations' ) );
		}
		$content = isset( $_POST['form_content'] ) && is_scalar( $_POST['form_content'] ) ? wp_kses_post( wp_unslash( (string) $_POST['form_content'] ) ) : '';
		$excerpt = isset( $_POST['form_excerpt'] ) && is_scalar( $_POST['form_excerpt'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['form_excerpt'] ) ) : '';
		$post_data = array(
			'post_type'    => DonationForms::POST_TYPE,
			'post_status'  => $status,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		);
		if ( $form_id ) {
			$post_data['ID'] = $form_id;
		}
		$saved_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $saved_id ) ) {
			wp_die( esc_html( $saved_id->get_error_message() ) );
		}
		DonationForms::save_settings( (int) $saved_id, $posted, true );
		$image_id = isset( $_POST['featured_image_id'] ) ? absint( wp_unslash( $_POST['featured_image_id'] ) ) : 0;
		if ( $image_id && 'attachment' === get_post_type( $image_id ) && current_user_can( 'edit_post', $image_id ) ) {
			set_post_thumbnail( $saved_id, $image_id );
		} else {
			delete_post_thumbnail( $saved_id );
		}
		self::redirect_with_notice( 'saved', '1', (int) $saved_id );
	}

	/** Return the administrator to the Forms page with a concise result notice. */
	private static function redirect_with_notice( $key, $value, $form_id = 0 ) {
		$args = array( 'page' => self::PAGE, $key => $value );
		if ( $form_id ) {
			$args['form_id'] = absint( $form_id );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
