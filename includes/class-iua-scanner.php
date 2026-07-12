<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scanner for image usage in content, meta, options and common builders.
 */
class IUA_Scanner {

	/**
	 * Provenance indexed by attachment ID.
	 *
	 * @var array<int, array<int, string>>
	 */
	private $provenance = array();

	/**
	 * CDN rewrite rules.
	 *
	 * @var array<int, array<string, string>>
	 */
	private $cdn_rewrites = array();

	/**
	 * CDN alias domains.
	 *
	 * @var array<int, string>
	 */
	private $cdn_aliases = array();

	/**
	 * Run the scan.
	 *
	 * @return array<string, mixed>
	 */
	public function run(): array {
		$iua_include_drafts = '1' === get_option( 'iua_include_drafts', '1' );

		$this->load_cdn_settings();

		$iua_attachment_ids = $this->get_image_attachment_ids();

		if ( empty( $iua_attachment_ids ) ) {
			return array(
				'used_ids'       => array(),
				'draft_only_ids' => array(),
				'unused_ids'     => array(),
				'orphans'        => array(),
				'scanned_at'     => time(),
				'include_drafts' => $iua_include_drafts,
				'provenance'     => array(),
			);
		}

		$iua_uploads = wp_get_upload_dir();
		$iua_basedir = isset( $iua_uploads['basedir'] ) ? (string) $iua_uploads['basedir'] : '';
		$iua_map     = $this->build_attachment_map( $iua_attachment_ids );

		$iua_used_in_published = array();
		$iua_used_in_drafts    = array();

		$iua_published_statuses = array( 'publish', 'private' );
		$iua_draft_statuses     = array( 'draft', 'pending', 'future' );

		$this->scan_post_contents( $iua_used_in_published, $iua_published_statuses, $iua_map );
		$this->scan_meta_references( $iua_used_in_published, $iua_published_statuses );
		$this->scan_builder_metas( $iua_used_in_published, $iua_published_statuses, $iua_map );
		$this->scan_any_meta_uploads( $iua_used_in_published, $iua_published_statuses, $iua_map );
		$this->scan_terms( $iua_used_in_published, $iua_map );
		$this->scan_options_for_uploads( $iua_used_in_published, $iua_map );
		$this->scan_site_identity( $iua_used_in_published );

		if ( $iua_include_drafts ) {
			$this->scan_post_contents( $iua_used_in_drafts, $iua_draft_statuses, $iua_map );
			$this->scan_meta_references( $iua_used_in_drafts, $iua_draft_statuses );
			$this->scan_builder_metas( $iua_used_in_drafts, $iua_draft_statuses, $iua_map );
			$this->scan_any_meta_uploads( $iua_used_in_drafts, $iua_draft_statuses, $iua_map );
		}

		$iua_all_ids   = array_map( 'intval', $iua_attachment_ids );
		$iua_used_ids  = array_values( array_intersect( array_map( 'intval', array_keys( $iua_used_in_published ) ), $iua_all_ids ) );
		$iua_draft_ids = array_values( array_intersect( array_map( 'intval', array_keys( $iua_used_in_drafts ) ), $iua_all_ids ) );

		$iua_draft_only_ids = array_values( array_diff( $iua_draft_ids, $iua_used_ids ) );
		$iua_unused_ids     = array_values( array_diff( $iua_all_ids, array_unique( array_merge( $iua_used_ids, $iua_draft_only_ids ) ) ) );

		$iua_manual_ids = array_map( 'intval', (array) get_option( 'iua_manual_used_ids', array() ) );

		if ( ! empty( $iua_manual_ids ) ) {
			$iua_used_ids       = array_values( array_unique( array_merge( $iua_used_ids, $iua_manual_ids ) ) );
			$iua_draft_only_ids = array_values( array_diff( $iua_draft_only_ids, $iua_manual_ids ) );
			$iua_unused_ids     = array_values( array_diff( $iua_unused_ids, $iua_manual_ids ) );
		}

		sort( $iua_used_ids, SORT_NUMERIC );
		sort( $iua_draft_only_ids, SORT_NUMERIC );
		sort( $iua_unused_ids, SORT_NUMERIC );

		return array(
			'used_ids'       => $iua_used_ids,
			'draft_only_ids' => $iua_draft_only_ids,
			'unused_ids'     => $iua_unused_ids,
			'orphans'        => $this->find_orphans( $iua_attachment_ids, $iua_basedir ),
			'scanned_at'     => time(),
			'include_drafts' => $iua_include_drafts,
			'provenance'     => $this->get_provenance_output(),
		);
	}

