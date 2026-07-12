<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$iua_include_drafts = '1' === get_option( 'iua_include_drafts', '1' );
$iua_cdn_aliases    = (string) get_option( 'iua_cdn_aliases', '' );
$iua_cdn_rewrites   = (string) get_option( 'iua_cdn_rewrites', '' );

$iua_usage = get_option(
	'iua_usage_results',
	array(
		'used_ids'       => array(),
		'draft_only_ids' => array(),
		'unused_ids'     => array(),
		'orphans'        => array(),
		'scanned_at'     => 0,
		'provenance'     => array(),
	)
);

$iua_manual_ids = array_map( 'intval', (array) get_option( 'iua_manual_used_ids', array() ) );

$iua_unused_ids = array_values(
	array_diff(
		array_map( 'intval', (array) ( $iua_usage['unused_ids'] ?? array() ) ),
		$iua_manual_ids
	)
);

$iua_draft_ids = array_values(
	array_diff(
		array_map( 'intval', (array) ( $iua_usage['draft_only_ids'] ?? array() ) ),
		$iua_manual_ids
	)
);

$iua_used_ids = array_values(
	array_unique(
		array_merge(
			array_map( 'intval', (array) ( $iua_usage['used_ids'] ?? array() ) ),
			$iua_manual_ids
		)
	)
);

$iua_raw_tab = filter_input( INPUT_GET, 'iua_tab', FILTER_UNSAFE_RAW );
$iua_tab     = is_string( $iua_raw_tab ) ? sanitize_key( $iua_raw_tab ) : 'unused';

if ( ! in_array( $iua_tab, array( 'unused', 'draft', 'used' ), true ) ) {
	$iua_tab = 'unused';
}

$iua_raw_filter = filter_input( INPUT_GET, 'iua_filter', FILTER_UNSAFE_RAW );
$iua_filter     = is_string( $iua_raw_filter ) ? sanitize_key( $iua_raw_filter ) : '';

if ( ! in_array( $iua_filter, array( '', 'manual' ), true ) ) {
	$iua_filter = '';
}

$iua_manual_used_ids = array_values( array_intersect( $iua_used_ids, $iua_manual_ids ) );
$iua_all_ids         = array();

if ( 'unused' === $iua_tab ) {
	$iua_all_ids = $iua_unused_ids;
} elseif ( 'draft' === $iua_tab ) {
	$iua_all_ids = $iua_draft_ids;
} else {
	$iua_all_ids = ( 'manual' === $iua_filter ) ? $iua_manual_used_ids : $iua_used_ids;
}

$iua_per_page = 50;
$iua_total    = count( $iua_all_ids );

$iua_raw_page = filter_input( INPUT_GET, 'iua_page', FILTER_VALIDATE_INT );
$iua_page     = ( false !== $iua_raw_page && null !== $iua_raw_page ) ? max( 1, absint( $iua_raw_page ) ) : 1;
$iua_offset   = ( $iua_page - 1 ) * $iua_per_page;
$iua_ids      = array_slice( $iua_all_ids, $iua_offset, $iua_per_page );

$iua_scanned_at = ! empty( $iua_usage['scanned_at'] )
	? wp_date( 'Y-m-d H:i', (int) $iua_usage['scanned_at'] )
	: __( 'Never', 'image-usage-audit' );

$iua_export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'iua_export_csv',
			'tab'    => $iua_tab,
			'filter' => ( 'used' === $iua_tab && 'manual' === $iua_filter ) ? 'manual' : null,
		),
		admin_url( 'admin-post.php' )
	),
	'iua_export_csv'
);

$iua_total_pages = max( 1, (int) ceil( $iua_total / $iua_per_page ) );

