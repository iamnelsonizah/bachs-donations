<?php
/**
 * Contract for payment providers used by donation checkouts.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PaymentGatewayInterface {
	/** Report whether this provider's credentials are ready for checkout. */
	public function is_configured();

	/** Create a hosted checkout session. */
	public function create_checkout_session( $payload, $idempotency_key );

	/** Return this provider's allowed method identifiers for a charge currency. */
	public function payment_method_types( $currency, $configured_methods );

	/** Retrieve a checkout session for reconciliation. */
	public function retrieve_checkout_session( $checkout_id );

	/** Restrict redirects to this provider's trusted hosted-checkout origin. */
	public function is_checkout_url_allowed( $url );

	/** Return whether this provider has a webhook signing secret configured. */
	public function webhook_is_configured();

	/** Verify and normalize one provider webhook into the plugin's common event shape. */
	public function parse_webhook( $raw_body, $headers );
}
