<?php

use PHPUnit\Framework\TestCase;

final class ScannerNormalizationTest extends TestCase {
	/**
	 * @return mixed
	 */
	private function call_private( IUA_Scanner $scanner, string $method, array $arguments = array() ) {
		$reflection = new ReflectionMethod( IUA_Scanner::class, $method );

		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $scanner, $arguments );
	}

	public function test_cdn_aliases_and_rewrites_normalize_upload_urls() : void {
		$GLOBALS['iua_test_options'] = array(
			'iua_cdn_aliases'  => 'cdn.example.test, media.example.test',
			'iua_cdn_rewrites' => "https://assets.example.test/media => /wp-content/uploads\ninvalid",
		);
		$scanner = new IUA_Scanner();

		$this->call_private( $scanner, 'load_cdn_settings' );

		$this->assertSame(
			'/wp-content/uploads/2024/image.jpg',
			$this->call_private( $scanner, 'normalize_text_rewrites', array( 'https://cdn.example.test/wp-content/uploads/2024/image.jpg' ) )
		);
		$this->assertSame(
			'/wp-content/uploads/2024/image.jpg',
			$this->call_private( $scanner, 'normalize_text_rewrites', array( 'https://assets.example.test/media/2024/image.jpg' ) )
		);
	}

	public function test_upload_urls_match_generated_sizes_and_record_provenance() : void {
		$scanner  = new IUA_Scanner();
		$used     = array();
		$path_map = array(
			'2024/image.jpg'         => 11,
			'2024/image-300x200.jpg' => 11,
		);

		$this->call_private(
			$scanner,
			'scan_text_for_uploads',
			array( '<img src="/wp-content/uploads/2024/image-300x200.jpg?cache=1">', $path_map, &$used, 'post:42 content:url' )
		);

		$this->assertSame( array( 11 => true ), $used );
		$this->assertSame(
			array( 11 => array( 'post:42 content:url' ) ),
			$this->call_private( $scanner, 'get_provenance_output' )
		);
	}

	public function test_builder_ids_are_detected_and_provenance_is_capped() : void {
		$scanner = new IUA_Scanner();
		$used    = array();

		$this->call_private( $scanner, 'scan_builder_value_for_ids', array( '{"id":27,"nested":{"id":28}}', &$used, 'post:8 meta:_elementor_data' ) );
		$this->assertSame( array( 27 => true, 28 => true ), $used );

		for ( $index = 0; $index < 14; $index++ ) {
			$this->call_private( $scanner, 'add_provenance', array( 27, 'source:' . $index ) );
		}

		$provenance = $this->call_private( $scanner, 'get_provenance_output' );
		$this->assertCount( 12, $provenance[27] );
		$this->assertSame( 'post:8 meta:_elementor_data json:id', $provenance[27][0] );
	}
}
