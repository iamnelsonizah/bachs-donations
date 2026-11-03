<?php
/**
 * Donation ledger and manual reconciliation action for site administrators.
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
		add_action( 'admin_post_bachs_donations_reconcile', array( __CLASS__, 'reconcile' ) );
	}

	public static function register_page() {
		add_menu_page(
			__( 'Donations', 'bachs-donations' ),
			__( 'Donations', 'bachs-donations' ),
			'manage_options',
			'bachs-donations',
			array( __CLASS__, 'render_page' ),
			'dashicons-heart',
			56
		);
	}

	public static function render_page() {
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
			echo '<p>' . esc_html__( 'No donations have been recorded yet.', 'bachs-donations' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Donor', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Amount', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Status', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Reference', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Action', 'bachs-donations' ) . '</th></tr></thead><tbody>';
		foreach ( $donations as $donation ) {
			$name = '' !== $donation['donor_name'] ? $donation['donor_name'] : __( 'Name not provided', 'bachs-donations' );
			if ( '' !== $donation['donor_email'] ) {
				$name .= ' <' . $donation['donor_email'] . '>';
			}
			echo '<tr><td>' . esc_html( get_date_from_gmt( $donation['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td>';
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
		wp_safe_redirect( add_query_arg( array( 'page' => 'bachs-donations', 'bachs-reconcile' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
