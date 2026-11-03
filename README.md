# Bachs Donations for WordPress

Accept one-time donations in **USD and NGN** through Bachs hosted checkout. This standalone WordPress plugin does not require WooCommerce. It is designed for NGOs, charities, and community organizations that want donors to pay on Bachs' secure checkout page.

> Early release: `0.1.0`. Test in Bachs Sandbox before enabling live donations. This release supports one-time payments only.

## Features

- Standalone WordPress plugin; no WooCommerce dependency.
- Donor-facing form with USD/NGN selection, preset amounts, custom amounts, optional name, and required email.
- USD checkout restricted to the USD card corridor.
- NGN checkout offers NGN card and bank transfer corridors, subject to the NGO's Bachs account configuration.
- Hosted Bachs checkout; this plugin never handles or stores card details.
- Signed webhook verification, replay protection, and exact currency/amount matching before a donation is marked paid.
- Donation ledger in WordPress admin, with manual checkout reconciliation if a webhook is delayed.
- WordPress personal-data exporter and eraser support.
- Secret keys can be defined in `wp-config.php` instead of stored in WordPress settings.

Bachs documents USD card, NGN card, and NGN bank transfer as supported payment corridors. Methods available to a donor still depend on the merchant's account configuration and checkout eligibility. See [Bachs supported payment methods](https://docs.bachs.io/guides/payments/payment-method-support).

