=== Bachs Donations ===
Contributors: nelsonizah
Tags: donations, charity, ngo, payments, bachs
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept one-time USD and NGN donations through Bachs hosted checkout. WooCommerce is not required.

== Description ==

Bachs Donations adds a shortcode donation form, signed payment webhooks, a donation ledger in WordPress admin, and checkout reconciliation. It supports USD card donations and NGN card or bank transfer donations, subject to the merchant's Bachs account setup.

This early release supports one-time payments only. See README.md for installation, configuration, security, and privacy details.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin, or copy the bachs-donations folder into wp-content/plugins/.
2. Activate Bachs Donations.
3. Configure credentials and currencies in Settings > Bachs Donations.
4. Register the webhook URL shown in settings in your Bachs dashboard and subscribe it to checkout.completed, checkout.expired, and collection.succeeded.
5. Put [bachs_donation_form] on a page.

== Changelog ==

= 0.1.0 =
* Initial standalone donation plugin release.
* Added hosted USD and NGN one-time donation checkout.
* Added signed webhook verification, ledger, reconciliation, and WordPress privacy hooks.
