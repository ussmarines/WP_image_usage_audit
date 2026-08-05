<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV output safety helpers.
 */
final class PIXCENSUS_CSV {

	/**
	 * Neutralize values that spreadsheet applications may interpret as formulas.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function neutralize_formula( $value ): string {
		$pixcensus_value = (string) $value;

		if ( preg_match( '/^[\x00-\x20]*[=+\-@]/', $pixcensus_value ) || preg_match( '/^[\t\r\n]/', $pixcensus_value ) ) {
			return "'" . $pixcensus_value;
		}

		return $pixcensus_value;
	}
}
