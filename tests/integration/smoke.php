<?php
/**
 * Disposable wp-env smoke assertions. Run with `wp eval-file`.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'PIXCENSUS_Plugin' ) || ! class_exists( 'PIXCENSUS_Scanner' ) ) {
	throw new RuntimeException( 'PixCensus — Media Usage Audit is not active.' );
}

/**
 * Fail the smoke test with a useful message.
 *
 * @param bool   $condition Condition to assert.
 * @param string $message Failure message.
 * @return void
 */
function pixcensus_smoke_assert( $condition, $message ) {
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
function pixcensus_smoke_create_attachment( $filename, $contents ) {
	$pixcensus_upload = wp_upload_bits( $filename, null, $contents );
	pixcensus_smoke_assert( empty( $pixcensus_upload['error'] ), 'PNG fixture upload failed.' );

	$pixcensus_attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $filename,
			'post_status'    => 'inherit',
		),
		$pixcensus_upload['file']
	);
	pixcensus_smoke_assert( ! is_wp_error( $pixcensus_attachment_id ) && $pixcensus_attachment_id > 0, 'Attachment fixture creation failed.' );
	update_attached_file( $pixcensus_attachment_id, $pixcensus_upload['file'] );

	return array(
		'id'   => (int) $pixcensus_attachment_id,
		'file' => $pixcensus_upload['file'],
		'url'  => $pixcensus_upload['url'],
	);
}

$pixcensus_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
pixcensus_smoke_assert( ! empty( $pixcensus_admins ), 'No administrator fixture is available.' );

$pixcensus_author_id = wp_insert_user(
	array(
		'user_login' => 'pixcensus-smoke-author',
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'pixcensus-smoke-author@example.test',
		'role'       => 'author',
	)
);
pixcensus_smoke_assert( ! is_wp_error( $pixcensus_author_id ), 'Could not create the author fixture.' );

wp_set_current_user( (int) $pixcensus_author_id );
pixcensus_smoke_assert( ! current_user_can( 'manage_options' ), 'Authors must not manage the audit.' );
wp_set_current_user( (int) $pixcensus_admins[0]->ID );
pixcensus_smoke_assert( current_user_can( 'manage_options' ), 'Administrators must manage the audit.' );

$pixcensus_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
pixcensus_smoke_assert( false !== $pixcensus_png, 'PNG fixture decoding failed.' );

$pixcensus_primary_attachment = pixcensus_smoke_create_attachment( 'pixcensus-smoke.png', $pixcensus_png );
$pixcensus_batched_attachment = pixcensus_smoke_create_attachment( 'pixcensus-batched.png', $pixcensus_png );
$pixcensus_option_attachment  = pixcensus_smoke_create_attachment( 'pixcensus-option.png', $pixcensus_png );
$pixcensus_draft_attachment   = pixcensus_smoke_create_attachment( 'pixcensus-draft.png', $pixcensus_png );
$pixcensus_attachment_id      = $pixcensus_primary_attachment['id'];

$pixcensus_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA smoke post',
		'post_status'  => 'publish',
		'post_content' => '<img class="wp-image-' . (int) $pixcensus_attachment_id . '" src="' . esc_url( $pixcensus_primary_attachment['url'] ) . '">',
	)
);
pixcensus_smoke_assert( ! is_wp_error( $pixcensus_post_id ) && $pixcensus_post_id > 0, 'Post fixture creation failed.' );

for ( $pixcensus_index = 0; $pixcensus_index < 205; ++$pixcensus_index ) {
	$pixcensus_filler_id = wp_insert_post(
		array(
			'post_title'   => 'IUA batch filler ' . $pixcensus_index,
			'post_status'  => 'publish',
			'post_content' => '',
		)
	);
	pixcensus_smoke_assert( ! is_wp_error( $pixcensus_filler_id ) && $pixcensus_filler_id > 0, 'Large-site post fixture creation failed.' );
}

$pixcensus_batched_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA page-two reference',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:image {"id":' . $pixcensus_batched_attachment['id'] . '} --><img data-src="' . esc_url( $pixcensus_batched_attachment['url'] ) . '?fit=100#hero">',
	)
);
pixcensus_smoke_assert( ! is_wp_error( $pixcensus_batched_post_id ) && $pixcensus_batched_post_id > 0, 'Batched post reference creation failed.' );

for ( $pixcensus_index = 0; $pixcensus_index < 501; ++$pixcensus_index ) {
	update_option( 'pixcensus-large-fixture-' . $pixcensus_index, 'fixture-' . $pixcensus_index, false );
}
update_option( 'pixcensus-large-fixture-501', wp_json_encode( array( 'url' => $pixcensus_option_attachment['url'] ) ), false );

