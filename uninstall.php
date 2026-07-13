<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options for the current site.
 *
 * @return void
 */
function iua_delete_plugin_options(): void {
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
		try {
			$iua_blog_ids = get_sites(
				array(
					'fields'  => 'ids',
					'number'  => $iua_batch_size,
					'offset'  => $iua_offset,
					'deleted' => 0,
				)
			);
		} catch ( Throwable $iua_exception ) {
			break;
		}

		if ( ! is_array( $iua_blog_ids ) || empty( $iua_blog_ids ) ) {
			break;
		}

		foreach ( $iua_blog_ids as $iua_blog_id ) {
			if ( $iua_current_blog_id === (int) $iua_blog_id ) {
				continue;
			}

			$iua_switched = false;

			try {
				$iua_switched = switch_to_blog( (int) $iua_blog_id );

				if ( $iua_switched ) {
					iua_delete_plugin_options();
				}
			} catch ( Throwable $iua_exception ) {
				// Continue so one unavailable site does not prevent cleanup elsewhere.
				continue;
			} finally {
				if ( $iua_switched ) {
					restore_current_blog();
				}
			}
		}

		$iua_count   = count( $iua_blog_ids );
		$iua_offset += $iua_count;
	} while ( $iua_count === $iua_batch_size );
}
