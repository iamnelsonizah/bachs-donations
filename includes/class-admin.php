<?php
/**
 * Fundraising dashboard, donation ledger, and reconciliation action.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_bachs_donations_reconcile', array( __CLASS__, 'reconcile' ) );
	}

	/** Load fundraising admin styles only on plugin screens. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false !== strpos( (string) $hook_suffix, 'bachs-donations' ) ) {
			wp_enqueue_style( 'bachs-donations-admin', plugins_url( 'assets/css/admin.css', BACHS_DONATIONS_FILE ), array(), BACHS_DONATIONS_VERSION );
		}
	}

	public static function register_page() {
		add_menu_page(
			__( 'Fundraising', 'bachs-donations' ),
			__( 'Fundraising', 'bachs-donations' ),
			'manage_options',
			'bachs-donations',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-heart',
			56
		);
		add_submenu_page( 'bachs-donations', __( 'Donations', 'bachs-donations' ), __( 'Donations', 'bachs-donations' ), 'manage_options', 'bachs-donations-ledger', array( __CLASS__, 'render_ledger' ) );
	}

	/** Show the form creation steps before the empty transaction ledger. */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage fundraising.', 'bachs-donations' ) );
		}
		$summary = DonationsStore::summary();
		$counts = wp_count_posts( DonationForms::POST_TYPE );
		$form_count = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
		echo '<div class="wrap bachs-fundraising"><h1>' . esc_html__( 'Fundraising', 'bachs-donations' ) . '</h1>';
		echo '<p>' . esc_html__( 'Create donation forms for your causes, connect Bachs, and review confirmed gifts here.', 'bachs-donations' ) . '</p>';
		echo '<p class="bachs-fundraising-actions"><a class="button button-primary button-hero" href="' . esc_url( admin_url( 'post-new.php?post_type=' . DonationForms::POST_TYPE ) ) . '">' . esc_html__( 'Create a donation form', 'bachs-donations' ) . '</a> <a class="button button-hero" href="' . esc_url( admin_url( 'edit.php?post_type=' . DonationForms::POST_TYPE ) ) . '">' . esc_html__( 'Manage forms', 'bachs-donations' ) . '</a> <a class="button button-hero" href="' . esc_url( admin_url( 'admin.php?page=bachs-donations-ledger' ) ) . '">' . esc_html__( 'View donations', 'bachs-donations' ) . '</a> <a class="button button-hero" href="' . esc_url( admin_url( 'admin.php?page=bachs-donations-reports' ) ) . '">' . esc_html__( 'View reports', 'bachs-donations' ) . '</a> <a class="button button-hero" href="' . esc_url( admin_url( 'admin.php?page=bachs-donations-settings' ) ) . '">' . esc_html__( 'Configure Bachs', 'bachs-donations' ) . '</a></p>';
		echo '<section class="bachs-fundraising-setup"><h2>' . esc_html__( 'Get started', 'bachs-donations' ) . '</h2><ol><li>' . esc_html__( 'Configure sandbox or live API credentials and webhook signing secret in Bachs Donations settings.', 'bachs-donations' ) . '</li><li>' . esc_html__( 'Create a donation form, add your cause story and image, then choose currencies and suggested amounts.', 'bachs-donations' ) . '</li><li>' . esc_html__( 'Publish the form. Its public page includes the secure donation form automatically. You can also copy its shortcode into another page.', 'bachs-donations' ) . '</li></ol>';
		if ( ! PaymentGateways::active()->is_configured() ) {
			echo '<p><strong>' . esc_html__( 'Payments are not configured yet.', 'bachs-donations' ) . '</strong> ' . esc_html__( 'The public form will show as unavailable until Bachs credentials and a webhook secret are saved.', 'bachs-donations' ) . '</p>';
		} else {
			echo '<p><strong>' . esc_html__( 'The payment provider is configured.', 'bachs-donations' ) . '</strong> ' . esc_html__( 'Use sandbox first, complete a test donation, and switch to live only when your webhook is receiving signed events.', 'bachs-donations' ) . '</p>';
		}
		echo '</section><div class="bachs-fundraising-cards">';
		self::summary_card( __( 'Published forms', 'bachs-donations' ), number_format_i18n( $form_count ) );
		self::summary_card( __( 'Donation attempts', 'bachs-donations' ), number_format_i18n( $summary['donation_count'] ) );
		self::summary_card( __( 'Confirmed donations', 'bachs-donations' ), number_format_i18n( $summary['paid_count'] ) );
		echo '</div>';
		if ( 0 === $form_count ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No donation forms have been published yet. Start with Create a donation form above; you will then be able to add the form to a page and accept donations.', 'bachs-donations' ) . '</p></div>';
		}
		echo '</div>';
	}

	public static function render_ledger() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view donations.', 'bachs-donations' ) );
		}
		$donations = DonationsStore::recent( 100 );
		echo '<div class="wrap"><h1>' . esc_html__( 'Donations', 'bachs-donations' ) . '</h1>';
		if ( isset( $_GET['bachs-reconcile'] ) && is_scalar( $_GET['bachs-reconcile'] ) ) {
			$result = sanitize_key( wp_unslash( $_GET['bachs-reconcile'] ) );
			$messages = array(
				'paid' => __( 'Payment confirmed with Bachs.', 'bachs-donations' ),
				'pending' => __( 'Bachs has not confirmed payment yet. The donation remains pending.', 'bachs-donations' ),
				'failed' => __( 'Bachs reports that this payment failed.', 'bachs-donations' ),
				'expired' => __( 'Bachs reports that this checkout expired.', 'bachs-donations' ),
				'error' => __( 'Could not reach Bachs. Try again later.', 'bachs-donations' ),
			);
			if ( isset( $messages[ $result ] ) ) {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
			}
		}
		echo '<p>' . esc_html__( 'Showing the 100 most recent donation records. Donor names and email addresses are visible only to site administrators.', 'bachs-donations' ) . '</p>';
		if ( empty( $donations ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No donations have been recorded yet. Publish a donation form and complete a payment to see its records here.', 'bachs-donations' ) . '</p></div>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=' . DonationForms::POST_TYPE ) ) . '">' . esc_html__( 'Create a donation form', 'bachs-donations' ) . '</a></p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Form', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Donor', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Amount', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Status', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Reference', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Action', 'bachs-donations' ) . '</th></tr></thead><tbody>';
		foreach ( $donations as $donation ) {
			$name = '' !== $donation['donor_name'] ? $donation['donor_name'] : __( 'Name not provided', 'bachs-donations' );
			if ( '' !== $donation['donor_email'] ) {
				$name .= ' <' . $donation['donor_email'] . '>';
			}
			echo '<tr><td>' . esc_html( get_date_from_gmt( $donation['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td>';
			$form_label = ! empty( $donation['campaign_title'] ) ? $donation['campaign_title'] : __( 'General donation form', 'bachs-donations' );
			echo '<td>' . esc_html( $form_label ) . '</td>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $donation['currency'] . ' ' . number_format_i18n( (float) $donation['amount'], 2 ) ) . '</td>';
			echo '<td><strong>' . esc_html( ucfirst( $donation['status'] ) ) . '</strong></td>';
			echo '<td><code>' . esc_html( $donation['reference'] ) . '</code></td><td>';
			if ( 'paid' !== $donation['status'] && '' !== $donation['checkout_id'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bachs_donations_reconcile"><input type="hidden" name="donation_id" value="' . esc_attr( (string) $donation['id'] ) . '">';
				wp_nonce_field( 'bachs_donations_reconcile_' . (int) $donation['id'] );
				submit_button( __( 'Check payment', 'bachs-donations' ), 'secondary small', 'submit', false );
				echo '</form>';
			} elseif ( '' === $donation['checkout_id'] ) {
				echo esc_html__( 'No checkout created', 'bachs-donations' );
			} else {
				echo '&mdash;';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function reconcile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reconcile donations.', 'bachs-donations' ) );
		}
		$id = isset( $_POST['donation_id'] ) ? absint( wp_unslash( $_POST['donation_id'] ) ) : 0;
		check_admin_referer( 'bachs_donations_reconcile_' . $id );
		$donation = DonationsStore::find_by_id( $id );
		$result = 'error';
		if ( is_array( $donation ) && '' !== $donation['checkout_id'] ) {
			$result = Reconciler::refresh( $donation );
		}
		$allowed = array( 'paid', 'pending', 'failed', 'expired' );
		if ( ! in_array( $result, $allowed, true ) ) {
			$result = 'error';
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bachs-donations-ledger', 'bachs-reconcile' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Output one compact count card on the Fundraising overview. */
	private static function summary_card( $label, $value ) {
		echo '<div class="bachs-fundraising-card"><p>' . esc_html( $label ) . '</p><strong>' . esc_html( $value ) . '</strong></div>';
	}
}