$pixcensus_draft_post_id = wp_insert_post(
	array(
		'post_title'   => 'IUA draft reference',
		'post_status'  => 'draft',
		'post_content' => '[caption id="attachment_' . $pixcensus_draft_attachment['id'] . '"]<img src="' . esc_url( $pixcensus_draft_attachment['url'] ) . '">[/caption]',
	)
);
pixcensus_smoke_assert( ! is_wp_error( $pixcensus_draft_post_id ) && $pixcensus_draft_post_id > 0, 'Draft fixture creation failed.' );

update_option( 'pixcensus_include_drafts', '1', false );

$pixcensus_results = ( new PIXCENSUS_Scanner() )->run();
pixcensus_smoke_assert( in_array( (int) $pixcensus_attachment_id, $pixcensus_results['used_ids'], true ), 'Published attachment usage was not detected.' );
pixcensus_smoke_assert( in_array( $pixcensus_batched_attachment['id'], $pixcensus_results['used_ids'], true ), 'A post beyond the first query batch was not scanned.' );
pixcensus_smoke_assert( in_array( $pixcensus_option_attachment['id'], $pixcensus_results['used_ids'], true ), 'An option beyond the first query batch was not scanned.' );
pixcensus_smoke_assert( in_array( $pixcensus_draft_attachment['id'], $pixcensus_results['draft_only_ids'], true ), 'Draft-only usage was not classified separately.' );
pixcensus_smoke_assert( ! in_array( (int) $pixcensus_attachment_id, $pixcensus_results['unused_ids'], true ), 'Used attachment was classified as unused.' );

update_option( 'pixcensus_include_drafts', '0', false );
$pixcensus_without_drafts = ( new PIXCENSUS_Scanner() )->run();
pixcensus_smoke_assert( in_array( $pixcensus_draft_attachment['id'], $pixcensus_without_drafts['unused_ids'], true ), 'Excluded drafts still affected classification.' );
update_option( 'pixcensus_include_drafts', '1', false );

$pixcensus_plugin     = PIXCENSUS_Plugin::instance();
$pixcensus_acquire    = new ReflectionMethod( PIXCENSUS_Plugin::class, 'acquire_scan_lock' );
$pixcensus_release    = new ReflectionMethod( PIXCENSUS_Plugin::class, 'release_scan_lock' );
$pixcensus_acquire->setAccessible( true );
$pixcensus_release->setAccessible( true );
pixcensus_smoke_assert( true === $pixcensus_acquire->invoke( $pixcensus_plugin ), 'First scan lock acquisition failed.' );
pixcensus_smoke_assert( false === $pixcensus_acquire->invoke( $pixcensus_plugin ), 'Concurrent scan lock was not rejected.' );
$pixcensus_release->invoke( $pixcensus_plugin );

update_option(
	'pixcensus_scan_lock',
	array(
		'token'      => 'expired-smoke-lock',
		'expires_at' => time() - 1,
	),
	false
);
pixcensus_smoke_assert( true === $pixcensus_acquire->invoke( $pixcensus_plugin ), 'Expired scan lock was not safely replaced.' );
$pixcensus_release->invoke( $pixcensus_plugin );

update_option( 'pixcensus_usage_results', $pixcensus_results, false );
update_option( 'pixcensus_manual_used_ids', array( (int) $pixcensus_attachment_id ), false );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The smoke test verifies the persisted autoload flag directly.
$pixcensus_results_autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		'pixcensus_usage_results'
	)
);
pixcensus_smoke_assert( ! in_array( $pixcensus_results_autoload, array( 'yes', 'on', 'auto-on' ), true ), 'Large scan results are autoloaded.' );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'pixcensus-media-audit/pixcensus-media-audit.php' );
}
require PIXCENSUS_PATH . 'uninstall.php';

pixcensus_smoke_assert( false === get_option( 'pixcensus_usage_results', false ), 'Uninstall retained scan results.' );
pixcensus_smoke_assert( false === get_option( 'pixcensus_manual_used_ids', false ), 'Uninstall retained manual decisions.' );
pixcensus_smoke_assert( file_exists( $pixcensus_primary_attachment['file'] ), 'Uninstall deleted user media.' );
pixcensus_smoke_assert( false !== get_option( 'pixcensus-large-fixture-501', false ), 'Uninstall deleted an unrelated site option.' );

WP_CLI::success( 'PixCensus — Media Usage Audit smoke assertions passed.' );
