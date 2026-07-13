<?php
/**
 * Prepare disposable fixtures for authenticated HTTP AJAX smoke tests.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'IUA_Plugin' ) ) {
	throw new RuntimeException( 'Image Usage Audit is not active.' );
}

$iua_editor = get_user_by( 'login', 'iua-ajax-editor' );

if ( ! $iua_editor ) {
	$iua_editor_id = wp_insert_user(
		array(
			'user_login' => 'iua-ajax-editor',
			'user_pass'  => 'iua-ajax-editor-password',
			'user_email' => 'iua-ajax-editor@example.test',
			'role'       => 'editor',
		)
	);

	if ( is_wp_error( $iua_editor_id ) ) {
		throw new RuntimeException( 'Could not create the AJAX editor fixture.' );
	}
}

$iua_png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );

if ( false === $iua_png ) {
	throw new RuntimeException( 'PNG fixture decoding failed.' );
}

$iua_upload = wp_upload_bits( 'iua-ajax.png', null, $iua_png );

if ( ! empty( $iua_upload['error'] ) ) {
	throw new RuntimeException( 'AJAX fixture upload failed.' );
}

$iua_attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'IUA AJAX fixture',
		'post_status'    => 'inherit',
	),
	$iua_upload['file']
);

if ( is_wp_error( $iua_attachment_id ) || $iua_attachment_id <= 0 ) {
	throw new RuntimeException( 'AJAX attachment fixture creation failed.' );
}

update_attached_file( $iua_attachment_id, $iua_upload['file'] );
update_option(
	'iua_usage_results',
	array(
		'used_ids'       => array(),
		'draft_only_ids' => array(),
		'unused_ids'     => array( (int) $iua_attachment_id ),
		'orphans'        => array(),
		'scanned_at'     => time(),
		'include_drafts' => true,
		'provenance'     => array(),
	),
	false
);

WP_CLI::success( 'AJAX HTTP fixtures prepared.' );
