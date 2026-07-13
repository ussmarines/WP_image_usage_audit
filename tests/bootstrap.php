<?php
/**
 * Minimal WordPress function doubles for scanner unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

$GLOBALS['iua_test_options'] = array();
$GLOBALS['iua_test_site_options'] = array();
$GLOBALS['iua_test_current_blog'] = 1;
$GLOBALS['iua_test_blog_stack'] = array();
$GLOBALS['iua_test_sites'] = array( 1 );
$GLOBALS['iua_test_multisite'] = false;
$GLOBALS['iua_test_can_manage'] = true;
$GLOBALS['iua_test_post_types'] = array();
$GLOBALS['iua_test_actions'] = array();
$GLOBALS['iua_test_get_posts'] = null;

final class IUA_Test_Json_Response extends RuntimeException {
	/** @var bool */
	public $success;

	/** @var mixed */
	public $data;

	/** @var int */
	public $status;

	/**
	 * @param bool  $success Success flag.
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status.
	 */
	public function __construct( bool $success, $data, int $status ) {
		parent::__construct( 'JSON response' );
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}

function get_option( $name, $default = false ) {
	$iua_blog_id = $GLOBALS['iua_test_current_blog'];
	$iua_options = 1 === $iua_blog_id
		? $GLOBALS['iua_test_options']
		: ( $GLOBALS['iua_test_site_options'][ $iua_blog_id ] ?? array() );

	return array_key_exists( $name, $iua_options ) ? $iua_options[ $name ] : $default;
}

function add_option( $name, $value, $deprecated = '', $autoload = null ) {
	if ( false !== get_option( $name, false ) ) {
		return false;
	}

	return update_option( $name, $value, $autoload );
}

function update_option( $name, $value, $autoload = null ) {
	$iua_blog_id = $GLOBALS['iua_test_current_blog'];

	if ( 1 === $iua_blog_id ) {
		$GLOBALS['iua_test_options'][ $name ] = $value;
	} else {
		$GLOBALS['iua_test_site_options'][ $iua_blog_id ][ $name ] = $value;
	}

	$GLOBALS['iua_test_autoload'][ $iua_blog_id ][ $name ] = $autoload;

	return true;
}

function delete_option( $name ) {
	$iua_blog_id = $GLOBALS['iua_test_current_blog'];

	if ( 1 === $iua_blog_id ) {
		unset( $GLOBALS['iua_test_options'][ $name ] );
	} else {
		unset( $GLOBALS['iua_test_site_options'][ $iua_blog_id ][ $name ] );
	}

	return true;
}

function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . DIRECTORY_SEPARATOR;
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/image-usage-audit/';
}

function add_action( $hook, $callback ) {
	$GLOBALS['iua_test_actions'][ $hook ] = $callback;
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['iua_test_activation_hook'] = $callback;
}

function current_user_can( $capability ) {
	return 'manage_options' === $capability && $GLOBALS['iua_test_can_manage'];
}

function wp_verify_nonce( $nonce, $action ) {
	return hash_equals( $action . '-valid', $nonce ) ? 1 : false;
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function wp_send_json_error( $data = null, $status_code = null ) {
	throw new IUA_Test_Json_Response( false, $data, (int) ( $status_code ?: 200 ) );
}

function wp_send_json_success( $data = null, $status_code = null ) {
	throw new IUA_Test_Json_Response( true, $data, (int) ( $status_code ?: 200 ) );
}

function get_post_type( $post_id ) {
	return $GLOBALS['iua_test_post_types'][ (int) $post_id ] ?? false;
}

function wp_raise_memory_limit( $context = 'admin' ) {
	return '256M';
}

function wp_generate_uuid4() {
	static $iua_uuid = 0;
	++$iua_uuid;

	return sprintf( '00000000-0000-4000-8000-%012d', $iua_uuid );
}

function maybe_serialize( $value ) {
	return is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value;
}

function wp_cache_delete( $key, $group = '' ) {
	return true;
}

function is_multisite() {
	return $GLOBALS['iua_test_multisite'];
}

function get_sites( $args = array() ) {
	$iua_offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
	$iua_number = isset( $args['number'] ) ? (int) $args['number'] : count( $GLOBALS['iua_test_sites'] );

	return array_slice( $GLOBALS['iua_test_sites'], $iua_offset, $iua_number );
}

function switch_to_blog( $blog_id ) {
	$GLOBALS['iua_test_blog_stack'][] = $GLOBALS['iua_test_current_blog'];
	$GLOBALS['iua_test_current_blog'] = (int) $blog_id;

	return true;
}

function restore_current_blog() {
	if ( empty( $GLOBALS['iua_test_blog_stack'] ) ) {
		return false;
	}

	$GLOBALS['iua_test_current_blog'] = array_pop( $GLOBALS['iua_test_blog_stack'] );

	return true;
}

function get_allowed_mime_types() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'pdf'          => 'application/pdf',
	);
}

function get_posts( $args = array() ) {
	if ( is_callable( $GLOBALS['iua_test_get_posts'] ) ) {
		return call_user_func( $GLOBALS['iua_test_get_posts'], $args );
	}

	return array();
}

require_once dirname( __DIR__ ) . '/includes/class-iua-scanner.php';
require_once dirname( __DIR__ ) . '/includes/class-iua-csv.php';
require_once dirname( __DIR__ ) . '/includes/class-iua-cdn-settings.php';
require_once dirname( __DIR__ ) . '/image-usage-audit.php';
