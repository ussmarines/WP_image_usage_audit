<?php
/**
 * Prepare disposable fixtures for authenticated HTTP AJAX smoke tests.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'PIXCENSUS_Plugin' ) ) {
	throw new RuntimeException( 'PixCensus — Media Usage Audit is not active.' );
}

$pixcensus_editor = get_user_by( 'login', 'pixcensus-ajax-editor' );

if ( ! $pixcensus_editor ) {
	$pixcensus_editor_id = wp_insert_user(
		array(
			'user_login' => 'pixcensus-ajax-editor',
			'user_pass'  => 'pixcensus-ajax-editor-password',
			'user_email' => 'pixcensus-ajax-editor@example.test',
			'role'       => 'editor',
		)
	);

	if ( is_wp_error( $pixcensus_editor_id ) ) {
		throw new RuntimeException( 'Could not create the AJAX editor fixture.' );
	}
}

$pixcensus_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );

if ( false === $pixcensus_png ) {
	throw new RuntimeException( 'PNG fixture decoding failed.' );
}

$pixcensus_upload = wp_upload_bits( 'pixcensus-ajax.png', null, $pixcensus_png );

if ( ! empty( $pixcensus_upload['error'] ) ) {
	throw new RuntimeException( 'AJAX fixture upload failed.' );
}

$pixcensus_attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'IUA AJAX fixture',
		'post_status'    => 'inherit',
	),
	$pixcensus_upload['file']
);

if ( is_wp_error( $pixcensus_attachment_id ) || $pixcensus_attachment_id <= 0 ) {
	throw new RuntimeException( 'AJAX attachment fixture creation failed.' );
}

update_attached_file( $pixcensus_attachment_id, $pixcensus_upload['file'] );
update_option(
	'pixcensus_usage_results',
	array(
		'used_ids'       => array(),
		'draft_only_ids' => array(),
		'unused_ids'     => array( (int) $pixcensus_attachment_id ),
		'orphans'        => array(),
		'scanned_at'     => time(),
		'include_drafts' => true,
		'provenance'     => array(),
	),
	false
);

WP_CLI::success( 'AJAX HTTP fixtures prepared.' );
