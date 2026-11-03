<?php
/**
 * Remove credentials on uninstall while retaining donation records for audit.
 *
 * @package BachsDonations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bachs_donations_settings' );
delete_option( 'bachs_donations_db_version' );
