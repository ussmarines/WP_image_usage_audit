<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate and normalize CDN scanner settings.
 */
final class IUA_CDN_Settings {

	private const MAX_ALIASES        = 20;
	private const MAX_REWRITES       = 20;
	private const MAX_ALIASES_BYTES  = 4096;
	private const MAX_REWRITES_BYTES = 8192;

	/**
	 * Validate raw CDN settings.
	 *
	 * @param string $aliases_raw Comma-separated aliases.
	 * @param string $rewrites_raw Newline-separated rewrite rules.
	 * @return array{valid: bool, aliases: string, rewrites: string, errors: array<int, string>}
	 */
	public static function validate( string $aliases_raw, string $rewrites_raw ): array {
		$iua_errors   = array();
		$iua_aliases  = array();
		$iua_rewrites = array();

		if ( strlen( $aliases_raw ) > self::MAX_ALIASES_BYTES ) {
			$iua_errors[] = 'aliases_too_long';
		} else {
			$iua_alias_candidates = array_filter( array_map( 'trim', explode( ',', $aliases_raw ) ), 'strlen' );

			if ( count( $iua_alias_candidates ) > self::MAX_ALIASES ) {
				$iua_errors[] = 'too_many_aliases';
			} else {
				foreach ( $iua_alias_candidates as $iua_alias ) {
					$iua_alias = strtolower( rtrim( $iua_alias, '.' ) );

					if ( ! self::is_valid_host( $iua_alias ) ) {
						$iua_errors[] = 'invalid_alias';
						continue;
					}

					$iua_aliases[] = $iua_alias;
				}
			}
		}

		if ( strlen( $rewrites_raw ) > self::MAX_REWRITES_BYTES ) {
			$iua_errors[] = 'rewrites_too_long';
		} else {
			$iua_lines = preg_split( '/\r?\n/', $rewrites_raw );
			$iua_lines = is_array( $iua_lines ) ? array_values( array_filter( array_map( 'trim', $iua_lines ), 'strlen' ) ) : array();

			if ( count( $iua_lines ) > self::MAX_REWRITES ) {
				$iua_errors[] = 'too_many_rewrites';
			} else {
				foreach ( $iua_lines as $iua_line ) {
					if ( false === strpos( $iua_line, '=>' ) ) {
						$iua_errors[] = 'invalid_rewrite';
						continue;
					}

					list( $iua_from, $iua_to ) = array_map( 'trim', explode( '=>', $iua_line, 2 ) );

					if ( ! self::is_valid_source( $iua_from ) || ! self::is_valid_target( $iua_to ) ) {
						$iua_errors[] = 'invalid_rewrite';
						continue;
					}

					$iua_rewrites[] = $iua_from . ' => ' . $iua_to;
				}
			}
		}

		$iua_aliases  = array_values( array_unique( $iua_aliases ) );
		$iua_rewrites = array_values( array_unique( $iua_rewrites ) );

		return array(
			'valid'    => empty( $iua_errors ),
			'aliases'  => implode( ', ', $iua_aliases ),
			'rewrites' => implode( "\n", $iua_rewrites ),
			'errors'   => array_values( array_unique( $iua_errors ) ),
		);
	}

	/**
	 * Check a host name or IP address without accepting schemes, paths, or ports.
	 *
	 * @param string $host Candidate host.
	 * @return bool
	 */
	private static function is_valid_host( string $host ): bool {
		if ( '' === $host || strlen( $host ) > 253 || preg_match( '/[\s\/:?#@]/', $host ) ) {
			return false;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		return 1 === preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/', $host );
	}

	/**
	 * Validate a rewrite source.
	 *
	 * @param string $source Source prefix.
	 * @return bool
	 */
	private static function is_valid_source( string $source ): bool {
		if ( strlen( $source ) < 3 || strlen( $source ) > 2048 || preg_match( '/[\x00-\x1F\x7F]/', $source ) ) {
			return false;
		}

		if ( 0 === strpos( $source, '/' ) ) {
			return true;
		}

		$iua_parts = wp_parse_url( $source );

		return is_array( $iua_parts )
			&& isset( $iua_parts['scheme'], $iua_parts['host'] )
			&& in_array( strtolower( (string) $iua_parts['scheme'] ), array( 'http', 'https' ), true )
			&& self::is_valid_host( strtolower( (string) $iua_parts['host'] ) );
	}

	/**
	 * Validate a rewrite target recognized by the scanner.
	 *
	 * @param string $target Target prefix.
	 * @return bool
	 */
	private static function is_valid_target( string $target ): bool {
		return strlen( $target ) <= 2048
			&& 0 === strpos( $target, '/wp-content/uploads' )
			&& ! preg_match( '/[\x00-\x1F\x7F]/', $target );
	}
}
