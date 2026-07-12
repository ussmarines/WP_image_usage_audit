<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options for the current site.
 *
 * @return void
 */
function iua_delete_plugin_options() {
	delete_option( 'iua_usage_results' );
	delete_option( 'iua_include_drafts' );
	delete_option( 'iua_manual_used_ids' );
	delete_option( 'iua_cdn_aliases' );
	delete_option( 'iua_cdn_rewrites' );
	delete_option( 'iua_scan_lock' );
}

iua_delete_plugin_options();

if ( is_multisite() ) {
	$iua_current_blog_id = get_current_blog_id();
	$iua_offset          = 0;
	$iua_batch_size      = 100;

	do {
		$iua_blog_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $iua_batch_size,
				'offset' => $iua_offset,
			)
		);

		foreach ( $iua_blog_ids as $iua_blog_id ) {
			if ( $iua_current_blog_id === (int) $iua_blog_id ) {
				continue;
			}

			switch_to_blog( (int) $iua_blog_id );
			iua_delete_plugin_options();
			restore_current_blog();
		}

		$iua_offset += $iua_batch_size;
		$iua_count   = count( $iua_blog_ids );
	} while ( $iua_count === $iua_batch_size );
}
