<?php
/**
 * Plugin Name: Bachs Donations
 * Plugin URI: https://github.com/iamnelsonizah/bachs-donations
 * Description: Hosted USD and NGN donation checkout for WordPress, powered by Bachs.
 * Version: 0.4.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nelson Izah
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bachs-donations
 * Domain Path: /languages
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BACHS_DONATIONS_VERSION', '0.4.0' );
define( 'BACHS_DONATIONS_FILE', __FILE__ );
define( 'BACHS_DONATIONS_DIR', plugin_dir_path( __FILE__ ) );

require_once BACHS_DONATIONS_DIR . 'includes/class-api-exception.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-settings.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-api-client.php';
require_once BACHS_DONATIONS_DIR . 'includes/interface-payment-gateway.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-bachs-gateway.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-payment-gateways.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-donations-store.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-webhook-verifier.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-webhook-controller.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-checkout-controller.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-reconciler.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-donation-shortcode.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-donation-forms.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-donation-form-builder.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-admin.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-reports.php';
require_once BACHS_DONATIONS_DIR . 'includes/class-privacy.php';

/** Install or upgrade the plugin's private donation tables. */
function install() {
	DonationsStore::install();
	DonationForms::register_post_type();
	flush_rewrite_rules();
	update_option( 'bachs_donations_db_version', BACHS_DONATIONS_VERSION, false );
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\install' );

/** Keep the database schema current after plugin updates. */
function maybe_upgrade() {
	if ( BACHS_DONATIONS_VERSION !== get_option( 'bachs_donations_db_version' ) ) {
		install();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\maybe_upgrade' );

/** Register the public, admin, and privacy integrations. */
function initialize() {
	load_plugin_textdomain( 'bachs-donations', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	Settings::register_hooks();
	CheckoutController::register_hooks();
	WebhookController::register_hooks();
	DonationShortcode::register_hooks();
	DonationForms::register_hooks();
	DonationFormBuilder::register_hooks();
	Admin::register_hooks();
	Reports::register_hooks();
	Privacy::register_hooks();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\initialize', 20 );