The currency selector controls what the donor is charged. Bachs settlement and withdrawal currencies are configured on the Bachs account: Bachs documents that USD card charges settle to a USD Bachs balance, while settlement for NGN depends on the account's held balance currencies. Check the NGO's balance and payout eligibility in Bachs before launch; the plugin does not initiate payouts. See [Bachs supported collection, balance, and withdrawal currencies](https://docs.bachs.io/for-you/supported-currencies).

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- A Bachs account with API access and the relevant payment corridors enabled
- A publicly reachable HTTPS WordPress site for webhook delivery. Bachs also rejects localhost and private-network checkout return URLs, including in Sandbox.

## Install from a ZIP

1. Download `bachs-donations.zip` from a release, or create a ZIP containing the `bachs-donations` plugin folder.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP, choose **Install Now**, and then **Activate Plugin**.
4. Open **Settings → Bachs Donations** and enter the credentials described below.
5. Add the webhook URL in the Bachs dashboard and subscribe it to `checkout.completed`, `checkout.expired`, and `collection.succeeded`.
6. Add the shortcode `[bachs_donation_form]` to a page and publish it.
7. Complete Sandbox donations before switching to Live.

For local development, use a public HTTPS staging site or a secure tunnel that forwards to your development site. Bachs must be able to reach the webhook endpoint and the checkout return URLs.

You can also install it manually by extracting the ZIP into `wp-content/plugins/bachs-donations/`, then activating it from the WordPress Plugins page.

## Configure Bachs

Create a restricted API secret key for the plugin. It needs the permission to create checkout sessions (shown as **Create & charge**) and retrieve them for reconciliation (shown as **View payments**). The corresponding API scopes are `payments:write` and `payments:read`. Keep Sandbox and Live credentials separate. See [Bachs API key scopes](https://docs.bachs.io/developer-portal/api-keys).

In **Settings → Bachs Donations**:

1. Choose **Sandbox**.
2. Enter your Sandbox secret API key and webhook signing secret.
3. Select the currencies the organization accepts and choose the default.
4. Save settings.
5. In the Bachs Sandbox dashboard, create a webhook endpoint using the URL shown on this settings page. Subscribe it to `checkout.completed`, `checkout.expired`, and `collection.succeeded`; copy its signing secret into the matching Sandbox field.
6. Make a test donation and verify that it appears as paid in **Donations**.
7. When ready, add separate Live credentials and webhook secret, select **Live**, and save.

The webhook URL is:

```text
https://your-wordpress-site.example/wp-json/bachs-donations/v1/webhook
```

Use the exact URL displayed in the plugin settings; sites with custom REST prefixes or routing may differ. Configure a separate Bachs webhook endpoint and signing secret for Sandbox and Live, even though the WordPress URL stays the same.

The plugin only treats a donation as paid when Bachs sends a valid, recent signature for `checkout.completed` or `collection.succeeded`, the event reports a successful payment, and its amount and currency exactly match the pending donation. `checkout.expired` updates an expired checkout. The donor's browser returning from checkout is not treated as proof of payment.

## Store secrets in `wp-config.php` (recommended for production)

Define credentials before the line that says `That's all, stop editing!` in `wp-config.php`:

```php
define( 'BACHS_DONATIONS_SANDBOX_SECRET_KEY', 'sk_sandbox_your_key' );
define( 'BACHS_DONATIONS_SANDBOX_WEBHOOK_SECRET', 'whsec_your_sandbox_secret' );
define( 'BACHS_DONATIONS_LIVE_SECRET_KEY', 'sk_live_your_key' );
define( 'BACHS_DONATIONS_LIVE_WEBHOOK_SECRET', 'whsec_your_live_secret' );
```

The plugin uses the constants for credentials when they are defined. Keep `wp-config.php` out of source control and restrict server access to it. You can still select Sandbox or Live and configure currencies in the settings screen. An optional `BACHS_DONATIONS_ENVIRONMENT` constant can pin the environment to `sandbox` or `live`.

During webhook-secret rotation, define the old secret temporarily as `BACHS_DONATIONS_SANDBOX_WEBHOOK_SECRET_PREVIOUS` or `BACHS_DONATIONS_LIVE_WEBHOOK_SECRET_PREVIOUS` while updating the current secret. Remove the previous-secret constant after Bachs finishes sending signatures from the old secret.

## Add a donation form

Place this shortcode in a WordPress page, post, or shortcode-capable block:

```text
[bachs_donation_form]
```

The form shows the enabled currencies and suggested amounts. Donors can enter a different amount. A donor email is required so the hosted checkout can associate the payment with the donation; the name is optional. The form can be styled with the `bachs-donation-form` and `bachs-donation-notice` CSS classes.

## View and reconcile donations

Administrators with the `manage_options` capability can open **Donations** in WordPress admin to review the most recent 100 donation records. For an unconfirmed payment with a checkout session, choose **Check payment** to retrieve the checkout status directly from Bachs. The plugin rechecks the amount and currency before updating the ledger.

## Payment scope and current limitations

- This release handles one-time USD and NGN gifts. Recurring donations are not implemented.
- USD currently uses the USD card corridor. NGN currently offers NGN card and NGN bank transfer. Which methods Bachs actually shows depends on the account's activation, enabled methods, amount, and eligibility.
- The plugin does not create tax receipts, donor accounts, campaigns, fundraising goals, or recurring schedules.
- The plugin does not send a custom donor receipt email; configure Bachs receipt settings for payment receipts and handle any NGO tax acknowledgements separately.
- Donation data is stored in custom WordPress database tables and is not converted into WooCommerce orders.
- Bachs checkout and payment processing remain subject to Bachs account terms, capabilities, and availability.

## Security and privacy notes

- The plugin redirects donors to Bachs hosted checkout; payment card details do not pass through WordPress.
- Store production keys outside the WordPress database where practical. Do not paste live credentials into issue reports or commit them to Git.
- Webhooks are authenticated with Bachs' timestamped HMAC signature and a five-minute tolerance. Duplicate event IDs are ignored.
- The plugin stores donor name, email, amount, currency, status, Bachs identifiers, and a random token used to show the donor their return status. Donor details are sent to Bachs when a checkout is created. To limit automated checkout creation, a keyed hash derived from the visitor IP is kept for up to 15 minutes; the raw IP address is not stored for this rate limit.
- The plugin adds suggested text to the WordPress Privacy Policy page and supports the WordPress personal-data export and erasure tools. Erasure removes donor name, email, and public return token while retaining transaction fields for accounting and reconciliation. This local erasure does not delete customer or transaction records from Bachs; handle any separate request there as well. Site owners must set a retention period appropriate to local requirements.
- Uninstalling removes plugin settings and database-version options. Donation ledger tables are retained so an uninstall does not silently destroy financial history. Remove those tables manually only after exporting records and confirming your retention obligations.

## Development

The code is organized by responsibility in `includes/`: API client, settings, checkout submission, webhook verification, storage, reconciliation, admin ledger, shortcode, and privacy hooks. The plugin uses WordPress APIs and has no Composer or npm runtime dependency.

Before a release, check PHP syntax on all PHP files and exercise the flow on a disposable WordPress site with Bachs Sandbox credentials. Never test with live donor funds.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution workflow and [SECURITY.md](SECURITY.md) for private vulnerability reporting.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
