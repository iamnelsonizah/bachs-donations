<?php
/**
 * Date-filtered fundraising analytics and a protected CSV export.
 *
 * Dollar and naira totals are always reported separately. Daily totals use
 * the UTC timestamps stored with each donation; date filters use the site's
 * local calendar dates.
 *
 * @package BachsDonations
 */

namespace BachsDonations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reports {
	const PAGE = 'bachs-donations-reports';

	/** Register the admin report and its authenticated export endpoint. */
	public static function register_hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_bachs_donations_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	/** Load report-specific styles only on the report screen. */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false !== strpos( (string) $hook_suffix, self::PAGE ) ) {
			wp_enqueue_style( 'bachs-donations-reports', plugins_url( 'assets/css/reports.css', BACHS_DONATIONS_FILE ), array(), BACHS_DONATIONS_VERSION );
		}
	}

	/** Add Reports beside the forms and donation ledger. */
	public static function register_page() {
		add_submenu_page( 'bachs-donations', __( 'Fundraising Reports', 'bachs-donations' ), __( 'Reports', 'bachs-donations' ), 'manage_options', self::PAGE, array( __CLASS__, 'render_page' ) );
	}

	/** Render date-filtered donation counts, currency totals, and form performance. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view fundraising reports.', 'bachs-donations' ) );
		}
		$range = self::date_range(
			isset( $_GET['from'] ) && is_scalar( $_GET['from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['from'] ) ) : '',
			isset( $_GET['to'] ) && is_scalar( $_GET['to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['to'] ) ) : ''
		);
		$currency = isset( $_GET['currency'] ) && is_scalar( $_GET['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['currency'] ) ) ) : '';
		if ( ! in_array( $currency, array( '', 'USD', 'NGN' ), true ) ) {
			$currency = '';
		}
		$summary_rows = DonationsStore::report_summary( $range['start_utc'], $range['end_utc'], $currency );
		$daily_rows = DonationsStore::report_daily( $range['start_utc'], $range['end_utc'], $currency );
		$form_rows = DonationsStore::report_forms( $range['start_utc'], $range['end_utc'], $currency );
		$summary = self::summarize( $summary_rows );
		$daily = self::group_daily( $daily_rows );

		echo '<div class="wrap"><h1>' . esc_html__( 'Fundraising Reports', 'bachs-donations' ) . '</h1>';
		echo '<p>' . esc_html__( 'Track confirmed gifts and checkout attempts by date, currency, and donation form. USD and NGN are never added together.', 'bachs-donations' ) . '</p>';
		if ( ! empty( $range['error'] ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $range['error'] ) . '</p></div>';
		}
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="bachs-donations-report-filters"><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><label>' . esc_html__( 'From', 'bachs-donations' ) . ' <input type="date" name="from" value="' . esc_attr( $range['from'] ) . '"></label> <label>' . esc_html__( 'To', 'bachs-donations' ) . ' <input type="date" name="to" value="' . esc_attr( $range['to'] ) . '"></label> <label>' . esc_html__( 'Currency', 'bachs-donations' ) . ' <select name="currency"><option value="">' . esc_html__( 'All currencies', 'bachs-donations' ) . '</option><option value="USD" ' . selected( $currency, 'USD', false ) . '>USD</option><option value="NGN" ' . selected( $currency, 'NGN', false ) . '>NGN</option></select></label> ';
		submit_button( __( 'Apply filters', 'bachs-donations' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<div class="bachs-donations-report-cards">';
		self::metric_card( __( 'Checkout attempts', 'bachs-donations' ), number_format_i18n( $summary['attempts'] ) );
		self::metric_card( __( 'Confirmed gifts', 'bachs-donations' ), number_format_i18n( $summary['paid'] ) );
		self::metric_card( __( 'Confirmation rate', 'bachs-donations' ), number_format_i18n( $summary['rate'], 1 ) . '%' );
		foreach ( array( 'USD', 'NGN' ) as $code ) {
			if ( '' !== $currency && $currency !== $code ) {
				continue;
			}
			$currency_summary = isset( $summary['currencies'][ $code ] ) ? $summary['currencies'][ $code ] : array( 'paid' => 0, 'amount' => 0 );
			$average = $currency_summary['paid'] > 0 ? $currency_summary['amount'] / $currency_summary['paid'] : 0;
			self::metric_card(
				sprintf( __( '%s raised', 'bachs-donations' ), $code ),
				$code . ' ' . number_format_i18n( $currency_summary['amount'], 2 ),
				sprintf( __( '%1$s gifts · %2$s average', 'bachs-donations' ), number_format_i18n( $currency_summary['paid'] ), $code . ' ' . number_format_i18n( $average, 2 ) )
			);
		}
		echo '</div>';

		echo '<div class="bachs-donations-report-toolbar"><h2>' . esc_html__( 'Daily performance', 'bachs-donations' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bachs_donations_export_csv"><input type="hidden" name="from" value="' . esc_attr( $range['from'] ) . '"><input type="hidden" name="to" value="' . esc_attr( $range['to'] ) . '"><input type="hidden" name="currency" value="' . esc_attr( $currency ) . '">';
		wp_nonce_field( 'bachs_donations_export_csv' );
		submit_button( __( 'Export CSV', 'bachs-donations' ), 'secondary', 'submit', false );
		echo '</form></div>';
		if ( empty( $daily ) ) {
			echo '<p>' . esc_html__( 'No confirmed gifts were recorded in this date range.', 'bachs-donations' ) . '</p>';
		} else {
			self::render_daily_table( $daily, $currency );
		}

		echo '<h2>' . esc_html__( 'Top donation forms', 'bachs-donations' ) . '</h2>';
		if ( empty( $form_rows ) ) {
			echo '<p>' . esc_html__( 'There are no confirmed gifts to show for this date range.', 'bachs-donations' ) . '</p>';
		} else {
			self::render_form_table( $form_rows );
		}
		echo '</div>';
	}

	/** Stream personal donation records only after an administrator check and nonce. */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export donation records.', 'bachs-donations' ) );
		}
		check_admin_referer( 'bachs_donations_export_csv' );
		$from = isset( $_POST['from'] ) && is_scalar( $_POST['from'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['from'] ) ) : '';
		$to = isset( $_POST['to'] ) && is_scalar( $_POST['to'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['to'] ) ) : '';
		$currency = isset( $_POST['currency'] ) && is_scalar( $_POST['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['currency'] ) ) ) : '';
		$range = self::date_range( $from, $to );
		if ( ! empty( $range['error'] ) || ! in_array( $currency, array( '', 'USD', 'NGN' ), true ) ) {
			wp_die( esc_html__( 'The report filters are invalid. Return to Reports and choose a valid date range.', 'bachs-donations' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bachs-donations-report-' . $range['from'] . '-to-' . $range['to'] . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Date (UTC)', 'Donation form', 'Reference', 'Currency', 'Amount', 'Status', 'Donor name', 'Donor email' ) );
		$offset = 0;
		$limit = 500;
		while ( true ) {
			$rows = DonationsStore::report_export_rows( $range['start_utc'], $range['end_utc'], $currency, $limit, $offset );
			foreach ( $rows as $row ) {
				fputcsv( $output, array_map( array( __CLASS__, 'safe_csv_cell' ), array_values( $row ) ) );
			}
			if ( count( $rows ) < $limit ) {
				break;
			}
			$offset += $limit;
		}
		fclose( $output );
		exit;
	}

	/** Stop spreadsheet applications from interpreting donor text as formulas. */
	private static function safe_csv_cell( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\s\x00-\x1F]*[=+@-]/', $value ) ? "'" . $value : $value;
	}

	/** Validate local calendar dates and convert them to the UTC storage range. */
	private static function date_range( $from, $to ) {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$default_from = $today->modify( '-29 days' )->format( 'Y-m-d' );
		$default_to = $today->format( 'Y-m-d' );
		$error = '';
		$has_input = '' !== $from || '' !== $to;
		if ( ! self::valid_date( $from ) || ! self::valid_date( $to ) ) {
			$from = $default_from;
			$to = $default_to;
			if ( $has_input ) {
				$error = __( 'Choose valid start and end dates. Showing the last 30 days instead.', 'bachs-donations' );
			}
		}
		$start_local = new \DateTimeImmutable( $from . ' 00:00:00', wp_timezone() );
		$last_local = new \DateTimeImmutable( $to . ' 00:00:00', wp_timezone() );
		$days = (int) $start_local->diff( $last_local )->format( '%r%a' );
		if ( $days < 0 || $days > 365 ) {
			$from = $default_from;
			$to = $default_to;
			$start_local = new \DateTimeImmutable( $from . ' 00:00:00', wp_timezone() );
			$last_local = new \DateTimeImmutable( $to . ' 00:00:00', wp_timezone() );
			$error = __( 'The report range must be no more than 366 days. Showing the last 30 days instead.', 'bachs-donations' );
		}
		$end_local = $last_local->modify( '+1 day' );
		$utc = new \DateTimeZone( 'UTC' );
		return array(
			'from'       => $from,
			'to'         => $to,
			'start_utc'  => $start_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'end_utc'    => $end_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			'error'      => $error,
		);
	}

	/** Strictly validate a YYYY-MM-DD date. */
	private static function valid_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return $parsed instanceof \DateTimeImmutable && $date === $parsed->format( 'Y-m-d' );
	}

	/** Convert grouped status rows into period and currency metrics. */
	private static function summarize( $rows ) {
		$result = array( 'attempts' => 0, 'paid' => 0, 'rate' => 0, 'currencies' => array() );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$code = isset( $row['currency'] ) ? strtoupper( sanitize_text_field( $row['currency'] ) ) : '';
			if ( '' === $code ) {
				continue;
			}
			$count = absint( $row['donation_count'] );
			$result['attempts'] += $count;
			if ( ! isset( $result['currencies'][ $code ] ) ) {
				$result['currencies'][ $code ] = array( 'paid' => 0, 'amount' => 0 );
			}
			if ( 'paid' === $row['status'] ) {
				$result['paid'] += $count;
				$result['currencies'][ $code ]['paid'] += $count;
				$result['currencies'][ $code ]['amount'] += (float) $row['paid_total'];
			}
		}
		$result['rate'] = $result['attempts'] > 0 ? ( $result['paid'] / $result['attempts'] ) * 100 : 0;
		return $result;
	}

	/** Combine daily query rows without combining monetary currencies. */
	private static function group_daily( $rows ) {
		$daily = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$day = isset( $row['donation_day'] ) ? (string) $row['donation_day'] : '';
			$code = isset( $row['currency'] ) ? strtoupper( sanitize_text_field( $row['currency'] ) ) : '';
			if ( '' === $day || ! in_array( $code, array( 'USD', 'NGN' ), true ) ) {
				continue;
			}
			if ( ! isset( $daily[ $day ] ) ) {
				$daily[ $day ] = array( 'USD' => array( 'count' => 0, 'amount' => 0 ), 'NGN' => array( 'count' => 0, 'amount' => 0 ) );
			}
			$daily[ $day ][ $code ] = array( 'count' => absint( $row['gift_count'] ), 'amount' => (float) $row['total'] );
		}
		krsort( $daily );
		return $daily;
	}

	/** Render a concise metric card with an optional second line. */
	private static function metric_card( $label, $value, $detail = '' ) {
		echo '<div class="bachs-donations-report-card"><p>' . esc_html( $label ) . '</p><strong>' . esc_html( $value ) . '</strong>';
		if ( '' !== $detail ) {
			echo '<small>' . esc_html( $detail ) . '</small>';
		}
		echo '</div>';
	}

	/** Render accessible daily trends with distinct USD and NGN columns. */
	private static function render_daily_table( $daily, $currency ) {
		$codes = '' === $currency ? array( 'USD', 'NGN' ) : array( $currency );
		$max = array_fill_keys( $codes, 0 );
		foreach ( $daily as $values ) {
			foreach ( $codes as $code ) {
				$max[ $code ] = max( $max[ $code ], $values[ $code ]['amount'] );
			}
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date (UTC)', 'bachs-donations' ) . '</th>';
		foreach ( $codes as $code ) {
			echo '<th>' . esc_html( $code . ' gifts' ) . '</th><th>' . esc_html( $code . ' raised' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $daily as $day => $values ) {
			echo '<tr><th scope="row">' . esc_html( $day ) . '</th>';
			foreach ( $codes as $code ) {
				$amount = $values[ $code ]['amount'];
				$percent = $max[ $code ] > 0 ? ( $amount / $max[ $code ] ) * 100 : 0;
				echo '<td>' . esc_html( number_format_i18n( $values[ $code ]['count'] ) ) . '</td><td><strong>' . esc_html( $code . ' ' . number_format_i18n( $amount, 2 ) ) . '</strong><progress max="100" value="' . esc_attr( (string) $percent ) . '" aria-label="' . esc_attr( sprintf( __( '%1$s percent of the highest daily %2$s total in this range', 'bachs-donations' ), number_format_i18n( $percent, 0 ), $code ) ) . '"></progress></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/** Render confirmed totals by form and currency. */
	private static function render_form_table( $rows ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Donation form', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Currency', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Confirmed gifts', 'bachs-donations' ) . '</th><th>' . esc_html__( 'Raised', 'bachs-donations' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$title = '' !== (string) $row['campaign_title'] ? $row['campaign_title'] : __( 'General donation form', 'bachs-donations' );
			echo '<tr><td>' . esc_html( $title ) . '</td><td>' . esc_html( $row['currency'] ) . '</td><td>' . esc_html( number_format_i18n( absint( $row['gift_count'] ) ) ) . '</td><td><strong>' . esc_html( $row['currency'] . ' ' . number_format_i18n( (float) $row['total'], 2 ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table>';
	}
}
