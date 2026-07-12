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
}

iua_delete_plugin_options();

if ( is_multisite() ) {
	$iua_current_blog_id = get_current_blog_id();
	$iua_blog_ids        = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	if ( ! empty( $iua_blog_ids ) ) {
		foreach ( $iua_blog_ids as $iua_blog_id ) {
			switch_to_blog( (int) $iua_blog_id );
			iua_delete_plugin_options();
		}

		switch_to_blog( $iua_current_blog_id );
	}
}
