<?php

use PHPUnit\Framework\TestCase;

final class PluginSecurityTest extends TestCase {
	/** @var IUA_Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['iua_test_options']      = array();
		$GLOBALS['iua_test_site_options'] = array();
		$GLOBALS['iua_test_current_blog'] = 1;
		$GLOBALS['iua_test_blog_stack']   = array();
		$GLOBALS['iua_test_sites']        = array( 1 );
		$GLOBALS['iua_test_multisite']    = false;
		$GLOBALS['iua_test_can_manage']   = true;
		$GLOBALS['iua_test_post_types']   = array();
		$_POST                            = array();
		$_SERVER['REQUEST_METHOD']        = 'POST';
		$this->plugin                     = IUA_Plugin::instance();
	}

	/**
	 * @param callable $callback Callback expected to emit JSON.
	 * @param bool     $success Expected success flag.
	 * @param int      $status Expected status.
	 * @return IUA_Test_Json_Response
	 */
	private function capture_json( callable $callback, bool $success, int $status ): IUA_Test_Json_Response {
		try {
			$callback();
			$this->fail( 'Expected a JSON response.' );
		} catch ( IUA_Test_Json_Response $response ) {
			$this->assertSame( $success, $response->success );
			$this->assertSame( $status, $response->status );
			$this->assertIsArray( $response->data );
			$this->assertArrayHasKey( 'message', $response->data );

			return $response;
		}
	}

	private function set_request( string $action, string $nonce ): void {
		$_POST = array(
			'action' => $action,
			'nonce'  => $nonce,
		);
	}

	public function test_unauthenticated_ajax_actions_have_stable_json_hooks(): void {
		$actions = array(
			'iua_run_scan',
			'iua_mark_manual_used',
			'iua_unmark_manual_used',
			'iua_mark_manual_used_bulk',
			'iua_unmark_manual_used_bulk',
		);

		foreach ( $actions as $action ) {
			$this->assertArrayHasKey( 'wp_ajax_nopriv_' . $action, $GLOBALS['iua_test_actions'] );
		}

		$this->capture_json( array( $this->plugin, 'ajax_unauthorized' ), false, 401 );
	}

	public function test_ajax_rejects_wrong_method_action_permission_and_nonce(): void {
		$this->set_request( 'iua_mark_manual_used', 'iua_mark_manual_used-valid' );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 405 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->set_request( 'iua_unmark_manual_used', 'iua_mark_manual_used-valid' );
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 400 );

		$this->set_request( 'iua_mark_manual_used', 'iua_mark_manual_used-valid' );
		$GLOBALS['iua_test_can_manage'] = false;
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 403 );

		$GLOBALS['iua_test_can_manage'] = true;
		$this->set_request( 'iua_mark_manual_used', '' );
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 403 );

		$this->set_request( 'iua_mark_manual_used', 'iua_unmark_manual_used-valid' );
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 403 );
	}

	public function test_single_and_bulk_ajax_inputs_are_strictly_bounded(): void {
		$GLOBALS['iua_test_post_types'][11] = 'attachment';
		$this->set_request( 'iua_mark_manual_used', 'iua_mark_manual_used-valid' );
		$_POST['id'] = '11';
		$response = $this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), true, 200 );
		$this->assertSame( 11, $response->data['id'] );
		$this->assertSame( array( 11 ), get_option( 'iua_manual_used_ids' ) );

		foreach ( array( '', '11oops', str_repeat( '9', 21 ), array( 11 ) ) as $invalid_id ) {
			$this->set_request( 'iua_mark_manual_used', 'iua_mark_manual_used-valid' );
			$_POST['id'] = $invalid_id;
			$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used' ), false, 400 );
		}

		$this->set_request( 'iua_mark_manual_used_bulk', 'iua_mark_manual_used_bulk-valid' );
		$_POST['ids'] = array_fill( 0, 501, '11' );
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used_bulk' ), false, 400 );

		$this->set_request( 'iua_mark_manual_used_bulk', 'iua_mark_manual_used_bulk-valid' );
		$_POST['ids'] = array( '11', 'bad' );
		$this->capture_json( array( $this->plugin, 'ajax_mark_manual_used_bulk' ), false, 400 );
	}

	public function test_concurrent_scan_is_refused_with_conflict_response(): void {
		update_option(
			'iua_scan_lock',
			array(
				'token'      => 'another-request',
				'expires_at' => time() + 300,
			),
			false
		);
		$this->set_request( 'iua_run_scan', 'iua_run_scan-valid' );

		$this->capture_json( array( $this->plugin, 'ajax_run_scan' ), false, 409 );
	}

	public function test_scan_lock_is_non_autoloaded_and_owner_released(): void {
		$acquire = new ReflectionMethod( IUA_Plugin::class, 'acquire_scan_lock' );
		$release = new ReflectionMethod( IUA_Plugin::class, 'release_scan_lock' );
		$acquire->setAccessible( true );
		$release->setAccessible( true );

		$this->assertTrue( $acquire->invoke( $this->plugin ) );
		$this->assertFalse( $acquire->invoke( $this->plugin ) );
		$this->assertFalse( $GLOBALS['iua_test_autoload'][1]['iua_scan_lock'] );

		$owned_lock = get_option( 'iua_scan_lock' );
		$this->assertIsArray( $owned_lock );
		$this->assertArrayHasKey( 'token', $owned_lock );
		$release->invoke( $this->plugin );
		$this->assertFalse( get_option( 'iua_scan_lock', false ) );

		$this->assertTrue( $acquire->invoke( $this->plugin ) );
		update_option(
			'iua_scan_lock',
			array(
				'token'      => 'replacement-owner',
				'expires_at' => time() + 300,
			),
			false
		);
		$release->invoke( $this->plugin );
		$this->assertSame( 'replacement-owner', get_option( 'iua_scan_lock' )['token'] );
	}

	public function test_network_activation_initializes_each_site_and_restores_context(): void {
		$GLOBALS['iua_test_multisite'] = true;
		$GLOBALS['iua_test_sites']     = array( 1, 2, 3 );

		IUA_Plugin::activate( true );

		$this->assertSame( 1, $GLOBALS['iua_test_current_blog'] );
		$this->assertSame( array(), $GLOBALS['iua_test_blog_stack'] );
		$this->assertSame( '1', $GLOBALS['iua_test_options']['iua_include_drafts'] );
		$this->assertSame( '1', $GLOBALS['iua_test_site_options'][2]['iua_include_drafts'] );
		$this->assertSame( '1', $GLOBALS['iua_test_site_options'][3]['iua_include_drafts'] );
		$this->assertFalse( $GLOBALS['iua_test_autoload'][2]['iua_manual_used_ids'] );
	}
}
