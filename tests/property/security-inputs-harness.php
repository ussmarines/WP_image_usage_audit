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

require_once dirname( __DIR__, 2 ) . '/includes/class-pixcensus-cdn-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-pixcensus-csv.php';

/**
 * Stop the harness with an actionable, case-specific failure.
 *
 * @param bool   $condition Required invariant.
 * @param string $message Failure message.
 * @return void
 */
function pixcensus_property_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}
}

try {
	$pixcensus_payload = json_decode( stream_get_contents( STDIN ), true, 512, JSON_THROW_ON_ERROR );
} catch ( Throwable $pixcensus_exception ) {
	fwrite( STDERR, 'Invalid property-test payload: ' . $pixcensus_exception->getMessage() . PHP_EOL );
	exit( 1 );
}

$pixcensus_cases = isset( $pixcensus_payload['cases'] ) && is_array( $pixcensus_payload['cases'] ) ? $pixcensus_payload['cases'] : array();
$pixcensus_assertions = 0;

foreach ( $pixcensus_cases as $pixcensus_index => $pixcensus_case ) {
	pixcensus_property_require( is_array( $pixcensus_case ), "Case {$pixcensus_index}: case must be an array." );

	$pixcensus_aliases  = isset( $pixcensus_case['aliases'] ) ? (string) $pixcensus_case['aliases'] : '';
	$pixcensus_rewrites = isset( $pixcensus_case['rewrites'] ) ? (string) $pixcensus_case['rewrites'] : '';
	$pixcensus_csv      = isset( $pixcensus_case['csv'] ) ? (string) $pixcensus_case['csv'] : '';
	$pixcensus_first    = PIXCENSUS_CDN_Settings::validate( $pixcensus_aliases, $pixcensus_rewrites );
	$pixcensus_second   = PIXCENSUS_CDN_Settings::validate( $pixcensus_aliases, $pixcensus_rewrites );

	pixcensus_property_require( $pixcensus_first === $pixcensus_second, "Case {$pixcensus_index}: CDN validation is not deterministic." );
	pixcensus_property_require(
		isset( $pixcensus_first['valid'], $pixcensus_first['aliases'], $pixcensus_first['rewrites'], $pixcensus_first['errors'] )
		&& is_bool( $pixcensus_first['valid'] )
		&& is_string( $pixcensus_first['aliases'] )
		&& is_string( $pixcensus_first['rewrites'] )
		&& is_array( $pixcensus_first['errors'] ),
		"Case {$pixcensus_index}: CDN validation returned an invalid schema."
	);
	pixcensus_property_require( $pixcensus_first['valid'] === empty( $pixcensus_first['errors'] ), "Case {$pixcensus_index}: validity and errors disagree." );
	pixcensus_property_require( strlen( $pixcensus_first['aliases'] ) <= 4096, "Case {$pixcensus_index}: aliases output exceeded its byte limit." );
	pixcensus_property_require( strlen( $pixcensus_first['rewrites'] ) <= 8192, "Case {$pixcensus_index}: rewrites output exceeded its byte limit." );

	$pixcensus_alias_count = '' === $pixcensus_first['aliases'] ? 0 : count( explode( ', ', $pixcensus_first['aliases'] ) );
	$pixcensus_rule_count  = '' === $pixcensus_first['rewrites'] ? 0 : count( explode( "\n", $pixcensus_first['rewrites'] ) );
	pixcensus_property_require( $pixcensus_alias_count <= 20, "Case {$pixcensus_index}: aliases output exceeded its item limit." );
	pixcensus_property_require( $pixcensus_rule_count <= 20, "Case {$pixcensus_index}: rewrites output exceeded its item limit." );

	$pixcensus_normalized = PIXCENSUS_CDN_Settings::validate( $pixcensus_first['aliases'], $pixcensus_first['rewrites'] );
	pixcensus_property_require( true === $pixcensus_normalized['valid'], "Case {$pixcensus_index}: normalized CDN settings are not valid." );
	pixcensus_property_require( $pixcensus_first['aliases'] === $pixcensus_normalized['aliases'], "Case {$pixcensus_index}: alias normalization is not idempotent." );
	pixcensus_property_require( $pixcensus_first['rewrites'] === $pixcensus_normalized['rewrites'], "Case {$pixcensus_index}: rewrite normalization is not idempotent." );

	$pixcensus_csv_output = PIXCENSUS_CSV::neutralize_formula( $pixcensus_csv );
	pixcensus_property_require( $pixcensus_csv_output === PIXCENSUS_CSV::neutralize_formula( $pixcensus_csv_output ), "Case {$pixcensus_index}: CSV neutralization is not idempotent." );

	$pixcensus_is_formula = 1 === preg_match( '/^[\x00-\x20]*[=+\-@]/', $pixcensus_csv ) || 1 === preg_match( '/^[\t\r\n]/', $pixcensus_csv );
	if ( $pixcensus_is_formula ) {
		pixcensus_property_require( 0 === strpos( $pixcensus_csv_output, "'" ), "Case {$pixcensus_index}: a dangerous CSV formula remained active." );
	} else {
		pixcensus_property_require( $pixcensus_csv === $pixcensus_csv_output, "Case {$pixcensus_index}: a safe CSV value changed." );
	}

	$pixcensus_assertions += 13;
}

echo json_encode(
	array(
		'result'     => 'pass',
		'cases'      => count( $pixcensus_cases ),
		'assertions' => $pixcensus_assertions,
	)
);
