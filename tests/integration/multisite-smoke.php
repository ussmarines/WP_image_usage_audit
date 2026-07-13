<?php
/**
 * Disposable multisite assertions. Run after network activation with `wp eval-file`.
 */

if ( ! defined( 'ABSPATH' ) || ! is_multisite() || ! class_exists( 'IUA_Plugin' ) || ! class_exists( 'IUA_Scanner' ) ) {
	throw new RuntimeException( 'A network-activated Image Usage Audit multisite environment is required.' );
}

/**
 * Fail the multisite smoke test with a useful message.
 *
 * @param bool   $condition Condition to assert.
 * @param string $message Failure message.
 * @return void
 */
function iua_multisite_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$iua_original_blog_id = get_current_blog_id();
$iua_site_ids         = get_sites(
	array(
		'fields'  => 'ids',
		'number'  => 10,
		'deleted' => 0,
	)
);

iua_multisite_assert( is_array( $iua_site_ids ) && count( $iua_site_ids ) >= 2, 'At least two active sites are required.' );

$iua_primary_site_id   = (int) $iua_site_ids[0];
$iua_secondary_site_id = (int) $iua_site_ids[1];
$iua_admin             = get_user_by( 'login', 'admin' );

iua_multisite_assert( $iua_admin instanceof WP_User, 'The network administrator fixture is missing.' );
grant_super_admin( $iua_admin->ID );
wp_set_current_user( $iua_admin->ID );

$iua_site_values = array(
	$iua_primary_site_id   => 101,
	$iua_secondary_site_id => 202,
);

foreach ( $iua_site_values as $iua_site_id => $iua_marker ) {
	$iua_switched = switch_to_blog( $iua_site_id );
	iua_multisite_assert( $iua_switched, 'Could not switch to a multisite fixture.' );

	try {
		iua_multisite_assert( current_user_can( 'manage_options' ), 'The super administrator lost manage_options in a site context.' );
		iua_multisite_assert( false !== get_option( 'iua_include_drafts', false ), 'Network activation did not initialize site options.' );
		update_option(
			'iua_usage_results',
			array(
				'used_ids'       => array( $iua_marker ),
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

foreach ( $iua_site_values as $iua_site_id => $iua_marker ) {
	switch_to_blog( $iua_site_id );

	try {
		$iua_results = get_option( 'iua_usage_results', array() );
		iua_multisite_assert( array( $iua_marker ) === $iua_results['used_ids'], 'Per-site scan results leaked across sites.' );
	} finally {
		restore_current_blog();
	}
}

$iua_switched = switch_to_blog( $iua_secondary_site_id );
iua_multisite_assert( $iua_switched, 'Could not enter the secondary site for scan assertions.' );

try {
	$iua_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	iua_multisite_assert( false !== $iua_png, 'PNG fixture decoding failed.' );

	$iua_upload = wp_upload_bits( 'iua-multisite.png', null, $iua_png );
	iua_multisite_assert( empty( $iua_upload['error'] ), 'Multisite media fixture upload failed.' );

	$iua_attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'IUA multisite image',
			'post_status'    => 'inherit',
		),
		$iua_upload['file']
	);
	iua_multisite_assert( ! is_wp_error( $iua_attachment_id ) && $iua_attachment_id > 0, 'Multisite attachment creation failed.' );
	update_attached_file( $iua_attachment_id, $iua_upload['file'] );

	$iua_post_id = wp_insert_post(
		array(
			'post_title'   => 'IUA multisite post',
			'post_status'  => 'publish',
			'post_content' => '<!-- wp:image {"id":' . (int) $iua_attachment_id . '} --><img src="' . esc_url( $iua_upload['url'] ) . '">',
		)
	);
	iua_multisite_assert( ! is_wp_error( $iua_post_id ) && $iua_post_id > 0, 'Multisite post creation failed.' );

	$iua_results = ( new IUA_Scanner() )->run();
	iua_multisite_assert( in_array( (int) $iua_attachment_id, $iua_results['used_ids'], true ), 'The scanner did not use the active site context.' );
} finally {
	restore_current_blog();
}

iua_multisite_assert( $iua_original_blog_id === get_current_blog_id(), 'The scan did not restore the original blog context.' );

$iua_deleted_site_id = wpmu_create_blog(
	'iua-deleted.example.test',
	'/',
	'IUA deleted fixture',
	$iua_admin->ID,
	array(),
	get_current_network_id()
);

if ( ! is_wp_error( $iua_deleted_site_id ) ) {
	update_blog_status( (int) $iua_deleted_site_id, 'deleted', 1 );
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'image-usage-audit/image-usage-audit.php' );
}

require IUA_PATH . 'uninstall.php';

iua_multisite_assert( $iua_original_blog_id === get_current_blog_id(), 'Multisite uninstall did not restore the original blog context.' );

foreach ( $iua_site_values as $iua_site_id => $iua_marker ) {
	switch_to_blog( $iua_site_id );

	try {
		iua_multisite_assert( false === get_option( 'iua_usage_results', false ), 'Multisite uninstall retained plugin results.' );

		if ( $iua_secondary_site_id === $iua_site_id ) {
			iua_multisite_assert( get_post( $iua_post_id ) instanceof WP_Post, 'Multisite uninstall deleted user content.' );
			iua_multisite_assert( file_exists( $iua_upload['file'] ), 'Multisite uninstall deleted user media.' );
		}
	} finally {
		restore_current_blog();
	}
}

WP_CLI::success( 'Image Usage Audit multisite assertions passed.' );
