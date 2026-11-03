<?php
/**
 * Safe, structured exception for Bachs API errors.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiException extends \RuntimeException {
	/** @var int */
	private $http_status;

	/** @var string */
	private $error_code;

	public function __construct( $message, $http_status = 0, $error_code = '' ) {
		parent::__construct( $message );
		$this->http_status = (int) $http_status;
		$this->error_code  = sanitize_key( $error_code );
	}

	public function get_http_status() {
		return $this->http_status;
	}

	public function get_error_code() {
		return $this->error_code;
	}
}