	/**
	 * Load CDN-related settings.
	 *
	 * @return void
	 */
	private function load_cdn_settings(): void {
		$iua_cdn_rewrites_raw = get_option( 'iua_cdn_rewrites', '' );
		$iua_cdn_aliases_raw  = get_option( 'iua_cdn_aliases', '' );

		$this->cdn_rewrites = array();

		if ( is_string( $iua_cdn_rewrites_raw ) && '' !== trim( $iua_cdn_rewrites_raw ) ) {
			$iua_lines = preg_split( '/\r?\n/', $iua_cdn_rewrites_raw );

			if ( is_array( $iua_lines ) ) {
				foreach ( $iua_lines as $iua_line ) {
					if ( false === strpos( $iua_line, '=>' ) ) {
						continue;
					}

					list( $iua_from, $iua_to ) = array_map( 'trim', explode( '=>', $iua_line, 2 ) );

					if ( '' === $iua_from || '' === $iua_to ) {
						continue;
					}

					$this->cdn_rewrites[] = array(
						'from' => $iua_from,
						'to'   => $iua_to,
					);
				}
			}
		}

		$this->cdn_aliases = array_values(
			array_filter(
				array_map(
					'trim',
					explode( ',', (string) $iua_cdn_aliases_raw )
				)
			)
		);
	}

