<?php
/**
 * Selects the active provider and exposes the extension point for integrations.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PaymentGateways {
	/**
	 * Return the active gateway. Extensions can provide another implementation
	 * with the `bachs_donations_payment_gateway` filter.
	 *
	 * @return PaymentGatewayInterface
	 */
	public static function active() {
		$gateway = apply_filters( 'bachs_donations_payment_gateway', new BachsGateway() );
		if ( ! $gateway instanceof PaymentGatewayInterface ) {
			return new BachsGateway();
		}
		return $gateway;
	}
}
