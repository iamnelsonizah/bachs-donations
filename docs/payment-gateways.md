# Adding a payment gateway

Bachs Donations calls a small provider contract instead of calling Bachs directly from form and reconciliation code. The bundled `BachsGateway` is the default implementation. A separate plugin can implement `BachsDonations\PaymentGatewayInterface` and return that object through the `bachs_donations_payment_gateway` filter.

```php
add_filter(
	'bachs_donations_payment_gateway',
	static function ( $gateway ) {
		return new My_Organization_Gateway();
	}
);
```

The implementation must provide these methods:

- `is_configured()`: report whether the provider is ready to receive donations.
- `create_checkout_session( $payload, $idempotency_key )`: create a hosted payment page and return an array containing `checkout_id` and `checkout_url`.
- `payment_method_types( $currency, $configured_methods )`: return the provider's allowed method identifiers for USD or NGN. The provider adapter owns the mapping.
- `retrieve_checkout_session( $checkout_id )`: return the provider's checkout data in the shape used by `Reconciler`, including payment status, charge status, amount, currency, and charge ID.
- `is_checkout_url_allowed( $url )`: allow only the provider's HTTPS hosted-checkout origin. Use an exact trusted host allowlist, never a suffix or user-supplied URL check.
- `webhook_is_configured()`: return whether the provider's signing credentials are ready to verify events.
- `parse_webhook( $raw_body, $headers )`: verify the raw request body with the provider's signature scheme and return `false` for an invalid event, or the normalized event array below.

The webhook event array must contain:

```php
array(
	'id'          => 'provider-event-id', // unique across events handled by this plugin
	'type'        => 'checkout.completed', // or checkout.expired / collection.succeeded
	'reference'   => 'don-...',
	'donation_id' => 123, // optional when reference is present
	'checkout_id' => 'provider-checkout-id',
	'amount'      => '25.00',
	'currency'    => 'USD',
	'paid'        => true,
	'charge_id'   => 'provider-charge-id',
);
```

Normalize successful provider events to `checkout.completed` or `collection.succeeded`, and expired sessions to `checkout.expired`. The shared webhook controller then matches the donation, validates amount and currency, applies idempotency, and updates the ledger. Never set `paid` based on the donor's browser redirect. The provider must confirm it with a verified webhook or an authenticated session lookup.

The filter selects one active gateway for the site. The bundled settings page is Bachs-specific; another provider add-on must add its own credential settings and supply credentials to its gateway implementation. Each provider implementation is responsible for exact HTTPS host validation in `is_checkout_url_allowed()`.

This interface is an extension point, not a claim that other gateways are already included or selectable in the admin UI.
