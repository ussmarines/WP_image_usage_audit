<?php
/**
 * Disposable wp-env smoke assertions. Run with `wp eval-file`.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'IUA_Plugin' ) || ! class_exists( 'IUA_Scanner' ) ) {
	throw new RuntimeException( 'Image Usage Audit is not active.' );
}

/**
 * Fail the smoke test with a useful message.
 *
 * @param bool   $condition Condition to assert.
 * @param string $message Failure message.
 * @return void
 */
function iua_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$iua_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
iua_smoke_assert( ! empty( $iua_admins ), 'No administrator fixture is available.' );

$iua_author_id = wp_insert_user(
	array(
		'user_login' => 'iua-smoke-author',
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'iua-smoke-author@example.test',
		'role'       => 'author',
	)
);
iua_smoke_assert( ! is_wp_error( $iua_author_id ), 'Could not create the author fixture.' );

wp_set_current_user( (int) $iua_author_id );
iua_smoke_assert( ! current_user_can( 'manage_options' ), 'Authors must not manage the audit.' );
wp_set_current_user( (int) $iua_admins[0]->ID );
iua_smoke_assert( current_user_can( 'manage_options' ), 'Administrators must manage the audit.' );

$iua_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
iua_smoke_assert( false !== $iua_png, 'PNG fixture decoding failed.' );

$iua_upload = wp_upload_bits( 'iua-smoke.png', null, $iua_png );
iua_smoke_assert( empty( $iua_upload['error'] ), 'PNG fixture upload failed.' );

$iua_attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'IUA smoke image',
		'post_status'    => 'inherit',
	),
	$iua_upload['file']
);
iua_smoke_assert( ! is_wp_error( $iua_attachment_id ) && $iua_attachment_id > 0, 'Attachment fixture creation failed.' );
update_attached_file( $iua_attachment_id, $iua_upload['file'] );

$iua_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA smoke post',
		'post_status'  => 'publish',
		'post_content' => '<img class="wp-image-' . (int) $iua_attachment_id . '" src="' . esc_url( $iua_upload['url'] ) . '">',
	)
);
iua_smoke_assert( ! is_wp_error( $iua_post_id ) && $iua_post_id > 0, 'Post fixture creation failed.' );

$iua_results = ( new IUA_Scanner() )->run();
iua_smoke_assert( in_array( (int) $iua_attachment_id, $iua_results['used_ids'], true ), 'Published attachment usage was not detected.' );
iua_smoke_assert( ! in_array( (int) $iua_attachment_id, $iua_results['unused_ids'], true ), 'Used attachment was classified as unused.' );

$iua_plugin     = IUA_Plugin::instance();
$iua_acquire    = new ReflectionMethod( IUA_Plugin::class, 'acquire_scan_lock' );
$iua_release    = new ReflectionMethod( IUA_Plugin::class, 'release_scan_lock' );
$iua_acquire->setAccessible( true );
$iua_release->setAccessible( true );
iua_smoke_assert( true === $iua_acquire->invoke( $iua_plugin ), 'First scan lock acquisition failed.' );
iua_smoke_assert( false === $iua_acquire->invoke( $iua_plugin ), 'Concurrent scan lock was not rejected.' );
$iua_release->invoke( $iua_plugin );

update_option( 'iua_usage_results', $iua_results, false );
update_option( 'iua_manual_used_ids', array( (int) $iua_attachment_id ), false );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'image-usage-audit/image-usage-audit.php' );
}
require dirname( __DIR__, 2 ) . '/uninstall.php';

iua_smoke_assert( false === get_option( 'iua_usage_results', false ), 'Uninstall retained scan results.' );
iua_smoke_assert( false === get_option( 'iua_manual_used_ids', false ), 'Uninstall retained manual decisions.' );
iua_smoke_assert( file_exists( $iua_upload['file'] ), 'Uninstall deleted user media.' );

WP_CLI::success( 'Image Usage Audit smoke assertions passed.' );
