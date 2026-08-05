<?php
/**
 * Disposable multisite assertions. Run after network activation with `wp eval-file`.
 */

if ( ! defined( 'ABSPATH' ) || ! is_multisite() || ! class_exists( 'PIXCENSUS_Plugin' ) || ! class_exists( 'PIXCENSUS_Scanner' ) ) {
	throw new RuntimeException( 'A network-activated PixCensus — Media Usage Audit multisite environment is required.' );
}

/**
 * Fail the multisite smoke test with a useful message.
 *
 * @param bool   $condition Condition to assert.
 * @param string $message Failure message.
 * @return void
 */
function pixcensus_multisite_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$pixcensus_original_blog_id = get_current_blog_id();
$pixcensus_site_ids         = get_sites(
	array(
		'fields'  => 'ids',
		'number'  => 10,
		'deleted' => 0,
	)
);

pixcensus_multisite_assert( is_array( $pixcensus_site_ids ) && count( $pixcensus_site_ids ) >= 2, 'At least two active sites are required.' );

$pixcensus_primary_site_id   = (int) $pixcensus_site_ids[0];
$pixcensus_secondary_site_id = (int) $pixcensus_site_ids[1];
$pixcensus_admin             = get_user_by( 'login', 'admin' );

pixcensus_multisite_assert( $pixcensus_admin instanceof WP_User, 'The network administrator fixture is missing.' );
grant_super_admin( $pixcensus_admin->ID );
wp_set_current_user( $pixcensus_admin->ID );

$pixcensus_site_values = array(
	$pixcensus_primary_site_id   => 101,
	$pixcensus_secondary_site_id => 202,
);

foreach ( $pixcensus_site_values as $pixcensus_site_id => $pixcensus_marker ) {
	$pixcensus_switched = switch_to_blog( $pixcensus_site_id );
	pixcensus_multisite_assert( $pixcensus_switched, 'Could not switch to a multisite fixture.' );

	try {
		pixcensus_multisite_assert( current_user_can( 'manage_options' ), 'The super administrator lost manage_options in a site context.' );
		pixcensus_multisite_assert( false !== get_option( 'pixcensus_include_drafts', false ), 'Network activation did not initialize site options.' );
		update_option(
			'pixcensus_usage_results',
			array(
				'used_ids'       => array( $pixcensus_marker ),
				'draft_only_ids' => array(),
				'unused_ids'     => array(),
				'orphans'        => array(),
				'scanned_at'     => time(),
				'provenance'     => array(),
			),
			false
		);
	} finally {
		restore_current_blog();
	}
}

foreach ( $pixcensus_site_values as $pixcensus_site_id => $pixcensus_marker ) {
	switch_to_blog( $pixcensus_site_id );

	try {
		$pixcensus_results = get_option( 'pixcensus_usage_results', array() );
		pixcensus_multisite_assert( array( $pixcensus_marker ) === $pixcensus_results['used_ids'], 'Per-site scan results leaked across sites.' );
	} finally {
		restore_current_blog();
	}
}

$pixcensus_switched = switch_to_blog( $pixcensus_secondary_site_id );
pixcensus_multisite_assert( $pixcensus_switched, 'Could not enter the secondary site for scan assertions.' );

try {
	$pixcensus_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	pixcensus_multisite_assert( false !== $pixcensus_png, 'PNG fixture decoding failed.' );

	$pixcensus_upload = wp_upload_bits( 'pixcensus-multisite.png', null, $pixcensus_png );
	pixcensus_multisite_assert( empty( $pixcensus_upload['error'] ), 'Multisite media fixture upload failed.' );

	$pixcensus_attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'IUA multisite image',
			'post_status'    => 'inherit',
		),
		$pixcensus_upload['file']
	);
	pixcensus_multisite_assert( ! is_wp_error( $pixcensus_attachment_id ) && $pixcensus_attachment_id > 0, 'Multisite attachment creation failed.' );
	update_attached_file( $pixcensus_attachment_id, $pixcensus_upload['file'] );

	$pixcensus_post_id = wp_insert_post(
		array(
			'post_title'   => 'IUA multisite post',
			'post_status'  => 'publish',
			'post_content' => '<!-- wp:image {"id":' . (int) $pixcensus_attachment_id . '} --><img src="' . esc_url( $pixcensus_upload['url'] ) . '">',
		)
	);
	pixcensus_multisite_assert( ! is_wp_error( $pixcensus_post_id ) && $pixcensus_post_id > 0, 'Multisite post creation failed.' );

	$pixcensus_results = ( new PIXCENSUS_Scanner() )->run();
	pixcensus_multisite_assert( in_array( (int) $pixcensus_attachment_id, $pixcensus_results['used_ids'], true ), 'The scanner did not use the active site context.' );
} finally {
	restore_current_blog();
}

pixcensus_multisite_assert( $pixcensus_original_blog_id === get_current_blog_id(), 'The scan did not restore the original blog context.' );

$pixcensus_deleted_site_id = wpmu_create_blog(
	'pixcensus-deleted.example.test',
	'/',
	'IUA deleted fixture',
	$pixcensus_admin->ID,
	array(),
	get_current_network_id()
);

if ( ! is_wp_error( $pixcensus_deleted_site_id ) ) {
	update_blog_status( (int) $pixcensus_deleted_site_id, 'deleted', 1 );
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'pixcensus-media-audit/pixcensus-media-audit.php' );
}

require PIXCENSUS_PATH . 'uninstall.php';

pixcensus_multisite_assert( $pixcensus_original_blog_id === get_current_blog_id(), 'Multisite uninstall did not restore the original blog context.' );

foreach ( $pixcensus_site_values as $pixcensus_site_id => $pixcensus_marker ) {
	switch_to_blog( $pixcensus_site_id );

	try {
		pixcensus_multisite_assert( false === get_option( 'pixcensus_usage_results', false ), 'Multisite uninstall retained plugin results.' );

		if ( $pixcensus_secondary_site_id === $pixcensus_site_id ) {
			pixcensus_multisite_assert( get_post( $pixcensus_post_id ) instanceof WP_Post, 'Multisite uninstall deleted user content.' );
			pixcensus_multisite_assert( file_exists( $pixcensus_upload['file'] ), 'Multisite uninstall deleted user media.' );
		}
	} finally {
		restore_current_blog();
	}
}

WP_CLI::success( 'PixCensus — Media Usage Audit multisite assertions passed.' );
