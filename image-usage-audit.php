<?php
/**
 * Plugin Name: Image Usage Audit
 * Plugin URI: https://github.com/ussmarines/WP_image_usage_audit
 * Description: Audit image usage in the Media Library with provenance, CSV export, manual false-negative handling, and CDN rewrite support.
 * Version: 2.2.5
 * Author: elliot
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image-usage-audit
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IUA_VERSION' ) ) {
	define( 'IUA_VERSION', '2.2.5' );
}

if ( ! defined( 'IUA_SLUG' ) ) {
	define( 'IUA_SLUG', 'image-usage-audit' );
}

if ( ! defined( 'IUA_PATH' ) ) {
	define( 'IUA_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'IUA_URL' ) ) {
	define( 'IUA_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register(
	static function ( $iua_class ) {
		if ( 0 !== strpos( $iua_class, 'IUA_' ) ) {
			return;
		}

		$file = IUA_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $iua_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Main plugin bootstrap.
 */
final class IUA_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var IUA_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return IUA_Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::ensure_default_options();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		self::ensure_default_options();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

		add_action( 'admin_post_iua_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_iua_export_csv', array( $this, 'export_csv' ) );

		add_action( 'wp_ajax_iua_run_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_iua_mark_manual_used', array( $this, 'ajax_mark_manual_used' ) );
		add_action( 'wp_ajax_iua_unmark_manual_used', array( $this, 'ajax_unmark_manual_used' ) );
		add_action( 'wp_ajax_iua_mark_manual_used_bulk', array( $this, 'ajax_mark_manual_used_bulk' ) );
		add_action( 'wp_ajax_iua_unmark_manual_used_bulk', array( $this, 'ajax_unmark_manual_used_bulk' ) );
	}

	/**
	 * Ensure option defaults exist.
	 *
	 * @return void
	 */
	private static function ensure_default_options(): void {
		if ( null === get_option( 'iua_include_drafts', null ) ) {
			update_option( 'iua_include_drafts', '1' );
		}

		if ( null === get_option( 'iua_manual_used_ids', null ) ) {
			update_option( 'iua_manual_used_ids', array() );
		}

		if ( null === get_option( 'iua_cdn_rewrites', null ) ) {
			update_option( 'iua_cdn_rewrites', '' );
		}

		if ( null === get_option( 'iua_cdn_aliases', null ) ) {
			update_option( 'iua_cdn_aliases', '' );
		}
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_media_page(
			__( 'Image Usage Audit', 'image-usage-audit' ),
			__( 'Image Usage Audit', 'image-usage-audit' ),
			'upload_files',
			'iua-audit',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue_admin( string $hook ): void {
		if ( 'media_page_iua-audit' !== $hook ) {
			return;
		}

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

		wp_enqueue_style( 'iua-admin', IUA_URL . 'assets/admin.css', array(), IUA_VERSION );
		wp_enqueue_script( 'iua-admin', IUA_URL . 'assets/admin.js', array( 'jquery' ), IUA_VERSION, true );

		wp_localize_script(
			'iua-admin',
			'IUAAdmin',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'iua_scan' ),
				'last_scan' => (int) ( $iua_usage['scanned_at'] ?? 0 ),
				'urls'      => array(
					'base'   => admin_url( 'upload.php?page=iua-audit' ),
					'used'   => admin_url( 'upload.php?page=iua-audit&iua_tab=used' ),
					'unused' => admin_url( 'upload.php?page=iua-audit&iua_tab=unused' ),
				),
				'i18n'      => array(
					'marked'         => __( 'Marked as used (manual).', 'image-usage-audit' ),
					'unmarked'       => __( 'Unmarked (manual).', 'image-usage-audit' ),
					'bulk_done'      => __( 'Bulk action completed.', 'image-usage-audit' ),
					'error'          => __( 'An error occurred.', 'image-usage-audit' ),
					'run_scan'       => __( 'Run scan', 'image-usage-audit' ),
					'run_scan_again' => __( 'Run scan again', 'image-usage-audit' ),
					'scanning'       => __( 'Scanning…', 'image-usage-audit' ),
					'scan_error'     => __( 'Scan error.', 'image-usage-audit' ),
					'none_selected'  => __( 'No items selected.', 'image-usage-audit' ),
					'show_more'      => __( 'Show more', 'image-usage-audit' ),
					'show_less'      => __( 'Show less', 'image-usage-audit' ),
					/* translators: %d: number of rows currently shown after filtering. */
					'shown_count'    => __( '%d shown', 'image-usage-audit' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'image-usage-audit' ) );
		}

		include IUA_PATH . 'views/admin-page.php';
	}

	/**
	 * Handle settings saves.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'image-usage-audit' ) );
		}

		$iua_section     = '';
		$iua_raw_section = filter_input( INPUT_POST, 'iua_section', FILTER_UNSAFE_RAW );

		if ( is_string( $iua_raw_section ) ) {
			$iua_section = sanitize_key( $iua_raw_section );
		}

		if ( 'scan' === $iua_section ) {
			check_admin_referer( 'iua_save_scan_settings', 'iua_settings_nonce' );
			$iua_include_drafts = isset( $_POST['iua_include_drafts'] ) ? '1' : '0';
			update_option( 'iua_include_drafts', $iua_include_drafts );
		} elseif ( 'cdn' === $iua_section ) {
			check_admin_referer( 'iua_save_cdn_settings', 'iua_settings_nonce' );

			$iua_cdn_aliases_raw  = filter_input( INPUT_POST, 'iua_cdn_aliases', FILTER_UNSAFE_RAW );
			$iua_cdn_rewrites_raw = filter_input( INPUT_POST, 'iua_cdn_rewrites', FILTER_UNSAFE_RAW );

			$iua_cdn_aliases  = is_string( $iua_cdn_aliases_raw ) ? sanitize_text_field( $iua_cdn_aliases_raw ) : '';
			$iua_cdn_rewrites = is_string( $iua_cdn_rewrites_raw ) ? sanitize_textarea_field( $iua_cdn_rewrites_raw ) : '';

			update_option( 'iua_cdn_aliases', $iua_cdn_aliases );
			update_option( 'iua_cdn_rewrites', $iua_cdn_rewrites );
		}

		$iua_redirect_url = add_query_arg(
			array(
				'page'       => 'iua-audit',
				'iua_notice' => 'settings-saved',
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $iua_redirect_url );
		exit;
	}

	/**
	 * Run the scanner by AJAX.
	 *
	 * @return void
	 */
	public function ajax_run_scan(): void {
		check_ajax_referer( 'iua_scan', 'nonce' );

		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'image-usage-audit' ),
				)
			);
		}

		$this->maybe_raise_resource_limits();

		$iua_scanner = new IUA_Scanner();
		$iua_results = $iua_scanner->run();

		update_option( 'iua_usage_results', $iua_results );

		wp_send_json_success(
			array(
				'message' => __( 'Scan complete.', 'image-usage-audit' ),
				'results' => $iua_results,
			)
		);
	}

	/**
	 * Add or remove manual IDs.
	 *
	 * @param array $ids Attachment IDs.
	 * @param bool  $add Whether to add or remove.
	 * @return array<int, int>
	 */
	private function add_manual_ids( array $ids, bool $add = true ): array {
		$iua_ids    = $this->filter_attachment_ids( $ids );
		$iua_manual = array_map( 'intval', (array) get_option( 'iua_manual_used_ids', array() ) );

		if ( $add ) {
			$iua_manual = array_values( array_unique( array_merge( $iua_manual, $iua_ids ) ) );
		} else {
			$iua_manual = array_values( array_diff( $iua_manual, $iua_ids ) );
		}

		sort( $iua_manual, SORT_NUMERIC );
		update_option( 'iua_manual_used_ids', $iua_manual );

		return $iua_manual;
	}

	/**
	 * Mark one attachment as manually used.
	 *
	 * @return void
	 */
	public function ajax_mark_manual_used(): void {
		check_ajax_referer( 'iua_scan', 'nonce' );

		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'image-usage-audit' ),
				)
			);
		}

		$iua_id = $this->get_posted_attachment_id();

		if ( ! $this->is_valid_attachment_id( $iua_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid attachment.', 'image-usage-audit' ),
				)
			);
		}

		$iua_manual = $this->add_manual_ids( array( $iua_id ), true );

		wp_send_json_success(
			array(
				'message' => __( 'Marked as used (manual).', 'image-usage-audit' ),
				'id'      => $iua_id,
				'manual'  => $iua_manual,
			)
		);
	}

	/**
	 * Unmark one attachment as manually used.
	 *
	 * @return void
	 */
	public function ajax_unmark_manual_used(): void {
		check_ajax_referer( 'iua_scan', 'nonce' );

		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'image-usage-audit' ),
				)
			);
		}

		$iua_id = $this->get_posted_attachment_id();

		if ( ! $this->is_valid_attachment_id( $iua_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid attachment.', 'image-usage-audit' ),
				)
			);
		}

		$iua_manual = $this->add_manual_ids( array( $iua_id ), false );

		wp_send_json_success(
			array(
				'message' => __( 'Unmarked (manual).', 'image-usage-audit' ),
				'id'      => $iua_id,
				'manual'  => $iua_manual,
			)
		);
	}

	/**
	 * Bulk mark manual usage.
	 *
	 * @return void
	 */
	public function ajax_mark_manual_used_bulk(): void {
		check_ajax_referer( 'iua_scan', 'nonce' );

		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'image-usage-audit' ),
				)
			);
		}

		$iua_ids    = $this->get_posted_attachment_ids();
		$iua_manual = $this->add_manual_ids( $iua_ids, true );

		wp_send_json_success(
			array(
				'message' => __( 'Bulk marked as used (manual).', 'image-usage-audit' ),
				'ids'     => $this->filter_attachment_ids( $iua_ids ),
				'manual'  => $iua_manual,
			)
		);
	}

	/**
	 * Bulk unmark manual usage.
	 *
	 * @return void
	 */
	public function ajax_unmark_manual_used_bulk(): void {
		check_ajax_referer( 'iua_scan', 'nonce' );

		if ( ! $this->current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'image-usage-audit' ),
				)
			);
		}

		$iua_ids    = $this->get_posted_attachment_ids();
		$iua_manual = $this->add_manual_ids( $iua_ids, false );

		wp_send_json_success(
			array(
				'message' => __( 'Bulk unmarked (manual).', 'image-usage-audit' ),
				'ids'     => $this->filter_attachment_ids( $iua_ids ),
				'manual'  => $iua_manual,
			)
		);
	}

	/**
	 * Export CSV.
	 *
	 * @return void
	 */
	public function export_csv(): void {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'image-usage-audit' ) );
		}

		check_admin_referer( 'iua_export_csv' );

		$iua_tab    = '';
		$iua_filter = '';

		$iua_raw_tab    = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$iua_raw_filter = filter_input( INPUT_GET, 'filter', FILTER_UNSAFE_RAW );

		if ( is_string( $iua_raw_tab ) ) {
			$iua_tab = sanitize_key( $iua_raw_tab );
		}

		if ( is_string( $iua_raw_filter ) ) {
			$iua_filter = sanitize_key( $iua_raw_filter );
		}

		if ( ! in_array( $iua_tab, array( 'unused', 'draft', 'used' ), true ) ) {
			$iua_tab = 'unused';
		}

		if ( ! in_array( $iua_filter, array( '', 'manual' ), true ) ) {
			$iua_filter = '';
		}

		$iua_usage      = get_option( 'iua_usage_results', array() );
		$iua_manual     = array_map( 'intval', (array) get_option( 'iua_manual_used_ids', array() ) );
		$iua_provenance = isset( $iua_usage['provenance'] ) && is_array( $iua_usage['provenance'] ) ? $iua_usage['provenance'] : array();

		$iua_unused_ids = array_map( 'intval', (array) ( $iua_usage['unused_ids'] ?? array() ) );
		$iua_draft_ids  = array_map( 'intval', (array) ( $iua_usage['draft_only_ids'] ?? array() ) );
		$iua_used_ids   = array_map( 'intval', (array) ( $iua_usage['used_ids'] ?? array() ) );

		$iua_unused_ids = array_values( array_diff( $iua_unused_ids, $iua_manual ) );
		$iua_draft_ids  = array_values( array_diff( $iua_draft_ids, $iua_manual ) );
		$iua_used_ids   = array_values( array_unique( array_merge( $iua_used_ids, $iua_manual ) ) );

		$iua_export_ids = array();

		if ( 'unused' === $iua_tab ) {
			$iua_export_ids = $iua_unused_ids;
		} elseif ( 'draft' === $iua_tab ) {
			$iua_export_ids = $iua_draft_ids;
		} else {
			$iua_export_ids = $iua_used_ids;

			if ( 'manual' === $iua_filter ) {
				$iua_export_ids = array_values( array_intersect( $iua_export_ids, $iua_manual ) );
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( 'image-usage-audit-' . $iua_tab . '.csv' ) );

		$iua_output = fopen( 'php://output', 'w' );

		if ( false === $iua_output ) {
			wp_die( esc_html__( 'Unable to create the CSV export.', 'image-usage-audit' ) );
		}

		fputcsv(
			$iua_output,
			array(
				'ID',
				'Status',
				'File',
				'URL',
				'Uploaded',
				'Filesize (bytes)',
				'Manual',
				'Count',
				'Provenance',
			)
		);

		foreach ( $iua_export_ids as $iua_attachment_id ) {
			$iua_file        = get_attached_file( $iua_attachment_id );
			$iua_url         = wp_get_attachment_url( $iua_attachment_id );
			$iua_uploaded_at = get_the_date( 'Y-m-d H:i', $iua_attachment_id );
			$iua_filesize    = ( $iua_file && file_exists( $iua_file ) ) ? (string) filesize( $iua_file ) : '';
			$iua_is_manual   = in_array( $iua_attachment_id, $iua_manual, true ) ? '1' : '0';
			$iua_sources     = isset( $iua_provenance[ $iua_attachment_id ] ) && is_array( $iua_provenance[ $iua_attachment_id ] ) ? array_values( $iua_provenance[ $iua_attachment_id ] ) : array();

			fputcsv(
				$iua_output,
				array(
					$iua_attachment_id,
					$iua_tab,
					basename( (string) $iua_file ),
					(string) $iua_url,
					(string) $iua_uploaded_at,
					$iua_filesize,
					$iua_is_manual,
					count( $iua_sources ),
					implode( ' | ', $iua_sources ),
				)
			);
		}

		exit;
	}

	/**
	 * Check current capability.
	 *
	 * @return bool
	 */
	private function current_user_can_manage(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Read and sanitize a single posted attachment ID.
	 *
	 * @return int
	 */
	private function get_posted_attachment_id(): int {
		$iua_raw_id = filter_input( INPUT_POST, 'id', FILTER_UNSAFE_RAW );

		if ( is_string( $iua_raw_id ) || is_numeric( $iua_raw_id ) ) {
			return absint( $iua_raw_id );
		}

		return 0;
	}

	/**
	 * Read and sanitize posted attachment IDs.
	 *
	 * @return array<int, int>
	 */
	private function get_posted_attachment_ids(): array {
		$iua_raw_ids = filter_input( INPUT_POST, 'ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$iua_ids     = is_array( $iua_raw_ids ) ? array_map( 'absint', $iua_raw_ids ) : array();

		return array_values( array_filter( $iua_ids ) );
	}

	/**
	 * Validate a single attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_valid_attachment_id( int $attachment_id ): bool {
		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id );
	}

	/**
	 * Filter to valid attachment IDs only.
	 *
	 * @param array $ids Candidate IDs.
	 * @return array<int, int>
	 */
	private function filter_attachment_ids( array $ids ): array {
		$iua_filtered_ids = array();

		foreach ( $ids as $id ) {
			$iua_attachment_id = absint( $id );

			if ( $this->is_valid_attachment_id( $iua_attachment_id ) ) {
				$iua_filtered_ids[] = $iua_attachment_id;
			}
		}

		return array_values( array_unique( $iua_filtered_ids ) );
	}

	/**
	 * Raise limits for scans when possible.
	 *
	 * @return void
	 */
	private function maybe_raise_resource_limits(): void {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
	}
}

register_activation_hook( __FILE__, array( 'IUA_Plugin', 'activate' ) );
IUA_Plugin::instance();
