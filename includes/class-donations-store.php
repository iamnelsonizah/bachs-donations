<?php
/**
 * Durable donation and webhook event storage.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DonationsStore {
	/** Install the donation tables without changing existing site tables. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$donations = self::donations_table();
		$events = self::events_table();
		$sql_donations = "CREATE TABLE {$donations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_token char(64) NOT NULL,
			reference varchar(128) NOT NULL,
			checkout_id varchar(128) NOT NULL DEFAULT '',
			charge_id varchar(128) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			currency varchar(10) NOT NULL,
			amount decimal(18,6) NOT NULL,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			campaign_title varchar(191) NOT NULL DEFAULT '',
			donor_name varchar(191) NOT NULL DEFAULT '',
			donor_email varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_token (public_token),
			UNIQUE KEY reference (reference),
			KEY checkout_id (checkout_id),
			KEY status_created (status,created_at),
			KEY campaign_status_currency (campaign_id,status,currency)
		) {$collate};";
		$sql_events = "CREATE TABLE {$events} (
			event_id varchar(128) NOT NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY  (event_id)
		) {$collate};";
		dbDelta( $sql_donations );
		dbDelta( $sql_events );
	}

	public static function create( $currency, $amount, $name, $email, $campaign_id = 0, $campaign_title = '' ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$token = bin2hex( random_bytes( 32 ) );
		$reference = 'don-' . strtolower( wp_generate_uuid4() );
		$result = $wpdb->insert(
			self::donations_table(),
			array(
				'public_token' => $token,
				'reference'    => $reference,
				'status'       => 'pending',
				'currency'     => $currency,
				'amount'       => $amount,
				'campaign_id'  => absint( $campaign_id ),
				'campaign_title' => sanitize_text_field( $campaign_title ),
				'donor_name'   => $name,
				'donor_email'  => $email,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $result ) {
			throw new \RuntimeException( 'Donation record could not be created.' );
		}
		return array( 'id' => (int) $wpdb->insert_id, 'token' => $token, 'reference' => $reference );
	}

	public static function set_checkout( $id, $checkout_id ) {
		global $wpdb;
		return $wpdb->update(
			self::donations_table(),
			array( 'checkout_id' => sanitize_text_field( $checkout_id ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $id ), 'status' => 'pending' ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( 'pending', 'session_error', 'failed', 'expired' ), true ) ) {
			return;
		}
		global $wpdb;
		return $wpdb->update(
			self::donations_table(),
			array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $id ), 'status' => 'pending' ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/** Mark a donation paid only once, after external amount and currency checks. */
	public static function mark_paid( $id, $charge_id ) {
		global $wpdb;
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . self::donations_table() . " SET status = %s, charge_id = %s, updated_at = %s WHERE id = %d AND status <> %s",
				'paid',
				sanitize_text_field( $charge_id ),
				current_time( 'mysql', true ),
				absint( $id ),
				'paid'
			)
		);
	}

	public static function find_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::donations_table() . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	public static function find_by_token( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::donations_table() . ' WHERE public_token = %s', $token ), ARRAY_A );
	}

	public static function find_by_reference( $reference ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::donations_table() . ' WHERE reference = %s', sanitize_text_field( $reference ) ), ARRAY_A );
	}

	public static function find_by_checkout_id( $checkout_id ) {
		if ( ! is_string( $checkout_id ) || '' === $checkout_id || strlen( $checkout_id ) > 128 ) {
			return null;
		}
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::donations_table() . ' WHERE checkout_id = %s', $checkout_id ), ARRAY_A );
	}

	public static function record_event_once( $event_id ) {
		if ( ! is_string( $event_id ) || '' === $event_id || strlen( $event_id ) > 128 ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::events_table() . ' (event_id, processed_at) VALUES (%s, %s)',
			$event_id,
				current_time( 'mysql', true )
			)
		);
		return 1 === (int) $result;
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$limit = min( 250, max( 1, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::donations_table() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
	}

	/** Return a confirmed total without combining different charge currencies. */
	public static function campaign_total( $campaign_id, $currency ) {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . self::donations_table() . ' WHERE campaign_id = %d AND currency = %s AND status = %s',
				absint( $campaign_id ),
				strtoupper( sanitize_text_field( $currency ) ),
				'paid'
			)
		);
	}

	/** Return confirmed gift count and per-currency totals for one form. */
	public static function campaign_summary( $campaign_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT currency, COUNT(*) AS gift_count, COALESCE(SUM(amount), 0) AS total FROM ' . self::donations_table() . ' WHERE campaign_id = %d AND status = %s GROUP BY currency',
				absint( $campaign_id ),
				'paid'
			),
			ARRAY_A
		);
		$summary = array( 'count' => 0, 'totals' => array() );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$currency = isset( $row['currency'] ) ? strtoupper( sanitize_text_field( $row['currency'] ) ) : '';
			if ( '' === $currency ) {
				continue;
			}
			$summary['count'] += absint( $row['gift_count'] );
			$summary['totals'][ $currency ] = (float) $row['total'];
		}
		return $summary;
	}

	/** Return simple ledger totals for the Fundraising dashboard. */
	public static function summary() {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS donation_count, COALESCE(SUM(CASE WHEN status = %s THEN 1 ELSE 0 END), 0) AS paid_count FROM ' . self::donations_table(),
				'paid'
			),
			ARRAY_A
		);
		return is_array( $row ) ? array_map( 'absint', $row ) : array( 'donation_count' => 0, 'paid_count' => 0 );
	}

	/** Return page of personal donation data for the WordPress privacy exporter. */
	public static function by_email( $email, $limit = 100, $offset = 0 ) {
		global $wpdb;
		$limit  = min( 500, max( 1, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, reference, currency, amount, status, campaign_title, donor_name, donor_email, created_at FROM ' . self::donations_table() . ' WHERE donor_email = %s ORDER BY id ASC LIMIT %d OFFSET %d',
				sanitize_email( $email ),
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/** Remove donor identifiers while retaining transaction history for accounting. */
	public static function anonymize_by_email( $email ) {
		global $wpdb;
		$email = sanitize_email( $email );
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::donations_table() . ' WHERE donor_email = %s', $email ) );
		if ( ! is_array( $ids ) ) {
			return false;
		}
		$affected = 0;
		foreach ( $ids as $id ) {
			try {
				$replacement_token = bin2hex( random_bytes( 32 ) );
			} catch ( \Throwable $exception ) {
				return false;
			}
			$result = $wpdb->update(
				self::donations_table(),
				array(
					'donor_name'  => '',
					'donor_email' => '',
					'public_token' => $replacement_token,
					'updated_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => absint( $id ), 'donor_email' => $email ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);
			if ( false === $result ) {
				return false;
			}
			$affected += (int) $result;
		}
		return $affected;
	}

	private static function donations_table() {
		global $wpdb;
		return $wpdb->prefix . 'bachs_donations';
	}

	private static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'bachs_donation_events';
	}
}
