=== Bachs Donations ===
Contributors: nelsonizah
Tags: donations, charity, ngo, payments, bachs
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and publish donation forms for one-time USD and NGN gifts through Bachs hosted checkout. WooCommerce is not required.

== Description ==

Bachs Donations lets site administrators create donation forms with a focused modal builder, live preview, per-form colors and layout, currencies, payment methods, suggested amounts, configurable donor fields including custom questions, and optional fundraising goals. Forms publish as public cause pages. Payments use Bachs hosted checkout, signed webhooks, and checkout reconciliation. It supports USD card donations and NGN card or bank transfer donations, subject to the merchant's Bachs account setup.

This early release supports one-time payments only. See README.md for installation, configuration, security, and privacy details.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New Plugin > Upload Plugin, or copy the bachs-donations folder into wp-content/plugins/.
2. Activate Bachs Donations.
3. Configure your Sandbox API key, webhook signing secret, and currencies in Fundraising > Payment Setup.
4. Register the webhook URL shown in settings in your Bachs dashboard and subscribe it to checkout.completed, checkout.expired, and collection.succeeded.
5. Open Fundraising > Donation Forms and click Create a donation form. Configure the cause, giving options, donor fields, and appearance in the modal, then publish. Its page includes the donation form automatically.
6. Optionally embed a form elsewhere with [bachs_donation_form id="123"], replacing 123 with the form ID.
7. Open Fundraising > Reports to review date-filtered gifts, confirmation rate, USD and NGN totals, daily performance, top forms, or export a CSV.

== Changelog ==

= 0.4.0 =
* Add accessible modal donation-form builder with live preview, campaign image, giving and donor field controls, custom text/paragraph/dropdown/consent questions, and per-form branding.
* Add a clear Bachs Payment Setup screen for API keys, webhook secrets, currencies, and connection status.
* Improve the Fundraising dashboard, forms list, phone collection, privacy export and erasure, and documentation.

= 0.3.0 =
* Add date-filtered fundraising reports with separate USD and NGN totals, daily performance, top forms, and protected CSV exports.
* Improve donor amount selection with accessible amount cards, currency-aware choices, and inline validation.

= 0.2.0 =
* Added Donation Forms with per-form currency, suggested amount, custom amount, and goal settings.
* Added public donation pages and campaign-linked donation records.
* Added a Fundraising dashboard and form-aware donation ledger.
* Added a payment provider interface for checkout, reconciliation, and normalized webhooks.

= 0.1.0 =
* Initial standalone donation plugin release.
* Added hosted USD and NGN one-time donation checkout.
* Added signed webhook verification, ledger, reconciliation, and WordPress privacy hooks.
