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

/**
 * Create a disposable image attachment.
 *
 * @param string $filename Fixture filename.
 * @param string $contents Image bytes.
 * @return array{id: int, file: string, url: string}
 */
function iua_smoke_create_attachment( $filename, $contents ) {
	$iua_upload = wp_upload_bits( $filename, null, $contents );
	iua_smoke_assert( empty( $iua_upload['error'] ), 'PNG fixture upload failed.' );

	$iua_attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $filename,
			'post_status'    => 'inherit',
		),
		$iua_upload['file']
	);
	iua_smoke_assert( ! is_wp_error( $iua_attachment_id ) && $iua_attachment_id > 0, 'Attachment fixture creation failed.' );
	update_attached_file( $iua_attachment_id, $iua_upload['file'] );

	return array(
		'id'   => (int) $iua_attachment_id,
		'file' => $iua_upload['file'],
		'url'  => $iua_upload['url'],
	);
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

$iua_primary_attachment = iua_smoke_create_attachment( 'iua-smoke.png', $iua_png );
$iua_batched_attachment = iua_smoke_create_attachment( 'iua-batched.png', $iua_png );
$iua_option_attachment  = iua_smoke_create_attachment( 'iua-option.png', $iua_png );
$iua_draft_attachment   = iua_smoke_create_attachment( 'iua-draft.png', $iua_png );
$iua_attachment_id      = $iua_primary_attachment['id'];

$iua_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA smoke post',
		'post_status'  => 'publish',
		'post_content' => '<img class="wp-image-' . (int) $iua_attachment_id . '" src="' . esc_url( $iua_primary_attachment['url'] ) . '">',
	)
);
iua_smoke_assert( ! is_wp_error( $iua_post_id ) && $iua_post_id > 0, 'Post fixture creation failed.' );

for ( $iua_index = 0; $iua_index < 205; ++$iua_index ) {
	$iua_filler_id = wp_insert_post(
		array(
			'post_title'   => 'IUA batch filler ' . $iua_index,
			'post_status'  => 'publish',
			'post_content' => '',
		)
	);
	iua_smoke_assert( ! is_wp_error( $iua_filler_id ) && $iua_filler_id > 0, 'Large-site post fixture creation failed.' );
}

$iua_batched_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA page-two reference',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:image {"id":' . $iua_batched_attachment['id'] . '} --><img data-src="' . esc_url( $iua_batched_attachment['url'] ) . '?fit=100#hero">',
	)
);
iua_smoke_assert( ! is_wp_error( $iua_batched_post_id ) && $iua_batched_post_id > 0, 'Batched post reference creation failed.' );

for ( $iua_index = 0; $iua_index < 501; ++$iua_index ) {
	update_option( 'iua-large-fixture-' . $iua_index, 'fixture-' . $iua_index, false );
}
update_option( 'iua-large-fixture-501', wp_json_encode( array( 'url' => $iua_option_attachment['url'] ) ), false );

$iua_draft_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA draft reference',
		'post_status'  => 'draft',
		'post_content' => '[caption id="attachment_' . $iua_draft_attachment['id'] . '"]<img src="' . esc_url( $iua_draft_attachment['url'] ) . '">[/caption]',
	)
);
iua_smoke_assert( ! is_wp_error( $iua_draft_post_id ) && $iua_draft_post_id > 0, 'Draft fixture creation failed.' );

update_option( 'iua_include_drafts', '1', false );

$iua_results = ( new IUA_Scanner() )->run();
iua_smoke_assert( in_array( (int) $iua_attachment_id, $iua_results['used_ids'], true ), 'Published attachment usage was not detected.' );
iua_smoke_assert( in_array( $iua_batched_attachment['id'], $iua_results['used_ids'], true ), 'A post beyond the first query batch was not scanned.' );
iua_smoke_assert( in_array( $iua_option_attachment['id'], $iua_results['used_ids'], true ), 'An option beyond the first query batch was not scanned.' );
iua_smoke_assert( in_array( $iua_draft_attachment['id'], $iua_results['draft_only_ids'], true ), 'Draft-only usage was not classified separately.' );
iua_smoke_assert( ! in_array( (int) $iua_attachment_id, $iua_results['unused_ids'], true ), 'Used attachment was classified as unused.' );

update_option( 'iua_include_drafts', '0', false );
$iua_without_drafts = ( new IUA_Scanner() )->run();
iua_smoke_assert( in_array( $iua_draft_attachment['id'], $iua_without_drafts['unused_ids'], true ), 'Excluded drafts still affected classification.' );
update_option( 'iua_include_drafts', '1', false );

$iua_plugin     = IUA_Plugin::instance();
$iua_acquire    = new ReflectionMethod( IUA_Plugin::class, 'acquire_scan_lock' );
$iua_release    = new ReflectionMethod( IUA_Plugin::class, 'release_scan_lock' );
$iua_acquire->setAccessible( true );
$iua_release->setAccessible( true );
iua_smoke_assert( true === $iua_acquire->invoke( $iua_plugin ), 'First scan lock acquisition failed.' );
iua_smoke_assert( false === $iua_acquire->invoke( $iua_plugin ), 'Concurrent scan lock was not rejected.' );
$iua_release->invoke( $iua_plugin );

update_option(
	'iua_scan_lock',
	array(
		'token'      => 'expired-smoke-lock',
		'expires_at' => time() - 1,
	),
	false
);
iua_smoke_assert( true === $iua_acquire->invoke( $iua_plugin ), 'Expired scan lock was not safely replaced.' );
$iua_release->invoke( $iua_plugin );

update_option( 'iua_usage_results', $iua_results, false );
update_option( 'iua_manual_used_ids', array( (int) $iua_attachment_id ), false );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The smoke test verifies the persisted autoload flag directly.
$iua_results_autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		'iua_usage_results'
	)
);
iua_smoke_assert( ! in_array( $iua_results_autoload, array( 'yes', 'on', 'auto-on' ), true ), 'Large scan results are autoloaded.' );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'image-usage-audit/image-usage-audit.php' );
}
require IUA_PATH . 'uninstall.php';

iua_smoke_assert( false === get_option( 'iua_usage_results', false ), 'Uninstall retained scan results.' );
iua_smoke_assert( false === get_option( 'iua_manual_used_ids', false ), 'Uninstall retained manual decisions.' );
iua_smoke_assert( file_exists( $iua_primary_attachment['file'] ), 'Uninstall deleted user media.' );
iua_smoke_assert( false !== get_option( 'iua-large-fixture-501', false ), 'Uninstall deleted an unrelated site option.' );

WP_CLI::success( 'Image Usage Audit smoke assertions passed.' );
