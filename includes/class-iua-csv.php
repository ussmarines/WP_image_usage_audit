<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV output safety helpers.
 */
final class IUA_CSV {

	/**
	 * Neutralize values that spreadsheet applications may interpret as formulas.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function neutralize_formula( $value ): string {
		$iua_value = (string) $value;

		if ( preg_match( '/^[\x00-\x20]*[=+\-@]/', $iua_value ) || preg_match( '/^[\t\r\n]/', $iua_value ) ) {
			return "'" . $iua_value;
		}

		return $iua_value;
	}
}
