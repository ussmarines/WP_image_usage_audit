<?php
/**
 * Bounded property-test harness for security-sensitive input helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR );
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Minimal WordPress-compatible URL parser for the production validator.
	 *
	 * @param string $url URL candidate.
	 * @return array|false
	 */
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-iua-cdn-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-iua-csv.php';

/**
 * Stop the harness with an actionable, case-specific failure.
 *
 * @param bool   $condition Required invariant.
 * @param string $message Failure message.
 * @return void
 */
function iua_property_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

try {
	$iua_payload = json_decode( stream_get_contents( STDIN ), true, 512, JSON_THROW_ON_ERROR );
} catch ( Throwable $iua_exception ) {
	fwrite( STDERR, 'Invalid property-test payload: ' . $iua_exception->getMessage() . PHP_EOL );
	exit( 1 );
}

$iua_cases = isset( $iua_payload['cases'] ) && is_array( $iua_payload['cases'] ) ? $iua_payload['cases'] : array();
$iua_assertions = 0;

foreach ( $iua_cases as $iua_index => $iua_case ) {
	iua_property_require( is_array( $iua_case ), "Case {$iua_index}: case must be an array." );

	$iua_aliases  = isset( $iua_case['aliases'] ) ? (string) $iua_case['aliases'] : '';
	$iua_rewrites = isset( $iua_case['rewrites'] ) ? (string) $iua_case['rewrites'] : '';
	$iua_csv      = isset( $iua_case['csv'] ) ? (string) $iua_case['csv'] : '';
	$iua_first    = IUA_CDN_Settings::validate( $iua_aliases, $iua_rewrites );
	$iua_second   = IUA_CDN_Settings::validate( $iua_aliases, $iua_rewrites );

	iua_property_require( $iua_first === $iua_second, "Case {$iua_index}: CDN validation is not deterministic." );
	iua_property_require(
		isset( $iua_first['valid'], $iua_first['aliases'], $iua_first['rewrites'], $iua_first['errors'] )
		&& is_bool( $iua_first['valid'] )
		&& is_string( $iua_first['aliases'] )
		&& is_string( $iua_first['rewrites'] )
		&& is_array( $iua_first['errors'] ),
		"Case {$iua_index}: CDN validation returned an invalid schema."
	);
	iua_property_require( $iua_first['valid'] === empty( $iua_first['errors'] ), "Case {$iua_index}: validity and errors disagree." );
	iua_property_require( strlen( $iua_first['aliases'] ) <= 4096, "Case {$iua_index}: aliases output exceeded its byte limit." );
	iua_property_require( strlen( $iua_first['rewrites'] ) <= 8192, "Case {$iua_index}: rewrites output exceeded its byte limit." );

	$iua_alias_count = '' === $iua_first['aliases'] ? 0 : count( explode( ', ', $iua_first['aliases'] ) );
	$iua_rule_count  = '' === $iua_first['rewrites'] ? 0 : count( explode( "\n", $iua_first['rewrites'] ) );
	iua_property_require( $iua_alias_count <= 20, "Case {$iua_index}: aliases output exceeded its item limit." );
	iua_property_require( $iua_rule_count <= 20, "Case {$iua_index}: rewrites output exceeded its item limit." );

	$iua_normalized = IUA_CDN_Settings::validate( $iua_first['aliases'], $iua_first['rewrites'] );
	iua_property_require( true === $iua_normalized['valid'], "Case {$iua_index}: normalized CDN settings are not valid." );
	iua_property_require( $iua_first['aliases'] === $iua_normalized['aliases'], "Case {$iua_index}: alias normalization is not idempotent." );
	iua_property_require( $iua_first['rewrites'] === $iua_normalized['rewrites'], "Case {$iua_index}: rewrite normalization is not idempotent." );

	$iua_csv_output = IUA_CSV::neutralize_formula( $iua_csv );
	iua_property_require( $iua_csv_output === IUA_CSV::neutralize_formula( $iua_csv_output ), "Case {$iua_index}: CSV neutralization is not idempotent." );

	$iua_is_formula = 1 === preg_match( '/^[\x00-\x20]*[=+\-@]/', $iua_csv ) || 1 === preg_match( '/^[\t\r\n]/', $iua_csv );
	if ( $iua_is_formula ) {
		iua_property_require( 0 === strpos( $iua_csv_output, "'" ), "Case {$iua_index}: a dangerous CSV formula remained active." );
	} else {
		iua_property_require( $iua_csv === $iua_csv_output, "Case {$iua_index}: a safe CSV value changed." );
	}

	$iua_assertions += 13;
}

echo json_encode(
	array(
		'result'     => 'pass',
		'cases'      => count( $iua_cases ),
		'assertions' => $iua_assertions,
	)
);