	/**
	 * Return all image attachment IDs.
	 *
	 * @return array<int, int>
	 */
	private function get_image_attachment_ids(): array {
		$iua_image_mime_types = array_values(
			array_unique(
				array_filter(
					get_allowed_mime_types(),
					static function ( string $iua_mime_type ): bool {
						return 0 === strpos( $iua_mime_type, 'image/' );
					}
				)
			)
		);

		$iua_attachment_ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => $iua_image_mime_types,
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $iua_attachment_ids );
	}

	/**
	 * Build attachment path map for originals and generated sizes.
	 *
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @return array<string, int>
	 */
	private function build_attachment_map( array $attachment_ids ): array {
		$iua_map = array();

		foreach ( $attachment_ids as $iua_attachment_id ) {
			$iua_relative_file = get_post_meta( $iua_attachment_id, '_wp_attached_file', true );

			if ( empty( $iua_relative_file ) ) {
				continue;
			}

			$iua_relative_file             = ltrim( (string) $iua_relative_file, '/\\' );
			$iua_map[ $iua_relative_file ] = $iua_attachment_id;

			$iua_metadata = wp_get_attachment_metadata( $iua_attachment_id );

			if ( ! empty( $iua_metadata['sizes'] ) && is_array( $iua_metadata['sizes'] ) ) {
				$iua_directory = dirname( $iua_relative_file );
				$iua_directory = ( '.' === $iua_directory ) ? '' : trailingslashit( $iua_directory );

				foreach ( $iua_metadata['sizes'] as $iua_size ) {
					if ( ! empty( $iua_size['file'] ) ) {
						$iua_map[ $iua_directory . $iua_size['file'] ] = $iua_attachment_id;
					}
				}
			}
		}

		return $iua_map;
	}

	/**
	 * Scan post_content for usage.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<int, string> $statuses Allowed statuses.
	 * @param array<string, int> $path_map Attachment path map.
	 * @return void
	 */
	private function scan_post_contents( array &$used_map, array $statuses, array $path_map ): void {
		$iua_posts = $this->get_scannable_posts(
			$statuses,
			array(
				'update_post_meta_cache' => false,
			)
		);

		foreach ( $iua_posts as $iua_post ) {
			$iua_post_id = (int) $iua_post->ID;
			$iua_content = $this->normalize_text_rewrites( (string) $iua_post->post_content );

			if ( '' === $iua_content ) {
				continue;
			}

			if ( preg_match_all( '/wp-image-(\d+)/', $iua_content, $iua_matches ) ) {
				foreach ( $iua_matches[1] as $iua_match ) {
					$iua_attachment_id = (int) $iua_match;

					if ( $iua_attachment_id > 0 ) {
						$used_map[ $iua_attachment_id ] = true;
						$this->add_provenance( $iua_attachment_id, 'post:' . $iua_post_id . ' content:wp-image' );
					}
				}
			}

			$this->scan_text_for_uploads( $iua_content, $path_map, $used_map, 'post:' . $iua_post_id . ' content:url' );
		}
	}

	/**
	 * Scan featured image and common gallery references.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<int, string> $statuses Allowed statuses.
	 * @return void
	 */
	private function scan_meta_references( array &$used_map, array $statuses ): void {
		$iua_post_ids = $this->get_scannable_posts(
			$statuses,
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => true,
			)
		);

		foreach ( $iua_post_ids as $iua_post_id ) {
			$iua_post_id       = (int) $iua_post_id;
			$iua_attachment_id = (int) get_post_thumbnail_id( $iua_post_id );

			if ( $iua_attachment_id > 0 ) {
				$used_map[ $iua_attachment_id ] = true;
				$this->add_provenance( $iua_attachment_id, 'post:' . $iua_post_id . ' meta:_thumbnail_id' );
			}

			$iua_gallery_ids = array_filter(
				array_map(
					'intval',
					explode( ',', (string) get_post_meta( $iua_post_id, '_product_image_gallery', true ) )
				)
			);

			foreach ( $iua_gallery_ids as $iua_gallery_id ) {
				$used_map[ $iua_gallery_id ] = true;
				$this->add_provenance( $iua_gallery_id, 'post:' . $iua_post_id . ' meta:_product_image_gallery' );
			}
		}
	}

	/**
	 * Scan builder-specific metas.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<int, string> $statuses Allowed statuses.
	 * @param array<string, int> $path_map Attachment path map.
	 * @return void
	 */
	private function scan_builder_metas( array &$used_map, array $statuses, array $path_map ): void {
		$iua_builder_keys = array(
			'_elementor_data',
			'_elementor_draft',
			'_elementor_page_settings',
			'_elementor_css',
			'_fl_builder_data',
			'_fl_builder_draft',
			'fl_builder_data',
			'fl_builder_data_settings',
			'ct_builder_shortcodes',
			'panels_data',
			'_bricks_page_content',
			'_bricks_page_content_2',
			'_wpb_shortcodes_custom_css',
			'_wpb_shortcodes_css',
			'_et_pb_shortcodes',
		);

		$iua_post_ids = $this->get_posts_with_meta_keys( $statuses, $iua_builder_keys );

		foreach ( $iua_post_ids as $iua_post_id ) {
			$iua_post_id = (int) $iua_post_id;

			foreach ( $iua_builder_keys as $iua_meta_key ) {
				$iua_values = get_post_meta( $iua_post_id, $iua_meta_key, false );

				if ( empty( $iua_values ) ) {
					continue;
				}

				foreach ( $iua_values as $iua_value ) {
					$iua_context = 'post:' . $iua_post_id . ' meta:' . $iua_meta_key;

					$this->scan_value_for_uploads( $iua_value, $path_map, $used_map, $iua_context );
					$this->scan_builder_value_for_ids( $iua_value, $used_map, $iua_context );
				}
			}
		}
	}

	/**
	 * Scan all post meta likely to contain URLs.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<int, string> $statuses Allowed statuses.
	 * @param array<string, int> $path_map Attachment path map.
	 * @return void
	 */
	private function scan_any_meta_uploads( array &$used_map, array $statuses, array $path_map ): void {
		$iua_post_ids = $this->get_scannable_posts(
			$statuses,
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => true,
			)
		);

		foreach ( $iua_post_ids as $iua_post_id ) {
			$iua_all_meta = get_post_meta( (int) $iua_post_id );

			if ( empty( $iua_all_meta ) || ! is_array( $iua_all_meta ) ) {
				continue;
			}

			foreach ( $iua_all_meta as $iua_meta_key => $iua_meta_values ) {
				foreach ( (array) $iua_meta_values as $iua_meta_value ) {
					if ( ! $this->value_might_reference_uploads( $iua_meta_value ) ) {
						continue;
					}

					$this->scan_value_for_uploads(
						$iua_meta_value,
						$path_map,
						$used_map,
						'post:' . (int) $iua_post_id . ' meta:' . (string) $iua_meta_key
					);
				}
			}
		}
	}

	/**
	 * Scan options for usage references.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<string, int> $path_map Attachment path map.
	 * @return void
	 */
	private function scan_options_for_uploads( array &$used_map, array $path_map ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Core options enumeration is required for the audit and WordPress has no API to list all options.
		$iua_rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}" );

		if ( ! is_array( $iua_rows ) ) {
			return;
		}

		foreach ( $iua_rows as $iua_row ) {
			$iua_option_name  = isset( $iua_row->option_name ) ? (string) $iua_row->option_name : '';
			$iua_option_value = isset( $iua_row->option_value ) ? (string) $iua_row->option_value : '';

			if ( '' === $iua_option_name || ! $this->text_might_reference_uploads( $iua_option_value ) ) {
				continue;
			}

			$this->scan_text_for_uploads( $iua_option_value, $path_map, $used_map, 'option:' . $iua_option_name );
		}
	}

	/**
	 * Scan term descriptions.
	 *
	 * @param array<int, bool>   $used_map Usage map.
	 * @param array<string, int> $path_map Attachment path map.
	 * @return void
	 */
	private function scan_terms( array &$used_map, array $path_map ): void {
		$iua_terms = get_terms(
			array(
				'taxonomy'   => get_taxonomies( array(), 'names' ),
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $iua_terms ) || ! is_array( $iua_terms ) ) {
			return;
		}

		foreach ( $iua_terms as $iua_term ) {
			if ( empty( $iua_term->description ) ) {
				continue;
			}

			$this->scan_text_for_uploads( (string) $iua_term->description, $path_map, $used_map, 'term:' . (int) $iua_term->term_id . ' description' );
		}
	}

	/**
	 * Scan site identity settings.
	 *
	 * @param array<int, bool> $used_map Usage map.
	 * @return void
	 */
	private function scan_site_identity( array &$used_map ): void {
		$iua_site_icon_id = (int) get_option( 'site_icon' );

		if ( $iua_site_icon_id > 0 ) {
			$used_map[ $iua_site_icon_id ] = true;
			$this->add_provenance( $iua_site_icon_id, 'site_icon' );
		}

		$iua_custom_logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $iua_custom_logo_id > 0 ) {
			$used_map[ $iua_custom_logo_id ] = true;
			$this->add_provenance( $iua_custom_logo_id, 'custom_logo' );
		}
	}

	/**
	 * Return the post types relevant to the scan.
	 *
	 * @return array<int, string>
	 */
	private function get_scannable_post_types(): array {
		$iua_post_types = array_map( 'strval', get_post_types( array(), 'names' ) );

		return array_values(
			array_diff(
				$iua_post_types,
				array(
					'attachment',
					'revision',
					'nav_menu_item',
				)
			)
		);
	}

	/**
	 * Return posts relevant to the scan.
	 *
	 * @param array<int, string> $statuses Allowed statuses.
	 * @param array<string, mixed> $args Additional query arguments.
	 * @return array<int, mixed>
	 */
	private function get_scannable_posts( array $statuses, array $args = array() ): array {
		$iua_post_types = $this->get_scannable_post_types();
		$iua_statuses   = array_values( array_unique( array_map( 'sanitize_key', $statuses ) ) );

		if ( empty( $iua_post_types ) || empty( $iua_statuses ) ) {
			return array();
		}

		$iua_defaults = array(
			'post_type'              => $iua_post_types,
			'post_status'            => $iua_statuses,
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
		);

		$iua_posts = get_posts( array_merge( $iua_defaults, $args ) );

		return is_array( $iua_posts ) ? $iua_posts : array();
	}

	/**
	 * Return post IDs having at least one of the provided meta keys.
	 *
	 * @param array<int, string> $statuses Allowed statuses.
	 * @param array<int, string> $meta_keys Meta keys.
	 * @return array<int, int>
	 */
	private function get_posts_with_meta_keys( array $statuses, array $meta_keys ): array {
		$iua_post_ids = $this->get_scannable_posts(
			$statuses,
			array(
				'fields'                 => 'ids',
				'update_post_meta_cache' => true,
			)
		);
		$iua_matches  = array();

		foreach ( $iua_post_ids as $iua_post_id ) {
			$iua_all_meta = get_post_meta( (int) $iua_post_id );

			if ( empty( $iua_all_meta ) || ! is_array( $iua_all_meta ) ) {
				continue;
			}

			foreach ( $meta_keys as $iua_meta_key ) {
				if ( array_key_exists( $iua_meta_key, $iua_all_meta ) ) {
					$iua_matches[] = (int) $iua_post_id;
					break;
				}
			}
		}

		return $iua_matches;
	}

	/**
	 * Scan arbitrary text for uploads references.
	 *
	 * @param string            $text Text content.
	 * @param array<string, int> $path_map Attachment path map.
	 * @param array<int, bool>  $used_map Usage map.
	 * @param string            $context Provenance label.
	 * @return void
	 */
	private function scan_text_for_uploads( string $text, array $path_map, array &$used_map, string $context = '' ): void {
		$iua_text = $this->normalize_text_rewrites( $text );

		if ( '' === $iua_text ) {
			return;
		}

		if ( preg_match_all( '#/wp-content/uploads/([^\s"\')<>]+)#', $iua_text, $iua_matches ) ) {
			foreach ( $iua_matches[1] as $iua_relative_file ) {
				$iua_relative_file = ltrim( preg_replace( '#\?.*$#', '', (string) $iua_relative_file ), '/\\' );

				if ( isset( $path_map[ $iua_relative_file ] ) ) {
					$iua_attachment_id              = $path_map[ $iua_relative_file ];
					$used_map[ $iua_attachment_id ] = true;

					if ( '' !== $context ) {
						$this->add_provenance( $iua_attachment_id, $context );
					}
				}
			}
		}
	}

	/**
	 * Scan any meta or option value for upload references.
	 *
	 * @param mixed              $value Meta or option value.
	 * @param array<string, int> $path_map Attachment path map.
	 * @param array<int, bool>   $used_map Usage map.
	 * @param string             $context Provenance label.
	 * @return void
	 */
	private function scan_value_for_uploads( $value, array $path_map, array &$used_map, string $context ): void {
		foreach ( $this->flatten_scan_strings( $value ) as $iua_text ) {
			if ( '' === $iua_text || ! $this->text_might_reference_uploads( $iua_text ) ) {
				continue;
			}

			$this->scan_text_for_uploads( $iua_text, $path_map, $used_map, $context );
		}
	}

	/**
	 * Scan builder values for image IDs nested in JSON-like structures.
	 *
	 * @param mixed            $value Builder value.
	 * @param array<int, bool> $used_map Usage map.
	 * @param string           $context Provenance label.
	 * @return void
	 */
	private function scan_builder_value_for_ids( $value, array &$used_map, string $context ): void {
		if ( is_array( $value ) ) {
			foreach ( $value as $iua_key => $iua_nested_value ) {
				if ( 'id' === strtolower( (string) $iua_key ) ) {
					$iua_attachment_id = (int) $iua_nested_value;

					if ( $iua_attachment_id > 0 ) {
						$used_map[ $iua_attachment_id ] = true;
						$this->add_provenance( $iua_attachment_id, $context . ' array:id' );
					}
				}

				$this->scan_builder_value_for_ids( $iua_nested_value, $used_map, $context );
			}

			return;
		}

		if ( is_object( $value ) ) {
			$this->scan_builder_value_for_ids( get_object_vars( $value ), $used_map, $context );
			return;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return;
		}

		if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $value, $iua_matches ) ) {
			foreach ( $iua_matches[1] as $iua_match ) {
				$iua_attachment_id = (int) $iua_match;

				if ( $iua_attachment_id > 0 ) {
					$used_map[ $iua_attachment_id ] = true;
					$this->add_provenance( $iua_attachment_id, $context . ' json:id' );
				}
			}
		}
	}

	/**
	 * Flatten a value into searchable strings.
	 *
	 * @param mixed $value Value to flatten.
	 * @return array<int, string>
	 */
	private function flatten_scan_strings( $value ): array {
		if ( is_string( $value ) ) {
			return array( $value );
		}

		if ( is_scalar( $value ) ) {
			return array( (string) $value );
		}

		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$iua_strings = array();

		foreach ( $value as $iua_nested_value ) {
			$iua_strings = array_merge( $iua_strings, $this->flatten_scan_strings( $iua_nested_value ) );
		}

		return $iua_strings;
	}

	/**
	 * Determine whether a value is worth scanning for uploads.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private function value_might_reference_uploads( $value ): bool {
		foreach ( $this->flatten_scan_strings( $value ) as $iua_text ) {
			if ( $this->text_might_reference_uploads( $iua_text ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether text is worth scanning for uploads.
	 *
	 * @param string $text Text to inspect.
	 * @return bool
	 */
	private function text_might_reference_uploads( string $text ): bool {
		$iua_text     = $this->normalize_text_rewrites( $text );
		$iua_patterns = $this->get_search_patterns();

		foreach ( $iua_patterns as $iua_pattern ) {
			if ( false !== stripos( $iua_text, $iua_pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize aliases and rewrites in text.
	 *
	 * @param string $text Original text.
	 * @return string
	 */
	private function normalize_text_rewrites( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}

		foreach ( $this->cdn_aliases as $iua_alias ) {
			$iua_alias = trim( $iua_alias );

			if ( '' === $iua_alias ) {
				continue;
			}

			$iua_text = preg_replace( '#https?://' . preg_quote( $iua_alias, '#' ) . '/#i', '/', $text );

			if ( is_string( $iua_text ) ) {
				$text = $iua_text;
			}
		}

		foreach ( $this->cdn_rewrites as $iua_pair ) {
			$iua_from = isset( $iua_pair['from'] ) ? (string) $iua_pair['from'] : '';
			$iua_to   = isset( $iua_pair['to'] ) ? (string) $iua_pair['to'] : '';

			if ( '' === $iua_from || '' === $iua_to ) {
				continue;
			}

			$text = str_ireplace( $iua_from, $iua_to, $text );
		}

		return $text;
	}

	/**
	 * Record provenance for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $label Provenance label.
	 * @return void
	 */
	private function add_provenance( int $attachment_id, string $label ): void {
		if ( $attachment_id <= 0 || '' === $label ) {
			return;
		}

		if ( ! isset( $this->provenance[ $attachment_id ] ) ) {
			$this->provenance[ $attachment_id ] = array();
		}

		if ( ! in_array( $label, $this->provenance[ $attachment_id ], true ) ) {
			$this->provenance[ $attachment_id ][] = $label;
		}
	}

	/**
	 * Return limited provenance output.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function get_provenance_output(): array {
		$iua_output = array();

		foreach ( $this->provenance as $iua_attachment_id => $iua_labels ) {
			$iua_output[ (int) $iua_attachment_id ] = array_slice( array_values( $iua_labels ), 0, 12 );
		}

		return $iua_output;
	}

	/**
	 * Find orphan image files on disk.
	 *
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @param string          $basedir Uploads base dir.
	 * @return array<int, string>
	 */
	private function find_orphans( array $attachment_ids, string $basedir ): array {
		$iua_all_files = $this->find_upload_images( $basedir );
		$iua_attached  = $this->collect_all_attachment_files( $attachment_ids, $basedir );

		return array_values( array_diff( $iua_all_files, $iua_attached ) );
	}

	/**
	 * Find image files in uploads.
	 *
	 * @param string $basedir Uploads base dir.
	 * @return array<int, string>
	 */
	private function find_upload_images( string $basedir ): array {
		$iua_files      = array();
		$iua_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif' );

		if ( ! is_dir( $basedir ) ) {
			return $iua_files;
		}

		$iua_iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basedir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iua_iterator as $iua_file ) {
			if ( $iua_file->isFile() ) {
				$iua_extension = strtolower( pathinfo( $iua_file->getFilename(), PATHINFO_EXTENSION ) );

				if ( in_array( $iua_extension, $iua_extensions, true ) ) {
					$iua_files[] = wp_normalize_path( $iua_file->getPathname() );
				}
			}
		}

		return $iua_files;
	}

	/**
	 * Collect all files referenced by attachments.
	 *
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @param string          $basedir Uploads base dir.
	 * @return array<int, string>
	 */
	private function collect_all_attachment_files( array $attachment_ids, string $basedir ): array {
		$iua_files = array();

		foreach ( $attachment_ids as $iua_attachment_id ) {
			$iua_relative_file = get_post_meta( $iua_attachment_id, '_wp_attached_file', true );

			if ( empty( $iua_relative_file ) ) {
				continue;
			}

			$iua_original_file = wp_normalize_path( trailingslashit( $basedir ) . ltrim( (string) $iua_relative_file, '/\\' ) );

			if ( file_exists( $iua_original_file ) ) {
				$iua_files[] = $iua_original_file;
			}

			$iua_metadata = wp_get_attachment_metadata( $iua_attachment_id );

			if ( ! empty( $iua_metadata['sizes'] ) && is_array( $iua_metadata['sizes'] ) ) {
				$iua_directory = trailingslashit( wp_normalize_path( dirname( $iua_original_file ) ) );

				foreach ( $iua_metadata['sizes'] as $iua_size ) {
					if ( ! empty( $iua_size['file'] ) ) {
						$iua_resized_file = $iua_directory . $iua_size['file'];

						if ( file_exists( $iua_resized_file ) ) {
							$iua_files[] = $iua_resized_file;
						}
					}
				}
			}
		}

		return array_values( array_unique( $iua_files ) );
	}

	/**
	 * Get all patterns worth scanning in PHP first.
	 *
	 * @return array<int, string>
	 */
	private function get_search_patterns(): array {
		$iua_patterns = array( '/wp-content/uploads/' );

		foreach ( $this->cdn_aliases as $iua_alias ) {
			$iua_alias = trim( $iua_alias );

			if ( '' !== $iua_alias ) {
				$iua_patterns[] = $iua_alias;
			}
		}

		foreach ( $this->cdn_rewrites as $iua_pair ) {
			if ( ! empty( $iua_pair['from'] ) ) {
				$iua_patterns[] = (string) $iua_pair['from'];
			}
		}

		return array_values( array_unique( array_filter( $iua_patterns ) ) );
	}
}