$iua_raw_notice = filter_input( INPUT_GET, 'iua_notice', FILTER_UNSAFE_RAW );
$iua_notice     = is_string( $iua_raw_notice ) ? sanitize_key( $iua_raw_notice ) : '';
?>
<div class="wrap" id="iua-admin">
	<?php if ( 'settings-saved' === $iua_notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'image-usage-audit' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="iua-hero">
		<h1><?php esc_html_e( 'Image Usage Audit', 'image-usage-audit' ); ?></h1>
		<div class="iua-sub"><?php esc_html_e( 'Audit image usage with provenance and safe review tools.', 'image-usage-audit' ); ?></div>
	</div>

	<div class="iua-grid">
		<div class="iua-card">
			<h2><?php esc_html_e( 'Scan', 'image-usage-audit' ); ?></h2>
			<p class="iua-help"><?php esc_html_e( 'The audit is not live. Re-run the scan to reflect content changes.', 'image-usage-audit' ); ?></p>
			<p class="iua-help"><?php esc_html_e( 'Tip: unchecking drafts makes the scan faster.', 'image-usage-audit' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iua_save_settings" />
				<input type="hidden" name="iua_section" value="scan" />
				<?php wp_nonce_field( 'iua_save_scan_settings', 'iua_settings_nonce' ); ?>

				<label class="iua-form-row">
					<input type="checkbox" name="iua_include_drafts" value="1" <?php checked( $iua_include_drafts, true ); ?> />
					<?php esc_html_e( 'Include drafts in scan (Draft-only list).', 'image-usage-audit' ); ?>
				</label>

				<p class="submit">
					<button type="submit" class="button"><?php esc_html_e( 'Save settings', 'image-usage-audit' ); ?></button>
					<button type="button" class="button button-primary" id="iua-run-scan">
						<?php echo esc_html( ! empty( $iua_usage['scanned_at'] ) ? __( 'Run scan again', 'image-usage-audit' ) : __( 'Run scan', 'image-usage-audit' ) ); ?>
					</button>
				</p>

				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: last scan datetime. */
							__( 'Last scan: %s', 'image-usage-audit' ),
							$iua_scanned_at
						)
					);
					?>
				</p>
			</form>

			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Disclaimer:', 'image-usage-audit' ); ?></strong> <?php esc_html_e( 'Images referenced via custom CSS, HTML widgets, theme files, plugin files, or external/CDN domains may require manual review.', 'image-usage-audit' ); ?></p>
			</div>

			<div class="notice notice-error inline">
				<p><strong><?php esc_html_e( 'Backup:', 'image-usage-audit' ); ?></strong> <?php esc_html_e( 'Make a full backup before deleting any images from the Media Library.', 'image-usage-audit' ); ?></p>
			</div>
		</div>

		<div class="iua-card">
			<h2><?php esc_html_e( 'CDN support', 'image-usage-audit' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iua_save_settings" />
				<input type="hidden" name="iua_section" value="cdn" />
				<?php wp_nonce_field( 'iua_save_cdn_settings', 'iua_settings_nonce' ); ?>

				<label class="iua-form-row">
					<span><?php esc_html_e( 'CDN aliases (comma-separated)', 'image-usage-audit' ); ?></span>
					<input type="text" name="iua_cdn_aliases" value="<?php echo esc_attr( $iua_cdn_aliases ); ?>" class="regular-text" placeholder="cdn.example.com, media.example.net" />
				</label>

				<label class="iua-form-row">
					<span><?php esc_html_e( 'Advanced CDN rewrites (one per line, format: FROM => TO)', 'image-usage-audit' ); ?></span>
					<textarea name="iua_cdn_rewrites" rows="4" class="large-text code" placeholder="https://cdn.example.com/assets => /wp-content/uploads&#10;/media => /wp-content/uploads"><?php echo esc_textarea( $iua_cdn_rewrites ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Each rule is applied read-only during the scan.', 'image-usage-audit' ); ?></span>
				</label>

				<p class="submit">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save CDN settings', 'image-usage-audit' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<h2 class="nav-tab-wrapper iua-tabs">
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'iua-audit', 'iua_tab' => 'unused' ), admin_url( 'upload.php' ) ) ); ?>" class="nav-tab <?php echo esc_attr( 'unused' === $iua_tab ? 'nav-tab-active' : '' ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of unused images. */
					__( 'Unused (%d)', 'image-usage-audit' ),
					count( $iua_unused_ids )
				)
			);
			?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'iua-audit', 'iua_tab' => 'draft' ), admin_url( 'upload.php' ) ) ); ?>" class="nav-tab <?php echo esc_attr( 'draft' === $iua_tab ? 'nav-tab-active' : '' ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of images used only in drafts. */
					__( 'Draft-only (%d)', 'image-usage-audit' ),
					count( $iua_draft_ids )
				)
			);
			?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'iua-audit', 'iua_tab' => 'used' ), admin_url( 'upload.php' ) ) ); ?>" class="nav-tab <?php echo esc_attr( 'used' === $iua_tab ? 'nav-tab-active' : '' ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of used images. */
					__( 'Used (published) (%d)', 'image-usage-audit' ),
					count( $iua_used_ids )
				)
			);
			?>
		</a>
	</h2>

	<p>
		<a class="button" href="<?php echo esc_url( $iua_export_url ); ?>"><?php esc_html_e( 'Export current tab (CSV with provenance)', 'image-usage-audit' ); ?></a>
	</p>

	<div class="iua-toolbar">
		<div class="iua-left">
			<label><input type="checkbox" class="iua-select-all-toggle" /> <?php esc_html_e( 'Select all visible', 'image-usage-audit' ); ?></label>

			<?php if ( 'unused' === $iua_tab ) : ?>
				<button class="button button-secondary" id="iua-bulk-mark"><?php esc_html_e( 'Mark selected as Used (manual)', 'image-usage-audit' ); ?></button>
			<?php elseif ( 'used' === $iua_tab ) : ?>
				<button class="button button-secondary" id="iua-bulk-unmark"><?php esc_html_e( 'Unmark selected (manual)', 'image-usage-audit' ); ?></button>

				<?php if ( 'manual' === $iua_filter ) : ?>
					<span class="iua-chip"><?php esc_html_e( 'Manual only', 'image-usage-audit' ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="iua-right">
			<div class="iua-columns">
				<button class="button" id="iua-columns-toggle"><?php esc_html_e( 'Columns', 'image-usage-audit' ); ?></button>
				<div id="iua-columns-panel" class="iua-columns-panel" style="display:none;">
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-thumb" data-col="thumb" checked /> <?php esc_html_e( 'Thumb', 'image-usage-audit' ); ?></label>
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-id" data-col="id" checked /> <?php esc_html_e( 'ID', 'image-usage-audit' ); ?></label>
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-file" data-col="file" checked /> <?php esc_html_e( 'File', 'image-usage-audit' ); ?></label>
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-uploaded" data-col="uploaded" checked /> <?php esc_html_e( 'Uploaded', 'image-usage-audit' ); ?></label>
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-provenance" data-col="provenance" checked /> <?php esc_html_e( 'Where used', 'image-usage-audit' ); ?></label>
					<label><input type="checkbox" class="iua-col-toggle" id="iua-col-count" data-col="count" checked /> <?php esc_html_e( 'Count', 'image-usage-audit' ); ?></label>
					<div class="iua-columns-actions">
						<a href="#" id="iua-columns-reset"><?php esc_html_e( 'Reset', 'image-usage-audit' ); ?></a>
						<span class="description"><?php esc_html_e( 'Saved locally in your browser.', 'image-usage-audit' ); ?></span>
					</div>
				</div>
			</div>

			<div class="iua-quick">
				<input type="search" id="iua-quick-filter" placeholder="<?php esc_attr_e( 'Quick filter (ID, file, provenance)…', 'image-usage-audit' ); ?>" />
				<span id="iua-quick-count" class="description"></span>
			</div>

			<div class="iua-density">
				<button class="button button-primary" data-iua-density="comfortable"><?php esc_html_e( 'Comfortable', 'image-usage-audit' ); ?></button>
				<button class="button" data-iua-density="compact"><?php esc_html_e( 'Compact', 'image-usage-audit' ); ?></button>
			</div>

			<?php if ( 'used' === $iua_tab ) : ?>
				<form method="get" class="iua-inline-form">
					<input type="hidden" name="page" value="iua-audit" />
					<input type="hidden" name="iua_tab" value="used" />
					<label>
						<?php esc_html_e( 'Filter:', 'image-usage-audit' ); ?>
						<select name="iua_filter" onchange="this.form.submit()">
							<option value="" <?php selected( $iua_filter, '' ); ?>><?php esc_html_e( 'All used', 'image-usage-audit' ); ?></option>
							<option value="manual" <?php selected( $iua_filter, 'manual' ); ?>><?php esc_html_e( 'Only manual (false negatives)', 'image-usage-audit' ); ?></option>
						</select>
					</label>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $iua_total_pages > 1 ) : ?>
		<div class="tablenav top">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'page'       => 'iua-audit',
									'iua_tab'    => $iua_tab,
									'iua_filter' => $iua_filter,
									'iua_page'   => '%#%',
								),
								admin_url( 'upload.php' )
							),
							'format'    => '',
							'current'   => $iua_page,
							'total'     => $iua_total_pages,
							'prev_text' => esc_html__( '« Previous', 'image-usage-audit' ),
							'next_text' => esc_html__( 'Next »', 'image-usage-audit' ),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>

	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th style="width:28px;"><input type="checkbox" class="iua-select-all-toggle" /></th>
				<th data-col="thumb"><?php esc_html_e( 'Thumb', 'image-usage-audit' ); ?></th>
				<th data-col="id"><?php esc_html_e( 'ID', 'image-usage-audit' ); ?></th>
				<th data-col="file"><?php esc_html_e( 'File', 'image-usage-audit' ); ?></th>
				<th data-col="uploaded"><?php esc_html_e( 'Uploaded', 'image-usage-audit' ); ?></th>
				<th data-col="provenance"><?php esc_html_e( 'Where used (provenance)', 'image-usage-audit' ); ?></th>
				<th data-col="count"><?php esc_html_e( 'Count', 'image-usage-audit' ); ?></th>
				<th data-col="actions"><?php esc_html_e( 'Actions', 'image-usage-audit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $iua_ids ) ) : ?>
				<?php foreach ( $iua_ids as $iua_attachment_id ) : ?>
					<?php
					$iua_file_path  = get_attached_file( $iua_attachment_id );
					$iua_thumb_html = wp_get_attachment_image( $iua_attachment_id, array( 60, 60 ), true, array( 'style' => 'max-width:60px;height:auto' ) );
					$iua_edit_url   = admin_url( 'post.php?post=' . $iua_attachment_id . '&action=edit' );
					$iua_provenance = ( isset( $iua_usage['provenance'][ $iua_attachment_id ] ) && is_array( $iua_usage['provenance'][ $iua_attachment_id ] ) ) ? array_values( $iua_usage['provenance'][ $iua_attachment_id ] ) : array();
					$iua_count      = count( $iua_provenance );
					$iua_haystack   = strtolower(
						implode(
							' ',
							array(
								(string) $iua_attachment_id,
								$iua_file_path ? basename( $iua_file_path ) : '',
								implode( ' ', $iua_provenance ),
							)
						)
					);
					?>
					<tr class="iua-row" id="iua-row-<?php echo esc_attr( $iua_attachment_id ); ?>" data-iua-haystack="<?php echo esc_attr( $iua_haystack ); ?>">
						<td><input type="checkbox" class="iua-select" value="<?php echo esc_attr( $iua_attachment_id ); ?>" /></td>
						<td data-col="thumb" class="iua-thumb"><?php echo $iua_thumb_html ? wp_kses_post( $iua_thumb_html ) : '&nbsp;'; ?></td>
						<td data-col="id"><?php echo esc_html( (string) $iua_attachment_id ); ?></td>
						<td data-col="file"><?php echo esc_html( $iua_file_path ? basename( $iua_file_path ) : __( '(unknown)', 'image-usage-audit' ) ); ?></td>
						<td data-col="uploaded"><?php echo esc_html( get_the_date( 'Y-m-d H:i', $iua_attachment_id ) ); ?></td>
						<td data-col="provenance">
							<?php if ( ! empty( $iua_provenance ) ) : ?>
								<?php if ( 'used' === $iua_tab ) : ?>
									<?php
									$iua_first_provenance = reset( $iua_provenance );
									$iua_other_provenance = array_slice( $iua_provenance, 1 );
									?>
									<div class="iua-prov-wrap">
										<?php if ( $iua_first_provenance ) : ?>
											<code><?php echo esc_html( $iua_first_provenance ); ?></code>
										<?php endif; ?>

										<?php if ( ! empty( $iua_other_provenance ) ) : ?>
											<div class="iua-prov-more" style="display:none;margin-top:6px;">
												<ul>
													<?php foreach ( $iua_other_provenance as $iua_provenance_item ) : ?>
														<li><code><?php echo esc_html( $iua_provenance_item ); ?></code></li>
													<?php endforeach; ?>
												</ul>
											</div>
											<p class="iua-provenance-toggle">
												<a href="#" class="button-link iua-toggle-prov"><?php esc_html_e( 'Show more', 'image-usage-audit' ); ?></a>
											</p>
										<?php endif; ?>
									</div>
								<?php else : ?>
									<ul>
										<?php foreach ( array_slice( $iua_provenance, 0, 6 ) as $iua_provenance_item ) : ?>
											<li><code><?php echo esc_html( $iua_provenance_item ); ?></code></li>
										<?php endforeach; ?>

										<?php if ( $iua_count > 6 ) : ?>
											<li>
												<?php
												echo esc_html(
													sprintf(
														/* translators: %d: hidden provenance count. */
														__( '…and %d more', 'image-usage-audit' ),
														$iua_count - 6
													)
												);
												?>
											</li>
										<?php endif; ?>
									</ul>
								<?php endif; ?>
							<?php else : ?>
								<span class="iua-muted-dash"><?php esc_html_e( '—', 'image-usage-audit' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-col="count"><?php echo esc_html( (string) $iua_count ); ?></td>
						<td data-col="actions" class="iua-actions">
							<a class="button" href="<?php echo esc_url( $iua_edit_url ); ?>"><?php esc_html_e( 'Open in Media', 'image-usage-audit' ); ?></a>

							<?php if ( 'unused' === $iua_tab ) : ?>
								<a class="button button-secondary iua-mark-used" data-id="<?php echo esc_attr( $iua_attachment_id ); ?>" href="#"><?php esc_html_e( 'Mark as Used (manual)', 'image-usage-audit' ); ?></a>
							<?php elseif ( in_array( $iua_attachment_id, $iua_manual_ids, true ) ) : ?>
								<span class="iua-badge"><?php esc_html_e( 'manual', 'image-usage-audit' ); ?></span>
								<a class="button button-link-delete iua-unmark-used" data-id="<?php echo esc_attr( $iua_attachment_id ); ?>" href="#"><?php esc_html_e( 'Unmark', 'image-usage-audit' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'No items.', 'image-usage-audit' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $iua_total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg(
								array(
									'page'       => 'iua-audit',
									'iua_tab'    => $iua_tab,
									'iua_filter' => $iua_filter,
									'iua_page'   => '%#%',
								),
								admin_url( 'upload.php' )
							),
							'format'    => '',
							'current'   => $iua_page,
							'total'     => $iua_total_pages,
							'prev_text' => esc_html__( '« Previous', 'image-usage-audit' ),
							'next_text' => esc_html__( 'Next »', 'image-usage-audit' ),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
