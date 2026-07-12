<?php
/**
 * Minimal WordPress function doubles for scanner unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

$GLOBALS['iua_test_options'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['iua_test_options'] ) ? $GLOBALS['iua_test_options'][ $name ] : $default;
}

function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}

require_once dirname( __DIR__ ) . '/includes/class-iua-scanner.php';
