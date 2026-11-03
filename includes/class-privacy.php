<?php
/**
 * WordPress personal data exporter, eraser, and privacy-policy guidance.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Privacy {
	public static function register_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_policy_content' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['bachs-donations'] = array(
			'exporter_friendly_name' => __( 'Bachs Donations', 'bachs-donations' ),
			'callback'               => array( __CLASS__, 'export_personal_data' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['bachs-donations'] = array(
			'eraser_friendly_name' => __( 'Bachs Donations', 'bachs-donations' ),
			'callback'             => array( __CLASS__, 'erase_personal_data' ),
		);
		return $erasers;
	}

	public static function export_personal_data( $email, $page = 1 ) {
		$rows  = DonationsStore::by_email( $email, 100, ( max( 1, absint( $page ) ) - 1 ) * 100 );
		$items = array();
		foreach ( $rows as $row ) {
			$custom_fields = isset( $row['donor_fields'] ) && is_string( $row['donor_fields'] ) ? json_decode( $row['donor_fields'], true ) : array();
			$personal_data = array(
				array( 'name' => __( 'Donor name', 'bachs-donations' ), 'value' => $row['donor_name'] ),
				array( 'name' => __( 'Donor email', 'bachs-donations' ), 'value' => $row['donor_email'] ),
				array( 'name' => __( 'Donor phone', 'bachs-donations' ), 'value' => $row['donor_phone'] ),
			);
			foreach ( is_array( $custom_fields ) ? $custom_fields : array() as $custom_field ) {
				if ( is_array( $custom_field ) && isset( $custom_field['label'], $custom_field['value'] ) && is_string( $custom_field['label'] ) && is_string( $custom_field['value'] ) ) {
					$personal_data[] = array( 'name' => $custom_field['label'], 'value' => $custom_field['value'] );
				}
			}
			$items[] = array(
				'group_id'    => 'bachs-donations',
				'group_label' => __( 'Donations', 'bachs-donations' ),
				'item_id'     => 'bachs-donation-' . (int) $row['id'],
				'data'        => array_merge(
					$personal_data,
					array(
						array( 'name' => __( 'Donation reference', 'bachs-donations' ), 'value' => $row['reference'] ),
						array( 'name' => __( 'Donation form', 'bachs-donations' ), 'value' => $row['campaign_title'] ),
						array( 'name' => __( 'Amount', 'bachs-donations' ), 'value' => $row['currency'] . ' ' . $row['amount'] ),
						array( 'name' => __( 'Payment status', 'bachs-donations' ), 'value' => $row['status'] ),
						array( 'name' => __( 'Created at (UTC)', 'bachs-donations' ), 'value' => $row['created_at'] ),
					)
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < 100,
		);
	}

	public static function erase_personal_data( $email, $page = 1 ) {
		$result = 0;
		if ( 1 === max( 1, absint( $page ) ) ) {
			$result = DonationsStore::anonymize_by_email( $email );
			if ( false === $result ) {
				return array(
					'items_removed'  => false,
					'items_retained' => true,
					'messages'       => array( __( 'Donor data could not be updated. Please retry the privacy erasure.', 'bachs-donations' ) ),
					'done'           => false,
				);
			}
		}
		return array(
			'items_removed'  => $result > 0,
			'items_retained' => false,
			'messages'       => array( __( 'Donor name, email, phone number, custom field responses, and public return token were removed. Donation transaction records are retained for accounting and fraud-prevention purposes.', 'bachs-donations' ) ),
			'done'           => true,
		);
	}

	public static function register_policy_content() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content = '<p>' . esc_html__( 'When a visitor makes a donation, this plugin stores the donor name and phone number if the form requests them, email address, custom field responses, donation form title, donation amount, currency, payment status, Bachs checkout and charge identifiers, and a random return-page token. The donor name, email, and phone number are sent to Bachs to create the hosted checkout. Custom field responses are stored on this WordPress site and are not sent to Bachs. Bachs processes payment data under its own privacy policy.', 'bachs-donations' ) . '</p>';
			$content .= '<p>' . esc_html__( 'To limit automated checkout creation, the plugin temporarily stores a keyed hash derived from the visitor IP address for up to 15 minutes. It does not store the raw IP address for this rate limit.', 'bachs-donations' ) . '</p>';
			$content .= '<p>' . esc_html__( 'Donation records are retained in the site database for accounting and reconciliation. A privacy erasure request removes donor contact details, custom field responses, and the public return token while retaining transaction fields. This does not erase information held by Bachs; submit any required request to Bachs separately. Site administrators should set a retention period that follows their local legal and accounting requirements.', 'bachs-donations' ) . '</p>';
			wp_add_privacy_policy_content( __( 'Bachs Donations', 'bachs-donations' ), wp_kses_post( $content ) );
		}
	}
}
